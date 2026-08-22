<?php

namespace App\Http\Controllers;

use App\Exports\SellerAliqaExport;
use App\Imports\ShipmentsImport;
use App\Jobs\ProcessExcelImportJob;
use App\Jobs\ProcessGoogleSheetSyncJob;
use App\Jobs\ProcessNiposTrackingJob;
use App\Models\OutgoingShipment;
use App\Models\SystemSetting;
use App\Services\TrackingBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class DashboardController extends Controller
{
    protected TrackingBotService $botService;

    public function __construct(TrackingBotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Display CS Monitoring Dashboard via Inertia React
     */
    public function index(Request $request)
    {
        if (OutgoingShipment::count() === 0) {
            $this->seedInitialDummyData();
        }

        $selectedSeller = $request->input('seller', 'ALL');
        $selectedKategori = $request->input('kategori', 'ALL');
        $selectedMonth = $request->input('month', 'ALL');
        $selectedYear = $request->input('year', null);
        $selectedColor = $request->input('color', null);
        $searchQuery = trim($request->input('search', ''));

        $query = OutgoingShipment::query();

        // 1. Month & Year Filters
        if (!empty($selectedMonth) && $selectedMonth !== 'ALL') {
            if (is_numeric($selectedMonth)) {
                $mInt = (int)$selectedMonth;
                $mNum = ($mInt >= 0 && $mInt <= 11) ? ($mInt + 1) : $mInt;
                $query->whereMonth('tanggal_kirim', $mNum);
            } else {
                $monthMap = [
                    'JANUARI' => 1, 'JAN' => 1,
                    'FEBRUARI' => 2, 'FEB' => 2,
                    'MARET' => 3, 'MAR' => 3,
                    'APRIL' => 4, 'APR' => 4,
                    'MEI' => 5, 'MAY' => 5,
                    'JUNI' => 6, 'JUN' => 6,
                    'JULI' => 7, 'JUL' => 7,
                    'AGUSTUS' => 8, 'AGT' => 8, 'AGUS' => 8,
                    'SEPTEMBER' => 9, 'SEP' => 9,
                    'OKTOBER' => 10, 'OKT' => 10,
                    'NOVEMBER' => 11, 'NOV' => 11,
                    'DESEMBER' => 12, 'DES' => 12,
                ];
                $mNum = $monthMap[strtoupper($selectedMonth)] ?? null;
                if ($mNum) {
                    $query->whereMonth('tanggal_kirim', $mNum);
                }
            }
        }
        if (!empty($selectedYear)) {
            $query->whereYear('tanggal_kirim', (int)$selectedYear);
        }

        // 2. Seller Filter
        if (!empty($selectedSeller) && $selectedSeller !== 'ALL' && $selectedSeller !== 'Semua Seller') {
            $query->where('nama_seller', $selectedSeller);
        }

        // 3. Color & Kategori Filter
        if (!empty($selectedColor) && $selectedColor !== 'ALL') {
            $query->where('color_code', strtoupper($selectedColor));
        }
        if (!empty($selectedKategori) && $selectedKategori !== 'ALL') {
            $query->where('status_kategori', strtoupper($selectedKategori));
        }

        // 4. Multi-resi Search Query
        if (!empty($searchQuery)) {
            $terms = preg_split('/[\s,\n;]+/', $searchQuery);
            $terms = array_filter(array_map('trim', $terms));

            $query->where(function ($q) use ($terms, $searchQuery) {
                if (count($terms) > 1) {
                    $q->whereIn('no_resi', $terms);
                } else {
                    $q->where('no_resi', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('nama_penerima', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('no_hp', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('alamat', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('status_pos', 'LIKE', "%{$searchQuery}%");
                }
            });
        }

        // Summary Statistics Cards (Memory < 1MB via SQL count)
        $stats = [
            'total' => (clone $query)->count(),
            'sukses' => (clone $query)->where('status_kategori', 'SUKSES')->count(),
            'retur' => (clone $query)->where('status_kategori', 'RETUR')->count(),
            'follow_up' => (clone $query)->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])->count(),
        ];

        // 50 Items Per Page Pagination to prevent Memory Exhaustion
        $paginatedShipments = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        $formattedShipmentsData = collect($paginatedShipments->items())->map(function ($s) {
            $fu = 'PUTIH';
            if ($s->color_code) {
                $fu = $s->color_code;
            } elseif ($s->status_kategori === 'SUKSES') {
                $fu = 'BIRU';
            } elseif ($s->status_kategori === 'RETUR') {
                $fu = 'ORANGE';
            } elseif ($s->status_kategori === 'FOLLOW_UP') {
                $fu = 'KUNING';
            }

            $tglStr = date('Y-m-d');
            if (!empty($s->tanggal_kirim)) {
                if (is_string($s->tanggal_kirim)) {
                    $tglStr = substr($s->tanggal_kirim, 0, 10);
                } else {
                    $tglStr = $s->tanggal_kirim->format('Y-m-d');
                }
            }

            return [
                'id' => (string)$s->id,
                'resi' => $s->no_resi,
                'seller' => $s->nama_seller ?? 'Aliqa',
                'tanggalKirim' => $tglStr,
                'tujuan' => $s->alamat ?? '-',
                'penerima' => $s->nama_penerima ?? '-',
                'telepon' => $s->no_hp ?? '-',
                'alamat' => $s->alamat ?? '-',
                'keterangan' => $s->keterangan ?? '-',
                'nipos' => $s->status_pos ?? 'ON PROCESS',
                'sla' => (int)($s->sla_days ?? 2),
                'fu' => $fu,
                'note' => $s->keterangan,
                'escalationDate' => $s->last_tracked_at ? (is_string($s->last_tracked_at) ? $s->last_tracked_at : $s->last_tracked_at->format('Y-m-d H:i')) : null,
            ];
        })->toArray();

        $paginatedResult = [
            'data' => $formattedShipmentsData,
            'current_page' => $paginatedShipments->currentPage(),
            'last_page' => $paginatedShipments->lastPage(),
            'per_page' => $paginatedShipments->perPage(),
            'total' => $paginatedShipments->total(),
            'from' => $paginatedShipments->firstItem(),
            'to' => $paginatedShipments->lastItem(),
            'links' => $paginatedShipments->linkCollection()->toArray(),
        ];

        $sellersList = OutgoingShipment::select('nama_seller')
            ->distinct()
            ->whereNotNull('nama_seller')
            ->orderBy('nama_seller', 'asc')
            ->pluck('nama_seller')
            ->toArray();

        if (!in_array('Aliqa', $sellersList)) {
            array_unshift($sellersList, 'Aliqa');
        }

        return Inertia::render('Dashboard', [
            'shipments' => $paginatedResult,
            'stats' => $stats,
            'sellersList' => array_values(array_unique($sellersList)),
            'googleSheetUrl' => SystemSetting::get('google_sheet_url', 'https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit'),
            'googleSheetId' => SystemSetting::get('google_sheet_id', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw'),
            'filters' => [
                'seller' => $selectedSeller,
                'kategori' => $selectedKategori,
                'month' => $selectedMonth,
                'year' => $selectedYear,
                'color' => $selectedColor,
                'search' => $searchQuery,
            ]
        ]);
    }

    /**
     * Real-time Tracking Progress Bar API Endpoint
     */
    public function progress()
    {
        $total = OutgoingShipment::count();
        $tracked = OutgoingShipment::whereNotNull('last_tracked_at')->count();
        $percentage = $total > 0 ? round(($tracked / $total) * 100, 1) : 0;

        return response()->json([
            'total' => $total,
            'tracked' => $tracked,
            'percentage' => $percentage,
            'is_running' => $tracked < $total,
        ]);
    }

    /**
     * Bulk Action: Mark multiple resis as Completed, Follow-Up, Retur, or Delete
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:outgoing_shipments,id',
            'action' => 'required|string|in:SUKSES,FOLLOW_UP,RETUR,IN_PROCESS,DELETE',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        if ($action === 'DELETE') {
            OutgoingShipment::whereIn('id', $ids)->delete();
            $msg = count($ids) . " data resi berhasil dihapus.";
        } else {
            OutgoingShipment::whereIn('id', $ids)->update([
                'status_kategori' => $action,
                'status_pos' => $action === 'SUKSES' ? 'DELIVERED' : ($action === 'RETUR' ? 'DELIVERED (RETURN DELIVERY)' : 'PERLU FOLLOW UP CS'),
                'updated_at' => now(),
            ]);
            $msg = count($ids) . " data resi berhasil diperbarui ke status " . $action . ".";
        }

        return back()->with('success', $msg);
    }

    /**
     * Handle Excel upload: Save raw file to storage/app/public/imports/ and dispatch ProcessExcelImportJob instantly (< 1 sec)
     */
    public function import(Request $request)
    {
        $file = $request->file('excel_file') ?? $request->file('file');

        if (!$file) {
            return back()->with('error', 'Silakan pilih file Excel / CSV terlebih dahulu.');
        }

        $defaultSeller = $request->input('default_seller', 'Aliqa');

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = 'import_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;

            // Save directly to storage/app/public/imports/
            $importsDir = storage_path('app/public/imports');
            if (!File::exists($importsDir)) {
                File::makeDirectory($importsDir, 0755, true);
            }

            $storedPath = $importsDir . DIRECTORY_SEPARATOR . $filename;
            $file->move($importsDir, $filename);

            // Dispatch background queue job for streaming import & tracking
            ProcessExcelImportJob::dispatch($storedPath, $defaultSeller);

            return redirect()->back()->with('success', 'File 10.000 data berhasil diunggah! Proses membaca data & tracking berjalan di latar belakang (Queue Worker).');
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengunggah file: ' . $e->getMessage());
        }
    }

    /**
     * Download Filtered Seller Excel report
     */
    public function exportAliqa(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $seller = $request->input('seller', 'Aliqa');
        $onlyFollowUp = $request->boolean('only_followup');
        $fileName = "LAPORAN_OUTGOING_SELLER_" . strtoupper($seller) . ($onlyFollowUp ? "_FOLLOW_UP" : "") . "_" . date('Ymd_His') . ".xlsx";

        return Excel::download(new SellerAliqaExport($seller, $onlyFollowUp), $fileName);
    }

    /**
     * Trigger background tracking job manually with Smart Filtering & 500-Resi Chunking
     */
    public function trackNow(Request $request)
    {
        // Fetch only resi IDs needing tracking (excluding SUKSES and RETUR)
        $shipmentIds = OutgoingShipment::where(function ($q) {
                $q->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])
                  ->orWhereNull('status_kategori');
            })
            ->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
            ->pluck('id')
            ->toArray();

        if (empty($shipmentIds)) {
            return back()->with('info', 'Semua resi sudah berstatus SUKSES / RETUR. Tidak ada resi yang perlu di-track.');
        }

        // Chunk resis into batches of 500 and dispatch to queue
        foreach (array_chunk($shipmentIds, 500) as $batchChunk) {
            ProcessNiposTrackingJob::dispatch($batchChunk);
        }

        return back()->with('success', count($shipmentIds) . ' resi (dalam batch 500) berhasil dikirim ke background queue untuk pelacakan NIPOS@MID!');
    }

    /**
     * Helper to seed initial sample records
     */
    protected function seedInitialDummyData(): void
    {
        $samples = [
            [
                'nama_seller' => 'Aliqa',
                'no_resi' => 'P2601020130901',
                'nama_penerima' => 'Siti Nurhaliza',
                'no_hp' => '081234567890',
                'alamat' => 'Jl. Merdeka No. 12, Cilacap',
                'tanggal_kirim' => '2026-08-15',
                'status_pos' => 'DELIVERED',
                'keterangan' => 'DITERIMA YANG BERSANGKUTAN',
                'status_kategori' => 'SUKSES',
                'sla_days' => 2,
                'last_tracked_at' => now(),
            ],
            [
                'nama_seller' => 'Aliqa',
                'no_resi' => 'P2601020130902',
                'nama_penerima' => 'Budi Santoso',
                'no_hp' => '085678901234',
                'alamat' => 'Jl. Jenderal Sudirman No. 45, Cilacap',
                'tanggal_kirim' => '2026-08-16',
                'status_pos' => 'DELIVERED (RETURN DELIVERY)',
                'keterangan' => 'RETUR ALAMAT TIDAK DITEMUKAN',
                'status_kategori' => 'RETUR',
                'sla_days' => 5,
                'last_tracked_at' => now(),
            ],
            [
                'nama_seller' => 'Aliqa',
                'no_resi' => 'P2601020130903',
                'nama_penerima' => 'Dewi Anggraini',
                'no_hp' => '087890123456',
                'alamat' => 'Jl. Diponegoro No. 88, Cilacap',
                'tanggal_kirim' => '2026-08-18',
                'status_pos' => 'FAILEDTODELIVERED',
                'keterangan' => 'RUMAH KOSONG (PERLU FOLLOW UP CS)',
                'status_kategori' => 'FOLLOW_UP',
                'sla_days' => 3,
                'last_tracked_at' => now(),
            ],
            [
                'nama_seller' => 'Aliqa',
                'no_resi' => 'P2601020130904',
                'nama_penerima' => 'Eko Prasetyo',
                'no_hp' => '089012345678',
                'alamat' => 'Jl. Gatot Subroto No. 101, Cilacap',
                'tanggal_kirim' => '2026-08-19',
                'status_pos' => 'ON PROCESS',
                'keterangan' => 'PROSES PENGOLAHAN KIRIMAN POS',
                'status_kategori' => 'IN_PROCESS',
                'sla_days' => 1,
                'last_tracked_at' => now(),
            ],
            [
                'nama_seller' => 'Bagas Store',
                'no_resi' => 'P2601020130905',
                'nama_penerima' => 'Fajar Pratama',
                'no_hp' => '082134567891',
                'alamat' => 'Jl. Ahmad Yani No. 22, Cilacap',
                'tanggal_kirim' => '2026-08-17',
                'status_pos' => 'DELIVERED',
                'keterangan' => 'DITERIMA ORANG SERUMAH',
                'status_kategori' => 'SUKSES',
                'sla_days' => 2,
                'last_tracked_at' => now(),
            ],
        ];

        foreach ($samples as $sample) {
            OutgoingShipment::updateOrCreate(['no_resi' => $sample['no_resi']], $sample);
        }
    }

    /**
     * Save dynamic Google Spreadsheet URL & ID to system_settings table
     */
    public function updateGoogleSheetsSetting(Request $request)
    {
        $url = trim($request->input('url', ''));
        if (empty($url)) {
            return back()->with('error', 'URL Google Spreadsheet tidak boleh kosong.');
        }

        $id = SystemSetting::extractSpreadsheetId($url);
        if (!$id) {
            return back()->with('error', 'Format URL Google Spreadsheet tidak valid.');
        }

        SystemSetting::set('google_sheet_url', $url);
        SystemSetting::set('google_sheet_id', $id);

        return back()->with('success', "URL Google Spreadsheet berhasil diperbarui (ID: {$id}).");
    }

    /**
     * Trigger background Google Sheets sync job
     */
    public function syncGoogleSheets(Request $request)
    {
        $url = trim($request->input('url', ''));
        $seller = $request->input('seller', 'Aliqa');

        if (!empty($url)) {
            $id = SystemSetting::extractSpreadsheetId($url);
            if ($id) {
                SystemSetting::set('google_sheet_url', $url);
                SystemSetting::set('google_sheet_id', $id);
            }
        }

        ProcessGoogleSheetSyncJob::dispatch($url ?: null, $seller);

        return redirect()->back()->with('success', 'Sinkronisasi Google Sheets berhasil dikirim ke background Queue Worker!');
    }
}