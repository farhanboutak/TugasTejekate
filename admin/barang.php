<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['admin', 'petugas']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pesan_map = [
    'ditambahkan' => ['success', 'Barang berhasil ditambahkan.'],
    'diperbarui' => ['success', 'Data barang berhasil diperbarui.'],
    'diarsipkan' => ['success', 'Barang dipindahkan ke arsip. Riwayat dan fotonya tetap tersimpan.'],
    'dipulihkan' => ['success', 'Barang berhasil dipulihkan ke inventaris aktif.'],
    'sudah_diarsipkan' => ['success', 'Barang tersebut sudah berada di arsip.'],
    'sudah_aktif' => ['success', 'Barang tersebut sudah ada di inventaris aktif.'],
    'invalid' => ['error', 'Data barang tidak valid. Periksa kembali isian.'],
    'stok_dipesan' => ['error', 'Stok baru tidak boleh lebih kecil dari jumlah barang yang sedang dipesan.'],
    'tidak_ada' => ['error', 'Barang tidak ditemukan.'],
    'csrf' => ['error', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'gagal' => ['error', 'Perubahan belum berhasil disimpan. Silakan coba kembali.'],
    'foto_invalid' => ['error', 'Format foto tidak didukung. Gunakan gambar JPG, PNG, atau WebP.'],
    'foto_terlalu_besar' => ['error', 'Ukuran foto maksimal 5 MB.'],
    'foto_gagal' => ['error', 'Foto gagal diunggah. Silakan coba kembali.'],
    'foto_pilih_satu' => ['error', 'Pilih foto dari perangkat atau tautan, jangan keduanya.'],
    'foto_url_invalid' => ['error', 'Tautan foto tidak valid. Gunakan URL HTTPS langsung ke gambar publik.'],
    'foto_url_gagal' => ['error', 'Foto dari tautan tidak dapat diambil. Pastikan tautan HTTPS dapat diakses dan mengarah ke gambar.'],
];
$pesan_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$pesan_info = $pesan_map[$pesan_key] ?? null;

$semua_barang = [];
$barang_list = [];
$error_message = null;
$lihat_arsip = isset($_GET['tampilan']) && $_GET['tampilan'] === 'arsip';
$jumlah_aktif = 0;
$jumlah_arsip = 0;
try {
    $stmt = $pdo->query(
        "SELECT b.id, b.nama_barang, b.kategori, b.stok, b.kondisi, b.foto, b.diarsipkan,
                COALESCE(SUM(
                    CASE WHEN p.status IN ('Pending', 'Disetujui')
                    THEN p.jumlah_pinjam ELSE 0 END
                ), 0) AS stok_dipesan,
                COUNT(p.id) AS jumlah_transaksi
         FROM barang b
         LEFT JOIN peminjaman p ON p.barang_id = b.id
         GROUP BY b.id, b.nama_barang, b.kategori, b.stok, b.kondisi, b.foto, b.diarsipkan
         ORDER BY b.nama_barang ASC"
    );
    $semua_barang = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($semua_barang as $barang) {
        if ((int)$barang['diarsipkan'] === 1) {
            $jumlah_arsip++;
        } else {
            $jumlah_aktif++;
        }
    }
    $barang_list = array_values(array_filter(
        $semua_barang,
        static fn(array $barang): bool => (int)$barang['diarsipkan'] === ($lihat_arsip ? 1 : 0)
    ));
} catch (PDOException $e) {
    error_log('SIMBAR inventory list query failed: ' . $e->getMessage());
    $error_message = 'Daftar barang belum dapat dimuat. Silakan muat ulang halaman.';
}

$csrf = $_SESSION['csrf_token'];
$dashboard_url = $_SESSION['role'] === 'admin' ? 'index.php' : '../petugas/index.php';
$total_stok = 0;
$total_tersedia = 0;
$stok_menipis = 0;
foreach ($semua_barang as $barang) {
    if ((int)$barang['diarsipkan'] === 1) {
        continue;
    }
    $tersedia = max(0, (int)$barang['stok'] - (int)$barang['stok_dipesan']);
    $total_stok += (int)$barang['stok'];
    $total_tersedia += $tersedia;
    if ($tersedia <= 2) {
        $stok_menipis++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kelola data dan stok inventaris SIMBAR.">
    <title>Inventaris — SIMBAR</title>
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
            background: radial-gradient(ellipse at 12% 0, rgba(20,107,104,.07), transparent 34%), var(--paper);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.55;
        }
        .container { width: min(1120px, calc(100% - 40px)); margin: 0 auto; }
        .topbar { background: var(--brand); color: var(--white); box-shadow: 0 8px 24px rgba(16,86,83,.16); }
        .topbar-inner { min-height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; align-items: center; gap: 11px; color: var(--white); font-size: 16px; font-weight: 800; letter-spacing: .04em; text-decoration: none; }
        .brand-mark { width: 40px; height: 40px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.28); border-radius: 13px; background: linear-gradient(145deg, rgba(255,255,255,.2), rgba(255,255,255,.07)); box-shadow: inset 0 1px 0 rgba(255,255,255,.16), 0 5px 14px rgba(10,55,53,.15); }
        .brand-mark svg { width: 27px; height: 27px; display: block; }
        .brand-copy { display: grid; gap: 0; line-height: 1.15; }
        .brand-copy small { margin-top: 3px; color: rgba(255,255,255,.72); font-size: 9px; font-weight: 600; letter-spacing: .08em; }
        .top-actions { display: flex; align-items: center; gap: 7px; font-size: 11px; }
        .top-actions a { display: inline-flex; min-height: 36px; align-items: center; padding: 0 10px; border: 1px solid rgba(255,255,255,.5); border-radius: 8px; background: var(--white); color: var(--brand); font-weight: 700; text-decoration: none; white-space: nowrap; }
        .top-actions a:hover, .top-actions a[aria-current="page"] { background: var(--mint); }
        main { padding: 32px 0 55px; }
        .heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; padding: 22px 24px; border: 1px solid #d5e7df; border-radius: 17px; background: linear-gradient(115deg, #eff7f2, #fff 72%); }
        .eyebrow { margin: 0 0 5px; color: var(--brand); font-size: 10px; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        h1 { margin: 0 0 5px; font-size: clamp(26px,4vw,34px); letter-spacing: -.04em; }
        .muted { margin: 0; color: var(--muted); font-size: 13px; }
        .heading-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 9px; }
        .heading-actions .back { border: 1px solid #d5e7df; background: var(--white); color: var(--brand); }
        .heading-actions .back:hover { background: var(--mint); }
        .add-button { gap: 8px; min-height: 44px; padding: 0 17px; box-shadow: 0 6px 15px rgba(20,107,104,.16); }
        .add-button .plus { font-size: 19px; font-weight: 400; line-height: 1; }
        .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary-card { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 82px; padding: 15px 17px; border: 1px solid var(--line); border-radius: 13px; background: var(--white); box-shadow: 0 6px 18px rgba(29,55,62,.035); }
        .summary-card span { display: block; color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
        .summary-card strong { display: block; margin-top: 2px; font-size: 23px; line-height: 1.2; }
        .summary-icon { width: 38px; height: 38px; display: grid; flex: 0 0 auto; place-items: center; border-radius: 12px; background: var(--mint); color: var(--brand); font-size: 17px; }
        .summary-card .summary-icon { display: grid; }
        .panel { overflow: hidden; border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: 0 8px 24px rgba(29,55,62,.045); }
        .add-panel[hidden] { display: none; }
        .add-backdrop { position: fixed; z-index: 5; inset: 0; display: none; place-items: center; overflow-y: auto; padding: 20px; background: rgba(23,42,58,.52); backdrop-filter: blur(3px); }
        .add-backdrop.open { display: grid; }
        .add-dialog { width: min(620px, 100%); max-height: min(92vh, 850px); overflow-y: auto; border: 1px solid var(--line); border-radius: 17px; background: var(--white); box-shadow: 0 25px 80px rgba(23,42,58,.23); }
        .add-dialog .panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 15px; padding: 20px 22px; }
        .close-add { width: 36px; min-height: 36px; flex: 0 0 auto; padding: 0; border: 1px solid var(--line); background: var(--white); color: var(--muted); font-size: 19px; }
        .close-add:hover { background: #f3f6f3; color: var(--ink); }
        .add-dialog .panel-body { padding: 20px 22px 22px; }
        .panel-heading { padding: 17px 19px; border-bottom: 1px solid var(--line); }
        .panel-heading h2 { margin: 0 0 3px; font-size: 16px; }
        .panel-heading p { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 19px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 14px; }
        .field { min-width: 0; }
        .name-field, .photo-field, .form-actions { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; color: #314556; font-size: 12px; font-weight: 700; }
        input, select { width: 100%; min-height: 42px; padding: 0 11px; border: 1px solid #dfe5e3; border-radius: 9px; outline: none; background: var(--white); color: var(--ink); font: inherit; font-size: 13px; }
        input[type="file"] { height: auto; min-height: 42px; padding: 8px; }
        input:focus, select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(20,107,104,.12); }
        button, .back { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 14px; border: 0; border-radius: 9px; background: var(--brand); color: var(--white); cursor: pointer; font: inherit; font-size: 12px; font-weight: 750; text-decoration: none; }
        button:hover, .back:hover { background: var(--brand-dark); color: var(--white); }
        .photo-field { padding: 14px; border: 1px dashed #cbd9d3; border-radius: 12px; background: #f8faf8; }
        .photo-source { display: flex; gap: 7px; margin: 0 0 12px; padding: 4px; border: 1px solid var(--line); border-radius: 10px; background: var(--white); }
        .photo-source label { flex: 1; margin: 0; cursor: pointer; }
        .photo-source input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .photo-source span { min-height: 36px; display: flex; align-items: center; justify-content: center; gap: 7px; padding: 0 10px; border-radius: 7px; color: var(--muted); font-size: 11px; font-weight: 700; }
        .photo-source input:checked + span { background: var(--mint); color: var(--brand-dark); }
        .photo-source input:focus-visible + span { outline: 3px solid rgba(20,107,104,.25); outline-offset: 2px; }
        .photo-source-panel[hidden] { display: none; }
        .photo-url-note { margin: 6px 0 0; color: var(--muted); font-size: 10px; }
        .photo-field input[type="file"] { background: var(--white); }
        .photo-preview { width: 100%; height: 150px; display: grid; place-items: center; margin-top: 10px; overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: var(--white); color: var(--muted); font-size: 12px; }
        .photo-preview img { width: 100%; height: 100%; object-fit: contain; }
        .file-hint { display: block; margin-top: 5px; color: var(--muted); font-size: 10px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 8px; }
        .form-actions .secondary { border: 1px solid var(--line); background: var(--white); color: var(--muted); }
        .inventory-panel { margin-top: 20px; }
        .inventory-tabs { display: flex; gap: 6px; margin-bottom: 12px; }
        .inventory-tab { min-height: 36px; display: inline-flex; align-items: center; gap: 7px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: var(--white); color: var(--muted); font-size: 11px; font-weight: 700; text-decoration: none; }
        .inventory-tab:hover { border-color: var(--brand); color: var(--brand); }
        .inventory-tab.active { border-color: var(--brand); background: var(--mint); color: var(--brand-dark); }
        .inventory-tab-count { min-width: 20px; padding: 1px 5px; border-radius: 99px; background: rgba(23,42,58,.07); text-align: center; font-size: 10px; }
        .inventory-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .inventory-toolbar > div:first-child { min-width: 0; }
        .inventory-search { width: min(290px, 100%); min-height: 40px; }
        .inventory-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; padding: 17px; }
        .inventory-card { min-width: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 14px; background: var(--white); transition: transform .18s ease, box-shadow .18s ease; }
        .inventory-card[hidden] { display: none !important; }
        .inventory-card.archived { opacity: .84; }
        .inventory-card.archived .inventory-photo::after { position: absolute; inset: auto 8px 8px; padding: 4px 8px; border-radius: 7px; background: rgba(23,42,58,.78); color: var(--white); content: "Diarsipkan"; font-size: 10px; font-weight: 750; text-align: center; }
        .inventory-card:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(29,55,62,.09); }
        .inventory-photo { position: relative; aspect-ratio: 16 / 10; display: grid; place-items: center; overflow: hidden; background: linear-gradient(135deg, #e8f4ef, #f8faf8); color: var(--brand); }
        .inventory-photo img { width: 100%; height: 100%; object-fit: cover; }
        .inventory-photo-placeholder { font-size: 36px; opacity: .7; }
        .inventory-body { padding: 14px; }
        .inventory-category { display: inline-flex; margin-bottom: 6px; padding: 3px 8px; border-radius: 99px; background: var(--mint); color: var(--brand-dark); font-size: 10px; font-weight: 750; }
        .inventory-name { min-height: 42px; margin: 0 0 12px; font-size: 15px; line-height: 1.4; overflow-wrap: anywhere; }
        .inventory-details { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; color: var(--muted); font-size: 11px; }
        .condition { padding: 3px 7px; border-radius: 99px; background: #f1f5f2; color: #50665d; white-space: nowrap; }
        .condition.damaged { background: #fff3da; color: #95620e; }
        .stock-summary { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 7px; font-size: 11px; }
        .stock-summary strong { color: var(--ink); }
        .stock-state { padding: 3px 7px; border-radius: 99px; background: var(--mint); color: var(--brand-dark); font-size: 10px; font-weight: 750; white-space: nowrap; }
        .stock-state.low { background: #fff3da; color: #95620e; }
        .stock-state.empty { background: #fff0ef; color: #a43737; }
        .stock-track { height: 5px; overflow: hidden; border-radius: 99px; background: #edf0ed; }
        .stock-fill { height: 100%; border-radius: inherit; background: var(--brand); }
        .stock-fill.low { background: #d29a32; }
        .stock-fill.empty { background: #c65a51; }
        .inventory-actions { display: flex; gap: 8px; margin-top: 14px; }
        .inventory-actions form { flex: 1; }
        .inventory-actions button { width: 100%; min-height: 36px; padding: 0 9px; background: #eef4f1; color: var(--brand-dark); }
        .inventory-actions .delete { border: 1px solid #f1d7d4; background: #fff6f5; color: #a43737; }
        .inventory-actions .delete:hover { background: #ffe9e6; }
        .inventory-actions .edit-button { flex: 1; }
        .delete-note { align-self: center; color: var(--muted); font-size: 10px; text-align: right; }
        .search-empty { display: none; padding: 28px 18px; color: var(--muted); text-align: center; font-size: 13px; }
        .alert { margin-bottom: 16px; padding: 11px 13px; border-radius: 9px; background: #fff0ef; color: #a43737; font-size: 13px; }
        .alert.success { background: #eff9f1; color: #28643a; }
        .empty { padding: 32px; color: var(--muted); text-align: center; font-size: 13px; }
        .modal-backdrop { position: fixed; z-index: 3; inset: 0; display: none; place-items: center; padding: 18px; background: rgba(23,42,58,.48); }
        .modal-backdrop.open { display: grid; }
        .modal { width: min(500px,100%); max-height: min(90vh, 760px); overflow-y: auto; padding: 21px; border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: 0 24px 70px rgba(29,55,62,.2); }
        .modal h2 { margin: 0 0 16px; font-size: 18px; }
        .modal .field { margin-bottom: 13px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
        .modal-actions .cancel { border: 1px solid var(--line); background: var(--white); color: var(--muted); }
        .notice { margin: 8px 0 0; color: var(--muted); font-size: 10px; }
        :focus-visible { outline: 3px solid rgba(20,107,104,.28); outline-offset: 2px; }
        @media (max-width: 900px) { .inventory-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 600px) {
            .container { width: min(100% - 28px,1120px); }
            .topbar-inner { min-height: 60px; align-items: flex-start; flex-direction: column; padding: 10px 0; }
            .top-actions { width: 100%; gap: 6px; overflow-x: auto; }
            .top-actions a { min-height: 33px; padding: 0 9px; }
            main { padding-top: 24px; }
            .heading { align-items: flex-start; flex-direction: column; padding: 18px; }
            .heading-actions { width: 100%; }
            .heading-actions > * { flex: 1; }
            .heading-actions .back { width: auto; }
            .summary { gap: 8px; }
            .summary-card { min-height: 74px; padding: 11px; }
            .summary-card span { font-size: 8px; }
            .summary-card strong { font-size: 19px; }
            .summary-icon { width: 31px; height: 31px; font-size: 14px; }
            .panel-body { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; gap: 10px; }
            .name-field, .photo-field { grid-column: auto; }
            .form-actions { flex-direction: column-reverse; }
            .form-actions button { width: 100%; }
            .inventory-toolbar { align-items: stretch; flex-direction: column; }
            .inventory-search { width: 100%; }
            .inventory-tabs { width: 100%; }
            .inventory-tab { flex: 1; justify-content: center; padding: 0 7px; }
            .inventory-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 9px; padding: 10px; }
            .inventory-body { padding: 10px; }
            .inventory-name { min-height: 54px; margin-bottom: 9px; font-size: 13px; }
            .inventory-details { align-items: flex-start; flex-direction: column; gap: 5px; }
            .stock-summary { align-items: flex-start; flex-direction: column; gap: 4px; }
            .inventory-actions { flex-direction: column; gap: 6px; margin-top: 10px; }
            .delete-note { text-align: left; }
            .add-backdrop { align-items: start; padding: 10px; }
            .add-dialog { max-height: calc(100vh - 20px); }
            .add-dialog .panel-heading, .add-dialog .panel-body { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .name-field, .photo-field, .form-actions { grid-column: auto; }
        }
        @media (max-width: 390px) {
            .top-actions span { display: none; }
            .topbar-inner { align-items: flex-start; flex-direction: column; padding: 10px 0; }
            .top-actions { width: 100%; gap: 6px; overflow-x: auto; }
            .top-actions a { min-height: 34px; padding: 0 9px; }
            .inventory-grid { grid-template-columns: 1fr; }
            .inventory-card { display: grid; grid-template-columns: minmax(105px, .75fr) minmax(0, 1.25fr); }
            .inventory-photo { height: 100%; min-height: 100%; aspect-ratio: auto; }
        }

        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; }
        .topbar { border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); color:var(--ink); box-shadow:none; backdrop-filter:saturate(180%) blur(18px); }
        .brand { color:var(--ink); }
        .brand-mark { border-color:var(--line); background:var(--mint); box-shadow:none; }
        .brand-mark svg path { stroke:var(--brand); }
        .brand-copy small { color:var(--muted); }
        .top-actions a { border-color:var(--line); background:#fff; color:var(--muted); }
        .top-actions a:hover,.top-actions a[aria-current="page"] { border-color:#c7e1d8; background:var(--mint); color:var(--brand-dark); }
        .heading { border-color:var(--line); border-radius:22px; background:linear-gradient(120deg,#f1f7f3,#fff 72%); }
        .panel,.summary-card,.inventory-card { border-radius:19px; }
        .inventory-card { box-shadow:0 10px 28px rgba(29,55,62,.055); }
        .inventory-photo { background:#f5f7f6; }
        input,select { border-radius:11px; }
        @media (max-width:600px) { .topbar { backdrop-filter:none; } .heading { border-radius:18px; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="<?= h($dashboard_url) ?>" aria-label="SIMBAR, kembali ke dashboard">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 3.8 27.1 9.9v12.2L16 28.2 4.9 22.1V9.9L16 3.8Z" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="m5.4 10.1 10.6 5.8 10.6-5.8M16 16v11.6" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="m11.4 7 11 6v7.4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity=".8"/>
                    </svg>
                </span>
                <span class="brand-copy">SIMBAR<small>INVENTARIS</small></span>
            </a>
            <div class="top-actions">
                <span><?= h($_SESSION['nama'] ?? 'Pengguna') ?></span>
                <a href="<?= h($dashboard_url) ?>">Dashboard</a>
                <a href="barang.php" aria-current="page">Inventaris</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="pengguna.php">Pengguna</a>
                    <a href="log.php">Log aktivitas</a>
                <?php endif; ?>
                <a href="../logout.php">Keluar</a>
            </div>
        </div>
    </header>
    <main class="container">
        <div class="heading">
            <div>
                <p class="eyebrow">Kelola barang</p>
                <h1>Data inventaris</h1>
                <p class="muted">Semua perlengkapan tersusun rapi, siap dipantau dan diperbarui.</p>
            </div>
            <div class="heading-actions">
                <?php if (!$lihat_arsip): ?><button class="add-button" type="button" id="openAdd">
                    <span class="plus" aria-hidden="true">+</span> Tambah barang
                </button><?php endif; ?>
                <a class="back" href="<?= h($dashboard_url) ?>">Dashboard</a>
            </div>
        </div>
        <?php if ($pesan_info): ?>
            <div class="alert<?= $pesan_info[0] === 'success' ? ' success' : '' ?>" role="alert"><?= h($pesan_info[1]) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?><div class="alert" role="alert"><?= h($error_message) ?></div><?php endif; ?>

        <section class="summary" aria-label="Ringkasan inventaris">
            <article class="summary-card">
                <div><span>Jenis barang aktif</span><strong><?= $jumlah_aktif ?></strong></div>
                <span class="summary-icon" aria-hidden="true">▦</span>
            </article>
            <article class="summary-card">
                <div><span>Total unit tersedia</span><strong><?= $total_tersedia ?></strong></div>
                <span class="summary-icon" aria-hidden="true">✓</span>
            </article>
            <article class="summary-card">
                <div><span>Perlu diperiksa</span><strong><?= $stok_menipis ?></strong></div>
                <span class="summary-icon" aria-hidden="true">!</span>
            </article>
        </section>

        <div class="add-backdrop" id="addBackdrop" role="presentation">
        <section class="add-dialog" id="addDialog" role="dialog" aria-modal="true" aria-labelledby="create-title" aria-describedby="create-description">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Inventaris baru</p>
                    <h2 id="create-title">Tambah barang</h2>
                    <p id="create-description">Isi detail barang agar mudah ditemukan dan dipinjam.</p>
                </div>
                <button class="close-add" type="button" id="closeAdd" aria-label="Tutup formulir">×</button>
            </div>
            <div class="panel-body">
                <form action="barang_aksi.php" method="post" enctype="multipart/form-data" class="form-grid" id="createForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="hidden" name="foto_mode" id="createPhotoMode" value="device">
                    <div class="field name-field">
                        <label for="nama_barang">Nama barang</label>
                        <input id="nama_barang" name="nama_barang" maxlength="100" placeholder="Contoh: Proyektor Epson" required>
                    </div>
                    <div class="field">
                        <label for="kategori">Kategori</label>
                        <input id="kategori" name="kategori" maxlength="50" placeholder="Contoh: Elektronik" required>
                    </div>
                    <div class="field">
                        <label for="stok">Jumlah unit</label>
                        <input id="stok" name="stok" type="number" min="0" step="1" value="0" required>
                    </div>
                    <div class="field">
                        <label for="kondisi">Kondisi barang</label>
                        <select id="kondisi" name="kondisi" required><option>Baik</option><option>Rusak Ringan</option></select>
                    </div>
                    <div class="photo-field">
                        <label>Foto barang <span class="file-hint">(opsional, tapi membantu pengguna mengenal barang)</span></label>
                        <div class="photo-source" role="group" aria-label="Pilih sumber foto">
                            <label><input type="radio" name="createPhotoSource" value="device" checked><span>▣ Dari perangkat</span></label>
                            <label><input type="radio" name="createPhotoSource" value="url"><span>↗ Dari tautan</span></label>
                        </div>
                        <div class="photo-source-panel" data-photo-source="device">
                            <label for="createPhoto">Pilih foto dari perangkat</label>
                            <input id="createPhoto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                            <span class="file-hint">JPG, PNG, atau WebP. Ukuran maksimal 5 MB.</span>
                        </div>
                        <div class="photo-source-panel" data-photo-source="url" hidden>
                            <label for="createPhotoUrl">Tempel tautan gambar</label>
                            <input id="createPhotoUrl" name="foto_url" type="url" maxlength="2048" placeholder="https://contoh.com/foto-barang.jpg" disabled>
                            <p class="photo-url-note">Gunakan tautan HTTPS langsung ke file gambar publik (JPG, PNG, atau WebP), bukan tautan halaman produk.</p>
                        </div>
                        <div class="photo-preview" id="createPhotoPreview" aria-live="polite">Foto yang dipilih akan terlihat di sini</div>
                    </div>
                    <div class="form-actions">
                        <button class="secondary" type="reset">Kosongkan formulir</button>
                        <button type="submit">Simpan barang</button>
                    </div>
                </form>
            </div>
        </section>
        </div>

        <section class="panel inventory-panel" aria-labelledby="inventory-title">
            <div class="panel-heading inventory-toolbar">
                <div>
                    <h2 id="inventory-title"><?= $lihat_arsip ? 'Arsip barang' : 'Barang inventaris' ?></h2>
                    <p><span id="visibleCount"><?= count($barang_list) ?></span> dari <?= count($barang_list) ?> barang ditampilkan<?= $lihat_arsip ? '' : ' · unit yang sedang dipesan tetap dihitung' ?></p>
                </div>
                <input class="inventory-search" type="search" id="inventorySearch" placeholder="Cari nama atau kategori..." aria-label="Cari barang berdasarkan nama atau kategori">
            </div>
            <nav class="inventory-tabs" aria-label="Filter status barang">
                <a class="inventory-tab<?= !$lihat_arsip ? ' active' : '' ?>" href="barang.php?tampilan=aktif"<?= !$lihat_arsip ? ' aria-current="page"' : '' ?>>
                    Aktif <span class="inventory-tab-count"><?= $jumlah_aktif ?></span>
                </a>
                <a class="inventory-tab<?= $lihat_arsip ? ' active' : '' ?>" href="barang.php?tampilan=arsip"<?= $lihat_arsip ? ' aria-current="page"' : '' ?>>
                    Arsip <span class="inventory-tab-count"><?= $jumlah_arsip ?></span>
                </a>
            </nav>
            <?php if ($error_message): ?>
                <div class="empty"><?= h($error_message) ?></div>
            <?php elseif (!$barang_list): ?>
                <div class="empty">
                    <span class="summary-icon" aria-hidden="true">▦</span>
                    <p><strong><?= $lihat_arsip ? 'Belum ada barang yang diarsipkan.' : 'Belum ada barang di inventaris.' ?></strong></p>
                    <p><?= $lihat_arsip ? 'Barang yang diarsipkan akan muncul di sini.' : 'Tambahkan barang pertama menggunakan tombol di atas.' ?></p>
                </div>
            <?php else: ?>
                <div class="inventory-grid" id="inventoryGrid">
                    <?php foreach ($barang_list as $barang): ?>
                        <?php
                        $stok_total = (int)$barang['stok'];
                        $stok_dipesan = (int)$barang['stok_dipesan'];
                        $tersedia = max(0, $stok_total - $stok_dipesan);
                        $stok_persen = $stok_total > 0 ? min(100, (int)round($tersedia / $stok_total * 100)) : 0;
                        $stok_rendah = $tersedia > 0 && $tersedia <= 2;
                        $stok_habis = $tersedia === 0;
                        $kelas_stok = $stok_habis ? 'empty' : ($stok_rendah ? 'low' : '');
                        $kondisi_rusak = $barang['kondisi'] === 'Rusak Ringan';
                        ?>
                        <article class="inventory-card<?= (int)$barang['diarsipkan'] === 1 ? ' archived' : '' ?>" data-search="<?= h(mb_strtolower($barang['nama_barang'] . ' ' . $barang['kategori'], 'UTF-8')) ?>">
                            <div class="inventory-photo">
                                <?php if (!empty($barang['foto'])): ?>
                                    <img src="../uploads/barang/<?= h($barang['foto']) ?>" alt="Foto <?= h($barang['nama_barang']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="inventory-photo-placeholder" aria-hidden="true">▦</span>
                                <?php endif; ?>
                            </div>
                            <div class="inventory-body">
                                <span class="inventory-category"><?= h($barang['kategori']) ?></span>
                                <h3 class="inventory-name"><?= h($barang['nama_barang']) ?></h3>
                                <div class="inventory-details">
                                    <span class="condition<?= $kondisi_rusak ? ' damaged' : '' ?>"><?= h($barang['kondisi']) ?></span>
                                    <span><?= (int)$barang['jumlah_transaksi'] ?> transaksi tercatat</span>
                                </div>
                                <div class="stock-summary">
                                    <span><strong><?= $tersedia ?></strong> tersedia dari <?= $stok_total ?> unit</span>
                                    <span class="stock-state<?= $kelas_stok ?>"><?= $stok_habis ? 'Stok habis' : ($stok_rendah ? 'Stok menipis' : 'Tersedia') ?></span>
                                </div>
                                <div class="stock-track" role="progressbar" aria-label="Stok tersedia" aria-valuemin="0" aria-valuemax="<?= max(1, $stok_total) ?>" aria-valuenow="<?= $tersedia ?>">
                                    <div class="stock-fill<?= $kelas_stok ?>" style="width: <?= $stok_persen ?>%"></div>
                                </div>
                                <div class="inventory-actions">
                                    <?php if ((int)$barang['diarsipkan'] === 0): ?>
                                    <button type="button" class="edit-button"
                                        aria-label="Edit <?= h($barang['nama_barang']) ?>"
                                        data-id="<?= (int)$barang['id'] ?>"
                                        data-name="<?= h($barang['nama_barang']) ?>"
                                        data-category="<?= h($barang['kategori']) ?>"
                                        data-stock="<?= $stok_total ?>"
                                        data-condition="<?= h($barang['kondisi']) ?>"
                                        data-photo="<?= h($barang['foto'] ?? '') ?>"
                                        data-reserved="<?= $stok_dipesan ?>">Edit barang</button>
                                        <form method="post" action="barang_aksi.php" onsubmit="return confirm('Arsipkan <?= h($barang['nama_barang']) ?>? Barang tidak tampil di katalog peminjaman, tetapi riwayat transaksinya tetap tersimpan.');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="aksi" value="arsipkan">
                                            <input type="hidden" name="id" value="<?= (int)$barang['id'] ?>">
                                            <button class="delete" type="submit" aria-label="Arsipkan <?= h($barang['nama_barang']) ?>">Arsipkan</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="barang_aksi.php" onsubmit="return confirm('Pulihkan <?= h($barang['nama_barang']) ?> ke inventaris aktif?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="aksi" value="pulihkan">
                                            <input type="hidden" name="id" value="<?= (int)$barang['id'] ?>">
                                            <button class="edit-button" type="submit" aria-label="Pulihkan <?= h($barang['nama_barang']) ?>">Pulihkan</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="search-empty" id="searchEmpty" role="status">Barang yang cocok belum ditemukan. Coba kata kunci lain.</div>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal-backdrop" id="editModal" role="presentation">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="editTitle">
            <h2 id="editTitle">Perbarui barang</h2>
            <form action="barang_aksi.php" method="post" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="foto_mode" id="editPhotoMode" value="device">
                <div class="field">
                    <label>Foto barang</label>
                    <div class="photo-source" role="group" aria-label="Pilih sumber foto pengganti">
                        <label><input type="radio" name="editPhotoSource" value="device" checked><span>▣ Dari perangkat</span></label>
                        <label><input type="radio" name="editPhotoSource" value="url"><span>↗ Dari tautan</span></label>
                    </div>
                    <div class="photo-source-panel" data-photo-source="device">
                        <label for="editPhoto">Pilih foto dari perangkat</label>
                        <input id="editPhoto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                        <span class="file-hint">Pilih JPG, PNG, atau WebP. Ukuran maksimal 5 MB.</span>
                    </div>
                    <div class="photo-source-panel" data-photo-source="url" hidden>
                        <label for="editPhotoUrl">Tempel tautan gambar baru</label>
                        <input id="editPhotoUrl" name="foto_url" type="url" maxlength="2048" placeholder="https://contoh.com/foto-barang.jpg" disabled>
                        <p class="photo-url-note">Gunakan tautan HTTPS langsung ke file gambar publik (JPG, PNG, atau WebP).</p>
                    </div>
                    <div class="photo-preview" id="editPhotoPreview" aria-live="polite">Belum ada foto</div>
                    <label class="notice"><input type="checkbox" name="hapus_foto" value="1" id="removePhoto" style="width:auto;min-height:0;margin-right:5px"> Hapus foto saat ini</label>
                </div>
                <div class="field"><label for="editName">Nama barang</label><input id="editName" name="nama_barang" maxlength="100" required></div>
                <div class="field"><label for="editCategory">Kategori</label><input id="editCategory" name="kategori" maxlength="50" required></div>
                <div class="field"><label for="editStock">Stok total</label><input id="editStock" name="stok" type="number" min="0" step="1" required><p class="notice" id="stockNotice"></p></div>
                <div class="field"><label for="editCondition">Kondisi</label><select id="editCondition" name="kondisi"><option>Baik</option><option>Rusak Ringan</option></select></div>
                <div class="modal-actions"><button class="cancel" type="button" id="cancelEdit">Batal</button><button type="submit">Simpan</button></div>
            </form>
        </section>
    </div>
    <script>
        const modal = document.getElementById('editModal');
        const addBackdrop = document.getElementById('addBackdrop');
        const openAddButton = document.getElementById('openAdd');
        let lastAddTrigger = null;
        function openAddForm() {
            lastAddTrigger = document.activeElement;
            addBackdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
            document.getElementById('nama_barang').focus();
        }
        function closeAddForm() {
            addBackdrop.classList.remove('open');
            document.body.style.overflow = '';
            if (lastAddTrigger) lastAddTrigger.focus();
        }
        if (openAddButton) openAddButton.addEventListener('click', openAddForm);
        document.getElementById('closeAdd').addEventListener('click', closeAddForm);
        addBackdrop.addEventListener('click', (event) => {
            if (event.target === addBackdrop) closeAddForm();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && addBackdrop.classList.contains('open')) {
                closeAddForm();
            }
        });
        let lastButton = null;
        function setPhotoPreview(container, source, emptyText) {
            container.replaceChildren();
            if (!source) {
                container.textContent = emptyText;
                return;
            }
            const image = document.createElement('img');
            image.src = source;
            image.alt = 'Pratinjau foto barang';
            container.appendChild(image);
        }
        function setupPhotoSource(form, radioName, modeInput, fileInput, urlInput, preview, emptyText, getCurrentPhoto) {
            let previewUrl = null;
            const radios = Array.from(form.querySelectorAll('input[name="' + radioName + '"]'));

            function refreshPreview() {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = null;
                const mode = modeInput.value;
                if (mode === 'device' && fileInput.files && fileInput.files[0]) {
                    previewUrl = URL.createObjectURL(fileInput.files[0]);
                } else if (mode === 'url' && /^https:\/\/\S+$/i.test(urlInput.value.trim())) {
                    previewUrl = urlInput.value.trim();
                }
                setPhotoPreview(preview, previewUrl || getCurrentPhoto() || '', emptyText);
            }

            function updateSource() {
                const selected = radios.find((radio) => radio.checked);
                const mode = selected ? selected.value : 'device';
                modeInput.value = mode;
                form.querySelectorAll('[data-photo-source]').forEach((panel) => {
                    const active = panel.dataset.photoSource === mode;
                    panel.hidden = !active;
                    panel.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
                });
                if (mode === 'device') urlInput.value = '';
                else fileInput.value = '';
                refreshPreview();
            }

            radios.forEach((radio) => radio.addEventListener('change', updateSource));
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length && form.id === 'editForm') {
                    document.getElementById('removePhoto').checked = false;
                }
                refreshPreview();
            });
            urlInput.addEventListener('input', () => {
                if (urlInput.value.trim() && form.id === 'editForm') {
                    document.getElementById('removePhoto').checked = false;
                }
                refreshPreview();
            });
            updateSource();

            return {
                refresh: refreshPreview,
                setMode(mode) {
                    const radio = radios.find((option) => option.value === mode);
                    if (!radio) return;
                    radio.checked = true;
                    updateSource();
                },
            };
        }

        const createPhotoSource = setupPhotoSource(
            document.getElementById('createForm'),
            'createPhotoSource',
            document.getElementById('createPhotoMode'),
            document.getElementById('createPhoto'),
            document.getElementById('createPhotoUrl'),
            document.getElementById('createPhotoPreview'),
            'Foto yang dipilih akan terlihat di sini',
            () => ''
        );
        const editPhotoSource = setupPhotoSource(
            document.getElementById('editForm'),
            'editPhotoSource',
            document.getElementById('editPhotoMode'),
            document.getElementById('editPhoto'),
            document.getElementById('editPhotoUrl'),
            document.getElementById('editPhotoPreview'),
            'Belum ada foto',
            () => lastButton && lastButton.dataset.photo ? '../uploads/barang/' + lastButton.dataset.photo : ''
        );
        document.getElementById('createForm').addEventListener('reset', () => {
            window.setTimeout(() => {
                createPhotoSource.setMode('device');
                createPhotoSource.refresh();
            }, 0);
        });
        document.getElementById('removePhoto').addEventListener('change', (event) => {
            const hasNewPhoto = document.getElementById('editPhoto').files.length
                || document.getElementById('editPhotoUrl').value.trim();
            if (event.target.checked && !hasNewPhoto) {
                setPhotoPreview(
                    document.getElementById('editPhotoPreview'),
                    '',
                    'Foto akan dihapus setelah perubahan disimpan'
                );
            } else {
                editPhotoSource.refresh();
            }
        });
        document.querySelectorAll('.edit-button').forEach((button) => {
            button.addEventListener('click', () => {
                lastButton = button;
                document.getElementById('editPhoto').value = '';
                document.getElementById('editPhotoUrl').value = '';
                document.getElementById('removePhoto').checked = false;
                editPhotoSource.setMode('device');
                document.getElementById('editId').value = button.dataset.id;
                document.getElementById('editName').value = button.dataset.name;
                document.getElementById('editCategory').value = button.dataset.category;
                document.getElementById('editStock').value = button.dataset.stock;
                document.getElementById('editStock').min = button.dataset.reserved;
                document.getElementById('editCondition').value = button.dataset.condition;
                document.getElementById('stockNotice').textContent = 'Stok minimal ' + button.dataset.reserved + ' unit karena sedang dipesan.';
                modal.classList.add('open');
                document.getElementById('editName').focus();
            });
        });
        function closeEdit() {
            modal.classList.remove('open');
            if (lastButton) lastButton.focus();
        }
        document.getElementById('cancelEdit').addEventListener('click', closeEdit);
        modal.addEventListener('click', (event) => { if (event.target === modal) closeEdit(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('open')) closeEdit(); });
        const searchInput = document.getElementById('inventorySearch');
        if (searchInput) {
            const cards = Array.from(document.querySelectorAll('.inventory-card'));
            const visibleCount = document.getElementById('visibleCount');
            const searchEmpty = document.getElementById('searchEmpty');
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLocaleLowerCase('id');
                let visible = 0;
                cards.forEach((card) => {
                    const match = card.dataset.search.includes(query);
                    card.hidden = !match;
                    if (match) visible++;
                });
                visibleCount.textContent = String(visible);
                searchEmpty.style.display = visible === 0 ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
