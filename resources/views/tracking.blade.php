<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otomatisasi Tracking Resi Pos Indonesia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: .6; }
        }
        .animate-pulse-slow {
            animation: pulse-slow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Navbar -->
    <header class="bg-gradient-to-r from-orange-600 via-orange-500 to-amber-600 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white text-orange-600 rounded-xl flex items-center justify-center font-black text-xl shadow-md">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Pos Indonesia Tracking Automation</h1>
                    <p class="text-xs text-orange-100 font-medium">Panther Web Bot & Excel Processor (Sheet: AGUSTUS (ZAHERBA))</p>
                </div>
            </div>
            <div class="flex items-center space-x-4 text-sm">
                <a href="{{ route('mock.nipos') }}" target="_blank" class="px-3 py-1.5 bg-orange-700/50 hover:bg-orange-700 rounded-lg text-xs font-semibold backdrop-blur-sm transition flex items-center gap-1.5 border border-orange-400/30">
                    <i class="fa-solid fa-satellite-dish"></i> Buka NIPOS Web Simulator
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Alerts -->
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm flex items-start space-x-3">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-xl mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-red-800">Terjadi Kesalahan</h3>
                    <p class="text-sm text-red-700 mt-1">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if(isset($success))
            <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm flex items-start space-x-3">
                <i class="fa-solid fa-circle-check text-emerald-500 text-xl mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-emerald-800">Proses Tracking Selesai</h3>
                    <p class="text-sm text-emerald-700 mt-1">{{ $success }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

            <!-- Upload & Execution Card -->
            <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center space-x-2 pb-4 mb-4 border-b border-slate-100">
                        <i class="fa-solid fa-file-excel text-green-600 text-lg"></i>
                        <h2 class="font-bold text-slate-800 text-base">Unggah File Excel</h2>
                    </div>

                    <form id="trackingForm" action="{{ route('tracking.process') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <!-- File Input -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Pilih File (.xlsx / .xls)</label>
                            <div id="dropzone" class="border-2 border-dashed border-slate-300 hover:border-orange-500 rounded-xl p-5 text-center bg-slate-50 transition cursor-pointer">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                                <p class="text-xs text-slate-600 font-medium" id="fileNameDisplay">Klik atau seret file Excel ke sini</p>
                                <p class="text-[11px] text-slate-400 mt-1">Target: POS_INPROSES_DUMMY_2026.xlsx</p>
                                <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" class="hidden">
                            </div>
                        </div>

                        <!-- Target URL Input -->
                        <div>
                            <label for="target_url" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Target URL NIPOS@MID
                            </label>
                            <input 
                                type="text" 
                                name="target_url" 
                                id="target_url" 
                                value="{{ $targetUrl ?? route('mock.nipos') }}" 
                                placeholder="http://nipos.posindonesia.co.id/lacak_item_banyakzaref.php" 
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none font-mono"
                            >
                            <span class="text-[10px] text-slate-400 mt-1 block">Default terhubung ke Web Simulator internal bot.</span>
                        </div>

                        <!-- Quick Dummy Option -->
                        @if($dummyExists)
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="use_dummy" value="1" id="use_dummy" class="w-4 h-4 text-orange-600 rounded border-amber-300 focus:ring-orange-500" checked>
                                <span class="text-xs font-semibold text-amber-900">Gunakan File Dummy di Root Proyek</span>
                            </label>
                            <p class="text-[11px] text-amber-700 mt-1 pl-6">
                                <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-900 font-mono">POS_INPROSES_DUMMY_2026.xlsx</code>
                            </p>
                        </div>
                        @endif

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            id="submitBtn" 
                            class="w-full py-3 bg-orange-600 hover:bg-orange-700 active:bg-orange-800 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2"
                        >
                            <i class="fa-solid fa-robot"></i>
                            <span>Jalankan Bot & Update Excel</span>
                        </button>
                    </form>
                </div>

                <!-- Info Box -->
                <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1">
                    <p><i class="fa-solid fa-circle-info text-blue-500 mr-1"></i> Sheet: <strong>AGUSTUS (ZAHERBA)</strong></p>
                    <p><i class="fa-solid fa-filter text-purple-500 mr-1"></i> Kolom L terisi = <strong>Dilewati</strong></p>
                    <p><i class="fa-solid fa-magnifying-glass text-orange-500 mr-1"></i> Kolom L kosong = <strong>Di-tracking Bot</strong></p>
                </div>
            </div>

            <!-- Summary & Information Card -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Excel Structure Summary Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-base font-bold text-slate-800 mb-4 flex items-center justify-between">
                        <span><i class="fa-solid fa-table-list text-orange-600 mr-2"></i> Konfigurasi Kolom Excel</span>
                        @if(isset($downloadUrl))
                            <a href="{{ $downloadUrl }}" class="inline-flex items-center space-x-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow transition animate-bounce">
                                <i class="fa-solid fa-download"></i>
                                <span>Unduh Excel Terupdate</span>
                            </a>
                        @endif
                    </h2>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Kolom E (Index 4)</span>
                            <p class="font-bold text-slate-800 text-sm mt-1">Resi / Barcode</p>
                            <p class="text-[11px] text-slate-500">Input untuk Bot</p>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Kolom K (Index 10)</span>
                            <p class="font-bold text-slate-800 text-sm mt-1">Keterangan</p>
                            <p class="text-[11px] text-slate-500">Diisi Penerima Paket</p>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Kolom L (Index 11)</span>
                            <p class="font-bold text-slate-800 text-sm mt-1">Tracking Pos</p>
                            <p class="text-[11px] text-slate-500">Status (DELIVERED)</p>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Kolom M (Index 12)</span>
                            <p class="font-bold text-slate-800 text-sm mt-1">SLA</p>
                            <p class="text-[11px] text-slate-500">Masa Tahan (Hari)</p>
                        </div>
                    </div>

                    @if(isset($dummyStats) && !isset($processedRows))
                        <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl">
                            <div class="flex items-center justify-between text-xs font-semibold text-orange-950 mb-2">
                                <span>Status Awal File Dummy:</span>
                                <span class="bg-orange-200 text-orange-800 px-2 py-0.5 rounded-full">{{ $dummyStats['sheet'] }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="bg-white p-2 rounded-lg border border-orange-100">
                                    <p class="text-slate-400 text-[10px]">Total Baris Resi</p>
                                    <p class="font-bold text-slate-800 text-base">{{ $dummyStats['totalData'] }}</p>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-orange-100">
                                    <p class="text-slate-400 text-[10px]">Sudah DELIVERED (Abaikan)</p>
                                    <p class="font-bold text-emerald-600 text-base">{{ $dummyStats['alreadyDelivered'] }} Resi</p>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-orange-100">
                                    <p class="text-slate-400 text-[10px]">Target Tracking (Kosong)</p>
                                    <p class="font-bold text-orange-600 text-base">{{ $dummyStats['needTracking'] }} Resi</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Bot Execution Flow Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-base font-bold text-slate-800 mb-3 flex items-center">
                        <i class="fa-solid fa-diagram-project text-blue-600 mr-2"></i>
                        Alur Otomatisasi Bot Scraper
                    </h2>
                    <ol class="relative border-l border-slate-200 ml-3 space-y-4 text-xs text-slate-600">
                        <li class="pl-6 relative">
                            <span class="absolute -left-2.5 top-0.5 w-5 h-5 bg-orange-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">1</span>
                            <h4 class="font-bold text-slate-800">Filter Baris Excel</h4>
                            <p class="text-slate-500">Membaca sheet <code>AGUSTUS (ZAHERBA)</code>, menyaring 8 resi yang Kolom L-nya masih kosong dan mengabaikan baris 1 & 2 yang sudah 'DELIVERED'.</p>
                        </li>
                        <li class="pl-6 relative">
                            <span class="absolute -left-2.5 top-0.5 w-5 h-5 bg-blue-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">2</span>
                            <h4 class="font-bold text-slate-800">Eksekusi Headless Chrome (Symfony Panther)</h4>
                            <p class="text-slate-500">Membuka NIPOS@MID (<code>lacak_item_banyakzaref.php</code>), mengisi input <code>#cari_barcode</code> dengan resi, dan mengambil hasil dari <code>#hasil</code>.</p>
                        </li>
                        <li class="pl-6 relative">
                            <span class="absolute -left-2.5 top-0.5 w-5 h-5 bg-green-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">3</span>
                            <h4 class="font-bold text-slate-800">Update Sel Excel & Siap Diunduh</h4>
                            <p class="text-slate-500">Menuliskan nilai status ke Kolom K (Penerima), Kolom L (DELIVERED), dan Kolom M (SLA) lalu menghasilkan file Excel terupdate.</p>
                        </li>
                    </ol>
                </div>

            </div>
        </div>

        <!-- Tracking Results Table Section -->
        @if(isset($processedRows) || isset($skippedRows))
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-12">
            <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-emerald-600"></i>
                        Hasil Pemrosesan Resi
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">Sheet: <strong>{{ $sheetName ?? 'AGUSTUS (ZAHERBA)' }}</strong> | Total Diproses: <strong>{{ count($processedRows) }} resi</strong> | Dilewati: <strong>{{ count($skippedRows) }} baris</strong></p>
                </div>
                @if(isset($downloadUrl))
                <a href="{{ $downloadUrl }}" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2">
                    <i class="fa-solid fa-file-excel text-emerald-200"></i>
                    <span>Download {{ $downloadFilename }}</span>
                </a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100/75 text-slate-700 font-bold uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Baris</th>
                            <th class="px-4 py-3">Nomor Resi (Kolom E)</th>
                            <th class="px-4 py-3">Keterangan / Penerima (Kolom K)</th>
                            <th class="px-4 py-3">Tracking Pos (Kolom L)</th>
                            <th class="px-4 py-3">SLA (Kolom M)</th>
                            <th class="px-4 py-3 text-center">Status Eksekusi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <!-- Skipped rows (Already DELIVERED) -->
                        @foreach($skippedRows as $item)
                        <tr class="bg-slate-50/50 hover:bg-slate-50 transition opacity-75">
                            <td class="px-4 py-3 font-mono font-bold text-slate-400">Row {{ $item['row'] }}</td>
                            <td class="px-4 py-3 font-mono font-semibold text-slate-700">{{ $item['resi'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $item['keterangan'] ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 bg-slate-200 text-slate-700 rounded-full font-bold text-[10px]">
                                    {{ $item['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-600">{{ $item['sla'] }} Hari</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full font-semibold text-[10px] inline-flex items-center gap-1">
                                    <i class="fa-solid fa-forward-step text-[9px]"></i> Dilewati (Sudah Ada)
                                </span>
                            </td>
                        </tr>
                        @endforeach

                        <!-- Processed rows (Updated by Panther Bot) -->
                        @foreach($processedRows as $item)
                        <tr class="bg-emerald-50/30 hover:bg-emerald-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-orange-600">Row {{ $item['row'] }}</td>
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">{{ $item['resi'] }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $item['keterangan'] }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 bg-emerald-600 text-white rounded-full font-bold text-[10px] shadow-sm">
                                    {{ $item['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-800">{{ $item['sla'] }} Hari</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px] inline-flex items-center gap-1 border border-emerald-300">
                                    <i class="fa-solid fa-check text-[9px]"></i> Berhasil Di-tracking
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </main>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center shadow-2xl space-y-4">
            <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mx-auto text-2xl animate-spin">
                <i class="fa-solid fa-gear"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-lg">Bot Sedang Berjalan...</h3>
                <p class="text-xs text-slate-500 mt-1">Membuka browser headless, menginput resi ke <code>#cari_barcode</code>, dan mengambil data status dari <code>#hasil</code>.</p>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-orange-600 h-2 rounded-full w-2/3 animate-pulse"></div>
            </div>
            <p class="text-[11px] text-slate-400">Harap tunggu beberapa saat hingga seluruh resi selesai diproses.</p>
        </div>
    </div>

    <!-- Drag & Drop / Form Scripts -->
    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('excel_file');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const form = document.getElementById('trackingForm');
        const loadingModal = document.getElementById('loadingModal');
        const useDummy = document.getElementById('use_dummy');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    fileNameDisplay.textContent = e.target.files[0].name;
                    if (useDummy) {
                        useDummy.checked = false;
                    }
                }
            });

            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('border-orange-500', 'bg-orange-50');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('border-orange-500', 'bg-orange-50');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-orange-500', 'bg-orange-50');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    fileNameDisplay.textContent = e.dataTransfer.files[0].name;
                    if (useDummy) {
                        useDummy.checked = false;
                    }
                }
            });
        }

        if (form) {
            form.addEventListener('submit', () => {
                loadingModal.classList.remove('hidden');
            });
        }
    </script>
</body>
</html>
