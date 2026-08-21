<?php

namespace App\Exports;

use App\Models\OutgoingShipment;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SellerAliqaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected string $seller;
    protected bool $onlyFollowUp;
    protected Collection $shipments;

    public function __construct(string $seller = 'Aliqa', bool $onlyFollowUp = false)
    {
        $this->seller = $seller;
        $this->onlyFollowUp = $onlyFollowUp;

        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');
    }

    public function collection()
    {
        $query = OutgoingShipment::query();
        if (strtoupper($this->seller) !== 'ALL' && $this->seller !== 'Semua Seller') {
            $query->where('nama_seller', 'LIKE', "%{$this->seller}%");
        }
        if ($this->onlyFollowUp) {
            $query->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP']);
        }

        $this->shipments = $query->orderBy('id', 'asc')->get();
        return $this->shipments;
    }

    public function headings(): array
    {
        return [
            'NO',
            'NAMA SELLER',
            'NO RESI',
            'NAMA PENERIMA',
            'NO HP',
            'ALAMAT',
            'TANGGAL KIRIM',
            'STATUS POS',
            'KETERANGAN',
            'KATEGORI STATUS',
            'SLA (HARI)',
        ];
    }

    public function map($shipment): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $shipment->nama_seller,
            $shipment->no_resi,
            $shipment->nama_penerima ?: '-',
            $shipment->no_hp ?: '-',
            $shipment->alamat ?: '-',
            $shipment->tanggal_kirim ? $shipment->tanggal_kirim->format('Y-m-d') : '-',
            $shipment->status_pos ?: 'PROSES PENGIRIMAN POS',
            $shipment->keterangan ?: '-',
            $shipment->status_kategori,
            $shipment->sla_days ?? 2,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Header Styling: Navy Blue (#003366), Bold, White text
        $sheet->getStyle('A1:K1')->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF003366');

        if ($this->shipments->isEmpty()) {
            return;
        }

        // 2. High-Performance Bulk Range Styling (Memory & CPU Optimized)
        $rowsByColor = [
            'FFE0F2FE' => [], // SUKSES (Soft Cyan / Blue)
            'FFFED7AA' => [], // RETUR (Soft Orange)
            'FFFEF08A' => [], // IN_PROCESS / FOLLOW_UP (Soft Yellow)
        ];

        foreach ($this->shipments as $idx => $shipment) {
            $rowNum = $idx + 2;
            $kategori = $shipment->status_kategori;

            if ($kategori === 'SUKSES') {
                $rowsByColor['FFE0F2FE'][] = $rowNum;
            } elseif ($kategori === 'RETUR') {
                $rowsByColor['FFFED7AA'][] = $rowNum;
            } else {
                $rowsByColor['FFFEF08A'][] = $rowNum;
            }
        }

        foreach ($rowsByColor as $color => $rowNumbers) {
            if (empty($rowNumbers)) {
                continue;
            }

            $ranges = $this->buildRowRanges($rowNumbers);
            foreach ($ranges as $range) {
                $sheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($color);
            }
        }
    }

    protected function buildRowRanges(array $rowNumbers): array
    {
        $ranges = [];
        $start = null;
        $prev = null;

        foreach ($rowNumbers as $num) {
            if ($start === null) {
                $start = $num;
                $prev = $num;
                continue;
            }

            if ($num === $prev + 1) {
                $prev = $num;
            } else {
                $ranges[] = "A{$start}:K{$prev}";
                $start = $num;
                $prev = $num;
            }
        }

        if ($start !== null) {
            $ranges[] = "A{$start}:K{$prev}";
        }

        return $ranges;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 20,
            'D' => 25,
            'E' => 16,
            'F' => 35,
            'G' => 15,
            'H' => 25,
            'I' => 30,
            'J' => 18,
            'K' => 12,
        ];
    }
}
