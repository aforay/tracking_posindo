<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIPOS@MID - Lacak Item Banyak (Simulator)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-2xl shadow-md border border-gray-200">
        <div class="flex items-center space-x-3 mb-6 pb-4 border-b">
            <div class="w-10 h-10 bg-orange-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow">
                POS
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-800">NIPOS@MID Simulator</h1>
                <p class="text-xs text-gray-500">Endpoint: <code>lacak_item_banyakzaref.php</code></p>
            </div>
        </div>

        <form id="form_tracking" method="GET" action="{{ route('mock.nipos') }}" class="space-y-4 mb-6">
            <div>
                <label for="cari_barcode" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Input Nomor Resi / Barcode:</label>
                <div class="flex gap-2">
                    <input 
                        type="text" 
                        id="cari_barcode" 
                        name="cari_barcode" 
                        value="{{ $barcode ?? '' }}" 
                        placeholder="Contoh: P2601020133264 atau BAC30072635B11B6E971" 
                        class="flex-1 px-4 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none font-mono"
                        required
                    >
                    <button 
                        type="submit" 
                        id="btn_cari" 
                        class="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold text-sm rounded-xl transition shadow"
                    >
                        Lacak
                    </button>
                </div>
            </div>

            <!-- Quick Status Simulator Options -->
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Simulasi Preset Status NIPOS:</label>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" onclick="setPreset('DELIVERED')" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg text-xs font-bold transition">DELIVERED</button>
                    <button type="button" onclick="setPreset('FAILEDTODELIVERED')" class="px-2.5 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-lg text-xs font-bold transition">FAILEDTODELIVERED</button>
                    <button type="button" onclick="setPreset('DELIVERYRUNSHEET')" class="px-2.5 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 rounded-lg text-xs font-bold transition">DELIVERYRUNSHEET</button>
                    <button type="button" onclick="setPreset('INLOCATION')" class="px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-lg text-xs font-bold transition">INLOCATION</button>
                    <button type="button" onclick="setPreset('INVEHICLE')" class="px-2.5 py-1 bg-cyan-50 text-cyan-700 hover:bg-cyan-100 border border-cyan-200 rounded-lg text-xs font-bold transition">INVEHICLE</button>
                    <button type="button" onclick="setPreset('inBag')" class="px-2.5 py-1 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded-lg text-xs font-bold transition">inBag</button>
                    <button type="button" onclick="setPreset('Irregularity')" class="px-2.5 py-1 bg-pink-50 text-pink-700 hover:bg-pink-100 border border-pink-200 rounded-lg text-xs font-bold transition">Irregularity</button>
                </div>
            </div>
        </form>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Elemen Respon NIPOS (#hasil):</label>
            <div id="hasil" class="min-h-[120px] p-4 bg-gray-50 border border-dashed border-gray-300 rounded-xl text-gray-700">
                {!! $htmlResult ?? '<span class="text-gray-400 italic text-xs">Masukkan nomor resi di atas untuk memuat status tracking.</span>' !!}
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('form_tracking');
        const input = document.getElementById('cari_barcode');
        const hasil = document.getElementById('hasil');
        let currentPreset = '';

        function setPreset(status) {
            currentPreset = status;
            fetchTracking(input.value || 'P2601020133264');
        }

        function fetchTracking(barcode) {
            if (!barcode.trim()) return;
            hasil.innerHTML = '<span class="text-orange-600 text-xs animate-pulse">Sedang memuat data dari NIPOS@MID...</span>';
            
            let url = "{{ route('mock.nipos') }}?cari_barcode=" + encodeURIComponent(barcode) + "&ajax=1";
            if (currentPreset) {
                url += "&preset_status=" + encodeURIComponent(currentPreset);
            }

            fetch(url)
                .then(res => res.text())
                .then(html => {
                    hasil.innerHTML = html;
                })
                .catch(err => {
                    hasil.innerHTML = '<span class="text-red-500 text-xs">Gagal: ' + err + '</span>';
                });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            fetchTracking(input.value);
        });

        input.addEventListener('change', function() {
            fetchTracking(input.value);
        });
    </script>
</body>
</html>
