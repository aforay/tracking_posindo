<?php

namespace App\Http\Controllers;

use App\Exports\SellerAliqaExport;
use App\Imports\ShipmentsImport;
use App\Jobs\ProcessExcelImportJob;
use App\Jobs\ProcessGoogleSheetSyncJob;
use App\Jobs\ProcessNiposTrackingJob;
use App\Jobs\ReverseSyncGoogleSheetsJob;
use App\Jobs\SyncSheetFilterJob;
use App\Jobs\UpdateSheetStatusJob;
use App\Models\OutgoingShipment;
use App\Models\SystemSetting;
use App\Services\GoogleSheetsSyncService;
use App\Services\TrackingBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $rawSeller = $request->input('seller');
        if (empty($rawSeller) || $rawSeller === 'Semua Seller' || $rawSeller === 'ALL') {
            $rawSeller = session('selected_seller', 'Mitra Aliqa');
        }
        $selectedSeller = str_contains(strtoupper((string)$rawSeller), 'ZAHERBA') ? 'Mitra Zaherba' : 'Mitra Aliqa';
        session(['selected_seller' => $selectedSeller]);
        $selectedKategori = $request->input('kategori');
        $selectedMonth = $request->input('month', 'ALL');
        $selectedYear = $request->input('year', date('Y'));
        $selectedColor = $request->input('color');
        $searchQuery = $request->input('search');
        $isOverdue = $request->boolean('overdue') || $request->input('sla_filter') === 'overdue';
        $sortBy = $request->input('sort', 'sheet');
        $sortDirection = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Auto-sync active filter to Google Spreadsheet via Webhook (with Debounce & Throttling)
        if ($request->has('seller') || $request->has('month') || $request->has('color') || $request->has('search')) {
            $filterSig = md5("{$selectedSeller}|{$selectedMonth}|{$selectedColor}|{$searchQuery}");
            $sigCacheKey = 'sheet_filter_sig_' . md5($selectedSeller);

            // Only dispatch when the filter parameters have actually changed
            if (Cache::get($sigCacheKey) !== $filterSig) {
                Cache::put($sigCacheKey, $filterSig, now()->addHours(2));

                // Debounce: prune any pending stale SyncSheetFilterJob in the queue so only the latest is executed
                try {
                    DB::table('jobs')
                        ->where('payload', 'LIKE', '%SyncSheetFilterJob%')
                        ->delete();
                } catch (\Throwable $e) {
                    // Ignore DB errors if table locked or in-memory sqlite testing
                }

                // Dispatch with 3-second delay debounce to absorb rapid keystrokes/clicks
                SyncSheetFilterJob::dispatch(
                    $selectedSeller,
                    $selectedMonth,
                    $selectedColor,
                    $searchQuery
                )->delay(now()->addSeconds(3));
            }
        }

        $query = OutgoingShipment::query();

        // 1. Month & Year Filters
        $monthMap = [
            'JANUARI' => 1, 'JAN' => 1,
            'FEBRUARI' => 2, 'FEB' => 2,
            'MARET' => 3, 'MAR' => 3,
            'APRIL' => 4, 'APR' => 4,
            'MEI' => 5, 'MAY' => 5,
            'JUNI' => 6, 'JUN' => 6,
            'JULI' => 7, 'JUL' => 7, 'JULY' => 7,
            'AGUSTUS' => 8, 'AGT' => 8, 'AGUS' => 8, 'AGU' => 8, 'AUGUST' => 8, 'AUG' => 8,
            'SEPTEMBER' => 9, 'SEP' => 9,
            'OKTOBER' => 10, 'OKT' => 10, 'OCT' => 10,
            'NOVEMBER' => 11, 'NOV' => 11,
            'DESEMBER' => 12, 'DES' => 12, 'DEC' => 12,
        ];

        $mNum = null;
        if ($selectedMonth !== 'ALL' && $selectedMonth !== null && $selectedMonth !== '') {
            $monthStrUpper = strtoupper((string)$selectedMonth);
            if (isset($monthMap[$monthStrUpper])) {
                $mNum = $monthMap[$monthStrUpper];
            } elseif (is_numeric($selectedMonth)) {
                $val = (int)$selectedMonth;
                if ($val >= 1 && $val <= 12) {
                    $mNum = $val;
                }
            }

            if ($mNum !== null) {
                $query->whereMonth('tanggal_kirim', $mNum);
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

        // 4. Multi-resi, Fuzzy Name, & Multi-field Search Query
        $applySearchQuery = function ($builder, $rawSearch) {
            $raw = trim((string)$rawSearch);
            if ($raw === '') {
                return;
            }

            // Split into tokens by comma, whitespace, semicolon, newline
            $terms = preg_split('/[\s,\n;]+/', $raw);
            $terms = array_values(array_filter(array_map('trim', $terms)));

            // Clean keywords: only letters and numbers, length >= 2
            $words = array_values(array_filter(array_map(function ($w) {
                return trim(preg_replace('/[^\p{L}\p{N}]/u', '', $w));
            }, $terms), function ($w) {
                return mb_strlen($w) >= 2;
            }));

            $builder->where(function ($q) use ($raw, $terms, $words) {
                // 1. Resi match (exact list if pasted multiple, or partial)
                if (!empty($terms)) {
                    $q->whereIn('no_resi', $terms);
                }
                $q->orWhere('no_resi', 'LIKE', "%{$raw}%");

                // 2. Direct partial match on customer name, phone, address, office, status
                $q->orWhere('nama_penerima', 'LIKE', "%{$raw}%")
                  ->orWhere('no_hp', 'LIKE', "%{$raw}%")
                  ->orWhere('alamat', 'LIKE', "%{$raw}%")
                  ->orWhere('kantor_tujuan', 'LIKE', "%{$raw}%")
                  ->orWhere('last_location', 'LIKE', "%{$raw}%")
                  ->orWhere('status_pos', 'LIKE', "%{$raw}%");

                // 3. Multi-word name match: ALL words present in nama_penerima (regardless of order!)
                // e.g. "Rina Taruk" or "Taruk Rina" matches "Rina Tarukbua"
                if (count($words) > 1) {
                    $q->orWhere(function ($allWordsQ) use ($words) {
                        foreach ($words as $w) {
                            $allWordsQ->where('nama_penerima', 'LIKE', "%{$w}%");
                        }
                    });
                }

                // 4. Fuzzy fallback: ANY significant word (>= 3 chars) in nama_penerima or alamat
                // e.g. "Tarukbua" or "Sukahar" or "Ningsih"
                if (count($words) > 1) {
                    foreach ($words as $w) {
                        if (mb_strlen($w) >= 3 && !is_numeric($w)) {
                            $q->orWhere('nama_penerima', 'LIKE', "%{$w}%")
                              ->orWhere('alamat', 'LIKE', "%{$w}%");
                        }
                    }
                }
            });
        };

        if (!empty($searchQuery)) {
            $applySearchQuery($query, $searchQuery);
        }

        // Summary Statistics Cards (calculated respecting seller/month/year/search filters, but independent of color filter so all card counters reflect the full breakdown)
        $statsBaseQuery = OutgoingShipment::query();
        if ($mNum !== null) {
            $statsBaseQuery->whereMonth('tanggal_kirim', $mNum);
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
            $applySearchQuery($statsBaseQuery, $searchQuery);
        }

        // 5. Overdue / Lewat SLA / Macet > 4 Hari Filter
        $fourDaysAgo = now()->subDays(4)->toDateString();
        if ($isOverdue) {
            $query->where(function ($q) {
                $q->whereNull('status_kategori')
                  ->orWhereNotIn('status_kategori', ['SUKSES', 'RETUR']);
            })->where(function ($q) {
                $q->whereNull('color_code')
                  ->orWhereNotIn('color_code', ['BIRU', 'ORANGE']);
            })->whereDate('tanggal_kirim', '<=', $fourDaysAgo);
        }

        // Summary Statistics Cards (calculated respecting seller/month/year/search filters, but independent of color filter so all card counters reflect the full breakdown)
        // Single Query Aggregation: calculate all 8 KPI cards plus tracking progress in 1 single fast query
        $statsRow = (clone $statsBaseQuery)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN color_code = 'BIRU' OR ((color_code IS NULL OR color_code = '') AND status_kategori = 'SUKSES') THEN 1 ELSE 0 END) as sukses,
            SUM(CASE WHEN color_code = 'ORANGE' OR ((color_code IS NULL OR color_code = '') AND status_kategori = 'RETUR') THEN 1 ELSE 0 END) as retur,
            SUM(CASE WHEN color_code = 'PUTIH' OR ((color_code IS NULL OR color_code = '') AND (status_kategori = 'IN_PROCESS' OR status_kategori IS NULL OR status_kategori = '' OR status_kategori NOT IN ('SUKSES', 'RETUR', 'FOLLOW_UP'))) THEN 1 ELSE 0 END) as belum,
            SUM(CASE WHEN color_code = 'KUNING' OR ((color_code IS NULL OR color_code = '') AND status_kategori = 'FOLLOW_UP') THEN 1 ELSE 0 END) as sudah_fu,
            SUM(CASE WHEN color_code = 'HIJAU' THEN 1 ELSE 0 END) as fu_2_kali,
            SUM(CASE WHEN color_code = 'BIRU_TUA' THEN 1 ELSE 0 END) as fu_pos,
            SUM(CASE WHEN color_code IN ('KUNING', 'HIJAU', 'BIRU_TUA') OR ((color_code IS NULL OR color_code = '') AND status_kategori = 'FOLLOW_UP') THEN 1 ELSE 0 END) as follow_up,
            SUM(CASE WHEN last_tracked_at IS NOT NULL THEN 1 ELSE 0 END) as tracked,
            SUM(CASE WHEN (
                (status_kategori IS NULL OR status_kategori NOT IN ('SUKSES', 'RETUR')) 
                AND (color_code IS NULL OR color_code NOT IN ('BIRU', 'ORANGE')) 
                AND tanggal_kirim <= '{$fourDaysAgo}'
            ) THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN (
                status_pos IS NULL OR status_pos = '' OR status_pos LIKE '%PROCESS%' 
                OR status_kategori NOT IN ('SUKSES', 'RETUR') OR status_kategori IS NULL 
                OR color_code NOT IN ('BIRU', 'ORANGE') OR color_code IS NULL 
                OR status_pos IN ('unBag', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'inBag', 'INBAG', 'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'Irregularity', 'MANIFEST', 'ARRIVAL', 'DEPARTURE')
                OR ((status_kategori = 'RETUR' OR color_code = 'ORANGE') AND (status_pos IS NULL OR (status_pos NOT LIKE '%RETURN DELIVERY%' AND status_pos NOT LIKE '%RETURN TO SENDER%' AND status_pos NOT LIKE '%DITERIMA PENGIRIM%')))
                OR ((status_kategori = 'SUKSES' OR color_code = 'BIRU') AND (status_pos IS NULL OR status_pos NOT LIKE '%DELIVERED%' OR status_pos LIKE '%RETURN%'))
            ) THEN 1 ELSE 0 END) as pending
        ")->first();

        $stats = [
            'total' => (int)($statsRow->total ?? 0),
            'sukses' => (int)($statsRow->sukses ?? 0),
            'retur' => (int)($statsRow->retur ?? 0),
            'belum' => (int)($statsRow->belum ?? 0),
            'sudah_fu' => (int)($statsRow->sudah_fu ?? 0),
            'fu_2_kali' => (int)($statsRow->fu_2_kali ?? 0),
            'fu_pos' => (int)($statsRow->fu_pos ?? 0),
            'follow_up' => (int)($statsRow->follow_up ?? 0),
            'overdue' => (int)($statsRow->overdue ?? 0),
        ];

        $allPostOffices = \App\Models\PostOffice::orderBy('province', 'asc')->orderBy('city', 'asc')->orderBy('name', 'asc')->get();
        $officeMapById = $allPostOffices->keyBy('id');

        // Sorting: Default urut persis seperti baris di Spreadsheet dari atas ke bawah (id asc)
        if ($sortBy === 'nama') {
            $query->orderByRaw("CASE WHEN nama_penerima IS NULL OR nama_penerima = '' THEN 1 ELSE 0 END")
                  ->orderBy('nama_penerima', $sortDirection)
                  ->orderBy('id', 'asc');
        } elseif ($sortBy === 'tanggal') {
            $query->orderBy('tanggal_kirim', $sortDirection)->orderBy('id', 'asc');
        } elseif ($sortBy === 'resi') {
            $query->orderBy('no_resi', $sortDirection);
        } else {
            // Default: 'sheet' -> Urutan persis seperti susunan baris di Google Spreadsheet dari baris atas ke bawah
            $query->orderBy('id', $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $paginatedShipments = $query->paginate(50)->withQueryString();

        // Dispatch non-blocking background queue job for missing kantor_tujuan (instant response < 50ms)
        $missingKantorIds = collect($paginatedShipments->items())
            ->filter(function ($s) {
                return empty($s->kantor_tujuan) && empty($s->last_location) && !empty($s->no_resi);
            })
            ->pluck('id')
            ->values()
            ->all();

        if (!empty($missingKantorIds)) {
            try {
                \App\Jobs\ProcessNiposTrackingJob::dispatch($missingKantorIds);
            } catch (\Throwable $e) {
                // Ignore queue dispatch error
            }
        }

        $formattedShipmentsData = collect($paginatedShipments->items())->map(function ($s) use ($officeMapById) {
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

            // Match post office for destination / KC
            $office = null;
            if (!empty($s->kantor_pos_id) && isset($officeMapById[$s->kantor_pos_id])) {
                $office = $officeMapById[$s->kantor_pos_id];
            } else {
                $office = \App\Models\PostOffice::matchByDestinationOrAddress($s->kantor_tujuan, $s->alamat);
            }

            $genericNames = ['KC TUJUAN', 'KANTOR POS TUJUAN', 'KC PENGANTARAN', 'KC POS PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA'];
            $kantorTujuan = null;
            if ($office && !empty($office->name)) {
                $kantorTujuan = strtoupper(trim($office->name));
            } elseif (!empty($s->kantor_tujuan) && !in_array(strtoupper(trim($s->kantor_tujuan)), $genericNames)) {
                $kantorTujuan = strtoupper(trim($s->kantor_tujuan));
            } elseif (!empty($s->last_location) && !in_array(strtoupper(trim($s->last_location)), $genericNames)) {
                $kantorTujuan = strtoupper(trim($s->last_location));
            } else {
                // Try extracting from status_pos or keterangan if it mentions "di KC ..." or "tujuan KC ..."
                $fromStatus = $this->botService->extractKantorTujuan($s->status_pos ?? '', $s->keterangan ?? '');
                if (!empty($fromStatus) && !in_array(strtoupper(trim($fromStatus)), $genericNames)) {
                    $kantorTujuan = strtoupper(trim($fromStatus));
                } else {
                    $derived = self::deriveKantorPosFromAddress($s->alamat);
                    $kantorTujuan = (!empty($derived) && !in_array(strtoupper(trim($derived)), $genericNames)) ? $derived : null;
                }
            }

            $sellerRaw = $s->nama_seller;
            $rowSeller = (empty($sellerRaw) || str_contains(strtoupper($sellerRaw), 'ALIQA')) 
                ? 'Mitra Aliqa' 
                : (str_contains(strtoupper($sellerRaw), 'ZAHERBA') ? 'Mitra Zaherba' : (str_starts_with($sellerRaw, 'Mitra ') ? $sellerRaw : 'Mitra ' . $sellerRaw));

            $isRetur = ($fu === 'ORANGE') || ($s->status_kategori === 'RETUR');
            $isDelivered = !$isRetur && (($fu === 'BIRU') || ($s->status_kategori === 'SUKSES'));
            $isFinal = $isDelivered || $isRetur;

            $sla = (int)($s->sla_days ?? 2);
            if (!$isFinal && !empty($s->tanggal_kirim)) {
                try {
                    $tglKirim = \Carbon\Carbon::parse($s->tanggal_kirim)->startOfDay();
                    $today = now()->startOfDay();
                    $elapsedDays = abs((int)$today->diffInDays($tglKirim));
                    $targetSla = ($s->sla_days !== null && $s->sla_days > 0) ? $s->sla_days : 2;

                    if ($s->sla_days !== null && $s->sla_days < 0) {
                        $sla = $s->sla_days;
                    } elseif ($elapsedDays > $targetSla) {
                        $sla = -1 * ($elapsedDays - $targetSla);
                    } else {
                        $sla = max(0, $targetSla - $elapsedDays);
                    }
                } catch (\Throwable $e) {
                    $sla = (int)($s->sla_days ?? 2);
                }
            }

            return [
                'id' => (string)$s->id,
                'resi' => $s->no_resi,
                'seller' => $rowSeller,
                'tanggalKirim' => $tglStr,
                'tujuan' => $s->alamat ?? '-',
                'penerima' => $s->nama_penerima ?? '-',
                'telepon' => $s->no_hp ?? '-',
                'alamat' => $s->alamat ?? '-',
                'keterangan' => $s->keterangan ?? '-',
                'nipos' => $s->status_pos ?? 'ON PROCESS',
                'sla' => $sla,
                'fu' => $fu,
                'note' => $s->noted ?: $s->keterangan,
                'escalationDate' => $s->fu_pos_date,
                'lastTrackedAt' => $s->last_tracked_at ? (is_string($s->last_tracked_at) ? $s->last_tracked_at : $s->last_tracked_at->format('Y-m-d H:i')) : null,
                'statusKategori' => $s->status_kategori,
                'kantorTujuan' => $kantorTujuan,
                'kantorPosPhone' => $office ? $office->phone_wa : '',
                'kantorPosPic' => $office ? ($office->pic_name ?: $office->name) : '',
                'lastLocation' => $s->last_location ?: $kantorTujuan,
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

        // Calculate accurate monthly shipment distribution for month tabs via single grouped aggregation
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

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $monthSql = $driver === 'sqlite' ? "CAST(strftime('%m', tanggal_kirim) AS INTEGER)" : "MONTH(tanggal_kirim)";

        $monthRows = (clone $monthQuery)
            ->selectRaw("
                {$monthSql} as m,
                COUNT(*) as total,
                SUM(CASE WHEN (
                    status_pos IS NULL 
                    OR status_pos = '' 
                    OR status_pos LIKE '%PROCESS%' 
                    OR status_kategori NOT IN ('SUKSES', 'RETUR') 
                    OR status_kategori IS NULL 
                    OR color_code NOT IN ('BIRU', 'ORANGE') 
                    OR color_code IS NULL 
                    OR status_pos IN ('unBag', 'UNBAG', 'INVEHICLE', 'INLOCATION', 'inBag', 'INBAG', 'DELIVERYRUNSHEET', 'FAILEDTODELIVERED', 'ARRIVEDUNPAID', 'Irregularity', 'MANIFEST', 'ARRIVAL', 'DEPARTURE')
                    OR (
                        (status_kategori = 'RETUR' OR color_code = 'ORANGE') 
                        AND (status_pos IS NULL OR (status_pos NOT LIKE '%RETURN DELIVERY%' AND status_pos NOT LIKE '%RETURN TO SENDER%' AND status_pos NOT LIKE '%DITERIMA PENGIRIM%'))
                    )
                    OR (
                        (status_kategori = 'SUKSES' OR color_code = 'BIRU') 
                        AND (status_pos IS NULL OR status_pos NOT LIKE '%DELIVERED%' OR status_pos LIKE '%RETURN%')
                    )
                ) THEN 1 ELSE 0 END) as pending
            ")
            ->whereNotNull('tanggal_kirim')
            ->groupByRaw($monthSql)
            ->get();

        $monthCounts = array_fill(0, 12, 0);
        $monthPendingCounts = array_fill(0, 12, 0);
        foreach ($monthRows as $row) {
            $mIdx = (int)$row->m;
            if ($mIdx >= 1 && $mIdx <= 12) {
                $monthCounts[$mIdx - 1] = (int)$row->total;
                $monthPendingCounts[$mIdx - 1] = (int)$row->pending;
            }
        }
        $yearTotal = array_sum($monthCounts);

        $sellersList = ['Mitra Aliqa', 'Mitra Zaherba'];

        $totalShipments = $stats['total'];
        $pendingShipments = (int)($statsRow->pending ?? 0);
        $trackedShipments = (int)($statsRow->tracked ?? 0);
        $progressPct = $totalShipments > 0 ? round((($totalShipments - $pendingShipments) / $totalShipments) * 100, 1) : 100;

        $isZaherba = str_contains(strtoupper($selectedSeller), 'ZAHERBA');
        $defaultSheetId = $isZaherba
            ? env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw')
            : env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');
        $sheetUrl = SystemSetting::get('google_sheet_url_' . ($isZaherba ? 'zaherba' : 'aliqa'))
            ?: "https://docs.google.com/spreadsheets/d/{$defaultSheetId}/edit";

        $currentUser = \Illuminate\Support\Facades\Auth::user();

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => $currentUser ? [
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'email' => $currentUser->email,
                    'role' => $currentUser->role,
                    'is_admin' => $currentUser->isAdmin(),
                ] : null,
            ],
            'shipments' => $paginatedResult,
            'stats' => $stats,
            'monthCounts' => $monthCounts,
            'monthPendingCounts' => $monthPendingCounts,
            'yearTotal' => $yearTotal,
            'sellersList' => $sellersList,
            'postOffices' => $allPostOffices,
            'trackingProgress' => [
                'total' => $totalShipments,
                'tracked' => $trackedShipments,
                'pending' => $pendingShipments,
                'percentage' => $progressPct,
                'is_running' => false,
            ],
            'googleSheetUrl' => $sheetUrl,
            'googleSheetId' => $defaultSheetId,
            'googleSheetWebhookUrl' => SystemSetting::get('google_sheet_webhook_url', env('GOOGLE_SHEET_WEBHOOK_URL', '')),
            'filters' => [
                'seller' => $selectedSeller,
                'kategori' => $selectedKategori,
                'month' => $mNum !== null ? $mNum : 'ALL',
                'year' => $selectedYear,
                'color' => $selectedColor,
                'search' => $searchQuery,
                'sort' => $sortBy,
                'direction' => $sortDirection,
                'overdue' => $isOverdue,
            ]
        ]);
    }

    /**
     * Real-time Tracking Progress Bar API Endpoint
     */
    public function progress()
    {
        $total = OutgoingShipment::count();
        $delivered = OutgoingShipment::where('status_kategori', 'SUKSES')->count();
        $retur = OutgoingShipment::where('status_kategori', 'RETUR')->count();
        $tracked = OutgoingShipment::whereNotNull('last_tracked_at')->count();
        $pending = OutgoingShipment::needsTracking()->count();
        $percentage = $total > 0 ? round((($total - $pending) / $total) * 100, 1) : 100;

        return response()->json([
            'total' => $total,
            'tracked' => $tracked,
            'delivered' => $delivered,
            'retur' => $retur,
            'pending' => $pending,
            'percentage' => $percentage,
            'is_running' => $pending > 0,
        ]);
    }

    /**
     * Real-time Google Sheets Sync Progress API Endpoint
     */
    public function syncProgress()
    {
        $progress = \Illuminate\Support\Facades\Cache::get('sync_progress', [
            'is_syncing'          => false,
            'current_sheet'       => '',
            'current_sheet_index' => 0,
            'total_sheets'        => 0,
            'processed_rows'      => 0,
            'inserted_rows'       => 0,
            'percentage'          => 0,
            'message'             => 'Idle',
        ]);

        return response()->json($progress);
    }

    /**
     * Status jadwal auto-sync (Sheet → DB dan DB → Sheet) untuk ditampilkan di frontend.
     * Mengembalikan: waktu terakhir sync, interval, apakah sync aktif, dll.
     */
    public function syncScheduleStatus()
    {
        $lastSheetSync = \Illuminate\Support\Facades\Cache::get('last_sheet_sync_at');
        $lastPushSync  = \Illuminate\Support\Facades\Cache::get('last_push_sync_at');
        $lastNiposSync = \Illuminate\Support\Facades\Cache::get('last_nipos_sync_at');

        $syncProgress = \Illuminate\Support\Facades\Cache::get('sync_progress', []);
        $isSyncing    = $syncProgress['is_syncing'] ?? false;

        // Hitung berapa menit lagi sync berikutnya (interval 5 menit dari terakhir sync)
        $nextSync = null;
        if ($lastSheetSync) {
            $lastAt  = \Illuminate\Support\Carbon::parse($lastSheetSync);
            $nextAt  = $lastAt->copy()->addMinutes(5);
            $nextSync = $nextAt->isFuture() ? $nextAt->diffForHumans() : 'sebentar lagi';
        }

        return response()->json([
            'auto_sync_enabled'    => true,
            'sync_interval_minutes'=> 5,
            'is_syncing'           => $isSyncing,
            'last_sheet_sync_at'   => $lastSheetSync,
            'last_push_sync_at'    => $lastPushSync,
            'last_nipos_sync_at'   => $lastNiposSync,
            'next_sync_in'         => $nextSync ?? 'Segera',
            'schedule_description' => 'Auto-sync setiap 5 menit (Sheet ↔ DB) | Bot NIPPOS setiap 15 menit',
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

        // Audit Trail: Catat riwayat ke tabel shipment_logs
        foreach ($ids as $sId) {
            \App\Models\ShipmentLog::logAction(
                $sId,
                'UPDATE_STATUS',
                "Status diubah ke {$fu}" . ($escDate ? " (Tgl Eskalasi: {$escDate})" : "")
            );
        }

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

        // Audit Trail: Catat riwayat ke tabel shipment_logs
        $logDetails = [];
        if (!empty($color)) $logDetails[] = "Status: {$color}";
        if ($request->has('noted') && $request->input('noted')) $logDetails[] = "Catatan: " . $request->input('noted');
        if ($request->has('fu_pos_date') && $request->input('fu_pos_date')) $logDetails[] = "Tgl Eskalasi: " . $request->input('fu_pos_date');

        \App\Models\ShipmentLog::logAction(
            $shipment->id,
            $request->has('noted') ? 'NOTED' : 'UPDATE_COLOR',
            implode(' | ', $logDetails) ?: "Perubahan status {$color}"
        );

        // Two-Way Sync ke Google Sheets di background queue
        // Kirim payload lengkap termasuk fu_timestamp agar Apps Script tahu kapan FU dilakukan
        if (!empty($shipment->no_resi)) {
            UpdateSheetStatusJob::dispatch(
                [$shipment->no_resi],
                $shipment->color_code ?: 'PUTIH',
                $shipment->noted,
                $shipment->fu_pos_date,
                now()->toDateTimeString()   // fu_timestamp
            );
        }

        $successMsg = "Status resi {$shipment->no_resi} berhasil diperbarui & disinkronkan ke Google Sheets!";

        if ($request->header('X-Inertia')) {
            return back()->with('success', $successMsg);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'shipment' => $shipment,
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Get audit trail logs for a shipment
     */
    public function shipmentLogs(int $id)
    {
        try {
            $shipment = OutgoingShipment::findOrFail($id);

            $logs = \App\Models\ShipmentLog::where('shipment_id', $shipment->id)
                ->with(['user:id,name,email,role'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'note' => $log->note,
                        'created_at' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'role' => $log->user->role,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'shipment' => [
                    'id' => $shipment->id,
                    'no_resi' => $shipment->no_resi,
                    'nama_penerima' => $shipment->nama_penerima,
                    'status_pos' => $shipment->status_pos,
                    'color_code' => $shipment->color_code,
                ],
                'logs' => $logs,
            ])->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat log: ' . $e->getMessage(),
                'logs' => [],
            ], 500)->header('Content-Type', 'application/json');
        }
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
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya Admin yang memiliki izin untuk menghapus data resi.',
                    ], 403);
                }
                abort(403, 'Hanya Admin yang memiliki izin untuk menghapus data resi.');
            }

            foreach ($ids as $sId) {
                \App\Models\ShipmentLog::logAction($sId, 'DELETE', 'Data resi dihapus oleh ' . ($user ? $user->name : 'Admin'));
            }
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

            // Audit Trail: Catat riwayat bulk action ke tabel shipment_logs
            foreach ($ids as $sId) {
                \App\Models\ShipmentLog::logAction(
                    $sId,
                    'BULK_UPDATE',
                    "Status massal diubah ke {$action} ({$kategori})"
                );
            }

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
     * Handle Excel upload: High-speed streaming import (< 20MB RAM, instant execution)
     */
    public function import(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

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

            // High-speed direct streaming import
            $importer = new ShipmentsImport($defaultSeller);
            $syncedItems = $importer->importFile($storedPath, $defaultSeller);

            // Clean up temp file
            if (file_exists($storedPath)) {
                @unlink($storedPath);
            }

            // Otomatis sinkronkan data hasil FU Kantor Pos ke Google Spreadsheet (Two-Way Sync / Reverse Sync)
            $syncedCount = count($syncedItems);
            if ($syncedCount > 0) {
                foreach (array_chunk($syncedItems, 250) as $chunk) {
                    \App\Jobs\ReverseSyncGoogleSheetsJob::dispatch($chunk);
                }
                Log::info("DashboardController::import: Dispatched ReverseSyncGoogleSheetsJob for {$syncedCount} imported items.");
                return redirect()->back()->with('success', "File data ({$syncedCount} resi) berhasil diimpor! Data follow-up & perubahan warna otomatis disinkronkan ke Google Sheets.");
            }

            return redirect()->back()->with('success', 'File data berhasil diimpor! Seluruh data resi telah tersimpan fix ke sistem.');
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal memproses file: ' . $e->getMessage());
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
        // Fetch only resi IDs needing tracking (excluding terminal SUKSES and terminal RETUR)
        $shipmentIds = OutgoingShipment::needsTracking()
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
     * Trigger Google Sheets sync
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

        try {
            $syncService = app(\App\Services\GoogleSheetsSyncService::class);
            $summary = $syncService->sync($url ?: null, $seller);
            return redirect()->back()->with('success', "Sinkronisasi Google Sheets Berhasil! {$summary['total_rows_processed']} baris diproses dari {$summary['total_sheets']} sheet.");
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Gagal sinkronisasi Google Sheets: ' . $e->getMessage());
        }
    }

    /**
     * Explicit trigger to sync filter state to Google Spreadsheet
     */
    public function syncFilter(Request $request)
    {
        $seller = $request->input('seller', 'ALL');
        $month = $request->input('month', 'ALL');
        $color = $request->input('color', 'ALL');
        $search = $request->input('search', '');

        SyncSheetFilterJob::dispatch($seller, $month, $color, $search);

        return back()->with('success', 'Filter berhasil disinkronkan ke Google Spreadsheet!');
    }

    /**
     * Discover sheet names for AJAX sync with optional month/sheet filtering
     */
    public function syncDiscover(Request $request)
    {
        $url = trim($request->input('url', ''));
        $webhookUrl = trim($request->input('webhook_url', ''));
        $targetMonth = $request->input('month');
        $targetSheet = $request->input('sheet');
        $seller = $request->input('seller', 'Mitra Aliqa');
        $isZaherba = str_contains(strtoupper((string)$seller), 'ZAHERBA');

        if (empty($url)) {
            $defaultId = $isZaherba
                ? env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw')
                : env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');
            $url = SystemSetting::get('google_sheet_url_' . ($isZaherba ? 'zaherba' : 'aliqa'))
                ?: "https://docs.google.com/spreadsheets/d/{$defaultId}/edit";
        }

        $spreadsheetId = SystemSetting::extractSpreadsheetId($url);
        if (!$spreadsheetId) {
            $spreadsheetId = $isZaherba
                ? env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw')
                : env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');
        }

        if (!$spreadsheetId) {
            return response()->json(['success' => false, 'message' => 'URL Google Spreadsheet tidak valid.'], 400);
        }

        // Save URL & ID if provided
        if (!empty($url)) {
            SystemSetting::set('google_sheet_url', $url);
        }
        SystemSetting::set('google_sheet_id', $spreadsheetId);

        if (!empty($webhookUrl)) {
            SystemSetting::set('google_sheet_webhook_url', $webhookUrl);
        }

        try {
            $syncService = app(\App\Services\GoogleSheetsSyncService::class);
            $sheetNames = $syncService->discoverSheetNames($spreadsheetId);

            // Filter sheetNames if targetSheet or targetMonth is passed
            if (!empty($targetSheet) && strtoupper((string)$targetSheet) !== 'ALL') {
                $tUpper = strtoupper(trim($targetSheet));
                $filtered = array_filter($sheetNames, fn($s) => str_contains(strtoupper($s), $tUpper) || strtoupper($s) === $tUpper);
                if (!empty($filtered)) {
                    $sheetNames = array_values($filtered);
                }
            } elseif (!empty($targetMonth) && strtoupper((string)$targetMonth) !== 'ALL' && (string)$targetMonth !== '0') {
                $mNum = (int)$targetMonth;
                if ($mNum >= 1 && $mNum <= 12) {
                    $monthKeywords = [
                        1 => ['JAN', 'JANUARI'], 2 => ['FEB', 'FEBRUARI'], 3 => ['MAR', 'MARET'],
                        4 => ['APR', 'APRIL'], 5 => ['MEI', 'MAY'], 6 => ['JUN', 'JUNI'],
                        7 => ['JUL', 'JULI'], 8 => ['AGT', 'AGUS', 'AGUSTUS', 'AUG'], 9 => ['SEP', 'SEPTEMBER'],
                        10 => ['OKT', 'OKTOBER', 'OCT'], 11 => ['NOV', 'NOVEMBER'], 12 => ['DES', 'DESEMBER', 'DEC'],
                    ];
                    $keywords = $monthKeywords[$mNum] ?? [];
                    $filtered = array_filter($sheetNames, function ($sName) use ($keywords) {
                        $sUpper = strtoupper($sName);
                        foreach ($keywords as $kw) {
                            if (str_contains($sUpper, $kw)) return true;
                        }
                        return false;
                    });
                    if (!empty($filtered)) {
                        $sheetNames = array_values($filtered);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'spreadsheet_id' => $spreadsheetId,
                'sheet_names' => array_values($sheetNames)
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync a single sheet tab for AJAX sync
     */
    public function syncSingleSheet(Request $request)
    {
        $spreadsheetId = $request->input('spreadsheet_id');
        $sheetName = $request->input('sheet_name');
        $seller = $request->input('seller', 'Aliqa');

        if (empty($spreadsheetId) || empty($sheetName)) {
            return response()->json(['success' => false, 'message' => 'Parameter spreadsheet_id atau sheet_name kurang.'], 400);
        }

        try {
            $syncService = app(\App\Services\GoogleSheetsSyncService::class);
            $metrics = $syncService->syncSingleSheet($spreadsheetId, $sheetName, $seller);
            return response()->json([
                'success' => true,
                'processed' => $metrics['processed'] ?? 0,
                'inserted' => $metrics['inserted'] ?? 0,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger background Reverse Sync (Push DB -> Google Sheets)
     */
    public function pushUpdatesToSheets(Request $request, GoogleSheetsSyncService $syncService)
    {
        try {
            $month = $request->input('month');
            $sheet = $request->input('sheet');
            $seller = $request->input('seller');
            $offset = $request->input('offset') !== null ? (int)$request->input('offset') : null;
            $limit = min(200, max(10, (int)$request->input('limit', 100)));

            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            $monthSheetMapZaherba = [
                1 => 'JANUARI (ZAHERBA)', 2 => 'FEBRUARI (ZAHERBA)', 3 => 'MARET (ZAHERBA)',
                4 => 'APRIL (ZAHERBA)', 5 => 'MEI (ZAHERBA)', 6 => 'JUNI (ZAHERBA)',
                7 => 'JULI (ZAHERBA)', 8 => 'AGUSTUS (ZAHERBA)', 9 => 'SEPTEMBER (ZAHERBA)',
                10 => 'OKTOBER (ZAHERBA)', 11 => 'NOVEMBER (ZAHERBA)', 12 => 'DESEMBER (ZAHERBA)',
            ];

            $monthSheetMapAliqa = [
                1 => 'JANUARI 2026 (FP ALIQA)', 2 => 'FEBRUARI 2026 (FP ALIQA)', 3 => 'MARET 2026 (FP ALIQA)',
                4 => 'APRIL 2026 (FP ALIQA)', 5 => 'MEI 2026 (FP ALIQA)', 6 => 'JUNI 2026 (FP ALIQA).',
                7 => 'JULI 2026 (FP ALIQA)', 8 => 'AGUSTUS 2026 (FP ALIQA)', 9 => 'SEPTEMBER 2026 (FP ALIQA)',
                10 => 'OKTOBER 2026 (FP ALIQA)', 11 => 'NOVEMBER 2026 (FP ALIQA)', 12 => 'DESEMBER 2026 (FP ALIQA)',
            ];

            $query = OutgoingShipment::query();

            $targetMonth = null;
            if (!empty($sheet)) {
                $sUpper = strtoupper($sheet);
                foreach ($monthNames as $mNum => $mName) {
                    if (str_contains($sUpper, strtoupper($mName)) || str_contains($sUpper, strtoupper(substr($mName, 0, 3)))) {
                        $targetMonth = $mNum;
                        break;
                    }
                }
            }

            if ($targetMonth === null && !empty($month) && strtoupper((string)$month) !== 'ALL' && (string)$month !== '0') {
                $targetMonth = (int)$month;
            }

            if ($targetMonth !== null && $targetMonth >= 1 && $targetMonth <= 12) {
                $query->whereMonth('tanggal_kirim', $targetMonth);
            } elseif (empty($sheet) && (empty($month) || (string)$month === '0' || (string)$month === 'current')) {
                $query->whereMonth('tanggal_kirim', (int)date('n'));
            }

            if (!empty($seller) && $seller !== 'ALL' && $seller !== 'Semua Seller') {
                $cleanSeller = trim(preg_replace('/^Mitra\s+/i', '', $seller));
                $query->where(function ($q) use ($seller, $cleanSeller) {
                    $q->where('nama_seller', $seller)
                      ->orWhere('nama_seller', $cleanSeller)
                      ->orWhere('nama_seller', 'Mitra ' . $cleanSeller)
                      ->orWhere('nama_seller', 'LIKE', "%{$cleanSeller}%");
                });
            }

            $query->where(function ($q) {
                $q->whereNotNull('last_tracked_at')
                  ->orWhereIn('status_kategori', ['SUKSES', 'RETUR', 'FOLLOW_UP', 'IN_PROCESS']);
            });

            $totalCount = $query->count();
            if ($totalCount === 0) {
                return response()->json([
                    'success' => true,
                    'total' => 0,
                    'offset' => 0,
                    'processed' => 0,
                    'done' => true,
                    'message' => 'Tidak ada status resi yang perlu di-push ke Google Sheets untuk filter tersebut.',
                    'count' => 0
                ]);
            }

            // If offset is provided (chunked / real-time mode from frontend)
            $isChunkMode = ($offset !== null);
            if ($isChunkMode) {
                $shipments = $query->orderBy('id', 'asc')->skip($offset)->take($limit)->get();
            } else {
                $shipments = $query->orderBy('id', 'asc')->get();
            }

            $botService = app(\App\Services\TrackingBotService::class);
            $payload = [];

            foreach ($shipments as $shipment) {
                $isDelivered = $shipment->status_kategori === 'SUKSES';
                $isRetur = $shipment->status_kategori === 'RETUR';
                $isInProcess = !$isDelivered && !$isRetur;

                $isAliqa = str_contains(strtoupper($shipment->nama_seller ?? ''), 'ALIQA') || str_contains(strtoupper($seller ?? ''), 'ALIQA');
                $mNum = $shipment->tanggal_kirim ? (int)date('n', strtotime($shipment->tanggal_kirim)) : ($targetMonth ?: (int)date('n'));
                $sheetName = !empty($sheet) ? $sheet : ($isAliqa
                    ? ($monthSheetMapAliqa[$mNum] ?? 'AGUSTUS 2026 (FP ALIQA)')
                    : ($monthSheetMapZaherba[$mNum] ?? 'AGUSTUS (ZAHERBA)'));

                $slaStr = $botService->formatRunningSla($shipment->tanggal_kirim, $shipment->status_kategori ?: 'IN_PROCESS', $shipment->sla_days ?: 2);

                if ($isInProcess) {
                    $statusPosText = $shipment->status_pos ?: 'IN PROSES';
                    $keteranganText = $shipment->keterangan ?: 'PROSES PENGIRIMAN POS';
                    $colorCode = $shipment->color_code ?: 'PUTIH';
                    $statusKategori = 'IN_PROCESS';
                } elseif ($isRetur) {
                    $statusPosText = $shipment->status_pos ?: 'DELIVERED (RETURN DELIVERY)';
                    $keteranganText = $shipment->keterangan ?: 'DITERIMA PENGIRIM (MITRA)';
                    $colorCode = $shipment->color_code ?: 'ORANGE';
                    $statusKategori = 'RETUR';
                } else {
                    $statusPosText = $shipment->status_pos ?: 'DELIVERED';
                    $keteranganText = $shipment->keterangan ?: 'DITERIMA YANG BERSANGKUTAN';
                    $colorCode = $shipment->color_code ?: 'BIRU';
                    $statusKategori = 'SUKSES';
                }

                $statusLabel = match($colorCode) {
                    'BIRU' => 'DELIVERED (SUKSES)',
                    'ORANGE' => 'RETUR (RETURN)',
                    'KUNING' => 'FOLLOW UP',
                    'HIJAU' => 'SUDAH DIHUBUNGI',
                    'BIRU_TUA' => 'ESKALASI POS',
                    default => ($isInProcess ? 'IN PROSES' : $statusPosText),
                };

                $payload[] = [
                    'seller' => $shipment->nama_seller ?: ($isAliqa ? 'Mitra Aliqa' : 'Mitra Zaherba'),
                    'resi' => $shipment->no_resi,
                    'sheet' => $sheetName,
                    'sheet_name' => $sheetName,
                    'status_pos' => $statusPosText,
                    'keterangan' => $keteranganText,
                    'status_kategori' => $statusKategori,
                    'color_code' => $colorCode,
                    'status_label' => $statusLabel,
                    'sla' => $slaStr,
                    'sla_days' => $slaStr,
                    'prevent_overwrite_delivered_retur' => true,
                ];
            }

            // In chunk mode: execute immediately & return real-time result
            if ($isChunkMode) {
                $syncRes = $syncService->reverseSyncNiposTracking($payload);
                $updatedInGas = $syncRes['response']['updated_count'] ?? 0;
                $processedCount = count($payload);
                $isDone = ($offset + $processedCount) >= $totalCount;
                $isWebhookOk = ($syncRes['webhook_success'] ?? false);

                if (!$isWebhookOk) {
                    $rawErr = is_array($syncRes['response'] ?? null) 
                        ? ($syncRes['response']['message'] ?? 'Webhook mengembalikan status bukan success.') 
                        : 'Webhook Google Apps Script mengembalikan error (bukan JSON valid). Periksa kode script di Google Sheets Anda.';
                    return response()->json([
                        'success' => false,
                        'message' => "Google Apps Script Error: {$rawErr}",
                        'details' => $syncRes,
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'total' => $totalCount,
                    'offset' => $offset,
                    'processed' => $processedCount,
                    'updated_in_gas' => $updatedInGas,
                    'done' => $isDone,
                    'message' => "Berhasil memproses batch {$processedCount} resi ke Google Sheets."
                ]);
            }

            // Fallback for non-chunked single request
            if ($totalCount <= 150) {
                $syncRes = $syncService->reverseSyncNiposTracking($payload);
                return response()->json([
                    'success' => true,
                    'total' => $totalCount,
                    'processed' => count($payload),
                    'updated_in_gas' => $syncRes['response']['updated_count'] ?? 0,
                    'done' => true,
                    'message' => "Reverse Sync berhasil dieksekusi untuk {$totalCount} resi ke Google Sheets.",
                    'count' => $totalCount
                ]);
            }

            // If large dataset and no offset: dispatch background jobs
            foreach (array_chunk($payload, 300) as $chunk) {
                ReverseSyncGoogleSheetsJob::dispatch($chunk);
            }

            return response()->json([
                'success' => true,
                'total' => $totalCount,
                'done' => true,
                'message' => "Reverse Sync berhasil dipicu untuk {$totalCount} resi di latar belakang.",
                'count' => $totalCount
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Dynamically derive clean Post Office (KC) name from recipient address
     */
    public static function deriveKantorPosFromAddress(?string $alamat): string
    {
        $addr = trim((string)$alamat);
        if (empty($addr)) {
            return '';
        }

        // 1. Match against master PostOffice database
        $matched = \App\Models\PostOffice::matchByDestinationOrAddress(null, $addr);
        if ($matched && !empty($matched->name)) {
            return strtoupper(trim($matched->name));
        }

        // 2. Extract Kota / Kabupaten / Kecamatan from address text
        if (preg_match('/\b(?:Kota|Kab(?:upaten)?\.?)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kab|Kota|Desa|Kel|Rt|Rw|\d{5})|$)/i', $addr, $m)) {
            $cleanCity = trim(preg_replace('/\s+/', ' ', $m[1]));
            $firstWord = explode(' ', $cleanCity)[0];
            if (strlen($firstWord) >= 3 && !in_array(strtoupper($firstWord), ['INDONESIA', 'TUJUAN', 'POS', 'PENGANTARAN'])) {
                return 'KC ' . strtoupper($cleanCity);
            }
        }

        if (preg_match('/\b(?:Kecamatan|Kec\.?)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kab|Kota|Desa|Kel|Rt|Rw|\d{5})|$)/i', $addr, $m)) {
            $cleanKec = trim(preg_replace('/\s+/', ' ', $m[1]));
            $words = explode(' ', $cleanKec);
            $kecName = implode(' ', array_slice($words, 0, 2));
            if (strlen($kecName) >= 3 && !in_array(strtoupper($kecName), ['INDONESIA', 'TUJUAN', 'POS'])) {
                return 'KC KEC. ' . strtoupper($kecName);
            }
        }

        return '';
    }
}