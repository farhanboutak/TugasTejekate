<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['peminjam']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (
        !is_string($token) ||
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        header('Location: index.php?pesan=csrf');
        exit;
    }

    $barang_id_input = $_POST['barang_id'] ?? '';
    $jumlah_input = $_POST['jumlah_pinjam'] ?? '';
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';

    $barang_id = is_string($barang_id_input) && ctype_digit($barang_id_input) ? (int)$barang_id_input : 0;
    $jumlah = is_string($jumlah_input) && ctype_digit($jumlah_input) ? (int)$jumlah_input : 0;
    $tanggal_valid = false;

    if (is_string($tanggal_pinjam) && is_string($tanggal_kembali)) {
        $pinjam_date = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_pinjam);
        $kembali_date = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_kembali);
        $tanggal_valid = $pinjam_date !== false
            && $kembali_date !== false
            && $pinjam_date->format('Y-m-d') === $tanggal_pinjam
            && $kembali_date->format('Y-m-d') === $tanggal_kembali
            && $tanggal_pinjam >= date('Y-m-d')
            && $tanggal_kembali > $tanggal_pinjam;
    }

    if ($barang_id <= 0 || $jumlah <= 0 || !$tanggal_valid) {
        header('Location: index.php?pesan=invalid');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, stok FROM barang WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $barang_id]);
        $barang = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$barang) {
            $pdo->rollBack();
            header('Location: index.php?pesan=barang_tidak_ada');
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(jumlah_pinjam), 0)
             FROM peminjaman
             WHERE barang_id = :barang_id AND status IN ('Pending', 'Disetujui')"
        );
        $stmt->execute([':barang_id' => $barang_id]);
        $jumlah_dipesan = (int)$stmt->fetchColumn();
        $stok_tersedia = max(0, (int)$barang['stok'] - $jumlah_dipesan);

        if ($jumlah > $stok_tersedia) {
            $pdo->rollBack();
            header('Location: index.php?pesan=stok_tidak_cukup');
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO peminjaman
                (user_id, barang_id, tanggal_pinjam, tanggal_kembali, jumlah_pinjam, status)
             VALUES
                (:user_id, :barang_id, :tanggal_pinjam, :tanggal_kembali, :jumlah_pinjam, 'Pending')"
        );
        $stmt->execute([
            ':user_id' => (int)$_SESSION['user_id'],
            ':barang_id' => $barang_id,
            ':tanggal_pinjam' => $tanggal_pinjam,
            ':tanggal_kembali' => $tanggal_kembali,
            ':jumlah_pinjam' => $jumlah,
        ]);

        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: index.php?pesan=sukses');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('SIMBAR borrowing request failed: ' . $e->getMessage());
        header('Location: index.php?pesan=gagal');
        exit;
    }
}

$pesan_map = [
    'sukses' => ['success', 'Pengajuan berhasil dikirim. Status pengajuan saat ini: Pending.'],
    'csrf' => ['error', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'invalid' => ['error', 'Data pengajuan tidak valid. Periksa barang, jumlah, dan tanggal.'],
    'barang_tidak_ada' => ['error', 'Barang yang dipilih tidak ditemukan. Silakan pilih barang lain.'],
    'stok_tidak_cukup' => ['error', 'Stok tersedia tidak mencukupi untuk jumlah yang diminta.'],
    'gagal' => ['error', 'Pengajuan belum berhasil disimpan. Silakan coba kembali.'],
];
$pesan_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$pesan_info = $pesan_map[$pesan_key] ?? null;

$barang_list = [];
$riwayat = [];
$error_message = null;
$nama_peminjam = $_SESSION['nama'] ?? 'Peminjam';
$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->query(
        "SELECT b.id, b.nama_barang, b.kategori, b.kondisi, b.stok, b.foto,
                GREATEST(
                    b.stok - COALESCE(SUM(
                        CASE WHEN p.status IN ('Pending', 'Disetujui')
                        THEN p.jumlah_pinjam ELSE 0 END
                    ), 0),
                    0
                ) AS stok_tersedia
         FROM barang b
         LEFT JOIN peminjaman p ON p.barang_id = b.id
         GROUP BY b.id, b.nama_barang, b.kategori, b.kondisi, b.stok, b.foto
         ORDER BY b.nama_barang ASC"
    );
    $barang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT p.id, b.nama_barang, p.tanggal_pinjam, p.tanggal_kembali,
                p.jumlah_pinjam, p.status
         FROM peminjaman p
         JOIN barang b ON b.id = p.barang_id
         WHERE p.user_id = :user_id
         ORDER BY p.id DESC'
    );
    $stmt->execute([':user_id' => $user_id]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR borrowing page query failed: ' . $e->getMessage());
    $error_message = 'Data barang atau riwayat belum dapat dimuat. Silakan muat ulang halaman.';
}

