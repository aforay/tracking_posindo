<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    public const MONTHS = [
        'JANUARI',
        'FEBRUARI',
        'MARET',
        'APRIL',
        'MEI',
        'JUNI',
        'JULI',
        'AGUSTUS',
        'SEPTEMBER',
        'OKTOBER',
        'NOVEMBER',
        'DESEMBER',
    ];

    protected $fillable = [
        'month',
        'year',
        'type', // 'keluar' (pengiriman baru) or 'masuk' (retur / transit)
        'row_index',
        'tanggal',
        'nama_konsumen',
        'invoice',
        'resi',
        'alamat',
        'nama_cs',
        'produk',
        'no_hp',
        'jumlah_cod',
        'keterangan',
        'status',
        'sla',
        'color_code',
        'needs_follow_up',
        'last_scanned_at',
    ];

    /**
     * Status keywords that mark a shipment as finished (no tracking needed)
     */
    public const FINAL_STATUS_KEYWORDS = ['DELIVERED', 'RETURN', 'RETUR', 'SUKSES'];

    protected $casts = [
        'year' => 'integer',
        'row_index' => 'integer',
        'needs_follow_up' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    /**
     * Scope query by month and year
     */
    public function scopeForMonth($query, ?string $month, ?int $year = null)
    {
        if (!empty($month) && $month !== 'ALL') {
            $query->where('month', strtoupper($month));
        }
        if (!empty($year)) {
            $query->where('year', $year);
        }
        return $query;
    }

    /**
     * Scope query to shipments that still need bot tracking:
     * skip anything already SUKSES (delivered) or RETUR.
     */
    public function scopeNeedsTracking($query)
    {
        return $query->where('type', '!=', 'masuk')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere(function ($inner) {
                        foreach (self::FINAL_STATUS_KEYWORDS as $keyword) {
                            $inner->where('status', 'not like', '%' . $keyword . '%');
                        }
                    });
            });
    }

    /**
     * Scope query by shipment type (keluar / masuk)
     */
    public function scopeForType($query, ?string $type)
    {
        if (!empty($type) && in_array($type, ['keluar', 'masuk'])) {
            $query->where('type', $type);
        }
        return $query;
    }

    /**
     * Helper to check if shipment is delivered
     */
    public function isDelivered(): bool
    {
        $stat = strtoupper($this->status ?? '');
        return str_contains($stat, 'DELIVERED') && !str_contains($stat, 'RETURN');
    }

    /**
     * Helper to check if shipment is returned
     */
    public function isReturn(): bool
    {
        $stat = strtoupper($this->status ?? '');
        $ket = strtoupper($this->keterangan ?? '');
        return str_contains($stat, 'RETURN') 
            || str_contains($stat, 'RETUR') 
            || str_contains($ket, 'RETUR') 
            || $this->type === 'masuk' 
            || $this->color_code === 'ORANGE';
    }

    /**
     * Check whether a raw status string means the shipment is already finished
     */
    public static function isFinalStatus(?string $status): bool
    {
        $stat = strtoupper(trim((string) $status));
        if ($stat === '') {
            return false;
        }

        foreach (self::FINAL_STATUS_KEYWORDS as $keyword) {
            if (str_contains($stat, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the bot still has to scan this shipment
     */
    public function isTrackable(): bool
    {
        return ! $this->isDelivered() && ! $this->isReturn() && ! self::isFinalStatus($this->status);
    }

    /**
     * Apply a bot tracking result to the shipment and persist it
     */
    public function applyTrackingResult(array $result): void
    {
        $stat = strtoupper($result['status'] ?? '');
        $isDelivered = str_contains($stat, 'DELIVERED') && ! str_contains($stat, 'RETURN');
        $isReturn = str_contains($stat, 'RETURN') || str_contains($stat, 'RETUR');

        $this->status = $result['status'] ?? $this->status;
        $this->keterangan = $result['keterangan'] ?? $this->keterangan;
        $this->sla = $result['sla'] ?? $this->sla;
        $this->color_code = $isDelivered ? 'BIRU' : ($isReturn ? 'ORANGE' : 'PUTIH');
        $this->needs_follow_up = ! $isDelivered && ! $isReturn;
        $this->last_scanned_at = now();
        $this->save();
    }
}
