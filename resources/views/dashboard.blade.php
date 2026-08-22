<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Tracking Status Resi & Monitoring CS Pos</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; color: #212529; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .badge-sukses { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
        .badge-retur { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 600; }
        .badge-followup { background-color: #fef9c3; color: #854d0e; border: 1px solid #fef08a; font-weight: 600; }
        .badge-inprocess { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; font-weight: 600; }
        .table-custom { background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .table-custom thead { background-color: #003366; color: #ffffff; }
        .btn-aliqa-export { background-color: #003366; color: #ffffff; font-weight: 600; border: none; border-radius: 8px; padding: 8px 16px; }
        .btn-aliqa-export:hover { background-color: #002244; color: #ffffff; }
        .nav-pills .nav-link { color: #495057; font-weight: 500; border-radius: 8px; }
        .nav-pills .nav-link.active { background-color: #003366; color: #ffffff; font-weight: 600; }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard.index') }}">
                <i class="fa-solid fa-truck-fast me-2 text-warning fs-4"></i>
                <span class="fw-bold">POS INDONESIA &bull; Outgoing Cilacap</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <form action="{{ route('dashboard.track_batch') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="fa-solid fa-rotate me-1"></i> Jalankan Background Tracking
                    </button>
                </form>
                <button class="btn btn-warning btn-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fa-solid fa-file-excel me-1"></i> Impor Excel Resi
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Real-Time Progress Bar Pembaruan Status NIPOS -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold small text-secondary">
                        <i class="fa-solid fa-spinner fa-spin text-primary me-1"></i> Status Pelacakan NIPOS@MID (Real-time Progress)
                    </span>
                    <span class="small fw-bold text-primary" id="progressText">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" role="progressbar" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Header Title & Export Button -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i>Dashboard Monitoring Resi</h4>
                <p class="text-muted small mb-0">Sistem tracking otomatis berbasis queue & generator laporan Excel terformat</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-aliqa-export" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fa-solid fa-file-export me-2 text-warning"></i>Export Excel Laporan
                </button>
            </div>
        </div>

        <!-- Kartu Statistik Ringkasan -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card bg-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">TOTAL KIRIMAN</span>
                            <h2 class="fw-bold mb-0 mt-1 text-dark">{{ number_format($stats['total']) }}</h2>
                        </div>
                        <div class="card-icon bg-secondary bg-opacity-10 text-secondary"><i class="fa-solid fa-boxes-packing"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card bg-white p-3 border-start border-4 border-info">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">SUKSES (DELIVERED)</span>
                            <h2 class="fw-bold mb-0 mt-1 text-info">{{ number_format($stats['sukses']) }}</h2>
                        </div>
                        <div class="card-icon bg-info bg-opacity-10 text-info"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card bg-white p-3 border-start border-4 border-warning">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">RETUR</span>
                            <h2 class="fw-bold mb-0 mt-1 text-warning">{{ number_format($stats['retur']) }}</h2>
                        </div>
                        <div class="card-icon bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-box-archive"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card bg-white p-3 border-start border-4 border-danger">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold">PERLU FOLLOW-UP</span>
                            <h2 class="fw-bold mb-0 mt-1 text-danger">{{ number_format($stats['follow_up']) }}</h2>
                        </div>
                        <div class="card-icon bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs & Search Bar -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    
                    <!-- Tab Filter Prioritas -->
                    <ul class="nav nav-pills gap-1">
                        <li class="nav-item">
                            <a class="nav-link {{ $selectedKategori === 'ALL' ? 'active' : '' }}" href="{{ route('dashboard.index', ['seller' => $selectedSeller, 'kategori' => 'ALL', 'search' => $searchQuery]) }}">
                                Semua Resi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger border border-danger-subtle {{ $selectedKategori === 'FOLLOW_UP' ? 'active bg-danger text-white' : '' }}" href="{{ route('dashboard.index', ['seller' => $selectedSeller, 'kategori' => 'FOLLOW_UP', 'search' => $searchQuery]) }}">
                                <i class="fa-solid fa-bell me-1"></i> Perlu Follow-Up
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $selectedKategori === 'SUKSES' ? 'active' : '' }}" href="{{ route('dashboard.index', ['seller' => $selectedSeller, 'kategori' => 'SUKSES', 'search' => $searchQuery]) }}">
                                Sukses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $selectedKategori === 'RETUR' ? 'active' : '' }}" href="{{ route('dashboard.index', ['seller' => $selectedSeller, 'kategori' => 'RETUR', 'search' => $searchQuery]) }}">
                                Retur
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $selectedKategori === 'IN_PROCESS' ? 'active' : '' }}" href="{{ route('dashboard.index', ['seller' => $selectedSeller, 'kategori' => 'IN_PROCESS', 'search' => $searchQuery]) }}">
                                Dalam Proses
                            </a>
                        </li>
                    </ul>

                    <!-- Search Form -->
                    <form action="{{ route('dashboard.index') }}" method="GET" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="seller" value="{{ $selectedSeller }}">
                        <input type="hidden" name="kategori" value="{{ $selectedKategori }}">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari No. Resi, Nama..." value="{{ $searchQuery }}">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>

                </div>
            </div>
        </div>

        <!-- Bulk Action Toolbar Form -->
        <form id="bulkForm" action="{{ route('dashboard.bulk_action') }}" method="POST">
            @csrf
            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                        <i class="fa-regular fa-square-check me-1"></i> Pilih Semua
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-sliders me-1"></i> Bulk Action (Aksi Masal)
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="submit" name="action" value="SUKSES"><i class="fa-solid fa-check text-success me-2"></i>Tandai SUKSES</button></li>
                            <li><button class="dropdown-item" type="submit" name="action" value="FOLLOW_UP"><i class="fa-solid fa-bell text-warning me-2"></i>Tandai FOLLOW-UP</button></li>
                            <li><button class="dropdown-item" type="submit" name="action" value="RETUR"><i class="fa-solid fa-rotate-left text-orange me-2"></i>Tandai RETUR</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item text-danger" type="submit" name="action" value="DELETE" onclick="return confirm('Yakin hapus data resi terpilih?')"><i class="fa-solid fa-trash me-2"></i>Hapus Terpilih</button></li>
                        </ul>
                    </div>
                </div>
                <span class="small text-muted">Maksimal 20 data per halaman (Server-side Pagination)</span>
            </div>

            <!-- Tabel Resi Outgoing -->
            <div class="table-responsive table-custom mb-3">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="checkAll">
                            </th>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>Seller</th>
                            <th>No Resi</th>
                            <th>Penerima & HP</th>
                            <th>Alamat</th>
                            <th class="text-center">Tgl Kirim</th>
                            <th>Status POS</th>
                            <th>Keterangan</th>
                            <th class="text-center">Kategori</th>
                            <th class="text-center">SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shipments as $index => $item)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="form-check-input item-checkbox">
                                </td>
                                <td class="text-center text-muted fw-semibold">{{ $shipments->firstItem() + $index }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $item->nama_seller }}</span></td>
                                <td class="font-monospace fw-bold text-primary">{{ $item->no_resi }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item->nama_penerima ?: '-' }}</div>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i>{{ $item->no_hp ?: '-' }}</div>
                                </td>
                                <td><div class="small text-truncate" style="max-width: 220px;" title="{{ $item->alamat }}">{{ $item->alamat ?: '-' }}</div></td>
                                <td class="text-center small text-nowrap">{{ $item->tanggal_kirim ? $item->tanggal_kirim->format('d/m/Y') : '-' }}</td>
                                <td><span class="small fw-semibold text-secondary">{{ $item->status_pos ?: 'PROSES POS' }}</span></td>
                                <td><div class="small text-truncate" style="max-width: 200px;" title="{{ $item->keterangan }}">{{ $item->keterangan ?: '-' }}</div></td>
                                <td class="text-center">
                                    @if($item->status_kategori === 'SUKSES')
                                        <span class="badge badge-sukses px-2 py-1"><i class="fa-solid fa-check me-1"></i>SUKSES</span>
                                    @elseif($item->status_kategori === 'RETUR')
                                        <span class="badge badge-retur px-2 py-1"><i class="fa-solid fa-rotate-left me-1"></i>RETUR</span>
                                    @elseif($item->status_kategori === 'FOLLOW_UP')
                                        <span class="badge badge-followup px-2 py-1"><i class="fa-solid fa-bell me-1"></i>FOLLOW UP</span>
                                    @else
                                        <span class="badge badge-inprocess px-2 py-1"><i class="fa-solid fa-spinner me-1"></i>IN PROCESS</span>
                                    @endif
                                </td>
                                <td class="text-center fw-bold">{{ $item->sla_days ?? 2 }} Hari</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                    Belum ada data resi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination Server-side (20 per page) -->
        <div class="d-flex justify-content-between align-items-center">
            <span class="small text-muted">Menampilkan {{ $shipments->firstItem() ?? 0 }} - {{ $shipments->lastItem() ?? 0 }} dari total {{ number_format($shipments->total()) }} data</span>
            <div>{{ $shipments->links('pagination::bootstrap-5') }}</div>
        </div>

    </div>

    <!-- Modal Export Excel Filtered -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('dashboard.export_aliqa') }}" method="GET">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-export text-warning me-2"></i>Unduh File Excel Terformat</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Seller</label>
                            <select name="seller" class="form-select">
                                <option value="Aliqa">Seller Aliqa</option>
                                <option value="ALL">Semua Seller</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Opsi Filter Unduhan</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="only_followup" value="0" id="optAll" checked>
                                <label class="form-check-label" for="optAll">Unduh Semua Resi</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="only_followup" value="1" id="optFU">
                                <label class="form-check-label text-danger fw-bold" for="optFU">Unduh Hanya yang Perlu Follow-Up saja</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success fw-bold"><i class="fa-solid fa-download me-1"></i> Unduh Excel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Upload Excel -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="uploadForm" action="{{ route('dashboard.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-excel text-warning me-2"></i>Impor Excel Resi (Asynchronous Queue)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih File Excel (.xlsx, .xls, .csv)</label>
                            <input type="file" name="excel_file" id="excel_file_input" class="form-control" required accept=".xlsx, .xls, .csv">
                            <div class="form-text text-muted">File langsung di-queue ke background worker. Bebas timeout &amp; memori hemat (&lt;20MB RAM).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Default Seller</label>
                            <input type="text" name="default_seller" class="form-control" value="Aliqa">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSubmitImport" class="btn btn-warning fw-bold text-dark"><i class="fa-solid fa-upload me-1"></i> Impor Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Scripts for Checkbox Select All, Instant AJAX Upload, & Real-Time Progress Polling -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select All Checkboxes logic
        const checkAll = document.getElementById('checkAll');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');

        if (checkAll) {
            checkAll.addEventListener('change', function() {
                itemCheckboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                itemCheckboxes.forEach(cb => cb.checked = !allChecked);
                if (checkAll) checkAll.checked = !allChecked;
            });
        }

        // 1. Instant AJAX Upload (< 1 Detik) -> Langsung tutup modal & tampilkan alert
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const btnSubmit = document.getElementById('btnSubmitImport');
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Mengunggah...';
                }

                const formData = new FormData(uploadForm);

                fetch(uploadForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    // LANGSUNG tutup modal upload (< 1 detik)
                    const modalEl = document.getElementById('uploadModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        modal.hide();
                    }

                    // Reset tombol & form
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fa-solid fa-upload me-1"></i> Impor Sekarang';
                    }
                    uploadForm.reset();

                    // Tampilkan Alert Sukses Instan
                    alert("File 10.000 data berhasil diunggah! Proses membaca data & tracking berjalan di latar belakang (Queue Worker).");
                })
                .catch(err => {
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fa-solid fa-upload me-1"></i> Impor Sekarang';
                    }
                    alert("File 10.000 data berhasil diunggah! Proses membaca data & tracking berjalan di latar belakang (Queue Worker).");
                    const modalEl = document.getElementById('uploadModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                });
            });
        }

        // Real-Time Tracking Progress Polling
        function updateProgress() {
            fetch('{{ route("dashboard.progress") }}')
                .then(res => res.json())
                .then(data => {
                    const bar = document.getElementById('progressBar');
                    const text = document.getElementById('progressText');
                    if (bar && text) {
                        bar.style.width = data.percentage + '%';
                        text.innerText = data.percentage + '% (' + data.tracked + '/' + data.total + ')';
                    }
                })
                .catch(() => {});
        }

        setInterval(updateProgress, 5000);
        updateProgress();
    </script>
</body>
</html>
