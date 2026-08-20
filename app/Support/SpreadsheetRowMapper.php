<?php

namespace App\Support;

use App\Models\Shipment;

/**
 * Maps a raw spreadsheet row (columns A..M) into shipment attributes.
 */
class SpreadsheetRowMapper
{
    /**
     * @param  array  $row  Raw values of columns A..M
     * @return array|null Attributes, or null when the row has no resi
     */
    public static function map(array $row, string $month, int $year, int $excelRow): ?array
    {
        $resi = trim((string) ($row[4] ?? ''));
        if ($resi === '') {
            return null;
        }

        $status = trim((string) ($row[11] ?? ''));
        $keterangan = trim((string) ($row[10] ?? ''));
        $sla = trim((string) ($row[12] ?? ''));

        $statusUpper = strtoupper($status);
        $isDelivered = str_contains($statusUpper, 'DELIVERED') && ! str_contains($statusUpper, 'RETURN');
        $isReturn = str_contains($statusUpper, 'RETURN') || str_contains($statusUpper, 'RETUR');

        if ($isDelivered) {
            $color = 'BIRU';
            $followUp = false;
        } elseif ($isReturn) {
            $color = 'ORANGE';
            $followUp = false;
        } else {
            $color = 'PUTIH';
            $followUp = true;
        }

        return [
            'month' => $month,
            'year' => $year,
            'type' => 'keluar',
            'row_index' => $excelRow,
            'tanggal' => trim((string) ($row[1] ?? '')) ?: date('Y-m-d'),
            'nama_konsumen' => trim((string) ($row[2] ?? '')),
            'invoice' => trim((string) ($row[3] ?? '')),
            'resi' => $resi,
            'alamat' => trim((string) ($row[5] ?? '')),
            'nama_cs' => trim((string) ($row[6] ?? '')) ?: 'CRM DILA',
            'produk' => trim((string) ($row[7] ?? '')) ?: 'LAMBUNG CERIA ZAHERBA',
            'no_hp' => trim((string) ($row[8] ?? '')),
            'jumlah_cod' => trim((string) ($row[9] ?? '')),
            'keterangan' => $keterangan ?: ($isReturn ? 'BARANG RETUR DITERIMA' : ($isDelivered ? 'DITERIMA YANG BERSANGKUTAN' : 'PROSES PENGIRIMAN POS')),
            'status' => $status ?: 'ON PROCESS',
            'sla' => $sla ?: '2',
            'color_code' => $color,
            'needs_follow_up' => $followUp,
            'raw_status' => $status,
        ];
    }

    /**
     * Decide whether a spreadsheet row still has to be tracked by the bot.
     */
    public static function shouldTrack(string $rawStatus, string $filterMode): bool
    {
        return match ($filterMode) {
            'all' => true,
            'undelivered' => ! Shipment::isFinalStatus($rawStatus),
            default => trim($rawStatus) === '',
        };
    }
}
