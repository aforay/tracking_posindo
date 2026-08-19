<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIPOS@MID - Lacak Item Banyak</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md border border-gray-200">
        <div class="flex items-center space-x-3 mb-6 pb-4 border-b">
            <div class="w-10 h-10 bg-orange-600 rounded flex items-center justify-center text-white font-bold text-xl">
                POS
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-800">NIPOS@MID Tracking System</h1>
                <p class="text-xs text-gray-500">Endpoint: lacak_item_banyakzaref.php</p>
            </div>
        </div>

        <form id="form_tracking" method="GET" action="{{ route('mock.nipos') }}" class="mb-6">
            <label for="cari_barcode" class="block text-sm font-semibold text-gray-700 mb-2">Input Nomor Resi / Barcode:</label>
            <div class="flex gap-2">
                <input 
                    type="text" 
                    id="cari_barcode" 
                    name="cari_barcode" 
                    value="{{ $barcode ?? '' }}" 
                    placeholder="Contoh: P2601020133264" 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none"
                    required
                >
                <button 
                    type="submit" 
                    id="btn_cari" 
                    class="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded transition shadow-sm"
                >
                    Cari
                </button>
            </div>
        </form>

        <div class="mt-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Hasil Tracking (#hasil):</label>
            <div id="hasil" class="min-h-[120px] p-4 bg-gray-50 border border-dashed border-gray-300 rounded text-gray-700">
                {!! $htmlResult ?? '<span class="text-gray-400 italic">Masukkan resi di atas untuk melihat status tracking.</span>' !!}
            </div>
        </div>
    </div>

    <script>
        // Automatic AJAX fetch when typing or submitting
        const form = document.getElementById('form_tracking');
        const input = document.getElementById('cari_barcode');
        const hasil = document.getElementById('hasil');

        function fetchTracking(barcode) {
            if (!barcode.trim()) return;
            hasil.innerHTML = '<span class="text-orange-600 animate-pulse">Sedang memuat data dari NIPOS...</span>';
            
            fetch("{{ route('mock.nipos') }}?cari_barcode=" + encodeURIComponent(barcode) + "&ajax=1")
                .then(res => res.text())
                .then(html => {
                    hasil.innerHTML = html;
                })
                .catch(err => {
                    hasil.innerHTML = '<span class="text-red-500">Gagal memuat status: ' + err + '</span>';
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
