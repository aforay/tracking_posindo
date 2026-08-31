<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\OutgoingShipment;
use App\Jobs\ProcessNiposTrackingJob;
use App\Jobs\UpdateSheetStatusJob;
use App\Services\TrackingBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

class TrackingController extends Controller
{
    protected TrackingBotService $botService;

    // Standard color palette matching Pos Indonesia Google Sheets specifications
    public const COLOR_PALETTE = [
        'BIRU' => [
            'name' => 'BIRU',
            'label' => 'PAKET SUKSES',
            'argb_fill' => 'FF46BDC6', // Cyan-Teal
            'argb_font' => 'FF083344',
            'css_bg' => '#46BDC6',
            'css_class' => 'bg-[#46BDC6] text-cyan-950 border-cyan-400',
        ],
        'ORANGE' => [
            'name' => 'ORANGE',
            'label' => 'PAKET RETUR',
            'argb_fill' => 'FFFBBC04', // Orange / Amber
            'argb_font' => 'FF451A03',
            'css_bg' => '#FBBC04',
            'css_class' => 'bg-[#FBBC04] text-amber-950 border-amber-400',
        ],
        'KUNING' => [
            'name' => 'KUNING',
            'label' => 'SUDAH DI FU',
            'argb_fill' => 'FFFFFF00', // Bright Yellow
            'argb_font' => 'FF422006',
            'css_bg' => '#FFFF00',
            'css_class' => 'bg-[#FFFF00] text-yellow-950 border-yellow-400',
        ],
        'PUTIH' => [
            'name' => 'PUTIH',
            'label' => 'BLM DI FU',
            'argb_fill' => 'FFFFFFFF', // White
            'argb_font' => 'FF1E293B',
            'css_bg' => '#FFFFFF',
            'css_class' => 'bg-white text-slate-800 border-slate-200',
        ],
        'HIJAU' => [
            'name' => 'HIJAU',
            'label' => 'FU 2 KALI',
            'argb_fill' => 'FF93C47D', // Soft Green
            'argb_font' => 'FF14532D',
            'css_bg' => '#93C47D',
            'css_class' => 'bg-[#93C47D] text-emerald-950 border-emerald-400',
        ],
        'BIRU_TUA' => [
            'name' => 'BIRU TUA',
            'label' => 'FU POS',
            'argb_fill' => 'FF1C4587', // Navy Blue
            'argb_font' => 'FFFFFFFF',
            'css_bg' => '#1C4587',
            'css_class' => 'bg-[#1C4587] text-white border-blue-950',
        ],
    ];

    public function __construct(TrackingBotService $botService)
    {
        $this->botService = $botService;
    }

