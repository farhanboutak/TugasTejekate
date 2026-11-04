<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses('transactions.view');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$data_peminjaman = [];
$error_message   = null;
$total_barang = 0;
$total_pengguna = 0;
$aktivitas_terbaru = [];
$audit_error = null;

try {
    $sql = "SELECT peminjaman.id, users.nama AS nama_peminjam, barang.nama_barang, barang.foto,
                   peminjaman.tanggal_pinjam, peminjaman.tanggal_kembali,
                   peminjaman.jumlah_pinjam, peminjaman.status
            FROM peminjaman
            JOIN users  ON peminjaman.user_id  = users.id
            JOIN barang ON peminjaman.barang_id = barang.id
            ORDER BY peminjaman.id DESC";
    $stmt = $pdo->query($sql);
    $data_peminjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_barang = (int)$pdo->query('SELECT COUNT(*) FROM barang WHERE diarsipkan = 0')->fetchColumn();
    $total_pengguna = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) {
    $error_message = "Gagal memuat data peminjaman. Silakan coba lagi.";
}

try {
    $aktivitas_terbaru = $pdo->query(
        'SELECT actor_name, actor_role, entity, action, summary, created_at
         FROM audit_logs
         ORDER BY id DESC
         LIMIT 6'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR dashboard audit query failed: ' . $e->getMessage());
    $audit_error = 'Log aktivitas belum tersedia. Jalankan migrasi audit log untuk mengaktifkannya.';
}

$total     = count($data_peminjaman);
$pending   = 0;
$disetujui = 0;
$dikembalikan = 0;
$ditolak   = 0;
foreach ($data_peminjaman as $r) {
    switch ($r['status']) {
        case 'Pending':       $pending++;       break;
        case 'Disetujui':     $disetujui++;     break;
        case 'Dikembalikan':  $dikembalikan++;  break;
        case 'Ditolak':       $ditolak++;       break;
    }
}

