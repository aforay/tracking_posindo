<?php

namespace App\Imports;

use App\Models\OutgoingShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ShipmentsImport implements ToCollection, WithChunkReading
{
    protected string $defaultSeller;

    public function __construct(string $defaultSeller = 'Aliqa')
    {
        $this->defaultSeller = $defaultSeller;
    }

    /**
     * Process collection chunk (1000 rows/chunk) with high-speed upserts
     */
    public function collection(Collection $rows)
    {
        $batchData = [];
        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $rowArray = $row->toArray();
            if (empty($rowArray)) {
                continue;
            }

            // Detect Resi column
            $resi = null;

            // Check associative keys first
            if (!empty($rowArray['resi'])) {
                $resi = $rowArray['resi'];
            } elseif (!empty($rowArray['no_resi'])) {
                $resi = $rowArray['no_resi'];
            } elseif (!empty($rowArray['barcode'])) {
                $resi = $rowArray['barcode'];
            } else {
                // Check positional columns
                if (isset($rowArray[4]) && !empty(trim((string)$rowArray[4]))) {
                    $resi = $rowArray[4];
                } else {
                    foreach ($rowArray as $colVal) {
                        $strVal = trim((string)$colVal);
                        if (preg_match('/^[A-Za-z0-9]{10,25}$/', $strVal) && (str_starts_with(strtoupper($strVal), 'P') || is_numeric($strVal))) {
                            if (!in_array(strtoupper($strVal), ['RESI', 'NO RESI', 'BARCODE', 'NO', 'INVOICE', 'NO HP'])) {
                                $resi = $strVal;
                                break;
                            }
                        }
                    }
                }
            }

            $resi = trim((string)$resi);

            // Filter header/title rows and empty resis
            if (empty($resi) || in_array(strtoupper($resi), ['RESI', 'NO RESI', 'BARCODE', 'TRACKING POS', 'FORM PEMANTAUAN', 'NO'])) {
                continue;
            }

            // Extract seller name
            $seller = trim((string)($rowArray['nama_seller'] ?? $rowArray['seller'] ?? $rowArray['pengirim'] ?? $this->defaultSeller));
            if (empty($seller) || strtoupper($seller) === 'NAMA CS' || str_starts_with(strtoupper($seller), 'CS ')) {
                $seller = $this->defaultSeller;
            }

            // Extract penerima (Col 2 or key)
            $penerima = trim((string)($rowArray['nama_penerima'] ?? $rowArray['penerima'] ?? $rowArray['nama_konsumen'] ?? $rowArray[2] ?? ''));
            if (strtoupper($penerima) === 'NAMA KONSUMEN' || strtoupper($penerima) === 'PENERIMA') {
                continue;
            }

            // Extract no_hp (Col 8 or key)
            $noHp = trim((string)($rowArray['no_hp'] ?? $rowArray['hp'] ?? $rowArray['telepon'] ?? $rowArray[8] ?? ''));

            // Extract alamat (Col 5 or key)
            $alamat = trim((string)($rowArray['alamat'] ?? $rowArray[5] ?? ''));

            // Extract tanggal_kirim (Col 1 or key)
            $tanggalRaw = $rowArray['tanggal_kirim'] ?? $rowArray['tanggal'] ?? $rowArray[1] ?? null;
            $tanggalKirim = null;
            if (!empty($tanggalRaw) && !in_array(strtoupper((string)$tanggalRaw), ['TANGGAL', 'TGL'])) {
                try {
                    if (is_numeric($tanggalRaw)) {
                        $tanggalKirim = ExcelDate::excelToDateTimeObject((float)$tanggalRaw)->format('Y-m-d');
                    } else {
                        $tanggalKirim = Carbon::parse($tanggalRaw)->format('Y-m-d');
                    }
                } catch (Throwable $e) {
                    $tanggalKirim = date('Y-m-d');
                }
            }

            // Extract status_pos (Col 11 or key)
            $statusPos = trim((string)($rowArray['status_pos'] ?? $rowArray['tracking_pos'] ?? $rowArray['status'] ?? $rowArray[11] ?? ''));
            if (in_array(strtoupper($statusPos), ['TRACKING POS', 'STATUS'])) {
                $statusPos = '';
            }

            // Extract keterangan (Col 10 or key)
            $keterangan = trim((string)($rowArray['keterangan'] ?? $rowArray[10] ?? ''));
            if (in_array(strtoupper($keterangan), ['KETERANGAN'])) {
                $keterangan = '';
            }

            // Extract SLA (Col 12 or key)
            $slaRaw = $rowArray['sla_days'] ?? $rowArray['sla'] ?? $rowArray['sla_masa_tahan'] ?? $rowArray[12] ?? null;
            $slaDays = is_numeric($slaRaw) ? (int)$slaRaw : null;

            // Categorize status_kategori
            $statusUpper = strtoupper($statusPos . ' ' . $keterangan);
            $kategori = 'IN_PROCESS';
            if (str_contains($statusUpper, 'DITERIMA') || str_contains($statusUpper, 'DELIVERED')) {
                if (str_contains($statusUpper, 'RETUR') || str_contains($statusUpper, 'RETURN')) {
                    $kategori = 'RETUR';
                } else {
                    $kategori = 'SUKSES';
                }
            } elseif (str_contains($statusUpper, 'RETUR') || str_contains($statusUpper, 'RETURN')) {
                $kategori = 'RETUR';
            } elseif (str_contains($statusUpper, 'FAILED') || str_contains($statusUpper, 'GAGAL') || str_contains($statusUpper, 'KENDALA') || str_contains($statusUpper, 'FOLLOW UP') || str_contains($statusUpper, 'RUNSHEET')) {
                $kategori = 'FOLLOW_UP';
            }

            $batchData[] = [
                'nama_seller' => $seller,
                'no_resi' => $resi,
                'nama_penerima' => $penerima ?: null,
                'no_hp' => $noHp ?: null,
                'alamat' => $alamat ?: null,
                'tanggal_kirim' => $tanggalKirim,
                'status_pos' => $statusPos ?: null,
                'keterangan' => $keterangan ?: null,
                'status_kategori' => $kategori,
                'sla_days' => $slaDays,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($batchData)) {
            // Upsert in safe chunks of 200 items to fit all database parameter limits
            foreach (array_chunk($batchData, 200) as $chunk) {
                OutgoingShipment::upsert(
                    $chunk,
                    ['no_resi'],
                    ['nama_seller', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'sla_days', 'updated_at']
                );
            }
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
