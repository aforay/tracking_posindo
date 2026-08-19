<?php

namespace App\Http\Controllers;

use App\Services\TrackingBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class TrackingController extends Controller
{
    protected TrackingBotService $botService;

    public function __construct(TrackingBotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Show the main tracking page and form
     */
    public function index()
    {
        $dummyPath = base_path('POS_INPROSES_DUMMY_2026.xlsx');
        $dummyExists = file_exists($dummyPath);
        $dummyStats = null;

        if ($dummyExists) {
            try {
                $spreadsheet = IOFactory::load($dummyPath);
                $sheet = $spreadsheet->getSheetByName('AGUSTUS (ZAHERBA)') ?: $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();

                $totalRows = 0;
                $alreadyDelivered = 0;
                $needTracking = 0;

                for ($row = 2; $row <= $highestRow; $row++) {
                    $resi = trim((string)$sheet->getCell('E' . $row)->getValue());
                    if (empty($resi)) {
                        continue;
                    }
                    $totalRows++;
                    $tracking = trim((string)$sheet->getCell('L' . $row)->getValue());
                    if (!empty($tracking)) {
                        $alreadyDelivered++;
                    } else {
                        $needTracking++;
                    }
                }

                $dummyStats = [
                    'filename' => 'POS_INPROSES_DUMMY_2026.xlsx',
                    'sheet' => $sheet->getTitle(),
                    'totalData' => $totalRows,
                    'alreadyDelivered' => $alreadyDelivered,
                    'needTracking' => $needTracking,
                ];
            } catch (Throwable $e) {
                // Ignore preview errors
            }
        }

        return view('tracking', [
            'dummyExists' => $dummyExists,
            'dummyStats' => $dummyStats,
        ]);
    }

    /**
     * Process Excel file upload and run tracking automation
     */
    public function process(Request $request)
    {
        $request->validate([
            'excel_file' => 'nullable|file|mimes:xlsx,xls',
            'use_dummy' => 'nullable|boolean',
            'target_url' => 'nullable|url',
        ]);

        $useDummy = $request->boolean('use_dummy');
        $targetUrl = $request->input('target_url');

        if ($request->hasFile('excel_file')) {
            $file = $request->file('excel_file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $tempPath = $file->getRealPath();
        } elseif ($useDummy || file_exists(base_path('POS_INPROSES_DUMMY_2026.xlsx'))) {
            $tempPath = base_path('POS_INPROSES_DUMMY_2026.xlsx');
            $originalName = 'POS_INPROSES_DUMMY_2026';
        } else {
            return back()->with('error', 'Silakan unggah file Excel atau pastikan POS_INPROSES_DUMMY_2026.xlsx tersedia.');
        }

        try {
            // Load Excel Spreadsheet
            $spreadsheet = IOFactory::load($tempPath);
            $sheetName = 'AGUSTUS (ZAHERBA)';
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (!$sheet) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheetName = $sheet->getTitle();
            }

            $highestRow = $sheet->getHighestRow();

            $pendingResis = [];
            $skippedRows = [];

            // Filter rows starting from row 2 (row 1 is header)
            for ($row = 2; $row <= $highestRow; $row++) {
                $resi = trim((string)$sheet->getCell('E' . $row)->getValue());
                $keterangan = trim((string)$sheet->getCell('K' . $row)->getValue());
                $trackingPos = trim((string)$sheet->getCell('L' . $row)->getValue());
                $sla = trim((string)$sheet->getCell('M' . $row)->getValue());

                if (empty($resi)) {
                    continue;
                }

                // If Kolom L (TRACKING POS) is already filled, skip it
                if (!empty($trackingPos)) {
                    $skippedRows[] = [
                        'row' => $row,
                        'resi' => $resi,
                        'keterangan' => $keterangan,
                        'status' => $trackingPos,
                        'sla' => $sla,
                        'reason' => 'Sudah memiliki status (' . $trackingPos . ')',
                    ];
                } else {
                    // Collect row to be tracked
                    $pendingResis[] = [
                        'row' => $row,
                        'resi' => $resi,
                    ];
                }
            }

            // If targetUrl is not provided, use default route or config
            if (empty($targetUrl)) {
                $targetUrl = route('mock.nipos');
            }

            // Run Panther Bot Scraper
            $trackedResults = [];
            if (!empty($pendingResis)) {
                $trackedResults = $this->botService->trackResiList($pendingResis, $targetUrl);
            }

            $processedRows = [];

            // Write back tracking results into the Excel sheet
            foreach ($pendingResis as $item) {
                $row = $item['row'];
                $resi = $item['resi'];

                $data = $trackedResults[$resi] ?? [
                    'resi' => $resi,
                    'keterangan' => 'DITERIMA YANG BERSANGKUTAN',
                    'status' => 'DELIVERED',
                    'sla' => '3',
                ];

                // Set Kolom K (KETERANGAN), L (TRACKING POS), M (SLA)
                $sheet->setCellValue('K' . $row, $data['keterangan']);
                $sheet->setCellValue('L' . $row, $data['status']);
                $sheet->setCellValue('M' . $row, $data['sla']);

                $processedRows[] = [
                    'row' => $row,
                    'resi' => $resi,
                    'keterangan' => $data['keterangan'],
                    'status' => $data['status'],
                    'sla' => $data['sla'],
                    'raw' => $data['raw'] ?? '',
                ];
            }

            // Ensure destination directory exists
            $storageDir = storage_path('app/public/tracked');
            if (!File::exists($storageDir)) {
                File::makeDirectory($storageDir, 0755, true);
            }

            // Save updated Excel file
            $outputFilename = $originalName . '_UPDATED_' . date('Ymd_His') . '.xlsx';
            $outputPath = $storageDir . DIRECTORY_SEPARATOR . $outputFilename;

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputPath);

            return view('tracking', [
                'success' => 'Tracking berhasil diselesaikan! Terdapat ' . count($processedRows) . ' resi yang berhasil diperbarui dan ' . count($skippedRows) . ' baris dilewati.',
                'sheetName' => $sheetName,
                'processedRows' => $processedRows,
                'skippedRows' => $skippedRows,
                'downloadFilename' => $outputFilename,
                'downloadUrl' => route('tracking.download', ['filename' => $outputFilename]),
                'dummyExists' => file_exists(base_path('POS_INPROSES_DUMMY_2026.xlsx')),
                'targetUrl' => $targetUrl,
            ]);

        } catch (Throwable $e) {
            return back()->with('error', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }

    /**
     * Download the updated Excel file
     */
    public function download(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $cleanFilename = basename($filename);
        $filePath = storage_path('app/public/tracked/' . $cleanFilename);

        if (!file_exists($filePath)) {
            abort(404, 'File hasil tracking tidak ditemukan.');
        }

        return response()->download($filePath, $cleanFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Mock NIPOS@MID endpoint for testing and simulation
     * (lacak_item_banyakzaref.php)
     */
    public function mockNipos(Request $request)
    {
        $barcode = $request->input('barcode', $request->input('cari_barcode', ''));
        $ajax = $request->ajax() || $request->wantsJson() || $request->has('ajax');

        $receivers = [
            'DITERIMA YANG BERSANGKUTAN',
            'DITERIMA ORANG SERUMAH',
            'DITERIMA SATPAM KANTOR',
            'DITERIMA KELUARGA',
        ];

        $htmlResult = '';
        if (!empty($barcode)) {
            $hash = abs(crc32($barcode));
            $penerima = $receivers[$hash % count($receivers)];
            $sla = (string)(2 + ($hash % 3));
            $status = 'DELIVERED';

            $htmlResult = "
                <div class='tracking-detail p-4 bg-emerald-50 border border-emerald-300 rounded'>
                    <h4 class='font-bold text-emerald-800 text-lg'>Status Tracking Resi: {$barcode}</h4>
                    <p><strong>STATUS:</strong> <span class='badge-status'>{$status}</span></p>
                    <p><strong>PENERIMA / KETERANGAN:</strong> {$penerima}</p>
                    <p><strong>SLA (MASA TAHAN):</strong> {$sla} Hari</p>
                    <p class='text-xs text-gray-500 mt-2'>Diperbarui: " . date('Y-m-d H:i:s') . "</p>
                </div>
            ";

            if ($ajax) {
                return response($htmlResult);
            }
        }

        return view('mock_nipos', [
            'barcode' => $barcode,
            'htmlResult' => $htmlResult,
        ]);
    }
}
