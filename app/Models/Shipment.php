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
}
