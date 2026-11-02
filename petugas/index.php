<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['petugas']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tanggal_indonesia($tanggal): string
{
    if (!$tanggal || $tanggal === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($tanggal);
    if ($timestamp === false) {
        return '-';
    }

    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

$data_peminjaman = [];
$error_message = null;

try {
    $stmt = $pdo->query(
        'SELECT p.id, u.nama AS nama_peminjam, b.nama_barang,
                p.tanggal_pinjam, p.tanggal_kembali, p.jumlah_pinjam, p.status
         FROM peminjaman p
         JOIN users u ON p.user_id = u.id
         JOIN barang b ON p.barang_id = b.id
         ORDER BY p.id DESC'
    );
    $data_peminjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR staff transaction query failed: ' . $e->getMessage());
    $error_message = 'Data transaksi belum dapat dimuat. Silakan muat ulang halaman.';
}

$total = count($data_peminjaman);
$pending = 0;
$disetujui = 0;
$dikembalikan = 0;
$ditolak = 0;
foreach ($data_peminjaman as $transaksi) {
    switch ($transaksi['status']) {
        case 'Pending':
            $pending++;
            break;
        case 'Disetujui':
            $disetujui++;
            break;
        case 'Dikembalikan':
            $dikembalikan++;
            break;
        case 'Ditolak':
            $ditolak++;
            break;
    }
}

$status_class = [
    'Pending' => 'pending',
    'Disetujui' => 'approved',
    'Ditolak' => 'rejected',
    'Dikembalikan' => 'returned',
];
$pesan_map = [
    'sukses' => ['success', 'Status transaksi berhasil diperbarui.'],
    'tidak_ada' => ['warning', 'Transaksi tidak ditemukan atau tidak berubah.'],
    'invalid' => ['error', 'Permintaan perubahan status tidak valid.'],
    'gagal' => ['error', 'Terjadi kesalahan saat memperbarui transaksi.'],
    'csrf' => ['error', 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.'],
];
$pesan_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$pesan_info = $pesan_map[$pesan_key] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pantau pengajuan dan kelola transaksi peminjaman SIMBAR.">
    <title>Dashboard Petugas — SIMBAR</title>
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

        a { color: inherit; text-decoration: none; }

        .container {
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
        }

        .topbar {
            background: var(--brand);
            color: var(--white);
            box-shadow: 0 8px 24px rgba(16, 86, 83, .16);
        }

        .topbar-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 12px;
            background: rgba(255,255,255,.12);
        }

        .brand-mark svg { width: 22px; height: 22px; }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 13px;
            color: rgba(255,255,255,.88);
            font-size: 13px;
        }

        .logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 15px;
            border: 1px solid rgba(255,255,255,.65);
            border-radius: 9px;
            background: var(--white);
            color: var(--brand);
            font-size: 13px;
            font-weight: 700;
        }

        .logout:hover { background: var(--mint); }
        .nav-links { display: flex; align-items: center; gap: 7px; }
        .nav-links a {
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 11px;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 9px;
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
        }
        .nav-links a:hover { background: rgba(255,255,255,.12); }

        main { padding: 38px 0 58px; }

        .welcome {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .eyebrow {
            margin: 0 0 7px;
            color: var(--brand);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        h1, h2, h3, p { margin-top: 0; }

        h1 {
            margin-bottom: 6px;
            font-size: clamp(27px, 4vw, 36px);
            line-height: 1.2;
            letter-spacing: -.045em;
        }

        .welcome-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .summary-card {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 96px;
            overflow: hidden;
            padding: 17px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045);
        }

        .summary-card::before {
            position: absolute;
            inset: 0 auto 0 0;
            width: 3px;
            background: var(--brand);
            content: "";
        }

        .summary-card.pending-card::before { background: #d29a2e; }
        .summary-card.approved-card::before { background: #43835a; }
        .summary-card.returned-card::before { background: #146b68; }

        .summary-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 750;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;
            font-size: 24px;
            font-weight: 750;
            line-height: 1.1;
        }

        .summary-icon {
            width: 40px;
            height: 40px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 12px;
            background: var(--mint);
            color: var(--brand);
            font-size: 18px;
        }

        .pending-card .summary-icon { background: #fff3da; color: #95620e; }
        .approved-card .summary-icon { background: #e9f5ed; color: #34734c; }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 19px;
            padding: 12px 14px;
            border: 1px solid #f1caca;
            border-radius: 10px;
            background: #fff3f2;
            color: #a43737;
            font-size: 13px;
        }

        .alert.success {
            border-color: #c9e6d3;
            background: #eff9f1;
            color: #28643a;
        }

        .alert strong { display: block; margin-bottom: 1px; }

        .panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
        }

        .panel-header h2 {
            margin: 0 0 3px;
            font-size: 17px;
            letter-spacing: -.02em;
        }

        .panel-header p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .filters {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(145px, 190px) repeat(2, minmax(125px, 145px)) 110px 105px;
            gap: 10px;
            padding: 15px 20px;
            border-bottom: 1px solid var(--line);
            background: #fdfdfb;
        }

        .filters input,
        .filters select {
            width: 100%;
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid #dfe5e3;
            border-radius: 9px;
            outline: none;
            background: var(--white);
            color: var(--ink);
            font: inherit;
            font-size: 12px;
        }

        .filters input:focus,
        .filters select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(20, 107, 104, .12);
        }

        .refresh {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--white);
            color: var(--muted);
            font: inherit;
            font-size: 12px;
            font-weight: 700;
        }

        .refresh:hover { border-color: var(--brand); background: var(--mint); color: var(--brand-dark); }
        .table-wrap { overflow-x: auto; }
        tr[hidden] { display: none !important; }
        table { width: 100%; border-collapse: collapse; text-align: left; }

        th {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            background: #f5f8f5;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 13px 14px;
            border-bottom: 1px solid #eef1ed;
            color: var(--ink);
            font-size: 12px;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #f8faf8; }
        .borrower { display: flex; align-items: center; gap: 9px; font-weight: 700; }

        .avatar {
            width: 32px;
            height: 32px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 50%;
            background: var(--mint);
            color: var(--brand);
            font-size: 12px;
            font-weight: 800;
        }

        .item-name { font-weight: 650; }
        .date-subtitle { display: block; margin-top: 2px; color: var(--muted); font-size: 10px; }
        .quantity { font-weight: 700; text-align: center; }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
            white-space: nowrap;
        }

        .status.pending { background: #fff3da; color: #95620e; }
        .status.approved { background: #e9f5ed; color: #34734c; }
        .status.rejected { background: #fff0ef; color: #a43737; }
        .status.returned { background: var(--mint); color: var(--brand-dark); }

        .actions { display: flex; justify-content: center; gap: 6px; }

        .action-button {
            min-width: 34px;
            min-height: 34px;
            display: inline-grid;
            place-items: center;
            padding: 0 8px;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
            font-size: 11px;
            font-weight: 700;
            transition: transform .15s ease, background .15s ease;
        }

        .action-button:hover { transform: translateY(-1px); }
        .action-button.approve { background: #e9f5ed; color: #34734c; }
        .action-button.reject { background: #fff0ef; color: #a43737; }
        .action-button.return { background: var(--mint); color: var(--brand-dark); }
        .action-button.approve:hover { background: #d9eedf; }
        .action-button.reject:hover { background: #ffe1df; }
        .action-button.return:hover { background: #d8eee4; }

        .empty-state {
            padding: 42px 20px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .empty-icon {
            width: 45px;
            height: 45px;
            display: grid;
            place-items: center;
            margin: 0 auto 10px;
            border-radius: 14px;
            background: var(--mint);
            color: var(--brand);
            font-size: 20px;
        }

        .table-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 11px;
        }

        .modal-backdrop {
            position: fixed;
            z-index: 10;
            inset: 0;
            display: none;
            place-items: center;
            padding: 20px;
            background: rgba(23, 42, 58, .48);
        }

        .modal-backdrop.open { display: grid; }

        .modal {
            width: min(440px, 100%);
            padding: 23px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 24px 70px rgba(29, 55, 62, .22);
        }

        .modal h2 { margin: 0 0 8px; font-size: 19px; }
        .modal p { margin: 0 0 17px; color: var(--muted); font-size: 13px; }
        .modal-summary { margin-bottom: 18px; padding: 13px; border-radius: 10px; background: var(--paper); font-size: 12px; }
        .modal-summary strong { display: block; }

        .modal-actions { display: flex; justify-content: flex-end; gap: 9px; }

        .modal-actions button {
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--white);
            color: var(--muted);
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
        }

        .modal-actions .confirm {
            border-color: var(--brand);
            background: var(--brand);
            color: var(--white);
        }

        .modal-actions .confirm.danger { border-color: #ad3b38; background: #ad3b38; }
        .page-footer { padding: 20px 0; color: var(--muted); font-size: 11px; text-align: center; }

        :focus-visible { outline: 3px solid rgba(20, 107, 104, .28); outline-offset: 2px; }

        @media (max-width: 900px) {
            .summary { grid-template-columns: repeat(2, 1fr); }
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters input[type="search"] { grid-column: 1 / -1; }
        }

        @media (max-width: 680px) {
            .container { width: min(100% - 28px, 1120px); }
            .topbar-inner { min-height: 64px; flex-wrap: wrap; padding: 10px 0; }
            .topbar-user { width: 100%; justify-content: space-between; gap: 8px; font-size: 11px; }
            .logout { min-height: 35px; padding: 0 11px; }
            .nav-links a { min-height: 33px; padding: 0 8px; font-size: 10px; }
            main { padding: 28px 0 42px; }
            .summary { gap: 9px; }
            .summary-card { min-height: 82px; padding: 13px; }
            .summary-icon { width: 34px; height: 34px; font-size: 15px; }
            .summary-value { font-size: 21px; }
            .summary-label { font-size: 9px; }
            .panel-header { align-items: flex-start; flex-direction: column; padding: 16px; }
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 13px 16px; }
            .filters input[type="search"] { grid-column: 1 / -1; }
            .refresh { width: 100%; }
            .table-wrap { overflow: visible; }
            .table-wrap table { display: block; }
            .table-wrap thead { display: none; }
            .table-wrap tbody { display: grid; gap: 10px; padding: 10px; }
            .table-wrap tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 8px 10px;
                border: 1px solid var(--line);
                border-radius: 11px;
                background: var(--white);
            }

            @media (max-width: 390px) {
                .user-name { max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .topbar-user { gap: 5px; }
                .nav-links { gap: 4px; }
                .nav-links a { padding-inline: 8px; }
            }
            .table-wrap tbody td {
                display: flex;
                flex-direction: column;
                gap: 3px;
                padding: 7px 4px;
                border: 0;
            }
            .table-wrap tbody td::before {
                color: var(--muted);
                content: attr(data-label);
                font-size: 9px;
                font-weight: 800;
                letter-spacing: .05em;
                text-transform: uppercase;
            }
            .table-wrap tbody td:first-child,
            .table-wrap tbody td:nth-child(2),
            .table-wrap tbody td:last-child { grid-column: 1 / -1; }
            .actions { justify-content: flex-start; flex-wrap: wrap; }
            .action-button { min-height: 38px; }
            .table-wrap tbody tr.empty-row { display: block; }
            .table-wrap tbody tr.empty-row td { display: block; }
        }

        @media (max-width: 390px) {
            .topbar-user .user-name { display: none; }
            .nav-links a .nav-label { display: none; }
            .summary-icon { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="index.php" aria-label="SIMBAR, dashboard petugas">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 8.5 12 4l8 4.5v9L12 22l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="m4.5 8.8 7.5 4.3 7.5-4.3M12 13.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>SIMBAR Petugas</span>
            </a>
            <div class="topbar-user">
                <span class="user-name">Halo, <?= h($_SESSION['nama'] ?? 'Petugas') ?></span>
                <nav class="nav-links" aria-label="Navigasi petugas">
                    <a href="../admin/barang.php" aria-label="Inventaris"><span aria-hidden="true">▦</span><span class="nav-label">&nbsp; Inventaris</span></a>
                    <a href="../akun.php" aria-label="Profil"><span aria-hidden="true">◉</span><span class="nav-label">&nbsp; Profil</span></a>
                </nav>
                <a class="logout" href="../logout.php">Keluar</a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="welcome" aria-labelledby="page-title">
            <div>
                <p class="eyebrow">Area petugas</p>
                <h1 id="page-title">Transaksi peminjaman</h1>
                <p class="welcome-copy">Tinjau pengajuan, perbarui status, dan pantau pengembalian barang.</p>
            </div>
        </section>

        <?php if ($pesan_info): ?>
            <div class="alert<?= $pesan_info[0] === 'success' ? ' success' : '' ?>" role="alert">
                <span aria-hidden="true"><?= $pesan_info[0] === 'success' ? '✓' : '!' ?></span>
                <span><strong><?= $pesan_info[0] === 'success' ? 'Berhasil' : 'Perlu diperiksa' ?></strong><?= h($pesan_info[1]) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert" role="alert">
                <span aria-hidden="true">!</span>
                <span><strong>Data belum tersedia</strong><?= h($error_message) ?></span>
            </div>
        <?php endif; ?>

        <section class="summary" aria-label="Ringkasan transaksi">
            <article class="summary-card">
                <div><span class="summary-label">Total transaksi</span><strong class="summary-value"><?= $total ?></strong></div>
                <span class="summary-icon" aria-hidden="true">▤</span>
            </article>
            <article class="summary-card pending-card">
                <div><span class="summary-label">Menunggu</span><strong class="summary-value"><?= $pending ?></strong></div>
                <span class="summary-icon" aria-hidden="true">◷</span>
            </article>
            <article class="summary-card approved-card">
                <div><span class="summary-label">Disetujui</span><strong class="summary-value"><?= $disetujui ?></strong></div>
                <span class="summary-icon" aria-hidden="true">✓</span>
            </article>
            <article class="summary-card returned-card">
                <div><span class="summary-label">Dikembalikan</span><strong class="summary-value"><?= $dikembalikan ?></strong></div>
                <span class="summary-icon" aria-hidden="true">↩</span>
            </article>
        </section>

        <section class="panel" aria-labelledby="transactions-title">
            <div class="panel-header">
                <div>
                    <h2 id="transactions-title">Daftar transaksi</h2>
                    <p>Gunakan pencarian atau filter status untuk menemukan transaksi.</p>
                </div>
            </div>
            <div class="filters">
                <input type="search" id="searchInput" aria-label="Cari peminjam atau barang" placeholder="Cari nama peminjam atau barang...">
                <select id="statusFilter" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="Pending">Pending</option>
                    <option value="Disetujui">Disetujui</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="Dikembalikan">Dikembalikan</option>
                </select>
                <input type="date" id="dateFrom" aria-label="Tanggal pinjam dari" title="Tanggal pinjam dari">
                <input type="date" id="dateTo" aria-label="Tanggal pinjam sampai" title="Tanggal pinjam sampai">
                <a class="refresh export-link" href="../admin/ekspor_peminjaman.php" id="exportLink">Ekspor CSV</a>
                <a class="refresh" href="index.php">Muat ulang</a>
            </div>

            <?php if (empty($data_peminjaman)): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">▤</span>
                    Belum ada transaksi peminjaman.
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table id="transactionTable">
                        <thead>
                            <tr>
                                <th>Peminjam</th>
                                <th>Barang</th>
                                <th>Tanggal pinjam</th>
                                <th>Tanggal kembali</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data_peminjaman as $transaksi): ?>
                                <?php
                                $status = $transaksi['status'];
                                $status_css = $status_class[$status] ?? 'pending';
                                $inisial = function_exists('mb_substr')
                                    ? mb_substr($transaksi['nama_peminjam'], 0, 1, 'UTF-8')
                                    : substr($transaksi['nama_peminjam'], 0, 1);
                                ?>
                                <tr>
                                    <td data-label="Peminjam">
                                        <div class="borrower">
                                            <span class="avatar"><?= h(function_exists('mb_strtoupper') ? mb_strtoupper($inisial, 'UTF-8') : strtoupper($inisial)) ?></span>
                                            <span><?= h($transaksi['nama_peminjam']) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Barang"><span class="item-name"><?= h($transaksi['nama_barang']) ?></span></td>
                                    <td data-label="Tanggal pinjam" data-date="<?= h($transaksi['tanggal_pinjam']) ?>"><?= h(tanggal_indonesia($transaksi['tanggal_pinjam'])) ?></td>
                                    <td data-label="Tanggal kembali" data-date="<?= h($transaksi['tanggal_kembali']) ?>"><?= h(tanggal_indonesia($transaksi['tanggal_kembali'])) ?></td>
                                    <td class="quantity" data-label="Jumlah"><?= (int)$transaksi['jumlah_pinjam'] ?></td>
                                    <td data-label="Status"><span class="status <?= h($status_css) ?>"><?= h($status) ?></span></td>
                                    <td data-label="Aksi">
                                        <div class="actions">
                                            <?php if ($status === 'Pending'): ?>
                                                <button class="action-button approve" type="button" title="Setujui pengajuan"
                                                    data-action-status="Disetujui" data-action-label="menyetujui"
                                                    data-id="<?= (int)$transaksi['id'] ?>"
                                                    data-nama="<?= h($transaksi['nama_peminjam']) ?>"
                                                    data-barang="<?= h($transaksi['nama_barang']) ?>">Setujui</button>
                                                <button class="action-button reject" type="button" title="Tolak pengajuan"
                                                    data-action-status="Ditolak" data-action-label="menolak"
                                                    data-id="<?= (int)$transaksi['id'] ?>"
                                                    data-nama="<?= h($transaksi['nama_peminjam']) ?>"
                                                    data-barang="<?= h($transaksi['nama_barang']) ?>">Tolak</button>
                                            <?php elseif ($status === 'Disetujui'): ?>
                                                <button class="action-button return" type="button" title="Tandai barang dikembalikan"
                                                    data-action-status="Dikembalikan" data-action-label="menandai barang sebagai dikembalikan"
                                                    data-id="<?= (int)$transaksi['id'] ?>"
                                                    data-nama="<?= h($transaksi['nama_peminjam']) ?>"
                                                    data-barang="<?= h($transaksi['nama_barang']) ?>">Dikembalikan</button>
                                            <?php else: ?>
                                                <span class="date-subtitle">Selesai</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="table-footer">Menampilkan <strong id="rowCount"><?= $total ?></strong> transaksi</div>
        </section>
    </main>

    <footer class="page-footer">SIMBAR · Sistem Inventaris &amp; Peminjaman Barang</footer>

    <div class="modal-backdrop" id="actionModal" role="presentation">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDescription">
            <h2 id="modalTitle">Konfirmasi tindakan</h2>
            <p id="modalDescription">Pastikan detail transaksi sudah benar sebelum melanjutkan.</p>
            <div class="modal-summary">
                <strong id="modalBorrower"></strong>
                <span id="modalItem"></span>
            </div>
            <form method="post" action="../admin/aksi_status.php" id="actionForm">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" id="actionId">
                <input type="hidden" name="status" id="actionStatus">
                <div class="modal-actions">
                    <button type="button" id="cancelAction">Batal</button>
                    <button type="submit" class="confirm" id="confirmAction">Ya, lanjutkan</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        (function () {
            const search = document.getElementById('searchInput');
            const filter = document.getElementById('statusFilter');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const exportLink = document.getElementById('exportLink');
            const table = document.getElementById('transactionTable');
            const count = document.getElementById('rowCount');

            function applyFilters() {
                if (!table) return;

                const query = search.value.trim().toLocaleLowerCase('id');
                const status = filter.value.toLocaleLowerCase('id');
                const from = dateFrom.value;
                const to = dateTo.value;
                let visible = 0;

                table.querySelectorAll('tbody tr').forEach((row) => {
                    const rowStatus = row.cells[5].textContent.trim().toLocaleLowerCase('id');
                    const date = row.cells[2].dataset.date || '';
                    const matches = row.textContent.toLocaleLowerCase('id').includes(query)
                        && (!status || rowStatus === status)
                        && (!from || date >= from)
                        && (!to || date <= to);
                    row.hidden = !matches;
                    if (matches) visible++;
                });

                count.textContent = String(visible);
                const params = new URLSearchParams();
                if (query) params.set('q', search.value.trim());
                if (filter.value) params.set('status', filter.value);
                if (from) params.set('dari', from);
                if (to) params.set('sampai', to);
                const queryString = params.toString();
                exportLink.href = '../admin/ekspor_peminjaman.php' + (queryString ? '?' + queryString : '');
            }

            search.addEventListener('input', applyFilters);
            filter.addEventListener('change', applyFilters);
            dateFrom.addEventListener('change', applyFilters);
            dateTo.addEventListener('change', applyFilters);

            const modal = document.getElementById('actionModal');
            const confirm = document.getElementById('confirmAction');
            let lastTrigger = null;

            document.querySelectorAll('[data-action-status]').forEach((button) => {
                button.addEventListener('click', () => {
                    lastTrigger = button;
                    const status = button.dataset.actionStatus;
                    const isReject = status === 'Ditolak';

                    document.getElementById('modalTitle').textContent = isReject
                        ? 'Tolak pengajuan peminjaman?'
                        : 'Konfirmasi perubahan status';
                    document.getElementById('modalDescription').textContent =
                        'Kamu akan ' + button.dataset.actionLabel + ' transaksi ini.';
                    document.getElementById('modalBorrower').textContent = button.dataset.nama;
                    document.getElementById('modalItem').textContent = button.dataset.barang;
                    document.getElementById('actionId').value = button.dataset.id;
                    document.getElementById('actionStatus').value = status;
                    confirm.classList.toggle('danger', isReject);
                    modal.classList.add('open');
                    confirm.focus();
                });
            });

            function closeModal() {
                modal.classList.remove('open');
                if (lastTrigger) lastTrigger.focus();
            }

            document.getElementById('cancelAction').addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
            });
        })();
    </script>
</body>
</html>
