<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Tracking NIPOS & Follow-Up CS - Pos Indonesia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .row-color-BIRU { background-color: #e0f2fe !important; }
        .row-color-ORANGE { background-color: #ffedd5 !important; }
        .row-color-KUNING { background-color: #fef9c3 !important; }
        .row-color-PUTIH { background-color: #ffffff !important; }
        .row-color-HIJAU { background-color: #dcfce7 !important; }
        .row-color-BIRU_TUA { background-color: #1e3a8a !important; color: #ffffff !important; }
        .row-color-BIRU_TUA td { color: #ffffff !important; }
        .row-color-BIRU_TUA .text-slate-900,
        .row-color-BIRU_TUA .text-slate-800,
        .row-color-BIRU_TUA .text-slate-700,
        .row-color-BIRU_TUA .text-slate-600,
        .row-color-BIRU_TUA .text-slate-500,
        .row-color-BIRU_TUA .text-slate-400 { color: #f8fafc !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen font-sans">

    <!-- Header -->
    <header class="bg-gradient-to-r from-orange-600 via-orange-500 to-amber-600 text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white text-orange-600 rounded-xl flex items-center justify-center font-black text-xl shadow">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-bold tracking-tight">Pos Indonesia Tracking & 12 Bulan Follow-Up</h1>
                    <p class="text-[11px] text-orange-100 font-medium">Monitoring Real-Time Barang Masuk / Keluar Sepanjang Tahun</p>
                </div>
            </div>

            <div class="flex items-center flex-wrap gap-2">
                <!-- Add Shipment Button -->
                <button
                    type="button"
                    onclick="openAddModal()"
                    class="px-3.5 py-1.5 bg-white text-orange-700 hover:bg-orange-50 font-bold text-xs rounded-xl shadow transition flex items-center gap-1.5 border border-white/80"
                >
                    <i class="fa-solid fa-plus-circle text-orange-600"></i>
                    <span>+ Tambah Data Barang (Masuk / Keluar)</span>
                </button>
            </div>
        </div>

        <!-- 12-Month Navigation Tab Bar -->
        <div class="bg-orange-800/90 border-t border-orange-400/30 overflow-x-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-1.5 flex items-center gap-1 text-xs whitespace-nowrap">
                <span class="text-[11px] font-bold text-orange-200 uppercase tracking-wider mr-1.5"><i class="fa-regular fa-calendar text-[10px]"></i> Bulan:</span>

                <a href="{{ route('tracking.index', ['month' => 'ALL', 'year' => $selectedYear]) }}"
                   class="px-3 py-1 rounded-lg font-bold transition flex items-center gap-1 {{ $selectedMonth === 'ALL' ? 'bg-white text-orange-800 shadow' : 'text-orange-100 hover:bg-orange-700/60' }}">
                   Semua (Setahun)
                   <span class="px-1.5 py-0.2 text-[9px] rounded-full {{ $selectedMonth === 'ALL' ? 'bg-orange-100 text-orange-800' : 'bg-orange-900/60 text-orange-200' }}">{{ $annualStats['total'] ?? 0 }}</span>
                </a>

                @foreach($months as $m)
                @php
                    $count = $monthCounts[$m] ?? 0;
                    $isActive = $selectedMonth === $m;
                @endphp
                <a href="{{ route('tracking.index', ['month' => $m, 'year' => $selectedYear]) }}"
                   class="px-2.5 py-1 rounded-lg font-semibold transition flex items-center gap-1 {{ $isActive ? 'bg-white text-orange-800 font-bold shadow' : 'text-orange-100 hover:bg-orange-700/60' }}">
                   {{ ucfirst(strtolower($m)) }}
                   @if($count > 0)
                   <span class="px-1.5 py-0.2 text-[9px] rounded-full {{ $isActive ? 'bg-orange-100 text-orange-800 font-bold' : 'bg-orange-900/60 text-orange-200' }}">{{ $count }}</span>
                   @endif
                </a>
                @endforeach
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Alerts -->
        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm flex items-start space-x-3">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-lg mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-red-800">Terjadi Kesalahan</h3>
                    <p class="text-xs text-red-700 mt-0.5">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if(session('success') || isset($success))
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm flex items-start space-x-3">
                <i class="fa-solid fa-circle-check text-emerald-500 text-lg mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-emerald-800">Operasi Berhasil</h3>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('success') ?? ($success ?? '') }}</p>
                </div>
            </div>
        @endif

        <!-- Summary & Upload Section -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Control & Upload Panel (4 cols) -->
            <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-file-excel text-emerald-600 text-lg"></i>
                            <h2 class="font-bold text-slate-800 text-sm">Upload & Jalankan Bot</h2>
                        </div>
                        <span class="text-[10px] bg-orange-100 text-orange-800 font-bold px-2 py-0.5 rounded-full">Bulan: {{ $selectedMonth }}</span>
                    </div>

                    <form id="trackingForm" action="{{ route('tracking.process') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                        <input type="hidden" name="year" value="{{ $selectedYear }}">

                        <!-- File Input (Excel / CSV / ODS / Spreadsheet) -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Pilih File Excel / Spreadsheet</label>
                            <div id="dropzone" class="border-2 border-dashed border-slate-300 hover:border-orange-500 rounded-xl p-3 text-center bg-slate-50 transition cursor-pointer">
                                <i class="fa-solid fa-cloud-arrow-up text-xl text-slate-400 mb-0.5"></i>
                                <p class="text-xs text-slate-700 font-semibold" id="fileNameDisplay">Upload File Excel / Spreadsheet</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Format: .xlsx, .xls, .csv, .ods</p>
                                <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv,.ods,.tsv,.txt" class="hidden">
                            </div>
                        </div>

                        <!-- Google Spreadsheet Link Input -->
                        <div>
                            <label for="spreadsheet_url" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">
                                <i class="fa-solid fa-link text-slate-400 mr-0.5"></i> Atau Link Google Spreadsheet:
                            </label>
                            <input
                                type="url"
                                name="spreadsheet_url"
                                id="spreadsheet_url"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                class="w-full px-3 py-1.5 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none font-mono"
                            >
                        </div>

                        <!-- Filter Mode Option -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Filter Resi yang Di-tracking</label>
                            <div class="space-y-1.5">
                                <label class="flex items-center space-x-2 p-2 bg-slate-50 hover:bg-orange-50/50 border border-slate-200 rounded-xl cursor-pointer transition text-xs">
                                    <input type="radio" name="filter_mode" value="only_empty" class="text-orange-600 focus:ring-orange-500" checked>
                                    <span class="font-semibold text-slate-800">Hanya Kolom Kosong / Pending</span>
                                </label>
                                <label class="flex items-center space-x-2 p-2 bg-slate-50 hover:bg-orange-50/50 border border-slate-200 rounded-xl cursor-pointer transition text-xs">
                                    <input type="radio" name="filter_mode" value="undelivered" class="text-orange-600 focus:ring-orange-500">
                                    <span class="font-semibold text-slate-800">Update Semua yang Belum DELIVERED</span>
                                </label>
                            </div>
                        </div>


                        <!-- Submit Button -->
                        <button
                            type="submit"
                            id="submitBtn"
                            class="w-full py-2.5 bg-orange-600 hover:bg-orange-700 active:bg-orange-800 text-white font-bold text-xs rounded-xl shadow transition flex items-center justify-center space-x-2"
                        >
                            <i class="fa-solid fa-robot"></i>
                            <span>Jalankan Bot Tracking {{ $selectedMonth !== 'ALL' ? 'Bulan ' . $selectedMonth : 'Semua Bulan' }}</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Summary & Color Legend Tables (8 cols) -->
            <div class="lg:col-span-8 grid grid-cols-1 sm:grid-cols-2 gap-4">

                <!-- Left Card: Pos Status Table (Total, Retur, Delivered, Inproses) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider pb-2 mb-3 border-b flex items-center justify-between">
                        <span><i class="fa-solid fa-chart-pie text-orange-600 mr-1.5"></i> Rekapitulasi: <strong>{{ $selectedMonth }} {{ $selectedYear }}</strong></span>
                        <span class="text-[10px] text-slate-400 font-mono">Persentase</span>
                    </h3>

                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <table class="w-full text-xs text-left">
                            <tbody class="divide-y divide-slate-200">
                                <tr class="bg-slate-50 font-bold hover:bg-slate-100 cursor-pointer transition" onclick="filterTableByColor('all')" title="Klik untuk menampilkan semua data">
                                    <td class="px-3 py-2.5 font-black text-slate-900 flex items-center gap-1.5"><i class="fa-solid fa-list text-slate-400"></i> TOTAL</td>
                                    <td class="px-3 py-2.5 font-mono text-right font-black text-slate-900" id="cnt_total">{{ number_format($stats['total'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2.5 font-mono text-right font-black text-slate-900">100%</td>
                                </tr>
                                <tr class="hover:bg-orange-50/80 cursor-pointer transition" onclick="filterTableByColor('ORANGE')" title="Klik untuk menampilkan Paket Retur">
                                    <td class="px-3 py-2 font-bold text-orange-700 flex items-center gap-1.5"><i class="fa-solid fa-rotate-left text-orange-500"></i> RETUR</td>
                                    <td class="px-3 py-2 font-mono text-right font-bold text-orange-700" id="cnt_retur">{{ number_format($stats['retur'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right text-slate-600" id="pct_retur">{{ $stats['pct_retur'] }}%</td>
                                </tr>
                                <tr class="hover:bg-sky-50/80 cursor-pointer transition" onclick="filterTableByColor('BIRU')" title="Klik untuk menampilkan Paket Delivered">
                                    <td class="px-3 py-2 font-bold text-sky-700 flex items-center gap-1.5"><i class="fa-solid fa-check text-sky-500"></i> DELIVERED</td>
                                    <td class="px-3 py-2 font-mono text-right font-bold text-sky-700" id="cnt_delivered">{{ number_format($stats['delivered'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right text-slate-600" id="pct_delivered">{{ $stats['pct_delivered'] }}%</td>
                                </tr>
                                <tr class="hover:bg-amber-50/80 cursor-pointer transition" onclick="filterTableByColor('PUTIH')" title="Klik untuk menampilkan Paket Inproses / Belum FU">
                                    <td class="px-3 py-2 font-bold text-amber-700 flex items-center gap-1.5"><i class="fa-solid fa-hourglass-half text-amber-500"></i> INPROSES</td>
                                    <td class="px-3 py-2 font-mono text-right font-bold text-amber-700" id="cnt_inproses">{{ number_format($stats['inproses'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 font-mono text-right text-slate-600" id="pct_inproses">{{ $stats['pct_inproses'] }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[10px] text-slate-500 flex items-center justify-between">
                        <span><i class="fa-solid fa-calendar-check text-blue-500 mr-1"></i> Data {{ $selectedMonth }} ({{ $stats['total'] }} Resi)</span>
                        <span class="font-bold text-slate-700">Setahun: {{ $annualStats['total'] }} Resi</span>
                    </div>
                </div>

                <!-- Right Card: NOTED / Color Follow-Up Legend -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider pb-2 mb-3 border-b flex items-center justify-between">
                        <span><i class="fa-solid fa-palette text-orange-600 mr-1.5"></i> Standar Warna & Status FU</span>
                        <span class="text-[10px] bg-slate-100 font-bold px-2 py-0.5 rounded text-slate-600">KLIK FILTER</span>
                    </h3>

                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <table class="w-full text-xs text-left">
                            <tbody class="divide-y divide-slate-200">
                                <tr class="hover:bg-sky-50/50 cursor-pointer transition" onclick="filterTableByColor('BIRU')" title="Filter Paket Sukses (Biru)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-[#BAE6FD] text-sky-950 border-r border-slate-200">BIRU</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">PAKET SUKSES</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-sky-700" id="badge_cnt_biru">{{ $stats['color_counts']['BIRU'] }}</td>
                                </tr>
                                <tr class="hover:bg-orange-50/50 cursor-pointer transition" onclick="filterTableByColor('ORANGE')" title="Filter Paket Retur (Orange)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-[#FED7AA] text-orange-950 border-r border-slate-200">ORANGE</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">PAKET RETUR</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-orange-700" id="badge_cnt_orange">{{ $stats['color_counts']['ORANGE'] }}</td>
                                </tr>
                                <tr class="hover:bg-yellow-50/50 cursor-pointer transition" onclick="filterTableByColor('KUNING')" title="Filter Sudah di FU (Kuning)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-[#FEF08A] text-yellow-950 border-r border-slate-200">KUNING</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">SUDAH DI FU</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-yellow-700" id="badge_cnt_kuning">{{ $stats['color_counts']['KUNING'] }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50 cursor-pointer transition" onclick="filterTableByColor('PUTIH')" title="Filter Blm di FU (Putih)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-white text-slate-800 border-r border-slate-200">PUTIH</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">BLM DI FU</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-slate-700" id="badge_cnt_putih">{{ $stats['color_counts']['PUTIH'] }}</td>
                                </tr>
                                <tr class="hover:bg-emerald-50/50 cursor-pointer transition" onclick="filterTableByColor('HIJAU')" title="Filter FU 2 Kali (Hijau)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-[#A7F3D0] text-emerald-950 border-r border-slate-200">HIJAU</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">FU 2 KALI</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-emerald-700" id="badge_cnt_hijau">{{ $stats['color_counts']['HIJAU'] }}</td>
                                </tr>
                                <tr class="hover:bg-blue-950/20 cursor-pointer transition" onclick="filterTableByColor('BIRU_TUA')" title="Filter FU POS (Biru Tua)">
                                    <td class="px-3 py-1.5 font-bold w-24 bg-[#1E40AF] text-white border-r border-slate-200">BIRU TUA</td>
                                    <td class="px-3 py-1.5 font-semibold text-slate-800">FU POS</td>
                                    <td class="px-3 py-1.5 font-mono text-right font-bold text-blue-900" id="badge_cnt_biru_tua">{{ $stats['color_counts']['BIRU_TUA'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-[10px] text-slate-500 italic">
                        *Klik baris warna di atas atau tombol pada tabel untuk menyaring tampilan.
                    </div>
                </div>

            </div>
        </div>

        <!-- Tracking Results Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            <!-- Table Action & Download Header -->
            <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                <div>
                    <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                        <i class="fa-solid fa-table text-orange-600"></i>
                        Tabel Monitoring Resi & Follow-Up: <strong>{{ $selectedMonth }} {{ $selectedYear }}</strong>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Menampilkan <strong>{{ count($shipments) }} data barang</strong></p>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                    <button onclick="downloadColoredExcel('{{ $selectedMonth }}')" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-1.5">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Download Excel {{ $selectedMonth !== 'ALL' ? 'Bulan ' . $selectedMonth : 'Semua Bulan' }}</span>
                    </button>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="px-4 py-2.5 bg-white border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-500 mr-1">Filter Tampilan:</span>
                    <button onclick="filterTableByColor('all')" class="color-filter-tab active px-3 py-1 bg-slate-800 text-white rounded-lg font-semibold transition" data-color="all">Semua ({{ count($shipments) }})</button>
                    <button onclick="filterTableByType('keluar')" class="type-filter-tab px-3 py-1 bg-blue-50 text-blue-800 border border-blue-200 rounded-lg font-semibold transition" data-type="keluar">📤 Barang Keluar</button>
                    <button onclick="filterTableByColor('ORANGE')" class="color-filter-tab px-3 py-1 bg-orange-100 text-orange-900 border border-orange-300 rounded-lg font-semibold transition flex items-center gap-1" data-color="ORANGE">
                        <i class="fa-solid fa-rotate-left text-[10px]"></i> Paket Retur ({{ $stats['retur'] }})
                    </button>
                    <button onclick="filterTableByColor('BIRU')" class="color-filter-tab px-3 py-1 bg-sky-100 text-sky-900 border border-sky-300 rounded-lg font-semibold transition" data-color="BIRU">Paket Sukses</button>
                    <button onclick="filterTableByColor('KUNING')" class="color-filter-tab px-3 py-1 bg-yellow-100 text-yellow-900 border border-yellow-300 rounded-lg font-semibold transition" data-color="KUNING">Sudah di FU</button>
                    <button onclick="filterTableByColor('PUTIH')" class="color-filter-tab px-3 py-1 bg-slate-100 text-slate-800 border border-slate-300 rounded-lg font-semibold transition" data-color="PUTIH">Blm di FU</button>
                    <button onclick="filterTableByColor('HIJAU')" class="color-filter-tab px-3 py-1 bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-lg font-semibold transition" data-color="HIJAU">FU 2 Kali</button>
                    <button onclick="filterTableByColor('BIRU_TUA')" class="color-filter-tab px-3 py-1 bg-blue-900 text-white border border-blue-950 rounded-lg font-semibold transition" data-color="BIRU_TUA">FU POS</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600" id="trackingTable">
                    <thead class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-3">No</th>
                            <th class="px-3 py-3">Tipe</th>
                            <th class="px-3 py-3">Resi (Kolom E)</th>
                            <th class="px-3 py-3">Konsumen / HP</th>
                            <th class="px-3 py-3">CS</th>
                            <th class="px-4 py-3">Keterangan / Penerima (Kolom K)</th>
                            <th class="px-3 py-3">Tracking Pos (Kolom L)</th>
                            <th class="px-3 py-3">SLA</th>
                            <th class="px-3 py-3 text-center">Status Follow-Up</th>
                            <th class="px-3 py-3 text-center w-60">Aksi & Tombol Warna FU</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200" id="trackingTableBody">
                        @forelse($shipments as $idx => $item)
                        @php
                            $rowColor = $item->color_code ?? 'PUTIH';
                            $status = strtoupper($item->status ?? '');
                            $isDelivered = $item->isDelivered();
                            $isFailed = str_contains($status, 'FAILED');
                            $isRunsheet = str_contains($status, 'RUNSHEET');
                        @endphp
                        <tr class="table-row-item transition row-color-{{ $rowColor }}"
                            id="row_{{ $item->id }}"
                            data-id="{{ $item->id }}"
                            data-color="{{ $rowColor }}"
                            data-type="{{ $item->type }}"
                            data-status="{{ $item->status }}"
                            data-followup="{{ $item->needs_follow_up ? '1' : '0' }}"
                        >
                            <td class="px-3 py-3 font-mono font-bold text-slate-400">#{{ $idx + 1 }}</td>
                            <td class="px-3 py-3">
                                @if($item->type === 'masuk')
                                    <span class="px-2 py-0.5 bg-orange-100 text-orange-800 border border-orange-300 font-bold rounded-lg text-[10px] inline-flex items-center gap-1">
                                        <i class="fa-solid fa-arrow-down"></i> Masuk (Retur)
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 border border-blue-300 font-bold rounded-lg text-[10px] inline-flex items-center gap-1">
                                        <i class="fa-solid fa-arrow-up"></i> Keluar
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono font-bold text-slate-900">{{ $item->resi }}</td>
                            <td class="px-3 py-3">
                                <p class="font-bold text-slate-800">{{ $item->nama_konsumen ?: '-' }}</p>
                                @if($item->no_hp)
                                <p class="text-[11px] text-slate-500 font-mono"><i class="fa-solid fa-phone text-[9px] text-slate-400"></i> {{ $item->no_hp }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-semibold text-slate-700">{{ $item->nama_cs ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if($item->needs_follow_up)
                                    <span class="font-bold text-rose-700 flex items-center gap-1">
                                        <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                                        {{ $item->keterangan }}
                                    </span>
                                @elseif($isDelivered)
                                    <span class="font-medium text-emerald-800">{{ $item->keterangan }}</span>
                                @else
                                    <span class="font-medium text-slate-700">{{ $item->keterangan ?: '-' }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if($isDelivered)
                                    <span class="px-2.5 py-1 bg-emerald-600 text-white rounded-full font-bold text-[10px] shadow-sm">
                                        {{ $item->status }}
                                    </span>
                                @elseif($isFailed)
                                    <span class="px-2.5 py-1 bg-rose-600 text-white rounded-full font-bold text-[10px] shadow-sm animate-pulse">
                                        {{ $item->status }}
                                    </span>
                                @elseif($isRunsheet)
                                    <span class="px-2.5 py-1 bg-amber-500 text-white rounded-full font-bold text-[10px] shadow-sm">
                                        {{ $item->status }}
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 bg-blue-600 text-white rounded-full font-bold text-[10px] shadow-sm">
                                        {{ $item->status ?: 'PENDING' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-bold text-slate-700">{{ $item->sla ?: '-' }}</td>
                            <td class="px-3 py-3 text-center" id="status_fu_col_{{ $item->id }}">
                                @if($rowColor === 'KUNING')
                                    <span class="px-2 py-0.5 bg-yellow-200 text-yellow-900 border border-yellow-400 font-bold rounded-lg text-[10px]">SUDAH DI FU</span>
                                @elseif($rowColor === 'HIJAU')
                                    <span class="px-2 py-0.5 bg-emerald-200 text-emerald-950 border border-emerald-400 font-bold rounded-lg text-[10px]">FU 2 KALI</span>
                                @elseif($rowColor === 'BIRU_TUA')
                                    <span class="px-2 py-0.5 bg-blue-900 text-white font-bold rounded-lg text-[10px]">FU POS</span>
                                @elseif($rowColor === 'BIRU')
                                    <span class="px-2 py-0.5 bg-sky-200 text-sky-950 border border-sky-300 font-bold rounded-lg text-[10px]">PAKET SUKSES</span>
                                @elseif($rowColor === 'ORANGE')
                                    <span class="px-2 py-0.5 bg-orange-200 text-orange-950 border border-orange-300 font-bold rounded-lg text-[10px]">PAKET RETUR</span>
                                @elseif($item->needs_follow_up)
                                    <span class="px-2 py-0.5 bg-rose-100 text-rose-700 border border-rose-300 font-bold rounded-lg text-[10px] inline-flex items-center gap-1 shadow-sm">
                                        <i class="fa-solid fa-triangle-exclamation text-rose-500"></i> Perlu Follow Up
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-lg text-[10px]">BLM DI FU</span>
                                @endif
                            </td>
                            <!-- Actions & Color Buttons -->
                            <td class="px-3 py-2 text-center">
                                <div class="inline-flex items-center gap-1 bg-white/80 p-1 rounded-xl shadow-xs border border-slate-200">
                                    <!-- Kuning (Sudah di FU) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'KUNING')" title="Sudah di FU (Kuning)" class="w-6 h-6 bg-[#FEF08A] hover:scale-110 active:scale-95 border border-yellow-400 rounded-lg transition text-[9px] font-bold text-yellow-950 flex items-center justify-center shadow-xs">
                                        FU
                                    </button>
                                    <!-- Hijau (FU 2 Kali) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'HIJAU')" title="FU 2 Kali (Hijau)" class="w-6 h-6 bg-[#A7F3D0] hover:scale-110 active:scale-95 border border-emerald-400 rounded-lg transition text-[9px] font-bold text-emerald-950 flex items-center justify-center shadow-xs">
                                        2x
                                    </button>
                                    <!-- Biru Tua (FU POS) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'BIRU_TUA')" title="FU POS (Biru Tua)" class="w-6 h-6 bg-[#1E40AF] hover:scale-110 active:scale-95 border border-blue-950 rounded-lg transition text-[8px] font-bold text-white flex items-center justify-center shadow-xs">
                                        POS
                                    </button>
                                    <!-- Biru Muda (Paket Sukses) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'BIRU')" title="Paket Sukses (Biru)" class="w-6 h-6 bg-[#BAE6FD] hover:scale-110 active:scale-95 border border-sky-400 rounded-lg transition text-[9px] font-bold text-sky-950 flex items-center justify-center shadow-xs">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <!-- Orange (Paket Retur) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'ORANGE')" title="Paket Retur (Orange)" class="w-6 h-6 bg-[#FED7AA] hover:scale-110 active:scale-95 border border-orange-400 rounded-lg transition text-[9px] font-bold text-orange-950 flex items-center justify-center shadow-xs">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <!-- Putih (Reset / Blm di FU) -->
                                    <button type="button" onclick="setRowColor({{ $item->id }}, 'PUTIH')" title="Blm di FU (Putih)" class="w-6 h-6 bg-white hover:scale-110 active:scale-95 border border-slate-300 rounded-lg transition text-[9px] text-slate-500 flex items-center justify-center shadow-xs">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <!-- Single Track Action -->
                                    <button type="button" onclick="trackSingleResi({{ $item->id }})" title="Lacak Ulang Resi Ini" class="w-6 h-6 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition text-[10px] flex items-center justify-center shadow-xs ml-0.5">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                <i class="fa-solid fa-box-open text-3xl text-slate-300 mb-2"></i>
                                <p class="text-sm font-semibold">Belum ada data barang untuk bulan {{ $selectedMonth }}.</p>
                                <p class="text-xs text-slate-400 mt-1">Klik <strong>"+ Tambah Data Barang"</strong> di atas atau unggah file Excel.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Tambah Data Barang (Masuk / Keluar) -->
    <div id="addShipmentModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center font-bold">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Tambah Data Barang</h3>
                        <p class="text-xs text-slate-400">Pencatatan Barang Masuk (Retur) / Keluar (Kirim)</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form id="addShipmentForm" action="{{ route('shipments.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <input type="hidden" name="year" value="{{ $selectedYear }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Month Selector -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Bulan Pengiriman</label>
                        <select name="month" id="modal_month" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-semibold">
                            @foreach($months as $m)
                            <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>{{ $m }} ({{ $selectedYear }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Type Selector -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Tipe Transaksi</label>
                        <select name="type" id="modal_type" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-bold text-orange-700">
                            <option value="keluar">📤 Barang Keluar (Pengiriman Baru)</option>
                            <option value="masuk">📥 Barang Masuk (Paket Retur / Transit DC)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Resi Input -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nomor Resi Pos *</label>
                        <input type="text" name="resi" id="modal_resi" required placeholder="Contoh: BAC010726366A0B05F90" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-mono font-bold uppercase">
                    </div>

                    <!-- Tanggal -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Nama Konsumen -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nama Konsumen</label>
                        <input type="text" name="nama_konsumen" placeholder="Nama pembeli / penerima" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-semibold">
                    </div>

                    <!-- No HP -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nomor HP / WhatsApp</label>
                        <input type="text" name="no_hp" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Invoice -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">No. Invoice</label>
                        <input type="text" name="invoice" placeholder="SO_xxxxxxxxx" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-mono">
                    </div>

                    <!-- Nama CS -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nama CS / CRM</label>
                        <input type="text" name="nama_cs" value="CRM DILA" placeholder="CRM DILA" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-semibold">
                    </div>

                    <!-- Jumlah COD -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Jumlah COD</label>
                        <input type="text" name="jumlah_cod" placeholder="Rp 276.000 = COD" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-mono">
                    </div>
                </div>

                <!-- Produk -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Nama Produk</label>
                    <input type="text" name="produk" value="LAMBUNG CERIA ZAHERBA" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500 font-semibold">
                </div>

                <!-- Alamat -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Alamat Tujuan Pengiriman</label>
                    <textarea name="alamat" rows="2" placeholder="Dusun / Jalan / RT RW / Desa / Kecamatan / Kota" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-orange-500"></textarea>
                </div>

                <!-- Auto Track Option -->
                <div class="p-2.5 bg-orange-50 rounded-xl border border-orange-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="auto_track" value="1" class="w-4 h-4 text-orange-600 rounded border-orange-300 focus:ring-orange-500" checked>
                        <span class="text-xs font-bold text-orange-950">Langsung Lacak Status NIPOS Sekarang Secara Otomatis</span>
                    </label>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Data Barang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center shadow-2xl space-y-4">
            <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mx-auto text-2xl animate-spin">
                <i class="fa-solid fa-gear"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-base" id="loadingTitle">Bot NIPOS Sedang Berjalan...</h3>
                <p class="text-xs text-slate-500 mt-1" id="loadingSubtitle">Mengambil status resmi dan mewarnai baris Excel otomatis.</p>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-orange-600 h-2 rounded-full w-3/4 animate-pulse"></div>
            </div>
        </div>
    </div>

    <!-- Client-side Scripts -->
    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('excel_file');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const form = document.getElementById('trackingForm');
        const loadingModal = document.getElementById('loadingModal');
        const useDummy = document.getElementById('use_dummy');
        const addShipmentModal = document.getElementById('addShipmentModal');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    fileNameDisplay.textContent = e.target.files[0].name;
                    if (useDummy) useDummy.checked = false;
                }
            });
        }

        if (form) {
            form.addEventListener('submit', () => {
                loadingModal.classList.remove('hidden');
            });
        }

        function openAddModal() {
            addShipmentModal.classList.remove('hidden');
            document.getElementById('modal_resi').focus();
        }

        function closeAddModal() {
            addShipmentModal.classList.add('hidden');
        }

        // Set row color on click and save to DB
        function setRowColor(shipmentId, colorCode) {
            const row = document.getElementById('row_' + shipmentId);
            const statusCell = document.getElementById('status_fu_col_' + shipmentId);
            if (!row) return;

            // Remove existing color classes
            ['row-color-BIRU', 'row-color-ORANGE', 'row-color-KUNING', 'row-color-PUTIH', 'row-color-HIJAU', 'row-color-BIRU_TUA'].forEach(cls => {
                row.classList.remove(cls);
            });

            // Add new color class
            row.classList.add('row-color-' + colorCode);
            row.dataset.color = colorCode;

            // Update status text badge
            if (statusCell) {
                const badgeMap = {
                    'KUNING': '<span class="px-2 py-0.5 bg-yellow-200 text-yellow-900 border border-yellow-400 font-bold rounded-lg text-[10px]">SUDAH DI FU</span>',
                    'HIJAU': '<span class="px-2 py-0.5 bg-emerald-200 text-emerald-950 border border-emerald-400 font-bold rounded-lg text-[10px]">FU 2 KALI</span>',
                    'BIRU_TUA': '<span class="px-2 py-0.5 bg-blue-900 text-white font-bold rounded-lg text-[10px]">FU POS</span>',
                    'BIRU': '<span class="px-2 py-0.5 bg-sky-200 text-sky-950 border border-sky-300 font-bold rounded-lg text-[10px]">PAKET SUKSES</span>',
                    'ORANGE': '<span class="px-2 py-0.5 bg-orange-200 text-orange-950 border border-orange-300 font-bold rounded-lg text-[10px]">PAKET RETUR</span>',
                    'PUTIH': '<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-lg text-[10px]">BLM DI FU</span>'
                };
                statusCell.innerHTML = badgeMap[colorCode] || '<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-lg text-[10px]">BLM DI FU</span>';
            }

            // Send async update to database
            fetch('/shipments/' + shipmentId + '/color', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ color_code: colorCode })
            }).catch(e => console.error(e));

            recalculateColorCounts();
        }

        // Single Track Resi Action
        function trackSingleResi(shipmentId) {
            document.getElementById('loadingTitle').textContent = 'Melacak Resi...';
            document.getElementById('loadingSubtitle').textContent = 'Menghubungkan ke NIPOS...';
            loadingModal.classList.remove('hidden');

            fetch('/shipments/' + shipmentId + '/track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                loadingModal.classList.add('hidden');
                if (data.success) {
                    location.reload();
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(err => {
                loadingModal.classList.add('hidden');
                alert('Error: ' + err);
            });
        }

        // Recalculate color counts dynamically
        function recalculateColorCounts() {
            const counts = { 'BIRU': 0, 'ORANGE': 0, 'KUNING': 0, 'PUTIH': 0, 'HIJAU': 0, 'BIRU_TUA': 0 };
            document.querySelectorAll('.table-row-item').forEach(row => {
                const c = row.dataset.color || 'PUTIH';
                if (counts[c] !== undefined) counts[c]++;
            });

            if (document.getElementById('badge_cnt_biru')) document.getElementById('badge_cnt_biru').textContent = counts['BIRU'];
            if (document.getElementById('badge_cnt_orange')) document.getElementById('badge_cnt_orange').textContent = counts['ORANGE'];
            if (document.getElementById('badge_cnt_kuning')) document.getElementById('badge_cnt_kuning').textContent = counts['KUNING'];
            if (document.getElementById('badge_cnt_putih')) document.getElementById('badge_cnt_putih').textContent = counts['PUTIH'];
            if (document.getElementById('badge_cnt_hijau')) document.getElementById('badge_cnt_hijau').textContent = counts['HIJAU'];
            if (document.getElementById('badge_cnt_biru_tua')) document.getElementById('badge_cnt_biru_tua').textContent = counts['BIRU_TUA'];
        }

        // Filter Table by Color
        function filterTableByColor(colorCode) {
            const rows = document.querySelectorAll('.table-row-item');
            const tabs = document.querySelectorAll('.color-filter-tab, .type-filter-tab');

            tabs.forEach(tab => {
                if (tab.dataset.color === colorCode) {
                    tab.classList.add('bg-slate-800', 'text-white');
                } else {
                    tab.classList.remove('bg-slate-800', 'text-white');
                }
            });

            rows.forEach(row => {
                const color = row.dataset.color || 'PUTIH';
                const type = row.dataset.type || 'keluar';
                const status = (row.dataset.status || '').toUpperCase();

                let matches = false;
                if (colorCode === 'all') {
                    matches = true;
                } else if (colorCode === 'ORANGE') {
                    matches = (color === 'ORANGE' || type === 'masuk' || status.includes('RETURN') || status.includes('RETUR'));
                } else if (colorCode === 'BIRU') {
                    matches = (color === 'BIRU' || (status.includes('DELIVERED') && !status.includes('RETURN')));
                } else {
                    matches = (color === colorCode);
                }

                row.style.display = matches ? '' : 'none';
            });
        }

        // Filter Table by Type (Keluar / Masuk)
        function filterTableByType(type) {
            const rows = document.querySelectorAll('.table-row-item');
            const tabs = document.querySelectorAll('.color-filter-tab, .type-filter-tab');

            tabs.forEach(tab => {
                if (tab.dataset.type === type) {
                    tab.classList.add('bg-slate-800', 'text-white');
                } else {
                    tab.classList.remove('bg-slate-800', 'text-white');
                }
            });

            rows.forEach(row => {
                const rowType = row.dataset.type || 'keluar';
                row.style.display = (rowType === type) ? '' : 'none';
            });
        }

        // Download Excel
        function downloadColoredExcel(month) {
            fetch("{{ route('tracking.export_colored') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    month: month,
                    year: {{ $selectedYear }}
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.download_url) {
                    window.location.href = data.download_url;
                } else {
                    alert('Gagal menghasilkan file: ' + (data.message || 'Error'));
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan: ' + err);
            });
        }
    </script>
</body>
</html>
