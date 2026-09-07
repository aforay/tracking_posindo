<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pengaturan Cookie Session NIPOS - Posindo Tracking</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">

    <!-- Navbar -->
    <nav class="bg-[#1E40AF] text-white shadow-md">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="bg-orange-500 p-2 rounded-lg text-white">
                    <i class="fa-solid fa-cookie-bite text-xl"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold leading-tight">Pengaturan Cookie NIPOS</h1>
                    <p class="text-xs text-blue-200">Sistem Pelacakan & Follow-Up CS Pos Indonesia</p>
                </div>
            </div>
            <a href="{{ route('dashboard.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold bg-white/10 hover:bg-white/20 text-white rounded-lg transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Flash Alert -->
        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg mt-0.5"></i>
                <div class="text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg mt-0.5"></i>
                <div class="text-sm">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Left Column: Status Card -->
            <div class="md:col-span-1 space-y-4">
                
                <!-- Connection Status Box -->
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Status Koneksi NIPOS</h2>
                    
                    <div id="statusContainer" class="p-4 rounded-xl {{ ($status['connected'] ?? false) ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-amber-50 border border-amber-200 text-amber-900' }} transition-all">
                        <div class="flex items-center gap-3">
                            <span id="statusDot" class="relative flex h-3.5 w-3.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ ($status['connected'] ?? false) ? 'bg-emerald-400' : 'bg-amber-400' }} opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 {{ ($status['connected'] ?? false) ? 'bg-emerald-600' : 'bg-amber-500' }}"></span>
                            </span>
                            <span id="statusTitle" class="font-bold text-sm">
                                {{ ($status['connected'] ?? false) ? 'TERHUBUNG (ONLINE)' : 'STATUS: PERLU DIUJI' }}
                            </span>
                        </div>
                        <p id="statusMessage" class="text-xs mt-2 opacity-85 leading-relaxed">
                            {{ $status['message'] ?? 'Klik tombol "Test Koneksi" di bawah untuk memvalidasi cookie.' }}
                        </p>
                        @if(isset($status['latency_ms']) && $status['latency_ms'])
                            <div id="statusLatency" class="mt-2 pt-2 border-t border-emerald-200/60 text-[11px] font-semibold text-emerald-700 flex items-center justify-between">
                                <span>Latensi Respon:</span>
                                <span>{{ $status['latency_ms'] }} ms</span>
                            </div>
                        @else
                            <div id="statusLatency" class="mt-2 pt-2 border-t border-slate-200 text-[11px] font-semibold text-slate-500 flex items-center justify-between hidden">
                                <span>Latensi Respon:</span>
                                <span id="latencyValue">-</span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-500">
                        <div class="flex justify-between">
                            <span>Penyimpanan:</span>
                            <span class="font-bold text-slate-700">Database (MySQL)</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Panjang Cookie:</span>
                            <span id="cookieLengthInfo" class="font-bold text-slate-700">{{ strlen($cookie) }} karakter</span>
                        </div>
                    </div>

                    <button type="button" id="btnTestLive" onclick="testConnectionLive()" class="w-full mt-5 py-2.5 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <i id="testIcon" class="fa-solid fa-satellite-dish"></i>
                        <span id="testBtnText">Uji Koneksi Realtime</span>
                    </button>
                </div>

                <!-- Guidance Box -->
                <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-5 text-xs text-blue-950 space-y-2">
                    <div class="font-bold flex items-center gap-1.5 text-blue-800">
                        <i class="fa-solid fa-circle-info text-sm"></i> Kapan Perlu Update Cookie?
                    </div>
                    <p class="leading-relaxed text-blue-900/90">
                        Sesi login portal NIPOS Pos Indonesia umumnya memiliki masa aktif berkala. Jika bot pelacakan menghasilkan status kosong atau peringatan sesi kedaluwarsa, salin cookie baru dan tempel di form ini.
                    </p>
                </div>

            </div>

            <!-- Right Column: Edit Form & Tutorial -->
            <div class="md:col-span-2 space-y-6">
                
                <!-- Cookie Form Card -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Konfigurasi Session Cookie</h2>
                            <p class="text-xs text-slate-500">Cookie ini digunakan oleh bot crawler untuk pelacakan resi masal via NIPOS</p>
                        </div>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-md bg-blue-50 text-blue-700 border border-blue-100">
                            NIPOS Crawler
                        </span>
                    </div>

                    <form action="{{ route('settings.nipos_cookie.store') }}" method="POST" id="cookieForm">
                        @csrf
                        <div class="space-y-2">
                            <label for="cookieInput" class="block text-xs font-bold text-slate-700">
                                String Cookie Lengkap (Termasuk PHPSESSID & TS01...)
                            </label>
                            <textarea 
                                name="cookie" 
                                id="cookieInput" 
                                rows="4" 
                                placeholder="Contoh: PHPSESSID=c7q1ktbiuvvvlhcllkn8lkirh2; TS011d97f9=01dc40192a65..." 
                                required
                                class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-600 focus:border-blue-600 bg-slate-50/50 transition resize-y leading-relaxed"
                            >{{ old('cookie', $cookie) }}</textarea>
                            <p class="text-[11px] text-slate-400">
                                Disimpan dengan aman di tabel database <code class="text-slate-600 bg-slate-100 px-1 py-0.5 rounded">system_settings</code>.
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                            <button 
                                type="button" 
                                onclick="testConnectionWithInput()"
                                class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-vial"></i> Test Input Ini Dulu
                            </button>
                            <button 
                                type="submit" 
                                class="py-2.5 px-5 bg-[#1E40AF] hover:bg-blue-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-500/20 cursor-pointer"
                            >
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Cookie ke Database
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tutorial Card -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-[#F97316]"></i>
                        Cara Mengambil Cookie dari Browser (Chrome / Edge)
                    </h3>
                    
                    <ol class="list-decimal pl-5 text-xs text-slate-600 space-y-2.5 leading-relaxed">
                        <li>Buka portal internal NIPOS / Lacak Pos Indonesia di browser Anda dan login seperti biasa.</li>
                        <li>Tekan tombol <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">F12</kbd> atau klik kanan pilih <strong>Inspect</strong> untuk membuka Developer Tools.</li>
                        <li>Buka tab <strong>Network</strong>, lalu lakukan 1 kali pencarian resi pada portal NIPOS.</li>
                        <li>Klik salah satu request URL yang muncul (misalnya <code class="bg-slate-100 text-slate-800 px-1 py-0.5 rounded font-mono">lacak_item_banyakzaref.php</code>).</li>
                        <li>Pada panel sebelah kanan, lihat bagian <strong>Request Headers</strong> dan cari baris <strong>Cookie:</strong>.</li>
                        <li>Salin seluruh teks setelah kata <code class="font-bold">Cookie:</code> lalu tempelkan pada kolom form di atas, kemudian klik <strong>Simpan</strong>.</li>
                    </ol>
                </div>

            </div>

        </div>

    </main>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        async function testConnectionLive() {
            const btn = document.getElementById('btnTestLive');
            const icon = document.getElementById('testIcon');
            const btnText = document.getElementById('testBtnText');

            btn.disabled = true;
            icon.className = 'fa-solid fa-spinner fa-spin';
            btnText.innerText = 'Menguji Koneksi...';

            try {
                const res = await fetch('{{ route("settings.nipos_cookie.test") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                const data = await res.json();
                updateStatusUI(data);
            } catch (err) {
                alert('Gagal menghubungi server: ' + err.message);
            } finally {
                btn.disabled = false;
                icon.className = 'fa-solid fa-satellite-dish';
                btnText.innerText = 'Uji Koneksi Realtime';
            }
        }

        async function testConnectionWithInput() {
            const cookieVal = document.getElementById('cookieInput').value.trim();
            if (!cookieVal) {
                alert('Silakan isi kolom cookie terlebih dahulu sebelum menguji.');
                return;
            }

            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menguji...';

            try {
                const res = await fetch('{{ route("settings.nipos_cookie.test") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ cookie: cookieVal }),
                });

                const data = await res.json();
                updateStatusUI(data);
                if (data.connected) {
                    alert('BERHASIL! ' + data.message);
                } else {
                    alert('GAGAL: ' + data.message);
                }
            } catch (err) {
                alert('Gagal melakukan test: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        function updateStatusUI(data) {
            const container = document.getElementById('statusContainer');
            const dot = document.getElementById('statusDot');
            const title = document.getElementById('statusTitle');
            const msg = document.getElementById('statusMessage');
            const latencyBox = document.getElementById('statusLatency');
            const latencyVal = document.getElementById('latencyValue');

            if (data.connected) {
                container.className = 'p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 transition-all';
                dot.innerHTML = `
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-600"></span>
                `;
                title.innerText = 'TERHUBUNG (ONLINE)';
                msg.innerText = data.message || 'Koneksi ke NIPOS aktif dan valid.';

                if (data.latency_ms) {
                    latencyBox.classList.remove('hidden');
                    latencyBox.className = 'mt-2 pt-2 border-t border-emerald-200/60 text-[11px] font-semibold text-emerald-700 flex items-center justify-between';
                    latencyVal.innerText = data.latency_ms + ' ms';
                }
            } else {
                container.className = 'p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 transition-all';
                dot.innerHTML = `
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-rose-600"></span>
                `;
                title.innerText = 'TERPUTUS / SESSION KEDALUWARSA';
                msg.innerText = data.message || 'Cookie kedaluwarsa atau tidak valid.';

                if (data.latency_ms) {
                    latencyBox.classList.remove('hidden');
                    latencyBox.className = 'mt-2 pt-2 border-t border-rose-200/60 text-[11px] font-semibold text-rose-700 flex items-center justify-between';
                    latencyVal.innerText = data.latency_ms + ' ms';
                }
            }
        }
    </script>

</body>
</html>
