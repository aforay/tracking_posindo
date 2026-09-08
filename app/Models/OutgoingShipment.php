<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutgoingShipment extends Model
{
    use HasFactory;

    protected $table = 'outgoing_shipments';

    protected $fillable = [
        'nama_seller',
        'no_resi',
        'nama_penerima',
        'no_hp',
        'alamat',
        'tanggal_kirim',
        'status_pos',
        'keterangan',
        'status_kategori',
        'color_code',
        'fu_pos_date',
        'noted',
        'sla_days',
        'last_tracked_at',
        'kantor_tujuan',
        'last_location',
        'kantor_pos_id',
    ];

    protected $casts = [
        'tanggal_kirim' => 'date',
        'sla_days' => 'integer',
        'last_tracked_at' => 'datetime',
    ];

    /**
     * Relationship to shipment action/audit logs
     */
    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShipmentLog::class, 'shipment_id');
    }

    /**
     * Relationship to post office (KC / KCU contact)
     */
    public function postOffice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PostOffice::class, 'kantor_pos_id');
    }

    /**
     * The "booted" method of the model.
     * Enforce strict date alignment based on resi barcode pattern.
     */
    protected static function booted()
    {
        static::saving(function (OutgoingShipment $shipment) {
            if (!empty($shipment->no_resi)) {
                $resiDate = \App\Imports\ShipmentsImport::extractDateFromResi($shipment->no_resi);
                if ($resiDate) {
                    $shipment->tanggal_kirim = $resiDate;
                }
            }
        });
    }

    /**
     * Scope query by seller name
     */
    public function scopeForSeller($query, ?string $seller)
    {
        if (!empty($seller) && strtoupper($seller) !== 'ALL' && $seller !== 'Semua Seller') {
            $query->where('nama_seller', 'LIKE', "%{$seller}%");
        }
        return $query;
    }

    /**
     * Scope query by category status
     */
    public function scopeForKategori($query, ?string $kategori)
    {
        if (!empty($kategori) && strtoupper($kategori) !== 'ALL') {
            $query->where('status_kategori', strtoupper($kategori));
        }
        return $query;
    }

    /**
     * Scope query for shipments that need live tracking from NIPOS.
     * Shipments that are STILL in transit/operational (e.g. unBag, INVEHICLE, INLOCATION, inBag, etc.)
     * MUST be tracked even if previously categorized as RETUR (ORANGE) or FOLLOW_UP,
     * until NIPOS confirms the final status (e.g. DELIVERED or DELIVERED (RETURN DELIVERY)).
     */
    public function scopeNeedsTracking($query, bool $force = false)
    {
        if ($force) {
            return $query;
        }

        return $query->where(function ($q) {
            // 1. Status pos kosong atau ON PROCESS / TRANSIT
            $q->whereNull('status_pos')
              ->orWhere('status_pos', '')
              ->orWhere('status_pos', 'ON PROCESS')
              ->orWhere('status_pos', 'LIKE', '%PROCESS%')
              // 2. Kategori atau warna belum final
              ->orWhereNotIn('status_kategori', ['SUKSES', 'RETUR'])
              ->orWhereNull('status_kategori')
              ->orWhereNotIn('color_code', ['BIRU', 'ORANGE'])
              ->orWhereNull('color_code')
              // 3. Status pos masih operasional/transit
              ->orWhereIn('status_pos', [
                  'unBag', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'inBag', 'INBAG',
                  'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'Irregularity',
                  'MANIFEST', 'ARRIVAL', 'DEPARTURE'
              ])
              // 4. Sudah RETUR / ORANGE tapi paketnya belum sampai ke pengirim (belum DELIVERED RETURN DELIVERY)
              ->orWhere(function ($returQ) {
                  $returQ->where(function ($sub) {
                      $sub->where('status_kategori', 'RETUR')
                          ->orWhere('color_code', 'ORANGE');
                  })->where(function ($sub) {
                      $sub->whereNull('status_pos')
                          ->orWhere(function ($s) {
                              $s->where('status_pos', 'NOT LIKE', '%RETURN DELIVERY%')
                                ->where('status_pos', 'NOT LIKE', '%RETURN TO SENDER%')
                                ->where('status_pos', 'NOT LIKE', '%DITERIMA PENGIRIM%');
                          });
                  });
              })
              // 5. Terdata SUKSES / BIRU tapi status pos nya belum DELIVERED final
              ->orWhere(function ($suksesQ) {
                  $suksesQ->where(function ($sub) {
                      $sub->where('status_kategori', 'SUKSES')
                          ->orWhere('color_code', 'BIRU');
                  })->where(function ($sub) {
                      $sub->whereNull('status_pos')
                          ->orWhere('status_pos', 'NOT LIKE', '%DELIVERED%')
                          ->orWhere('status_pos', 'LIKE', '%RETURN%');
                  });
              })
              // 6. Belum memiliki Kantor Pos Tujuan resmi dari NIPOS
              ->orWhere(function ($kantorQ) {
                  $kantorQ->whereNull('kantor_tujuan')
                          ->orWhere('kantor_tujuan', '')
                          ->orWhereIn('kantor_tujuan', ['KC TUJUAN', 'KANTOR POS TUJUAN', 'KC PENGANTARAN', 'KC POS PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA']);
              });
        });
    }

    /**
     * Check if this shipment is in a return status
     */
    public function isReturn(): bool
    {
        $stat = strtoupper((string)($this->status_pos ?? ''));
        $ket = strtoupper((string)($this->keterangan ?? ''));
        $kat = strtoupper((string)($this->status_kategori ?? ''));
        $color = strtoupper((string)($this->color_code ?? ''));

        return $kat === 'RETUR'
            || $color === 'ORANGE'
            || str_contains($stat, 'RETURN')
            || str_contains($stat, 'RETUR')
            || str_contains($ket, 'RETUR')
            || str_contains($ket, 'DITOLAK');
    }

    /**
     * Helper to check if shipment has reached final terminal status
     */
    public function isFinalStatus(): bool
    {
        $statusPos = strtoupper(trim((string)($this->status_pos ?? '')));
        $kategori = strtoupper(trim((string)($this->status_kategori ?? '')));
        $color = strtoupper(trim((string)($this->color_code ?? '')));

        if (empty($statusPos) || $statusPos === 'ON PROCESS' || str_contains($statusPos, 'PROCESS')) {
            return false;
        }

        if (in_array($statusPos, ['UNBAG', 'INVEHICLE', 'INLOCATION', 'INBAG', 'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'IRREGULARITY', 'MANIFEST', 'ARRIVAL', 'DEPARTURE'])) {
            return false;
        }

        if (($kategori === 'SUKSES' || $color === 'BIRU') && str_contains($statusPos, 'DELIVERED') && !str_contains($statusPos, 'RETURN')) {
            return true;
        }

        if (($kategori === 'RETUR' || $color === 'ORANGE') && (str_contains($statusPos, 'RETURN DELIVERY') || str_contains($statusPos, 'RETURN TO SENDER') || str_contains($statusPos, 'DITERIMA PENGIRIM'))) {
            return true;
        }

        return false;
    }

    /**
     * Relationship to PostOffice
     */
    public function kantorPos(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PostOffice::class, 'kantor_pos_id');
    }
}
