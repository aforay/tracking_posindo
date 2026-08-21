<?php

namespace App\Http\Controllers;

use App\Exports\SellerAliqaExport;
use App\Imports\ShipmentsImport;
use App\Jobs\ProcessNiposTrackingJob;
use App\Models\OutgoingShipment;
use App\Services\TrackingBotService;
use Illuminate\Http\Request;
use Inertia\Inertia; // <--- DITAMBAHKAN
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
        $searchQuery = trim($request->input('search', ''));

        $baseQuery = OutgoingShipment::query();

        if ($selectedSeller !== 'ALL' && $selectedSeller !== 'Semua Seller') {
            $baseQuery->where('nama_seller', $selectedSeller);
        }

        // Summary Statistics Cards
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'sukses' => (clone $baseQuery)->where('status_kategori', 'SUKSES')->count(),
            'retur' => (clone $baseQuery)->where('status_kategori', 'RETUR')->count(),
            'follow_up' => (clone $baseQuery)->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])->count(),
        ];

        // Filter tab priority
        if ($selectedKategori !== 'ALL') {
            $baseQuery->where('status_kategori', strtoupper($selectedKategori));
        }

        // Search Query
        if (!empty($searchQuery)) {
            $baseQuery->where(function ($q) use ($searchQuery) {
                $q->where('no_resi', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('nama_penerima', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('no_hp', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('alamat', 'LIKE', "%{$searchQuery}%")
                  ->orWhere('status_pos', 'LIKE', "%{$searchQuery}%");
            });
        }

        $shipments = $baseQuery->orderBy('id', 'desc')->paginate(20)->withQueryString();

        $sellersList = OutgoingShipment::select('nama_seller')
            ->distinct()
            ->whereNotNull('nama_seller')
            ->orderBy('nama_seller', 'asc')
            ->pluck('nama_seller')
            ->toArray();

        if (!in_array('Aliqa', $sellersList)) {
            array_unshift($sellersList, 'Aliqa');
        }

        // --- DIUBAH DARI view() MENJADI Inertia::render() ---
        return Inertia::render('Dashboard', [
            'shipments' => $shipments,
            'stats' => $stats,
            'sellersList' => array_values(array_unique($sellersList)),
            'filters' => [
                'seller' => $selectedSeller,
                'kategori' => $selectedKategori,
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
     * Handle Excel upload using ShipmentsImport
     */
    public function import(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:102400',
            'default_seller' => 'nullable|string',
        ]);

        $defaultSeller = $request->input('default_seller', 'Aliqa');

        try {
            $file = $request->file('excel_file');
            Excel::import(new ShipmentsImport($defaultSeller), $file);

            ProcessNiposTrackingJob::dispatch();

            return redirect()->back()
                ->with('success', "File Excel berhasil diimpor! Background process melacak status resi NIPOS telah dijalankan.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal mengimpor file: " . $e->getMessage());
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
     * Trigger background tracking job manually
     */
    public function trackNow(Request $request)
    {
        ProcessNiposTrackingJob::dispatch();
        return back()->with('success', 'Job pelacakan resi NIPOS@MID berhasil dikirim ke background queue!');
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
}