$jumlah_pending = 0;
$jumlah_aktif = 0;
$pembaruan = [];
foreach ($riwayat as $transaksi) {
    if ($transaksi['status'] === 'Pending') {
        $jumlah_pending++;
    } else {
        $pembaruan[] = $transaksi;
    }
    if (in_array($transaksi['status'], ['Pending', 'Disetujui'], true)) {
        $jumlah_aktif++;
    }
}
$pembaruan = array_slice($pembaruan, 0, 5);

$barang_tersedia = array_values(array_filter(
    $barang_list,
    static fn(array $barang): bool => (int)$barang['stok_tersedia'] > 0
));

$status_class = [
    'Pending' => 'pending',
    'Disetujui' => 'approved',
    'Ditolak' => 'rejected',
    'Dikembalikan' => 'returned',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ajukan dan pantau peminjaman barang SIMBAR.">
    <title>Peminjaman — SIMBAR</title>
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

        main { padding: 38px 0 60px; }

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
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 100px;
            padding: 19px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045);
        }

        .summary-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;
            font-size: 25px;
            font-weight: 750;
            line-height: 1.1;
        }

        .summary-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--mint);
            color: var(--brand);
        }

        .summary-icon svg { width: 21px; height: 21px; }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(280px, .75fr) minmax(0, 1.25fr);
            align-items: start;
            gap: 20px;
        }

        .catalog-section { margin: 0 0 24px; }
        .catalog-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 13px; }
        .catalog-heading h2 { margin: 0 0 3px; font-size: 19px; letter-spacing: -.025em; }
        .catalog-heading p { margin: 0; color: var(--muted); font-size: 12px; }
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(205px, 1fr)); gap: 14px; }
        .product-card { overflow: hidden; border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: 0 8px 24px rgba(29,55,62,.045); transition: transform .18s ease, box-shadow .18s ease; }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(29,55,62,.09); }
        .product-image { aspect-ratio: 16 / 10; display: grid; place-items: center; overflow: hidden; background: linear-gradient(135deg, #e8f4ef, #f8faf8); color: var(--brand); }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-image .placeholder { font-size: 36px; opacity: .7; }
        .product-copy { padding: 14px; }
        .product-category { display: block; margin-bottom: 4px; color: var(--brand); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .product-copy h3 { margin: 0 0 9px; font-size: 15px; line-height: 1.35; }
        .product-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; color: var(--muted); font-size: 11px; }
        .product-condition { padding: 3px 7px; border-radius: 99px; background: #f1f5f2; white-space: nowrap; }
        .product-button { width: 100%; min-height: 38px; border: 1px solid var(--brand); border-radius: 8px; background: var(--white); color: var(--brand); cursor: pointer; font: inherit; font-size: 12px; font-weight: 750; }
        .product-button:hover { background: var(--brand); color: var(--white); }
        .product-button:disabled { border-color: var(--line); background: #f4f5f3; color: var(--muted); cursor: not-allowed; }

        .panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045);
        }

        .panel-heading {
            padding: 20px 22px 16px;
            border-bottom: 1px solid var(--line);
        }

        .panel-heading h2 {
            margin: 0 0 4px;
            font-size: 17px;
            letter-spacing: -.02em;
        }

        .panel-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .panel-body { padding: 21px 22px 23px; }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 20px;
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

        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            margin-bottom: 6px;
            color: #314556;
            font-size: 12px;
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 44px;
            padding: 0 12px;
            border: 1px solid #dfe5e3;
            border-radius: 9px;
            outline: none;
            background: var(--white);
            color: var(--ink);
            font: inherit;
            font-size: 13px;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(20, 107, 104, .12);
        }

        .field-hint {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .date-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px;
        }

        .submit-button {
            width: 100%;
            min-height: 46px;
            margin-top: 3px;
            border: 0;
            border-radius: 9px;
            background: var(--brand);
            color: var(--white);
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 750;
            transition: background .2s ease, transform .2s ease;
        }

        .submit-button:hover {
            transform: translateY(-1px);
            background: var(--brand-dark);
        }

        .submit-button:focus-visible,
        .logout:focus-visible,
        .field input:focus-visible,
        .field select:focus-visible {
            outline: 3px solid rgba(20, 107, 104, .25);
            outline-offset: 2px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

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
        .item-name { font-weight: 700; }
        .item-subtitle { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; }
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

        .empty-state {
            padding: 36px 20px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .empty-icon {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            margin: 0 auto 10px;
            border-radius: 14px;
            background: var(--mint);
            color: var(--brand);
            font-size: 20px;
        }

        .updates {
            margin-bottom: 22px;
            border: 1px solid #cfe5da;
            border-radius: 15px;
            background: linear-gradient(110deg, #f1f8f3, #fff);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .035);
        }

        .updates-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 18px 10px;
        }

        .updates-heading .summary-icon { width: 35px; height: 35px; flex: 0 0 auto; }
        .updates-heading h2 { margin: 0 0 2px; font-size: 14px; }
        .updates-heading p { margin: 0; color: var(--muted); font-size: 11px; }
        .updates-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; padding: 0 18px 15px; }
        .update-item { padding: 10px 11px; border: 1px solid rgba(20,107,104,.1); border-radius: 10px; background: rgba(255,255,255,.82); font-size: 11px; }
        .update-item strong { display: block; margin-bottom: 3px; }
        .update-item span { color: var(--muted); }

        .page-footer {
            padding: 22px 0;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 860px) {
            .content-grid { grid-template-columns: 1fr; }
            .catalog-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
        }

        @media (max-width: 600px) {
            .container { width: min(100% - 28px, 1120px); }
            .topbar-inner { min-height: 64px; }
            .topbar-user { gap: 8px; font-size: 11px; }
            .logout { min-height: 35px; padding: 0 11px; }
            main { padding: 27px 0 42px; }
            .welcome { align-items: flex-start; flex-direction: column; }
            .catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
            .product-copy { padding: 11px; }
            .product-copy h3 { font-size: 13px; }
            .product-meta { align-items: flex-start; flex-direction: column; gap: 5px; }
            .summary { gap: 9px; }
            .summary-card { min-height: 85px; padding: 13px; }
            .summary-icon { width: 35px; height: 35px; }
            .summary-value { font-size: 21px; }
            .summary-label { font-size: 9px; }
            .panel-heading { padding: 17px 17px 14px; }
            .panel-body { padding: 17px; }
            .table-wrap { overflow: visible; }
            .table-wrap table { display: block; }
            .table-wrap thead { display: none; }
            .table-wrap tbody { display: grid; gap: 9px; padding: 10px; }
            .table-wrap tbody tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 8px 10px; border: 1px solid var(--line); border-radius: 11px; background: var(--white); }
            .table-wrap tbody td { display: flex; flex-direction: column; gap: 3px; padding: 7px 4px; border: 0; }
            .table-wrap tbody td::before { content: attr(data-label); color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
            .table-wrap tbody td:first-child,
            .table-wrap tbody td:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 390px) {
            .topbar-user .user-name { display: none; }
            .summary-icon { display: none; }
            .date-grid { grid-template-columns: 1fr; gap: 0; }
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
            <a class="brand" href="index.php" aria-label="SIMBAR, halaman peminjaman">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 8.5 12 4l8 4.5v9L12 22l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="m4.5 8.8 7.5 4.3 7.5-4.3M12 13.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>SIMBAR</span>
            </a>
            <div class="topbar-user">
                <span class="user-name">Halo, <?= h($nama_peminjam) ?></span>
                <a class="logout" href="../akun.php">Profil</a>
                <a class="logout" href="../logout.php">Keluar</a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="welcome" aria-labelledby="page-title">
            <div>
                <p class="eyebrow">Area peminjam</p>
                <h1 id="page-title">Peminjaman barang</h1>
                <p class="welcome-copy">Ajukan peminjaman dan pantau status transaksi kamu di sini.</p>
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

        <?php if ($pembaruan): ?>
            <section class="updates" aria-labelledby="updates-title">
                <div class="updates-heading">
                    <span class="summary-icon" aria-hidden="true">✓</span>
                    <div>
                        <h2 id="updates-title">Pembaruan pengajuan</h2>
                        <p>Status terbaru dari pengajuan peminjaman kamu.</p>
                    </div>
                </div>
                <div class="updates-list">
                    <?php foreach ($pembaruan as $item): ?>
                        <?php $class = $status_class[$item['status']] ?? 'pending'; ?>
                        <article class="update-item">
                            <strong><?= h($item['nama_barang']) ?> <span class="status <?= h($class) ?>"><?= h($item['status']) ?></span></strong>
                            <span><?= h(tanggal_indonesia($item['tanggal_pinjam'])) ?> · <?= (int)$item['jumlah_pinjam'] ?> unit</span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="summary" aria-label="Ringkasan peminjaman">
            <article class="summary-card">
                <div>
                    <span class="summary-label">Total pengajuan</span>
                    <strong class="summary-value"><?= count($riwayat) ?></strong>
                </div>
                <span class="summary-icon" aria-hidden="true">▤</span>
            </article>
            <article class="summary-card">
                <div>
                    <span class="summary-label">Menunggu persetujuan</span>
                    <strong class="summary-value"><?= $jumlah_pending ?></strong>
                </div>
                <span class="summary-icon" aria-hidden="true">◷</span>
            </article>
            <article class="summary-card">
                <div>
                    <span class="summary-label">Dalam proses</span>
                    <strong class="summary-value"><?= $jumlah_aktif ?></strong>
                </div>
                <span class="summary-icon" aria-hidden="true">↗</span>
            </article>
        </section>

        <section class="catalog-section" aria-labelledby="catalog-title">
            <div class="catalog-heading">
                <div>
                    <h2 id="catalog-title">Katalog barang</h2>
                    <p>Lihat foto, kondisi, dan ketersediaan sebelum mengajukan peminjaman.</p>
                </div>
            </div>
            <?php if ($error_message): ?>
                <div class="empty-state">Katalog barang belum dapat dimuat.</div>
            <?php elseif (!$barang_list): ?>
                <div class="empty-state">Belum ada barang di inventaris.</div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($barang_list as $barang): ?>
                        <?php $stok_kartu = (int)$barang['stok_tersedia']; ?>
                        <article class="product-card">
                            <div class="product-image">
                                <?php if (!empty($barang['foto'])): ?>
                                    <img src="../uploads/barang/<?= h($barang['foto']) ?>" alt="Foto <?= h($barang['nama_barang']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="placeholder" aria-hidden="true">▦</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-copy">
                                <span class="product-category"><?= h($barang['kategori']) ?></span>
                                <h3><?= h($barang['nama_barang']) ?></h3>
                                <div class="product-meta">
                                    <span><?= $stok_kartu ?> unit tersedia</span>
                                    <span class="product-condition"><?= h($barang['kondisi']) ?></span>
                                </div>
                                <button
                                    class="product-button"
                                    type="button"
                                    data-pilih-barang="<?= (int)$barang['id'] ?>"
                                    <?= $stok_kartu < 1 ? 'disabled' : '' ?>>
                                    <?= $stok_kartu > 0 ? 'Pilih barang' : 'Stok habis' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="content-grid">
            <section class="panel" aria-labelledby="form-title">
                <div class="panel-heading">
                    <h2 id="form-title">Ajukan peminjaman</h2>
                    <p>Pengajuan akan menunggu persetujuan admin.</p>
                </div>
                <div class="panel-body">
                    <?php if (!$error_message && empty($barang_tersedia)): ?>
                        <div class="empty-state">
                            <span class="empty-icon" aria-hidden="true">□</span>
                            Saat ini belum ada stok barang yang tersedia untuk dipinjam.
                        </div>
                    <?php elseif (!$error_message): ?>
                        <form method="post" action="index.php">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <div class="field">
                                <label for="barang_id">Pilih barang</label>
                                <select id="barang_id" name="barang_id" required>
                                    <option value="">Pilih barang yang ingin dipinjam</option>
                                    <?php foreach ($barang_tersedia as $barang): ?>
                                        <option value="<?= (int)$barang['id'] ?>">
                                            <?= h($barang['nama_barang']) ?> — <?= h($barang['kategori']) ?>
                                            (tersedia: <?= (int)$barang['stok_tersedia'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="jumlah_pinjam">Jumlah barang</label>
                                <input type="number" id="jumlah_pinjam" name="jumlah_pinjam" min="1" step="1" value="1" required>
                                <p class="field-hint">Jumlah akan diperiksa kembali berdasarkan stok yang tersedia.</p>
                            </div>
                            <div class="date-grid">
                                <div class="field">
                                    <label for="tanggal_pinjam">Tanggal pinjam</label>
                                    <input type="date" id="tanggal_pinjam" name="tanggal_pinjam" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="field">
                                    <label for="tanggal_kembali">Tanggal kembali</label>
                                    <input type="date" id="tanggal_kembali" name="tanggal_kembali" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                </div>
                            </div>
                            <button class="submit-button" type="submit">Kirim pengajuan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel" aria-labelledby="history-title">
                <div class="panel-heading">
                    <h2 id="history-title">Riwayat peminjaman</h2>
                    <p>Daftar transaksi peminjaman akun kamu.</p>
                </div>
                <?php if (empty($riwayat)): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">▤</span>
                        Belum ada riwayat peminjaman.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th>Tanggal</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat as $transaksi): ?>
                                    <?php
                                    $status = $transaksi['status'];
                                    $class = $status_class[$status] ?? 'pending';
                                    ?>
                                    <tr>
                                        <td data-label="Barang"><span class="item-name"><?= h($transaksi['nama_barang']) ?></span></td>
                                        <td data-label="Periode">
                                            <?= h(tanggal_indonesia($transaksi['tanggal_pinjam'])) ?>
                                            <span class="item-subtitle">s.d. <?= h(tanggal_indonesia($transaksi['tanggal_kembali'])) ?></span>
                                        </td>
                                        <td class="quantity" data-label="Jumlah"><?= (int)$transaksi['jumlah_pinjam'] ?></td>
                                        <td data-label="Status"><span class="status <?= h($class) ?>"><?= h($status) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="page-footer">
        <div class="container">SIMBAR · Sistem Inventaris &amp; Peminjaman Barang</div>
    </footer>
<script>
    document.querySelectorAll('[data-pilih-barang]').forEach((button) => {
        button.addEventListener('click', () => {
            const pilihan = document.getElementById('barang_id');
            if (!pilihan) return;
            pilihan.value = button.dataset.pilihBarang;
            pilihan.focus();
            pilihan.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
</script>
</body>
</html>
