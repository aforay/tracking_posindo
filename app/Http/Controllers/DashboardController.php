<?php

namespace App\Http\Controllers;

use App\Exports\SellerAliqaExport;
use App\Imports\ShipmentsImport;
use App\Jobs\ProcessExcelImportJob;
use App\Jobs\ProcessGoogleSheetSyncJob;
use App\Jobs\ProcessNiposTrackingJob;
use App\Jobs\UpdateSheetStatusJob;
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
        // Dummy data seeding removed - ensure stats return 0 when database is empty

        $selectedSeller = $request->input('seller', 'ALL');
        $selectedKategori = $request->input('kategori', 'ALL');
        $selectedMonth = $request->input('month', 'ALL');
        $selectedYear = $request->input('year', null);
        $selectedColor = $request->input('color', null);
        $searchQuery = trim($request->input('search', ''));

        $query = OutgoingShipment::query();

        // 1. Month & Year Filters
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

        if ($selectedMonth !== 'ALL' && $selectedMonth !== null && $selectedMonth !== '') {
            if (is_numeric($selectedMonth)) {
                $mInt = (int)$selectedMonth;
                $mNum = ($mInt >= 0 && $mInt <= 11) ? ($mInt + 1) : $mInt;
                $query->whereMonth('tanggal_kirim', $mNum);
            } else {
                $mNum = $monthMap[strtoupper((string)$selectedMonth)] ?? null;
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
            $cleanSeller = trim(preg_replace('/^Mitra\s+/i', '', $selectedSeller));
            $query->where(function ($q) use ($selectedSeller, $cleanSeller) {
                $q->where('nama_seller', $selectedSeller)
                  ->orWhere('nama_seller', $cleanSeller)
                  ->orWhere('nama_seller', 'Mitra ' . $cleanSeller)
                  ->orWhere('nama_seller', 'LIKE', "%{$cleanSeller}%");
            });
        }

        // 3. Color & Kategori Filter
        if (!empty($selectedColor) && $selectedColor !== 'ALL') {
            $cUpper = strtoupper($selectedColor);
            $query->where(function ($q) use ($cUpper) {
                if ($cUpper === 'BIRU') {
                    $q->where('color_code', 'BIRU')
                      ->orWhere(function ($sub) {
                          $sub->where(function ($c) {
                              $c->whereNull('color_code')->orWhere('color_code', '');
                          })->where('status_kategori', 'SUKSES');
                      });
                } elseif ($cUpper === 'ORANGE') {
                    $q->where('color_code', 'ORANGE')
                      ->orWhere(function ($sub) {
                          $sub->where(function ($c) {
                              $c->whereNull('color_code')->orWhere('color_code', '');
                          })->where('status_kategori', 'RETUR');
                      });
                } elseif ($cUpper === 'KUNING') {
                    $q->where('color_code', 'KUNING')
                      ->orWhere(function ($sub) {
                          $sub->where(function ($c) {
                              $c->whereNull('color_code')->orWhere('color_code', '');
                          })->where('status_kategori', 'FOLLOW_UP');
                      });
                } elseif ($cUpper === 'PUTIH') {
                    $q->where('color_code', 'PUTIH')
                      ->orWhere(function ($sub) {
                          $sub->where(function ($c) {
                              $c->whereNull('color_code')->orWhere('color_code', '');
                          })->where(function ($sub2) {
                              $sub2->where('status_kategori', 'IN_PROCESS')
                                   ->orWhereNull('status_kategori')
                                   ->orWhere('status_kategori', '')
                                   ->orWhereNotIn('status_kategori', ['SUKSES', 'RETUR', 'FOLLOW_UP']);
                          });
                      });
                } elseif ($cUpper === 'HIJAU' || $cUpper === 'BIRU_TUA') {
                    $q->where('color_code', $cUpper);
                } else {
                    $q->where('color_code', $cUpper);
                }
            });
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

        // Summary Statistics Cards (calculated respecting seller/month/year/search filters, but independent of color filter so all card counters reflect the full breakdown)
        $statsBaseQuery = OutgoingShipment::query();
        if ($selectedMonth !== 'ALL' && $selectedMonth !== null && $selectedMonth !== '') {
            if (is_numeric($selectedMonth)) {
                $mInt = (int)$selectedMonth;
                $mNum = ($mInt >= 0 && $mInt <= 11) ? ($mInt + 1) : $mInt;
                $statsBaseQuery->whereMonth('tanggal_kirim', $mNum);
            } else {
                $mNum = $monthMap[strtoupper((string)$selectedMonth)] ?? null;
                if ($mNum) {
                    $statsBaseQuery->whereMonth('tanggal_kirim', $mNum);
                }
            }
        }
        if (!empty($selectedYear)) {
            $statsBaseQuery->whereYear('tanggal_kirim', (int)$selectedYear);
        }
        if (!empty($selectedSeller) && $selectedSeller !== 'ALL' && $selectedSeller !== 'Semua Seller') {
            $cleanSeller = trim(preg_replace('/^Mitra\s+/i', '', $selectedSeller));
            $statsBaseQuery->where(function ($q) use ($selectedSeller, $cleanSeller) {
                $q->where('nama_seller', $selectedSeller)
                  ->orWhere('nama_seller', $cleanSeller)
                  ->orWhere('nama_seller', 'Mitra ' . $cleanSeller)
                  ->orWhere('nama_seller', 'LIKE', "%{$cleanSeller}%");
            });
        }
        if (!empty($searchQuery)) {
            $terms = preg_split('/[\s,\n;]+/', $searchQuery);
            $terms = array_filter(array_map('trim', $terms));

            $statsBaseQuery->where(function ($q) use ($terms, $searchQuery) {
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

        $stats = [
            'total' => (clone $statsBaseQuery)->count(),
            'sukses' => (clone $statsBaseQuery)->where(function ($q) {
                $q->where('color_code', 'BIRU')
                  ->orWhere(function ($sub) {
                      $sub->where(function ($c) {
                          $c->whereNull('color_code')->orWhere('color_code', '');
                      })->where('status_kategori', 'SUKSES');
                  });
            })->count(),
            'retur' => (clone $statsBaseQuery)->where(function ($q) {
                $q->where('color_code', 'ORANGE')
                  ->orWhere(function ($sub) {
                      $sub->where(function ($c) {
                          $c->whereNull('color_code')->orWhere('color_code', '');
                      })->where('status_kategori', 'RETUR');
                  });
            })->count(),
            'belum' => (clone $statsBaseQuery)->where(function ($q) {
                $q->where('color_code', 'PUTIH')
                  ->orWhere(function ($sub) {
                      $sub->where(function ($c) {
                          $c->whereNull('color_code')->orWhere('color_code', '');
                      })->where(function ($sub2) {
                          $sub2->where('status_kategori', 'IN_PROCESS')
                               ->orWhereNull('status_kategori')
                               ->orWhere('status_kategori', '')
                               ->orWhereNotIn('status_kategori', ['SUKSES', 'RETUR', 'FOLLOW_UP']);
                      });
                  });
            })->count(),
            'sudah_fu' => (clone $statsBaseQuery)->where(function ($q) {
                $q->where('color_code', 'KUNING')
                  ->orWhere(function ($sub) {
                      $sub->where(function ($c) {
                          $c->whereNull('color_code')->orWhere('color_code', '');
                      })->where('status_kategori', 'FOLLOW_UP');
                  });
            })->count(),
            'fu_2_kali' => (clone $statsBaseQuery)->where('color_code', 'HIJAU')->count(),
            'fu_pos' => (clone $statsBaseQuery)->where('color_code', 'BIRU_TUA')->count(),
            'follow_up' => (clone $statsBaseQuery)->where(function ($q) {
                $q->whereIn('color_code', ['KUNING', 'HIJAU', 'BIRU_TUA'])
                  ->orWhere(function ($sub) {
                      $sub->where(function ($c) {
                          $c->whereNull('color_code')->orWhere('color_code', '');
                      })->where('status_kategori', 'FOLLOW_UP');
                  });
            })->count(),
        ];

        // 50 Items Per Page Pagination to prevent Memory Exhaustion
        $paginatedShipments = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        $formattedShipmentsData = collect($paginatedShipments->items())->map(function ($s) {
            $fu = 'PUTIH';
            if (!empty($s->color_code)) {
                $fu = strtoupper($s->color_code);
            } elseif ($s->status_kategori === 'SUKSES') {
                $fu = 'BIRU';
            } elseif ($s->status_kategori === 'RETUR') {
                $fu = 'ORANGE';
            } elseif ($s->status_kategori === 'FOLLOW_UP') {
                $fu = 'KUNING';
            } else {
                $fu = 'PUTIH';
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

        // Calculate accurate monthly shipment distribution for month tabs
        $monthQuery = OutgoingShipment::query();
        if (!empty($selectedSeller) && $selectedSeller !== 'ALL' && $selectedSeller !== 'Semua Seller') {
            $cleanSeller = trim(preg_replace('/^Mitra\s+/i', '', $selectedSeller));
            $monthQuery->where(function ($q) use ($selectedSeller, $cleanSeller) {
                $q->where('nama_seller', $selectedSeller)
                  ->orWhere('nama_seller', $cleanSeller)
                  ->orWhere('nama_seller', 'Mitra ' . $cleanSeller)
                  ->orWhere('nama_seller', 'LIKE', "%{$cleanSeller}%");
            });
        }
        if (!empty($selectedYear)) {
            $monthQuery->whereYear('tanggal_kirim', (int)$selectedYear);
        }

        $monthCounts = array_fill(0, 12, 0);
        for ($m = 1; $m <= 12; $m++) {
            $monthCounts[$m - 1] = (clone $monthQuery)->whereMonth('tanggal_kirim', $m)->count();
        }
        $yearTotal = (clone $monthQuery)->count();

        $dbSellers = OutgoingShipment::select('nama_seller')
            ->distinct()
            ->whereNotNull('nama_seller')
            ->where('nama_seller', '!=', '')
            ->where('nama_seller', '!=', 'ALL')
            ->pluck('nama_seller')
            ->toArray();

        $defaultSellers = ['Mitra Aliqa', 'Mitra Zaherba', 'Mitra Herbal', 'Mitra Nusantara', 'Mitra Barokah'];
        $formattedDbSellers = array_map(fn($s) => str_starts_with($s, 'Mitra ') ? $s : 'Mitra ' . $s, $dbSellers);
        $sellersList = array_values(array_unique(array_merge($defaultSellers, $formattedDbSellers)));

        return Inertia::render('Dashboard', [
            'shipments' => $paginatedResult,
            'stats' => $stats,
            'monthCounts' => $monthCounts,
            'yearTotal' => $yearTotal,
            'sellersList' => array_values(array_unique($sellersList)),
            'googleSheetUrl' => SystemSetting::get('google_sheet_url', 'https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit'),
            'googleSheetId' => SystemSetting::get('google_sheet_id', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw'),
            'googleSheetWebhookUrl' => SystemSetting::get('google_sheet_webhook_url', env('GOOGLE_SHEET_WEBHOOK_URL', '')),
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
     * Bulk status update for multiple resis from Inertia/React frontend (AJAX)
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

        $kategori = 'IN_PROCESS';
        if ($fu === 'BIRU') {
            $kategori = 'SUKSES';
        } elseif ($fu === 'ORANGE') {
            $kategori = 'RETUR';
        } elseif (in_array($fu, ['KUNING', 'HIJAU', 'BIRU_TUA'])) {
            $kategori = 'FOLLOW_UP';
        }

        $updateData = [
            'color_code' => $fu,
            'status_kategori' => $kategori,
            'updated_at' => now(),
        ];
        if ($escDate) {
            $updateData['fu_pos_date'] = $escDate;
        }

        OutgoingShipment::whereIn('id', $ids)->update($updateData);

        // Two-Way Sync to Google Sheets in background queue
        $resis = OutgoingShipment::whereIn('id', $ids)->pluck('no_resi')->toArray();
        if (!empty($resis)) {
            UpdateSheetStatusJob::dispatch($resis, $fu, null, $escDate);
        }

        return back()->with('success', 'Status ' . count($ids) . ' resi berhasil diperbarui & disinkronkan ke Google Sheets!');
    }

    /**
     * Update color, escalation date, or note for a single shipment via AJAX
     */
    public function updateColor(Request $request, int $id)
    {
        $request->validate([
            'color_code' => 'nullable|string',
            'color' => 'nullable|string',
            'fu_pos_date' => 'nullable|string',
            'noted' => 'nullable|string',
        ]);

        $shipment = OutgoingShipment::findOrFail($id);
        $colorInput = $request->input('color_code', $request->input('color', ''));
        $color = strtoupper((string)$colorInput);

        if (!empty($color) && in_array($color, ['BIRU', 'ORANGE', 'KUNING', 'PUTIH', 'HIJAU', 'BIRU_TUA'])) {
            $shipment->color_code = $color;
            if ($color === 'BIRU') {
                $shipment->status_kategori = 'SUKSES';
            } elseif ($color === 'ORANGE') {
                $shipment->status_kategori = 'RETUR';
            } elseif (in_array($color, ['KUNING', 'HIJAU', 'BIRU_TUA'])) {
                $shipment->status_kategori = 'FOLLOW_UP';
            } else {
                $shipment->status_kategori = 'IN_PROCESS';
            }
        }

        if ($request->has('fu_pos_date')) {
            $shipment->fu_pos_date = $request->input('fu_pos_date');
        }

        if ($request->has('noted')) {
            $shipment->noted = $request->input('noted');
        }

        $shipment->save();

        // Two-Way Sync to Google Sheets in background queue
        if (!empty($shipment->no_resi)) {
            UpdateSheetStatusJob::dispatch(
                [$shipment->no_resi],
                $shipment->color_code ?: 'PUTIH',
                $shipment->noted,
                $shipment->fu_pos_date
            );
        }

        $successMsg = "Status resi {$shipment->no_resi} berhasil diperbarui & disinkronkan ke Google Sheets!";

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
     * Bulk Action: Mark multiple resis as Completed, Follow-Up, Retur, or Delete
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:outgoing_shipments,id',
            'action' => 'required|string',
        ]);

        $ids = $request->input('ids');
        $action = strtoupper($request->input('action'));

        if ($action === 'DELETE') {
            OutgoingShipment::whereIn('id', $ids)->delete();
            $msg = count($ids) . " data resi berhasil dihapus.";
        } else {
            $kategori = 'IN_PROCESS';
            if ($action === 'BIRU' || $action === 'SUKSES') {
                $kategori = 'SUKSES';
                $action = 'BIRU';
            } elseif ($action === 'ORANGE' || $action === 'RETUR') {
                $kategori = 'RETUR';
                $action = 'ORANGE';
            } elseif (in_array($action, ['KUNING', 'HIJAU', 'BIRU_TUA', 'FOLLOW_UP'])) {
                $kategori = 'FOLLOW_UP';
                if ($action === 'FOLLOW_UP') $action = 'KUNING';
            }

            OutgoingShipment::whereIn('id', $ids)->update([
                'color_code' => $action,
                'status_kategori' => $kategori,
                'status_pos' => $kategori === 'SUKSES' ? 'DELIVERED' : ($kategori === 'RETUR' ? 'DELIVERED (RETURN DELIVERY)' : 'PERLU FOLLOW UP CS'),
                'updated_at' => now(),
            ]);

            // Two-Way Sync to Google Sheets in background queue
            $resis = OutgoingShipment::whereIn('id', $ids)->pluck('no_resi')->toArray();
            if (!empty($resis)) {
                UpdateSheetStatusJob::dispatch($resis, $action);
            }

            $msg = "Status " . count($ids) . " resi berhasil diperbarui & disinkronkan ke Google Sheets!";
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
     * Helper to seed initial sample records (DISABLED)
     */
    protected function seedInitialDummyData(): void
    {
        // Dummy data seeding disabled to ensure clean 0 stats on empty database
        return;
    }

    /**
     * Save dynamic Google Spreadsheet URL, ID, and Webhook URL to system_settings table
     */
    public function updateGoogleSheetsSetting(Request $request)
    {
        $url = trim($request->input('url', ''));
        $webhookUrl = trim($request->input('webhook_url', ''));

        if (empty($url)) {
            return back()->with('error', 'URL Google Spreadsheet tidak boleh kosong.');
        }

        $id = SystemSetting::extractSpreadsheetId($url);
        if (!$id) {
            return back()->with('error', 'Format URL Google Spreadsheet tidak valid.');
        }

        SystemSetting::set('google_sheet_url', $url);
        SystemSetting::set('google_sheet_id', $id);

        if (!empty($webhookUrl)) {
            SystemSetting::set('google_sheet_webhook_url', $webhookUrl);
        }

        return back()->with('success', "URL Google Spreadsheet & Webhook berhasil diperbarui (ID: {$id}).");
    }

    /**
     * Trigger background Google Sheets sync job
     */
    public function syncGoogleSheets(Request $request)
    {
        $url = trim($request->input('url', ''));
        $webhookUrl = trim($request->input('webhook_url', ''));
        $seller = $request->input('seller', 'Aliqa');

        if (!empty($url)) {
            $id = SystemSetting::extractSpreadsheetId($url);
            if ($id) {
                SystemSetting::set('google_sheet_url', $url);
                SystemSetting::set('google_sheet_id', $id);
            }
        }

        if (!empty($webhookUrl)) {
            SystemSetting::set('google_sheet_webhook_url', $webhookUrl);
        }

        ProcessGoogleSheetSyncJob::dispatch($url ?: null, $seller);

        return redirect()->back()->with('success', 'Sinkronisasi Google Sheets berhasil dikirim ke background Queue Worker!');
    }
}