    public function index(Request $request)
    {
        $selectedMonth = strtoupper($request->input('month', 'AGUSTUS'));
        $selectedYear = (int)$request->input('year', 2026);
        $selectedType = $request->input('type', null);
        $selectedSeller = $request->input('seller', 'Semua Seller');
        $selectedColor = $request->input('color', null);
        $searchQuery = trim($request->input('search', ''));

        // Inisialisasi query dasar dan filter terlebih dahulu
        $query = Shipment::query();
        if ($selectedMonth !== 'ALL') {
            $query->forMonth($selectedMonth, $selectedYear);
        }
        if (!empty($selectedType)) {
            $query->forType($selectedType);
        }
        if ($selectedSeller !== 'Semua Seller' && !empty($selectedSeller) && $selectedSeller !== 'ALL') {
            $query->where('seller', $selectedSeller);
        }
        if (!empty($selectedColor) && $selectedColor !== 'ALL') {
            $query->where('color_code', strtoupper($selectedColor));
        }
        if (!empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('resi', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('nama_konsumen', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('no_hp', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('alamat', 'LIKE', "%{$searchQuery}%");
            });
        }

        // Aggregate counts directly from DB setelah $query terbentuk
        $stats = [
            'total' => (clone $query)->count(),
            'delivered' => (clone $query)->where(function($q) {
                $q->where('status', 'DELIVERED')->orWhere('color_code', 'BIRU');
            })->count(),
            'retur' => (clone $query)->where(function($q) {
                $q->where('status', 'LIKE', '%RETURN%')->orWhere('color_code', 'ORANGE');
            })->count(),
            'inproses' => (clone $query)->where(function($q) {
                $q->where('status', 'ON PROCESS')->orWhere('color_code', 'PUTIH');
            })->count(),
            'needs_follow_up' => (clone $query)->where('needs_follow_up', true)->count(),
        ];

        // 50 per page pagination to prevent memory exhaustion (Ordered ascending from row 1 downwards)
        $paginatedShipments = $query->orderBy('id', 'asc')->paginate(50)->withQueryString();

        $formattedShipmentsData = collect($paginatedShipments->items())->map(function ($s) {
            return [
                'id' => (string)$s->id,
                'resi' => $s->resi,
                'seller' => $s->seller ?? 'Mitra Aliqa',
                'tanggalKirim' => $s->tanggal ?? date('Y-m-d'),
                'tujuan' => $s->alamat ?? 'Cilacap',
                'penerima' => $s->nama_konsumen ?? '-',
                'telepon' => $s->no_hp ?? '-',
                'alamat' => $s->alamat ?? '-',
                'keterangan' => $s->keterangan ?? '-',
                'nipos' => $s->status ?? 'ON PROCESS',
                'sla' => (int)($s->sla ?? 2),
                'fu' => $s->color_code ?? 'PUTIH',
                'note' => $s->noted,
                'escalationDate' => $s->fu_pos_date,
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

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'shipments' => $paginatedResult,
                'stats' => $stats,
            ]);
        }

        return \Inertia\Inertia::render('Dashboard', [
            'shipments' => $paginatedResult,
            'filters' => [
                'seller' => $selectedSeller,
                'month' => $selectedMonth,
                'color' => $selectedColor,
                'search' => $searchQuery,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Store a new inbound or outbound shipment (Tambah Data Barang Masuk / Keluar)
     */
    public function store(Request $request)
    {
        $request->validate([
            'month' => 'required|string',
            'year' => 'nullable|integer',
            'type' => 'required|in:keluar,masuk',
            'resi' => 'required|string',
            'tanggal' => 'nullable|string',
            'nama_konsumen' => 'nullable|string',
            'no_hp' => 'nullable|string',
            'invoice' => 'nullable|string',
            'nama_cs' => 'nullable|string',
            'produk' => 'nullable|string',
            'jumlah_cod' => 'nullable|string',
            'alamat' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'auto_track' => 'nullable|boolean',
        ]);

        $month = strtoupper($request->input('month'));
        $year = (int)$request->input('year', 2026);
        $type = $request->input('type', 'keluar');
        $resi = trim($request->input('resi'));

        $shipment = Shipment::create([
            'month' => $month,
            'year' => $year,
            'type' => $type,
            'tanggal' => $request->input('tanggal', date('Y-m-d')),
            'nama_konsumen' => $request->input('nama_konsumen'),
            'no_hp' => $request->input('no_hp'),
            'invoice' => $request->input('invoice'),
            'resi' => $resi,
            'nama_cs' => $request->input('nama_cs', 'CRM DILA'),
            'produk' => $request->input('produk', 'LAMBUNG CERIA ZAHERBA'),
            'jumlah_cod' => $request->input('jumlah_cod'),
            'alamat' => $request->input('alamat'),
            'keterangan' => $request->input('keterangan', $type === 'masuk' ? 'BARANG RETUR DITERIMA' : 'PROSES PENGIRIMAN POS'),
            'status' => $type === 'masuk' ? 'DELIVERED (RETURN DELIVERY)' : 'ON PROCESS',
            'sla' => '2',
            'color_code' => $type === 'masuk' ? 'ORANGE' : 'PUTIH',
            'needs_follow_up' => $type !== 'masuk',
            'last_scanned_at' => null,
        ]);

        // Auto-track immediately if requested
        if ($request->boolean('auto_track')) {
            $this->trackSingleShipment($shipment);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Data barang {$type} dengan resi {$resi} berhasil ditambahkan ke bulan {$month}.",
                'shipment' => $shipment,
            ]);
        }

        return redirect()->route('tracking.index', ['month' => $month, 'year' => $year])
            ->with('success', "Data barang {$type} ({$resi}) berhasil ditambahkan ke bulan {$month}.");
    }

    /**
     * Track a single shipment via NIPOS bot
     */
    public function trackSingle(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $this->trackSingleShipment($shipment);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Resi {$shipment->resi} berhasil di-tracking.",
                'shipment' => $shipment,
            ]);
        }

        return back()->with('success', "Resi {$shipment->resi} berhasil diperbarui: {$shipment->status} ({$shipment->keterangan}).");
    }

    /**
     * Update manual color and follow-up status for a shipment
     */
    public function updateColor(Request $request, int $id)
    {
        $request->validate([
            'color_code' => 'required|string',
            'fu_pos_date' => 'nullable|string',
            'noted' => 'nullable|string',
        ]);

        $shipment = Shipment::findOrFail($id);
        $color = strtoupper($request->input('color_code'));

        if (in_array($color, ['BIRU', 'ORANGE', 'KUNING', 'PUTIH', 'HIJAU', 'BIRU_TUA'])) {
            $shipment->color_code = $color;
        }

        if ($request->has('fu_pos_date')) {
            $shipment->fu_pos_date = $request->input('fu_pos_date');
        }

        if ($request->has('noted')) {
            $shipment->noted = $request->input('noted');
        }

        if (in_array($color, ['KUNING', 'HIJAU', 'BIRU_TUA', 'PUTIH'])) {
            $shipment->needs_follow_up = in_array($color, ['KUNING', 'HIJAU', 'BIRU_TUA']) || !$shipment->isDelivered();
        } else {
            $shipment->needs_follow_up = false;
        }
        $shipment->save();

        // Two-Way Sync to Google Sheets in background queue
        if (!empty($shipment->resi)) {
            UpdateSheetStatusJob::dispatch(
                [$shipment->resi],
                $shipment->color_code ?: 'PUTIH',
                $shipment->noted,
                $shipment->fu_pos_date
            );
        }

        $successMsg = "Status resi {$shipment->resi} berhasil diperbarui & disinkronkan ke Google Sheets!";

        if ($request->header('X-Inertia') || $request->wantsJson()) {
            return back()->with('success', $successMsg);
        }

        return response()->json([
            'success' => true,
            'message' => $successMsg,
            'shipment' => $shipment,
        ]);
    }

