<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TRACKO Posindo Tracking Portal</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}?v=2">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-[#1C4587] to-blue-900 px-8 pt-8 pb-6 text-white text-center relative">
            <img src="{{ asset('favicon.svg') }}" alt="TRACKO" class="w-14 h-14 mx-auto mb-3 drop-shadow-md rounded-2xl" />
            <h1 class="text-xl font-extrabold tracking-tight">TRACKO Portal</h1>
            <p class="text-xs text-blue-200 mt-1">Sistem Pemantauan & Follow-Up CS Pos Indonesia</p>
        </div>

        <!-- Form Card Body -->
        <div class="p-8 space-y-6">
            @if(session('error'))
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" id="loginForm" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="email">
                        Alamat Email:
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        required 
                        autocomplete="username"
                        value="{{ old('email', 'admin@posindo.com') }}" 
                        placeholder="admin@posindo.com"
                        class="w-full px-4 py-2.5 rounded-xl border @error('email') border-rose-500 @else border-slate-300 @enderror text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
                    >
                    @error('email')
                        <p class="text-rose-500 text-[11px] font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" for="password">
                        Kata Sandi:
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-4 py-2.5 rounded-xl border @error('password') border-rose-500 @else border-slate-300 @enderror text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
                    >
                    @error('password')
                        <p class="text-rose-500 text-[11px] font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-slate-600">
                        <input type="checkbox" name="remember" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>Ingat Sesi Saya</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-[#1C4587] hover:bg-blue-900 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition duration-150 shadow-md hover:shadow-lg cursor-pointer"
                >
                    Masuk ke Sistem
                </button>
            </form>

            <!-- Quick Demo Login Helper -->
            <div class="pt-2 border-t border-slate-200 text-center">
                <p class="text-[11px] font-semibold text-slate-500 mb-2.5">Opsi Cepat Masuk:</p>
                <div>
                    <button 
                        type="button" 
                        onclick="quickLogin('admin@posindo.com', 'password')"
                        class="w-full px-4 py-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-xl text-left transition cursor-pointer flex items-center justify-between group"
                    >
                        <div>
                            <span class="block text-[10px] font-black text-blue-700 uppercase">Role Admin</span>
                            <span class="block text-[11px] font-bold text-slate-800">admin@posindo.com</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 group-hover:text-blue-700">Masuk Cepat &rarr;</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="bg-slate-50 px-8 py-3 text-center border-t border-slate-200/80">
            <p class="text-[10px] text-slate-400 font-medium">
                Hak Cipta &copy; 2026 PT Pos Indonesia (Persero). Dilindungi Undang-Undang.
            </p>
        </div>
    </div>

    <script>
        function quickLogin(email, password) {
            var emailInput = document.getElementById('email');
            var passInput = document.getElementById('password');
            if (emailInput) emailInput.value = email;
            if (passInput) passInput.value = password;
            var form = document.getElementById('loginForm');
            if (form) form.submit();
        }
    </script>
</body>
</html>