function format_tanggal($tgl) {
    if (empty($tgl) || $tgl === '0000-00-00') return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($tgl);
    if ($ts === false) return htmlspecialchars($tgl);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function badge_status($status) {
    $map = [
        'Pending'      => ['warning', 'bi-hourglass-split', 'Pending'],
        'Disetujui'    => ['success', 'bi-check-circle',   'Disetujui'],
        'Ditolak'      => ['danger',  'bi-x-circle',       'Ditolak'],
        'Dikembalikan' => ['primary', 'bi-arrow-return-left', 'Dikembalikan'],
    ];
    $cfg = $map[$status] ?? ['secondary', 'bi-dash-circle', $status];
    return '<span class="badge badge-status bg-' . $cfg[0] . '-subtle text-' . $cfg[0] . ' border border-' . $cfg[0] . '-subtle">'
         . '<i class="bi ' . $cfg[1] . ' me-1"></i>' . htmlspecialchars($cfg[2]) . '</span>';
}

$pesan_map = [
    'sukses'    => ['success', 'bi-check-circle-fill', 'Status transaksi berhasil diperbarui.'],
    'dihapus'   => ['success', 'bi-trash-fill',        'Transaksi berhasil dihapus.'],
    'tidak_ada' => ['warning', 'bi-exclamation-circle-fill', 'Data tidak ditemukan atau tidak berubah.'],
    'invalid'   => ['danger',  'bi-x-circle-fill',     'Permintaan tidak valid.'],
    'gagal'     => ['danger',  'bi-exclamation-triangle-fill', 'Terjadi kesalahan saat memproses data.'],
    'csrf'      => ['danger',  'bi-shield-x',          'Sesi tidak valid. Silakan coba lagi.'],
];
$pesan_key = $_GET['pesan'] ?? '';
$pesan_info = $pesan_map[$pesan_key] ?? null;

$nama_admin = $_SESSION['nama'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIMBAR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #172a3a;
            --muted: #647587;
            --brand: #146b68;
            --brand-dark: #105653;
            --mint: #e8f4ef;
            --paper: #fbfaf6;
            --line: #e7e9e3;
            --white: #fff;
            --primary: var(--brand);
            --primary-soft: var(--mint);
            --bs-primary: var(--brand);
            --bs-primary-rgb: 20, 107, 104;
            --bs-body-color: var(--ink);
            --bs-body-bg: var(--paper);
            --bs-border-color: var(--line);
            --bs-primary-text-emphasis: var(--brand-dark);
            --bs-primary-bg-subtle: var(--mint);
            --bs-primary-border-subtle: #c7e1d8;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(ellipse at 12% 0, rgba(20, 107, 104, .07), transparent 34%),
                var(--paper);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.55;
        }

        .container { max-width: 1120px; }

        .navbar-custom {
            background: var(--brand);
            box-shadow: 0 8px 24px rgba(16, 86, 83, .16);
        }
        .navbar-custom .container { min-height: 72px; }
        .navbar-custom .navbar-brand {
            display: inline-flex;
            align-items: center;
            letter-spacing: .04em;
        }
        .navbar-custom .navbar-brand i {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 12px;
            background: rgba(255,255,255,.12);
            font-size: 1rem;
        }
        .user-chip {
            padding: .35rem .8rem;
            border-radius: 999px;
            background: rgba(255,255,255,.15);
            font-size: .85rem;
            backdrop-filter: blur(4px);
        }

        .page-title {
            gap: 12px;
            margin-bottom: 25px !important;
            color: var(--ink);
            font-size: clamp(1.45rem, 3vw, 1.85rem);
            font-weight: 700;
            letter-spacing: -.04em;
        }
        .page-title i {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            flex: 0 0 auto;
            margin: 0;
            padding: 0;
            border-radius: 13px;
            background: var(--mint);
            color: var(--brand);
            font-size: 1.15rem;
        }
        .admin-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
            padding: 22px 24px;
            border: 1px solid #d5e7df;
            border-radius: 16px;
            background: linear-gradient(115deg, #eff7f2, #fff 72%);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .04);
        }
        .admin-heading .eyebrow { margin: 0 0 4px; color: var(--brand); font-size: .68rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        .admin-heading h1 { margin: 0 0 4px; font-size: clamp(1.35rem, 3vw, 1.8rem); font-weight: 750; letter-spacing: -.045em; }
        .admin-heading p:last-child { margin: 0; color: var(--muted); font-size: .86rem; }
        .admin-heading .btn { flex: 0 0 auto; padding: .65rem 1rem; }

        .stat-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 3px;
            background: var(--accent, var(--brand));
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(29, 55, 62, .09) !important;
        }
        .stat-card .card-body { min-height: 104px; padding: 20px; }
        .stat-icon {
            width: 44px;
            height: 44px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 13px;
            font-size: 1.15rem;
        }
        .stat-label {
            color: var(--muted);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
        }
        .stat-value { color: var(--ink); }
        .stat-icon.bg-primary-subtle,
        .stat-icon.text-primary {
            background-color: var(--mint) !important;
            color: var(--brand) !important;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: -8px 0 22px;
        }
        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--white);
            color: var(--ink);
            text-decoration: none;
            transition: border-color .2s ease, transform .2s ease;
        }
        .quick-link:hover { transform: translateY(-1px); border-color: var(--brand); color: var(--brand-dark); }
        .quick-link-icon {
            width: 38px;
            height: 38px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 11px;
            background: var(--mint);
            color: var(--brand);
        }
        .quick-link strong { display: block; font-size: 13px; }
        .quick-link small { color: var(--muted); font-size: 11px; }

        .activity-panel {
            overflow: hidden;
            margin: 0 0 22px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29,55,62,.045);
        }
        .activity-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 19px;
            border-bottom: 1px solid var(--line);
        }
        .activity-heading h2 { margin: 0; font-size: 1rem; font-weight: 750; }
        .activity-heading p { margin: 2px 0 0; color: var(--muted); font-size: .75rem; }
        .activity-heading a { color: var(--brand); font-size: .78rem; font-weight: 750; }
        .activity-list { display: grid; }
        .activity-item { display: grid; grid-template-columns: 35px minmax(0,1fr) auto; align-items: center; gap: 11px; padding: 12px 19px; border-bottom: 1px solid #eef1ed; }
        .activity-item:last-child { border-bottom: 0; }
        .activity-icon { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: var(--mint); color: var(--brand); }
        .activity-summary { color: var(--ink); font-size: .78rem; font-weight: 650; }
        .activity-meta,.activity-time { color: var(--muted); font-size: .68rem; }
        .activity-time { text-align: right; white-space: nowrap; }
        .activity-empty { padding: 20px; color: var(--muted); font-size: .8rem; text-align: center; }

        .filter-card {
            padding: 16px !important;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045) !important;
        }
        .filter-card .form-control,
        .filter-card .form-select,
        .filter-card .input-group-text {
            min-height: 44px;
            border-color: var(--line);
            background-color: var(--white);
            color: var(--muted);
        }
        .filter-card .form-control { padding: .55rem .85rem; }
        .filter-card .input-group-text { padding-inline: .85rem; }
        .filter-card .form-control,
        .filter-card .form-select { border-radius: 9px; }
        .filter-card .input-group-text:first-child { border-radius: 9px 0 0 9px; }
        .filter-card .input-group .form-control { border-radius: 0 9px 9px 0; }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem rgba(20, 107, 104, .13);
        }
        .filter-card .form-control::placeholder { color: #97a4aa; }
        .filter-actions { display: flex; gap: 8px; }
        .filter-actions .btn { flex: 1 1 0; padding-right: .4rem; padding-left: .4rem; white-space: nowrap; }

        .btn {
            border-radius: 9px;
            font-weight: 650;
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }
        .btn:focus-visible,
        .btn-close:focus-visible,
        .form-control:focus-visible,
        .form-select:focus-visible {
            outline: 3px solid rgba(20, 107, 104, .25);
            outline-offset: 2px;
            box-shadow: none;
        }
        .btn-primary {
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: var(--brand-dark);
            --bs-btn-hover-border-color: var(--brand-dark);
            --bs-btn-active-bg: var(--brand-dark);
            --bs-btn-active-border-color: var(--brand-dark);
            --bs-btn-focus-shadow-rgb: 20, 107, 104;
        }
        .btn-outline-secondary {
            --bs-btn-color: var(--muted);
            --bs-btn-border-color: var(--line);
            --bs-btn-hover-bg: var(--mint);
            --bs-btn-hover-color: var(--brand-dark);
            --bs-btn-hover-border-color: var(--brand);
        }

        .table-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045) !important;
        }
        .table-card .table { margin-bottom: 0; }
        .table-card thead th {
            background: #f5f8f5;
            color: var(--muted);
            font-size: .69rem;
            letter-spacing: .07em;
            border-bottom: 1px solid var(--line);
            padding: .9rem 1rem;
        }
        .table-card tbody td {
            padding: .9rem 1rem;
            border-color: #eef1ed;
            color: var(--ink);
            font-size: .875rem;
        }
        .table-card tbody tr:hover { background: #f8faf8; }
        .peminjam-cell { font-weight: 650; }
        .avatar {
            background: var(--mint);
            color: var(--brand);
        }
        .barang-cell { color: var(--ink); }
        .barang-cell { display: flex; align-items: center; gap: 10px; min-width: 175px; }
        .barang-thumb { width: 42px; height: 42px; flex: 0 0 auto; display: grid; place-items: center; overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: #f2f7f4; color: var(--brand); }
        .barang-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .barang-cell i { color: var(--brand); }
        .badge-status {
            padding: .4rem .7rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-aksi {
            min-width: 34px;
            min-height: 34px;
            padding: .3rem .5rem;
        }
        .btn-aksi:hover { transform: translateY(-1px); }

        .modal-content {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 24px 70px rgba(29, 55, 62, .18);
        }
        .modal-header,
        .modal-footer { border-color: var(--line); }
        .modal-body { padding: 1.25rem; }
        .alert-custom {
            border: 0;
            border-radius: 11px;
            box-shadow: 0 4px 12px rgba(29, 55, 62, .05);
        }

        .fade-in { animation: fadeIn .4s ease both; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 767.98px) {
            .navbar-custom .container {
                min-height: 64px;
                flex-wrap: wrap;
                padding-top: .45rem;
                padding-bottom: .45rem;
            }
            .navbar-custom .container > .d-flex {
                flex: 1 1 100%;
                justify-content: space-between;
                overflow-x: auto;
            }
            .admin-heading { align-items: flex-start; }
        }

        @media (max-width: 575.98px) {
            .navbar-custom .container { gap: 8px !important; }
            .navbar-custom .container > .d-flex { gap: .35rem !important; }
            .navbar-custom .navbar-brand { font-size: .95rem; }
            .navbar-custom .navbar-brand i {
                width: 34px;
                height: 34px;
                margin-right: .55rem !important;
            }
            .navbar-custom .nav-label { display: none; }
            .navbar-custom .btn { padding-right: .55rem !important; padding-left: .55rem !important; }
            .admin-heading { flex-direction: column; padding: 18px; }
            .admin-heading .btn { width: 100%; }
            .user-chip { display: none !important; }
            .stat-card .card-body { min-height: 88px; padding: 14px; }
            .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
            .stat-value { font-size: 1.4rem; }
            .stat-label { font-size: .64rem; }
            .filter-card { padding: 12px !important; }
            .quick-links { gap: 8px; }
            .quick-links { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .quick-link { align-items: flex-start; padding: 12px; }
            .quick-link-icon { width: 33px; height: 33px; }
            .activity-item { grid-template-columns: 32px minmax(0,1fr); padding: 11px 13px; }
            .activity-time { grid-column: 2; text-align: left; }
            .activity-heading { padding: 14px; }
            .table-responsive { overflow: visible; }
            .table-card .table { display: block; }
            .table-card thead { display: none; }
            .table-card tbody { display: grid; gap: 10px; padding: 10px; }
            .table-card tbody tr:not(.empty-row) {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 8px 10px;
                border: 1px solid var(--line);
                border-radius: 11px;
                background: var(--white);
            }
            .table-card tbody tr:not(.empty-row) td {
                display: flex;
                flex-direction: column;
                gap: 3px;
                padding: 7px 4px;
                border: 0;
                text-align: left !important;
            }
            .table-card tbody tr:not(.empty-row) td::before {
                color: var(--muted);
                content: attr(data-label);
                font-size: 9px;
                font-weight: 800;
                letter-spacing: .05em;
                text-transform: uppercase;
            }
            .table-card tbody tr:not(.empty-row) td:first-child { display: none; }
            .table-card tbody tr:not(.empty-row) td:nth-child(2),
            .table-card tbody tr:not(.empty-row) td:nth-child(3),
            .table-card tbody tr:not(.empty-row) td:last-child { grid-column: 1 / -1; }
            .table-card tbody tr:not(.empty-row) td:last-child .d-inline-flex { flex-wrap: wrap; }
            .table-card tbody .empty-row { display: block; }
            .table-card tbody .empty-row td { display: block; width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }

        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; }
        .navbar-custom { border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); box-shadow:none; backdrop-filter:saturate(180%) blur(18px); }
        .navbar-custom .navbar-brand { color:var(--ink)!important; }
        .navbar-custom .navbar-brand i { border-color:var(--line); background:var(--mint); color:var(--brand); }
        .navbar-custom .user-chip { background:#f2f4f3; color:var(--muted)!important; }
        .navbar-custom .btn-outline-light { --bs-btn-color:var(--muted); --bs-btn-border-color:var(--line); --bs-btn-hover-bg:var(--mint); --bs-btn-hover-color:var(--brand-dark); --bs-btn-hover-border-color:var(--brand); }
        .navbar-custom .btn-light { border-color:var(--line); background:var(--mint); color:var(--brand-dark); }
        .admin-heading { border-color:var(--line); border-radius:22px; background:linear-gradient(120deg,#f1f7f3,#fff 72%); }
        .stat-card,.quick-link,.activity-panel,.table-card,.filter-card { border-radius:19px; }
        .table-card thead th { background:#f7f8f7; }
        @media (max-width:575.98px) { .navbar-custom { backdrop-filter:none; } .admin-heading { border-radius:18px; } }
    </style>
    <?php require __DIR__ . '/sidebar_styles.php'; ?>
</head>
<body>
<?php $admin_sidebar_active = 'dashboard'; require __DIR__ . '/sidebar.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-box-seam-fill me-2"></i>SIMBAR Admin
        </a>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <span class="user-chip text-white d-none d-md-inline-flex align-items-center">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($nama_admin) ?>
            </span>
            <?php if (simbar_user_has_permission('users.manage')): ?>
                <a href="pengguna.php" class="btn btn-sm btn-outline-light rounded-pill px-3" aria-label="Kelola pengguna"><i class="bi bi-people me-1"></i><span class="nav-label">Pengguna</span></a>
                <a href="peran.php" class="btn btn-sm btn-outline-light rounded-pill px-3" aria-label="Kelola role"><i class="bi bi-shield-lock me-1"></i><span class="nav-label">Role</span></a>
            <?php endif; ?>
            <?php if (simbar_user_has_permission('inventory.manage')): ?>
                <a href="barang.php" class="btn btn-sm btn-outline-light rounded-pill px-3" aria-label="Kelola inventaris"><i class="bi bi-box-seam me-1"></i><span class="nav-label">Inventaris</span></a>
            <?php endif; ?>
            <?php if (simbar_user_has_permission('audit.view')): ?>
                <a href="log.php" class="btn btn-sm btn-outline-light rounded-pill px-3" aria-label="Log aktivitas"><i class="bi bi-clock-history me-1"></i><span class="nav-label">Log</span></a>
            <?php endif; ?>
            <a href="../akun.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                <i class="bi bi-person-gear me-1"></i><span class="d-none d-md-inline">Profil</span>
            </a>
            <a href="../logout.php" class="btn btn-sm btn-light rounded-pill px-3">
                <i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Keluar</span>
            </a>
        </div>
    </div>
</nav>

<div class="container my-4 my-md-5 fade-in">

    <?php if ($pesan_info): ?>
        <div class="alert alert-<?= $pesan_info[0] ?> alert-custom alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi <?= $pesan_info[1] ?> me-2 fs-5"></i>
            <div><?= htmlspecialchars($pesan_info[2]) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-custom d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
        </div>
    <?php endif; ?>

    <section class="admin-heading" aria-labelledby="admin-title">
        <div>
            <p class="eyebrow">Area administrasi</p>
            <h1 id="admin-title">Selamat datang, <?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Pantau peminjaman, kelola inventaris, dan atur akun SIMBAR dari satu tempat.</p>
        </div>
        <?php if (simbar_user_has_permission('inventory.manage')): ?>
            <a href="barang.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Kelola inventaris</a>
        <?php endif; ?>
    </section>

    <h2 class="page-title d-flex align-items-center">
        <i class="bi bi-clipboard-data-fill"></i>Data Transaksi Peminjaman
    </h2>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1">Total</div>
                        <div class="stat-value"><?= $total ?></div>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-collection-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1">Pending</div>
                        <div class="stat-value"><?= $pending ?></div>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1">Disetujui</div>
                        <div class="stat-value"><?= $disetujui ?></div>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1">Dikembalikan</div>
                        <div class="stat-value"><?= $dikembalikan ?></div>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-arrow-return-left"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="quick-links">
        <?php if (simbar_user_has_permission('inventory.manage')): ?>
        <a class="quick-link" href="barang.php">
            <span class="quick-link-icon"><i class="bi bi-box-seam"></i></span>
            <span><strong>Kelola inventaris</strong><small><?= $total_barang ?> jenis barang</small></span>
        </a>
        <?php endif; ?>
        <?php if (simbar_user_has_permission('users.manage')): ?>
        <a class="quick-link" href="pengguna.php">
            <span class="quick-link-icon"><i class="bi bi-people"></i></span>
            <span><strong>Kelola pengguna</strong><small><?= $total_pengguna ?> akun terdaftar</small></span>
        </a>
        <?php endif; ?>
        <?php if (simbar_user_has_permission('audit.view')): ?>
        <a class="quick-link" href="log.php">
            <span class="quick-link-icon"><i class="bi bi-clock-history"></i></span>
            <span><strong>Log aktivitas</strong><small>Lihat jejak perubahan admin</small></span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (simbar_user_has_permission('audit.view')): ?>
    <section class="activity-panel" aria-labelledby="activity-title">
        <div class="activity-heading">
            <div><h2 id="activity-title">Aktivitas terbaru</h2><p>Jejak tindakan admin dan petugas</p></div>
            <a href="log.php">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if ($audit_error): ?>
            <div class="activity-empty"><?= htmlspecialchars($audit_error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (!$aktivitas_terbaru): ?>
            <div class="activity-empty">Belum ada aktivitas yang tercatat.</div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($aktivitas_terbaru as $aktivitas): ?>
                    <?php
                    $ikonAktivitas = [
                        'auth' => 'bi-shield-check',
                        'transaksi' => 'bi-arrow-left-right',
                        'barang' => 'bi-box-seam',
                        'pengguna' => 'bi-person-gear',
                    ][$aktivitas['entity']] ?? 'bi-activity';
                    $waktuAktivitas = strtotime($aktivitas['created_at']);
                    ?>
                    <article class="activity-item">
                        <span class="activity-icon" aria-hidden="true"><i class="bi <?= htmlspecialchars($ikonAktivitas, ENT_QUOTES, 'UTF-8') ?>"></i></span>
                        <div>
                            <div class="activity-summary"><?= htmlspecialchars($aktivitas['summary'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="activity-meta"><?= htmlspecialchars($aktivitas['actor_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst($aktivitas['actor_role']), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <time class="activity-time"<?= $waktuAktivitas ? ' datetime="' . htmlspecialchars(date(DATE_ATOM, $waktuAktivitas), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?= $waktuAktivitas ? htmlspecialchars(date('d M Y, H:i', $waktuAktivitas), ENT_QUOTES, 'UTF-8') : htmlspecialchars($aktivitas['created_at'], ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="filter-card p-3 mb-3 shadow-sm">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0"
                           placeholder="Cari peminjam / nama barang...">
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Disetujui">Disetujui</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="Dikembalikan">Dikembalikan</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="row g-2">
                    <div class="col-6">
                        <input type="date" id="dateFrom" class="form-control" aria-label="Tanggal pinjam dari" title="Tanggal pinjam dari">
                    </div>
                    <div class="col-6">
                        <input type="date" id="dateTo" class="form-control" aria-label="Tanggal pinjam sampai" title="Tanggal pinjam sampai">
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <div class="filter-actions">
                    <?php if (simbar_user_has_permission('transactions.export')): ?><a href="ekspor_peminjaman.php" id="exportLink" class="btn btn-primary" aria-label="Ekspor CSV" title="Ekspor CSV">
                        <i class="bi bi-download me-1"></i>CSV
                    </a><?php endif; ?>
                    <a href="index.php" class="btn btn-outline-secondary" aria-label="Muat ulang filter" title="Muat ulang filter">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle" id="dataTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width:55px;">No</th>
                        <th>Peminjam</th>
                        <th>Nama Barang</th>
                        <th>Tgl Pinjam</th>
                        <th>Tgl Kembali</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_peminjaman)): ?>
                        <tr class="empty-row">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <div class="fw-semibold">Belum ada data transaksi peminjaman.</div>
                                <div class="small">Transaksi baru akan tampil di sini.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($data_peminjaman as $row):
                            $inisial = strtoupper(mb_substr($row['nama_peminjam'], 0, 1));
                        ?>
                            <tr>
                                <td class="text-center text-muted" data-label="No"><?= $no++ ?></td>
                                <td data-label="Peminjam">
                                    <div class="peminjam-cell">
                                        <div class="avatar"><?= htmlspecialchars($inisial) ?></div>
                                        <span><?= htmlspecialchars($row['nama_peminjam']) ?></span>
                                    </div>
                                </td>
                                <td data-label="Nama Barang">
                                    <div class="barang-cell">
                                        <span class="barang-thumb">
                                            <?php if (!empty($row['foto'])): ?>
                                                <img src="../uploads/barang/<?= htmlspecialchars($row['foto'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td data-label="Tanggal Pinjam" data-date="<?= htmlspecialchars($row['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?>"><?= format_tanggal($row['tanggal_pinjam']) ?></td>
                                <td data-label="Tanggal Kembali" data-date="<?= htmlspecialchars($row['tanggal_kembali'], ENT_QUOTES, 'UTF-8') ?>"><?= format_tanggal($row['tanggal_kembali']) ?></td>
                                <td class="text-center" data-label="Jumlah">
                                    <span class="badge bg-light text-dark border"><?= (int)$row['jumlah_pinjam'] ?></span>
                                </td>
                                <td class="text-center" data-label="Status"><?= badge_status($row['status']) ?></td>
                                <td class="text-center" data-label="Aksi">
                                    <div class="d-inline-flex gap-1">
                                        <?php if (simbar_user_has_permission('transactions.manage')): ?>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <button type="button" class="btn btn-success btn-aksi"
                                                title="Setujui"
                                                data-bs-toggle="modal" data-bs-target="#modalAksi"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-status="Disetujui"
                                                data-label="menyetujui"
                                                data-nama="<?= htmlspecialchars($row['nama_peminjam']) ?>"
                                                data-barang="<?= htmlspecialchars($row['nama_barang']) ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-aksi"
                                                title="Tolak"
                                                data-bs-toggle="modal" data-bs-target="#modalAksi"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-status="Ditolak"
                                                data-label="menolak"
                                                data-nama="<?= htmlspecialchars($row['nama_peminjam']) ?>"
                                                data-barang="<?= htmlspecialchars($row['nama_barang']) ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php elseif ($row['status'] === 'Disetujui'): ?>
                                            <button type="button" class="btn btn-primary btn-aksi"
                                                title="Kembalikan"
                                                data-bs-toggle="modal" data-bs-target="#modalAksi"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-status="Dikembalikan"
                                                data-label="mengembalikan"
                                                data-nama="<?= htmlspecialchars($row['nama_peminjam']) ?>"
                                                data-barang="<?= htmlspecialchars($row['nama_barang']) ?>">
                                                <i class="bi bi-arrow-return-left"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (simbar_user_has_permission('transactions.delete')): ?>
                                        <button type="button" class="btn btn-outline-danger btn-aksi"
                                            title="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#modalHapus"
                                            data-id="<?= (int)$row['id'] ?>"
                                            data-nama="<?= htmlspecialchars($row['nama_peminjam']) ?>"
                                            data-barang="<?= htmlspecialchars($row['nama_barang']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (!simbar_user_has_any_permission(['transactions.manage', 'transactions.delete'])): ?><span class="text-muted small">-</span><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Total ditampilkan: <span id="rowCount" class="fw-semibold"><?= $total ?></span> baris
    </p>
</div>

<div class="modal fade" id="modalAksi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="aksi_status.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" id="aksiId">
            <input type="hidden" name="status" id="aksiStatus">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-question-circle me-2 text-primary"></i>Konfirmasi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Anda akan <strong id="aksiLabel"></strong> transaksi berikut:</p>
                <div class="bg-light rounded p-3 small">
                    <div class="mb-1"><i class="bi bi-person me-2 text-muted"></i>Peminjam: <strong id="aksiNama"></strong></div>
                    <div><i class="bi bi-box-seam me-2 text-muted"></i>Barang: <strong id="aksiBarang"></strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4">Ya, lanjutkan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="hapus.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" id="hapusId">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold text-danger">
                    <i class="bi bi-trash me-2"></i>Hapus Transaksi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Yakin ingin menghapus transaksi ini secara permanen?</p>
                <div class="bg-light rounded p-3 small">
                    <div class="mb-1"><i class="bi bi-person me-2 text-muted"></i>Peminjam: <strong id="hapusNama"></strong></div>
                    <div><i class="bi bi-box-seam me-2 text-muted"></i>Barang: <strong id="hapusBarang"></strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4">Hapus</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const search = document.getElementById('searchInput');
    const filter = document.getElementById('statusFilter');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const exportLink = document.getElementById('exportLink');
    const table  = document.getElementById('dataTable');
    const rows   = table.querySelectorAll('tbody tr');
    const countEl = document.getElementById('rowCount');

    function applyFilter() {
        const q = search.value.toLowerCase();
        const s = filter.value.toLowerCase();
        const from = dateFrom.value;
        const to = dateTo.value;
        let visible = 0;

        rows.forEach(row => {
            if (row.children.length < 8) { row.style.display = ''; return; }

            const text = row.textContent.toLowerCase();
            const statusBadge = row.cells[6]?.textContent.trim().toLowerCase() || '';
            const date = row.cells[3]?.dataset.date || '';
            const matchText   = !q || text.includes(q);
            const matchStatus = !s || statusBadge === s;
            const matchDate = (!from || date >= from) && (!to || date <= to);

            const show = matchText && matchStatus && matchDate;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countEl) countEl.textContent = visible;
        const params = new URLSearchParams();
        if (q) params.set('q', search.value.trim());
        if (filter.value) params.set('status', filter.value);
        if (from) params.set('dari', from);
        if (to) params.set('sampai', to);
        const queryString = params.toString();
        if (exportLink) {
            exportLink.href = 'ekspor_peminjaman.php' + (queryString ? '?' + queryString : '');
        }
    }

    search.addEventListener('input', applyFilter);
    filter.addEventListener('change', applyFilter);
    dateFrom.addEventListener('change', applyFilter);
    dateTo.addEventListener('change', applyFilter);

    document.getElementById('modalAksi').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('aksiId').value     = btn.getAttribute('data-id');
        document.getElementById('aksiStatus').value = btn.getAttribute('data-status');
        document.getElementById('aksiLabel').textContent  = btn.getAttribute('data-label');
        document.getElementById('aksiNama').textContent   = btn.getAttribute('data-nama');
        document.getElementById('aksiBarang').textContent = btn.getAttribute('data-barang');
    });

    document.getElementById('modalHapus').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('hapusId').value          = btn.getAttribute('data-id');
        document.getElementById('hapusNama').textContent  = btn.getAttribute('data-nama');
        document.getElementById('hapusBarang').textContent = btn.getAttribute('data-barang');
    });
})();
</script>
</body>
</html>