    /**
     * Bulk update status for multiple resis from Inertia/React frontend
     */
    public function updateStatusBulk(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'fu' => 'required|string',
            'escalationDate' => 'nullable|string',
        ]);

        $ids = $request->input('ids');
        $fu = strtoupper($request->input('fu'));
        $escDate = $request->input('escalationDate');

        Shipment::whereIn('id', $ids)->update([
            'color_code' => $fu,
            'fu_pos_date' => $escDate,
            'needs_follow_up' => in_array($fu, ['KUNING', 'HIJAU', 'BIRU_TUA']),
            'updated_at' => now(),
        ]);

        return back()->with('success', count($ids) . ' resi berhasil diperbarui.');
    }

    /**
     * Trigger bot tracking for pending OutgoingShipments (direct execution by default, queue optional)
     */
    public function startBotTracking(Request $request)
    {
        @set_time_limit(0);
        $shipmentIds = $request->input('shipment_ids', []);
        $useQueue = $request->boolean('use_queue', false);
        $force = $request->boolean('force', false);
        $limit = (int)$request->input('limit', 50);

        $pendingQuery = OutgoingShipment::query();
        if (!empty($shipmentIds)) {
            $pendingQuery->whereIn('id', (array)$shipmentIds);
        } elseif ($force) {
            $pendingQuery->where('status_pos', '!=', 'DELIVERED');
        } else {
            $pendingQuery->where(function ($q) {
                $q->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
                  ->orWhereNull('status_kategori')
                  ->orWhere('status_pos', '!=', 'DELIVERED');
            });
        }

        $allPendingIds = $pendingQuery->orderBy('last_tracked_at', 'asc')->limit($limit)->pluck('id')->toArray();
        $pendingCount = count($allPendingIds);

        Log::info("TrackingController@startBotTracking: Processing tracking for {$pendingCount} shipments (Limit: {$limit}, Queue: " . ($useQueue ? 'YES' : 'NO') . ").");

        if ($pendingCount === 0) {
            $msg = 'Semua data resi sudah berstatus SUKSES atau RETUR.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'processed_count' => 0,
                    'pending_count' => 0,
                    'total_pending' => 0,
                    'is_finished' => true,
                ]);
            }
            return back()->with('info', $msg);
        }

        if ($useQueue) {
            // Asynchronous Queue chunked dispatch (100 resis per background job)
            foreach (array_chunk($allPendingIds, 100) as $chunkIds) {
                ProcessNiposTrackingJob::dispatch($chunkIds);
            }
            $msg = "Tracking Bot NIPOS berhasil dijalankan di background queue untuk {$pendingCount} resi.";
            $updatedCount = $pendingCount;
        } else {
            // Pure asynchronous background CLI execution
            cache()->put('bot_running', true, 3600);
            cache()->put('bot_progress', ['current' => 0, 'total' => $pendingCount > 0 ? $pendingCount : 1], 3600);

            if (app()->runningUnitTests()) {
                $job = new ProcessNiposTrackingJob($allPendingIds);
                $job->handle($this->botService);
            } else {
                $phpBinary = PHP_BINARY;
                $artisanPath = base_path('artisan');
                
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $cmd = sprintf('start /B "" %s %s nipos:track --all > NUL 2>&1', escapeshellarg($phpBinary), escapeshellarg($artisanPath));
                    pclose(popen($cmd, "r"));
                } else {
                    $cmd = sprintf('%s %s nipos:track --all > /dev/null 2>&1 &', escapeshellarg($phpBinary), escapeshellarg($artisanPath));
                    exec($cmd);
                }
            }
            $updatedCount = $pendingCount;
            $msg = "Bot NIPOS sedang berjalan di latar belakang (Background Process).";
        }

        $remainingPending = OutgoingShipment::where(function ($q) {
            $q->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
              ->orWhereNull('status_kategori')
              ->orWhere('status_pos', '!=', 'DELIVERED');
        })->count();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'processed_count' => $updatedCount,
                'pending_count' => $remainingPending,
                'total_pending' => $remainingPending,
                'is_running' => true,
                'is_finished' => false,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Real-time bot scraping progress polling API
     */
    public function progress()
    {
        $botProgress = cache('bot_progress', ['current' => 0, 'total' => 0]);
        $isRunning = cache('bot_running', false);

        $total = OutgoingShipment::count();
        $delivered = OutgoingShipment::where('status_kategori', 'SUKSES')->count();
        $retur = OutgoingShipment::where('status_kategori', 'RETUR')->count();
        $tracked = OutgoingShipment::whereNotNull('last_tracked_at')->count();
        $pending = OutgoingShipment::where(function ($q) {
            $q->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
              ->orWhereNull('status_kategori')
              ->orWhere('status_pos', '!=', 'DELIVERED');
        })->count();
        
        $percentage = ($botProgress['total'] > 0)
            ? round(($botProgress['current'] / $botProgress['total']) * 100, 1)
            : ($isRunning ? 0 : 100);

        return response()->json([
            'bot_current' => $botProgress['current'],
            'bot_total' => $botProgress['total'],
            'percentage' => $percentage,
            'is_running' => (bool)$isRunning,
            
            // Database raw stats
            'total' => $total,
            'tracked' => $tracked,
            'delivered' => $delivered,
            'retur' => $retur,
            'pending' => $pending,
        ]);
    }

    /**
     * Bulk Action handler for Inertia frontend
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string',
        ]);

        $ids = $request->input('ids');
        $action = strtoupper($request->input('action'));

        if ($action === 'DELETE') {
            Shipment::whereIn('id', $ids)->delete();
            $msg = count($ids) . ' resi berhasil dihapus.';
        } else {
            Shipment::whereIn('id', $ids)->update([
                'color_code' => $action,
                'needs_follow_up' => in_array($action, ['KUNING', 'HIJAU', 'BIRU_TUA']),
                'updated_at' => now(),
            ]);
            $msg = count($ids) . ' resi berhasil diperbarui.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Export Seller Report for Aliqa / selected seller
     */
    public function exportAliqa(Request $request)
    {
        $seller = $request->input('seller', 'Aliqa');
        $onlyFollowUp = $request->boolean('only_followup');
        $fileName = "LAPORAN_OUTGOING_SELLER_" . strtoupper($seller) . ($onlyFollowUp ? "_FOLLOW_UP" : "") . "_" . date('Ymd_His') . ".xlsx";

        if (class_exists(\App\Exports\SellerAliqaExport::class) && class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\SellerAliqaExport($seller, $onlyFollowUp), $fileName);
        }

        return $this->exportColoredExcel($request);
    }


    /**
     * Process Excel / Spreadsheet file upload or Google Sheets link and run batch tracking
     */
    public function process(Request $request)
    {
        // Increase execution time and memory for handling large spreadsheets (e.g. 40k+ rows)
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        $request->validate([
            'excel_file' => 'nullable|file',
            'spreadsheet_url' => 'nullable|url',
            'use_dummy' => 'nullable|boolean',
            'month' => 'nullable|string',
            'year' => 'nullable|integer',
            'filter_mode' => 'nullable|string|in:only_empty,undelivered,all',
        ]);

        $useDummy = $request->boolean('use_dummy');
        $filterMode = $request->input('filter_mode', 'only_empty');
        $targetMonth = strtoupper($request->input('month', 'AGUSTUS'));
        $targetYear = (int)$request->input('year', 2026);
        $spreadsheetUrl = $request->input('spreadsheet_url');

        $tempPath = null;
        $originalName = 'SPREADSHEET';

        if ($request->hasFile('excel_file')) {
            $file = $request->file('excel_file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $tempPath = $file->getRealPath();
        } elseif (!empty($spreadsheetUrl)) {
            // Support Google Spreadsheet URL
            try {
                $exportUrl = $spreadsheetUrl;
                if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $spreadsheetUrl, $m)) {
                    $sheetKey = $m[1];
                    $gid = '0';
                    if (preg_match('/gid=(\d+)/', $spreadsheetUrl, $gm)) {
                        $gid = $gm[1];
                    }
                    $exportUrl = "https://docs.google.com/spreadsheets/d/{$sheetKey}/export?format=csv&gid={$gid}";
                }

                $csvContent = @file_get_contents($exportUrl);
                if ($csvContent === false) {
                    return back()->with('error', 'Gagal mengakses link Google Spreadsheet. Pastikan link dapat diakses publik (Anyone with the link).');
                }

                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }
                $tempPath = $tempDir . DIRECTORY_SEPARATOR . 'gsheet_' . time() . '.csv';
                file_put_contents($tempPath, $csvContent);
                $originalName = 'GOOGLE_SPREADSHEET';
            } catch (Throwable $e) {
                return back()->with('error', 'Gagal memproses Google Spreadsheet: ' . $e->getMessage());
            }
        } elseif ($useDummy || file_exists(base_path('POS_INPROSES_DUMMY_2026.xlsx'))) {
            $tempPath = base_path('POS_INPROSES_DUMMY_2026.xlsx');
            $originalName = 'POS_INPROSES_DUMMY_2026';
        } else {
            return back()->with('error', 'Silakan pilih file Excel/Spreadsheet atau masukkan link Google Spreadsheet.');
        }

        try {
            // Memory & speed optimization: setReadDataOnly avoids loading fonts, styles, drawings
            $reader = IOFactory::createReaderForFile($tempPath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($tempPath);
            $sheetNames = $spreadsheet->getSheetNames();

            // Build map: sheetName => matched month
            $sheetMonthMap = [];
            foreach ($sheetNames as $name) {
                foreach (Shipment::MONTHS as $m) {
                    if (str_contains(strtoupper($name), $m)) {
                        $sheetMonthMap[$name] = $m;
                        break;
                    }
                }
            }

            // If no sheet matched any month name, fall back to active sheet with targetMonth
            if (empty($sheetMonthMap)) {
                $sheetMonthMap[$spreadsheet->getActiveSheet()->getTitle()] = $targetMonth;
            }

            $pendingResis = [];
            $importedCount = 0;
            $processedMonths = [];
            $now = now()->toDateTimeString();

            DB::beginTransaction();

            foreach ($sheetMonthMap as $sheetName => $matchedMonth) {
                $activeSheet = $spreadsheet->getSheetByName($sheetName);
                if (!$activeSheet) {
                    continue;
                }

                $highestRow = $activeSheet->getHighestRow();
                if ($highestRow < 2) {
                    continue;
                }

                $processedMonths[] = $matchedMonth;

                // High speed array extraction for columns A to M (13 columns)
                $rows = $activeSheet->rangeToArray("A2:M{$highestRow}", null, false, false, false);

                // Fetch existing shipments for this month & year into keyBy map
                $existingMap = Shipment::where('month', $matchedMonth)
                    ->where('year', $targetYear)
                    ->get()
                    ->keyBy('resi');

                $insertRows = [];

                foreach ($rows as $idx => $row) {
                    $excelRow = $idx + 2;

                    $resi = trim((string)($row[4] ?? ''));
                    if (empty($resi)) {
                        continue;
                    }

                    $tanggal = trim((string)($row[1] ?? '')) ?: date('Y-m-d');
                    $namaKonsumen = trim((string)($row[2] ?? ''));
                    $invoice = trim((string)($row[3] ?? ''));
                    $alamat = trim((string)($row[5] ?? ''));
                    $namaCs = trim((string)($row[6] ?? '')) ?: 'CRM DILA';
                    $produk = trim((string)($row[7] ?? '')) ?: 'LAMBUNG CERIA ZAHERBA';
                    $noHp = trim((string)($row[8] ?? ''));
                    $jumlahCod = trim((string)($row[9] ?? ''));
                    $keterangan = trim((string)($row[10] ?? ''));
                    $status = trim((string)($row[11] ?? ''));
                    $sla = trim((string)($row[12] ?? ''));

                    $statusUpper = strtoupper($status);
                    $isDelivered = str_contains($statusUpper, 'DELIVERED') && !str_contains($statusUpper, 'RETURN');
                    $isReturn = str_contains($statusUpper, 'RETURN') || str_contains($statusUpper, 'RETUR');

                    if ($isDelivered) {
                        $initColor = 'BIRU';
                        $initFollowUp = false;
                    } elseif ($isReturn) {
                        $initColor = 'ORANGE';
                        $initFollowUp = false;
                    } else {
                        $initColor = 'PUTIH';
                        $initFollowUp = true;
                    }

                    $defaultKet = $keterangan ?: ($isReturn ? 'BARANG RETUR DITERIMA' : ($isDelivered ? 'DITERIMA YANG BERSANGKUTAN' : 'PROSES PENGIRIMAN POS'));
                    $defaultStatus = $status ?: ($isReturn ? 'DELIVERED (RETURN DELIVERY)' : 'DELIVERED');
                    $defaultSla = $sla ?: '2';

                    if (isset($existingMap[$resi])) {
                        $s = $existingMap[$resi];
                        $s->row_index = $excelRow;
                        $s->tanggal = $tanggal;
                        $s->nama_konsumen = $namaKonsumen;
                        $s->invoice = $invoice;
                        $s->alamat = $alamat;
                        $s->nama_cs = $namaCs;
                        $s->produk = $produk;
                        $s->no_hp = $noHp;
                        $s->jumlah_cod = $jumlahCod;
                        $s->keterangan = $defaultKet;
                        $s->status = $defaultStatus;
                        $s->sla = $defaultSla;
                        $s->color_code = $initColor;
                        $s->needs_follow_up = $initFollowUp;
                        if ($s->isDirty()) {
                            $s->save();
                        }
                    } else {
                        $insertRows[] = [
                            'month' => $matchedMonth,
                            'year' => $targetYear,
                            'type' => 'keluar',
                            'row_index' => $excelRow,
                            'tanggal' => $tanggal,
                            'nama_konsumen' => $namaKonsumen,
                            'invoice' => $invoice,
                            'resi' => $resi,
                            'alamat' => $alamat,
                            'nama_cs' => $namaCs,
                            'produk' => $produk,
                            'no_hp' => $noHp,
                            'jumlah_cod' => $jumlahCod,
                            'keterangan' => $defaultKet,
                            'status' => $defaultStatus,
                            'sla' => $defaultSla,
                            'color_code' => $initColor,
                            'needs_follow_up' => $initFollowUp,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $importedCount++;

                    $currentStatus = strtoupper($status);
                    $isCurrentlyDone = (str_contains($currentStatus, 'DELIVERED') && !str_contains($currentStatus, 'RETURN'))
                        || str_contains($currentStatus, 'RETURN')
                        || str_contains($currentStatus, 'RETUR');
                    $shouldTrack = false;

                    if ($filterMode === 'all') {
                        $shouldTrack = true;
                    } elseif ($filterMode === 'undelivered') {
                        if (empty($currentStatus) || !$isCurrentlyDone) {
                            $shouldTrack = true;
                        }
                    } else {
                        // only_empty: track only those with no status yet in the file
                        if (empty($status)) {
                            $shouldTrack = true;
                        }
                    }

                    if ($shouldTrack) {
                        $pendingResis[] = [
                            'row' => $excelRow,
                            'resi' => $resi,
                            'month' => $matchedMonth,
                        ];
                    }
                }

                // Batch insert new records in safe chunks of 50 to fit SQLite variable limits
                if (!empty($insertRows)) {
                    foreach (array_chunk($insertRows, 50) as $chunk) {
                        DB::table('shipments')->insert($chunk);
                    }
                }
            }

            DB::commit();

            // Disconnect worksheets to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            // Run bot tracking on pending resis if any (cap to max 100 per import session for responsiveness)
            if (!empty($pendingResis)) {
                $resiListToTrack = array_slice($pendingResis, 0, 100);
                $resisOnly = array_column($resiListToTrack, 'resi');
                
                $shipmentsToTrack = Shipment::whereIn('resi', $resisOnly)
                    ->where('year', $targetYear)
                    ->get()
                    ->keyBy('resi');

                $targetUrl = route('mock.nipos');
                $chunks = array_chunk($resiListToTrack, 25);
                foreach ($chunks as $chunk) {
                    $trackedResults = $this->botService->trackResiList($chunk, $targetUrl);

                    foreach ($chunk as $p) {
                        $r = $p['resi'];
                        if (isset($trackedResults[$r], $shipmentsToTrack[$r])) {
                            $res = $trackedResults[$r];
                            $s = $shipmentsToTrack[$r];
                            $stat = strtoupper($res['status']);
                            $isDeliv = str_contains($stat, 'DELIVERED') && !str_contains($stat, 'RETURN');
                            $isRet = str_contains($stat, 'RETURN') || str_contains($stat, 'RETUR');

                            $s->status = $res['status'];
                            $s->keterangan = $res['keterangan'];
                            $s->sla = $res['sla'];
                            $s->color_code = $isDeliv ? 'BIRU' : ($isRet ? 'ORANGE' : 'PUTIH');
                            $s->needs_follow_up = !$isDeliv && !$isRet;
                            $s->last_scanned_at = now();
                            $s->save();
                        }
                    }
                }

                $msg = "Berhasil mengimpor {$importedCount} data dari " . count($processedMonths) . " bulan (" . implode(', ', $processedMonths) . "). " . count($resiListToTrack) . " resi di-tracking.";
            } else {
                $msg = "Berhasil mengimpor {$importedCount} data dari " . count($processedMonths) . " bulan (" . implode(', ', $processedMonths) . "). Seluruh data berstatus DELIVERED / RETUR.";
            }

            // Redirect ke bulan pertama yang diproses, atau bulan yg dipilih user
            $redirectMonth = $processedMonths[0] ?? $targetMonth;
            return redirect()->route('tracking.index', ['month' => $redirectMonth, 'year' => $targetYear])
                ->with('success', $msg);

        } catch (Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }

    /**
     * Export colored Excel for active month or all 12 months
     */
    public function exportColoredExcel(Request $request)
    {
        $month = strtoupper($request->input('month', 'AGUSTUS'));
        $year = (int)$request->input('year', 2026);
        $exportAllMonths = $month === 'ALL';

        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0); // Remove default sheet

            $targetMonths = $exportAllMonths ? Shipment::MONTHS : [$month];

            foreach ($targetMonths as $m) {
                $shipments = Shipment::where('month', $m)->where('year', $year)->orderBy('id', 'asc')->get();
                if ($shipments->isEmpty() && $exportAllMonths) {
                    continue;
                }

                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($m . ' (ZAHERBA)');

                // Set headers (Columns A to M)
                $headers = [
                    'A1' => 'NO',
                    'B1' => 'TANGGAL',
                    'C1' => 'NAMA KONSUMEN',
                    'D1' => 'INVOICE',
                    'E1' => 'RESI',
                    'F1' => 'ALAMAT',
                    'G1' => 'NAMA CS',
                    'H1' => 'PRODUK',
                    'I1' => 'NO HP',
                    'J1' => 'JUMLAH COD',
                    'K1' => 'KETERANGAN',
                    'L1' => 'TRACKING POS',
                    'M1' => 'SLA (MASA TAHAN)',
                ];

                foreach ($headers as $cell => $val) {
                    $sheet->setCellValue($cell, $val);
                }

                // Style Header
                $sheet->getStyle('A1:M1')->getFont()->setBold(true);
                $sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF97316'); // Orange Header
                $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFFFF');

                $rowNum = 2;
                foreach ($shipments as $idx => $s) {
                    $sheet->setCellValue('A' . $rowNum, $idx + 1);
                    $sheet->setCellValue('B' . $rowNum, $s->tanggal);
                    $sheet->setCellValue('C' . $rowNum, $s->nama_konsumen);
                    $sheet->setCellValue('D' . $rowNum, $s->invoice);
                    $sheet->setCellValue('E' . $rowNum, $s->resi);
                    $sheet->setCellValue('F' . $rowNum, $s->alamat);
                    $sheet->setCellValue('G' . $rowNum, $s->nama_cs);
                    $sheet->setCellValue('H' . $rowNum, $s->produk);
                    $sheet->setCellValue('I' . $rowNum, $s->no_hp);
                    $sheet->setCellValue('J' . $rowNum, $s->jumlah_cod);
                    $sheet->setCellValue('K' . $rowNum, $s->keterangan);
                    $sheet->setCellValue('L' . $rowNum, $s->status);
                    $sheet->setCellValue('M' . $rowNum, $s->sla);

                    // Apply fill color according to color_code
                    $colorDef = self::COLOR_PALETTE[$s->color_code] ?? self::COLOR_PALETTE['PUTIH'];
                    $range = "A{$rowNum}:M{$rowNum}";
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($colorDef['argb_fill']);

                    if (!empty($colorDef['argb_font']) && $colorDef['argb_font'] === 'FFFFFFFF') {
                        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFFFFFFF');
                    }

                    $rowNum++;
                }

                // Auto-fit column widths
                foreach (range('A', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            if ($spreadsheet->getSheetCount() === 0) {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('EMPTY');
                $sheet->setCellValue('A1', 'Tidak ada data pengiriman.');
            }

            $storageDir = storage_path('app/public/tracked');
            if (!File::exists($storageDir)) {
                File::makeDirectory($storageDir, 0755, true);
            }

            $filename = "REKAP_TRACKING_POS_{$month}_{$year}_" . date('Ymd_His') . ".xlsx";
            $exportPath = $storageDir . DIRECTORY_SEPARATOR . $filename;

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($exportPath);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'download_url' => route('tracking.download', ['filename' => $filename]),
                    'filename' => $filename,
                ]);
            }

            return response()->download($exportPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (Throwable $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal membuat file Excel: ' . $e->getMessage());
        }
    }

    /**
     * Download tracked Excel file
     */
    public function download(string $filename)
    {
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
     * Mock NIPOS@MID endpoint for testing
     */
    public function mockNipos(Request $request)
    {
        $barcode = $request->input('barcode', $request->input('cari_barcode', ''));
        $presetStatus = $request->input('preset_status', '');
        $ajax = $request->ajax() || $request->wantsJson() || $request->has('ajax');

        $htmlResult = '';
        if (!empty($barcode)) {
            $trackingData = $this->botService->generateSimulatedResult($barcode);
            if (!empty($presetStatus)) {
                $trackingData['status'] = $this->botService->normalizeNiposStatus($presetStatus);
                if ($trackingData['status'] === 'FAILEDTODELIVERED') {
                    $trackingData['keterangan'] = 'RUMAH KOSONG (PERLU FOLLOW UP CS)';
                } elseif ($trackingData['status'] === 'INLOCATION') {
                    $trackingData['keterangan'] = 'TIBA DI KANTOR POS / DC TUJUAN (BELUM DITERIMA - PERLU FOLLOW UP)';
                } elseif ($trackingData['status'] === 'DELIVERYRUNSHEET') {
                    $trackingData['keterangan'] = 'SEDANG DIBAWA KURIR ANTARAN';
                }
            }

            $isRetur = str_contains($trackingData['status'], 'RETURN');
            $irregularity = $isRetur ? 'Retur Barang' : '-';
            $statusCod = $isRetur ? 'COD Retur' : 'COD Terbayar';
            $petugasLoket = 'Pt Zaherba Indonesia Bahagia (595062740)';
            $kantorKirim = 'KC CILACAP 53200';
            $tglKolekting = '2026-01-02 20:39:34';
            $tglUpdate = '2026-01-12 22:06:23';
            $petugasUpdate = 'Diki Cahyo Putranto';
            $isiKiriman = 'HERBAL HI_251229000117';
            $va = '2411083595062740';

            $htmlResult = "
                <div class='table-responsive p-1 overflow-x-auto'>
                    <table class='table-auto w-full text-[11px] border border-sky-300 rounded overflow-hidden'>
                        <thead class='bg-sky-500 text-white font-bold uppercase'>
                            <tr>
                                <th class='p-2 text-center border-r border-sky-400'>No</th>
                                <th class='p-2 text-left border-r border-sky-400'>Barcode</th>
                                <th class='p-2 text-left border-r border-sky-400'>ExtID</th>
                                <th class='p-2 text-left border-r border-sky-400'>No.Bag Akhir</th>
                                <th class='p-2 text-left border-r border-sky-400'>Tanggal Kolekting</th>
                                <th class='p-2 text-left border-r border-sky-400'>Kantor Kirim</th>
                                <th class='p-2 text-left border-r border-sky-400'>Petugas Loket</th>
                                <th class='p-2 text-left border-r border-sky-400'>Status Akhir</th>
                                <th class='p-2 text-left border-r border-sky-400'>Irregularity</th>
                                <th class='p-2 text-left border-r border-sky-400'>Posisi Akhir</th>
                                <th class='p-2 text-left border-r border-sky-400'>Tanggal Update</th>
                                <th class='p-2 text-left border-r border-sky-400'>Petugas Update</th>
                                <th class='p-2 text-left border-r border-sky-400'>Penerima</th>
                                <th class='p-2 text-left border-r border-sky-400'>Tlp Penerima</th>
                                <th class='p-2 text-center border-r border-sky-400'>SLA</th>
                                <th class='p-2 text-left border-r border-sky-400'>SWP</th>
                                <th class='p-2 text-left border-r border-sky-400'>Isi Kiriman</th>
                                <th class='p-2 text-left border-r border-sky-400'>Status COD</th>
                                <th class='p-2 text-left'>Virtual Account</th>
                            </tr>
                        </thead>
                        <tbody class='divide-y divide-slate-200 bg-white'>
                            <tr class='hover:bg-slate-50'>
                                <td class='p-2 text-center border-r'>1</td>
                                <td class='p-2 font-mono font-bold text-amber-600 border-r'>{$barcode}</td>
                                <td class='p-2 font-mono border-r'>{$barcode}</td>
                                <td class='p-2 border-r'></td>
                                <td class='p-2 font-mono border-r'>{$tglKolekting}</td>
                                <td class='p-2 border-r'>{$kantorKirim}</td>
                                <td class='p-2 border-r'>{$petugasLoket}</td>
                                <td class='p-2 font-bold text-slate-800 border-r'>{$trackingData['status']}</td>
                                <td class='p-2 border-r'>{$irregularity}</td>
                                <td class='p-2 border-r'>{$kantorKirim}</td>
                                <td class='p-2 font-mono border-r'>{$tglUpdate}</td>
                                <td class='p-2 border-r'>{$petugasUpdate}</td>
                                <td class='p-2 font-semibold text-slate-800 border-r'>{$trackingData['keterangan']}</td>
                                <td class='p-2 border-r'>0</td>
                                <td class='p-2 font-bold text-center border-r'>{$trackingData['sla']}</td>
                                <td class='p-2 border-r'></td>
                                <td class='p-2 border-r'>{$isiKiriman}</td>
                                <td class='p-2 font-semibold border-r'>{$statusCod}</td>
                                <td class='p-2 font-mono text-[10px]'>{$va}</td>
                            </tr>
                        </tbody>
                    </table>
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

    /**
     * Helper to track a single shipment instance
     */
    protected function trackSingleShipment(Shipment $shipment): void
    {
        $targetUrl = route('mock.nipos');
        $client = $this->botService->getClient();

        try {
            $result = $this->botService->trackSingleResi($client, $targetUrl, $shipment->resi);

            $stat = strtoupper($result['status']);
            $isDelivered = str_contains($stat, 'DELIVERED') && !str_contains($stat, 'RETURN');
            $isReturn = str_contains($stat, 'RETURN') || str_contains($stat, 'RETUR') || $shipment->type === 'masuk';

            $shipment->status = $result['status'];
            $shipment->keterangan = $result['keterangan'];
            $shipment->sla = $result['sla'];
            $shipment->color_code = $isDelivered ? 'BIRU' : ($isReturn ? 'ORANGE' : 'PUTIH');
            $shipment->needs_follow_up = !$isDelivered && !$isReturn;
            $shipment->last_scanned_at = now();
            $shipment->save();
        } finally {
            $this->botService->closeClient();
        }
    }

    /**
     * Calculate summary statistics for a collection of shipments
     */
    protected function calculateStats($shipments): array
    {
        $total = $shipments->count();
        $delivered = $shipments->filter(fn($s) => $s->isDelivered())->count();
        $retur = $shipments->filter(fn($s) => $s->isReturn())->count();
        $inproses = $shipments->filter(fn($s) => !$s->isDelivered() && !$s->isReturn())->count();
        $needsFollowUp = $shipments->where('needs_follow_up', true)->count();

        $colorCounts = [
            'BIRU' => $shipments->where('color_code', 'BIRU')->count(),
            'ORANGE' => $shipments->where('color_code', 'ORANGE')->count(),
            'KUNING' => $shipments->where('color_code', 'KUNING')->count(),
            'PUTIH' => $shipments->where('color_code', 'PUTIH')->count(),
            'HIJAU' => $shipments->where('color_code', 'HIJAU')->count(),
            'BIRU_TUA' => $shipments->where('color_code', 'BIRU_TUA')->count(),
        ];

        return [
            'total' => $total,
            'delivered' => $delivered,
            'retur' => $retur,
            'inproses' => $inproses,
            'needs_follow_up' => $needsFollowUp,
            'pct_delivered' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'pct_retur' => $total > 0 ? round(($retur / $total) * 100, 2) : 0,
            'pct_inproses' => $total > 0 ? round(($inproses / $total) * 100, 2) : 0,
            'color_counts' => $colorCounts,
        ];
    }

    /**
     * Pre-seed database with records from dummy file
     */
    protected function seedFromDummyFile(): void
    {
        $dummyPath = base_path('POS_INPROSES_DUMMY_2026.xlsx');
        if (!file_exists($dummyPath)) {
            return;
        }

        try {
            $spreadsheet = IOFactory::load($dummyPath);
            $sheet = $spreadsheet->getSheetByName('AGUSTUS (ZAHERBA)') ?: $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 2; $row <= $highestRow; $row++) {
                $resi = trim((string)$sheet->getCell('E' . $row)->getValue());
                if (empty($resi)) {
                    continue;
                }

                $status = trim((string)$sheet->getCell('L' . $row)->getValue()) ?: 'DELIVERED';
                $keterangan = trim((string)$sheet->getCell('K' . $row)->getValue()) ?: 'DITERIMA YANG BERSANGKUTAN';
                $sla = trim((string)$sheet->getCell('M' . $row)->getValue()) ?: '2';

                $statusUpper = strtoupper($status);
                $isDelivered = str_contains($statusUpper, 'DELIVERED') && !str_contains($statusUpper, 'RETURN');
                $isReturn = str_contains($statusUpper, 'RETURN') || str_contains($statusUpper, 'RETUR');

                Shipment::create([
                    'month' => 'AGUSTUS',
                    'year' => 2026,
                    'type' => $isReturn ? 'masuk' : 'keluar',
                    'row_index' => $row,
                    'tanggal' => trim((string)$sheet->getCell('B' . $row)->getValue()) ?: '2026-08-01',
                    'nama_konsumen' => trim((string)$sheet->getCell('C' . $row)->getValue()),
                    'invoice' => trim((string)$sheet->getCell('D' . $row)->getValue()),
                    'resi' => $resi,
                    'alamat' => trim((string)$sheet->getCell('F' . $row)->getValue()),
                    'nama_cs' => trim((string)$sheet->getCell('G' . $row)->getValue()) ?: 'CRM DILA',
                    'produk' => trim((string)$sheet->getCell('H' . $row)->getValue()) ?: 'LAMBUNG CERIA ZAHERBA',
                    'no_hp' => trim((string)$sheet->getCell('I' . $row)->getValue()),
                    'jumlah_cod' => trim((string)$sheet->getCell('J' . $row)->getValue()),
                    'keterangan' => $keterangan,
                    'status' => $status,
                    'sla' => $sla,
                    'color_code' => $isDelivered ? 'BIRU' : ($isReturn ? 'ORANGE' : 'PUTIH'),
                    'needs_follow_up' => !$isDelivered && !$isReturn,
                ]);
            }
        } catch (Throwable $e) {
            // Ignore pre-seed failure
        }
    }
}
