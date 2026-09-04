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
    ];

    protected $casts = [
        'tanggal_kirim' => 'date',
        'sla_days' => 'integer',
        'last_tracked_at' => 'datetime',
    ];

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
}
