<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses('borrow.use');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$peminjaman_view = isset($peminjaman_view) && in_array($peminjaman_view, ['ringkasan', 'katalog', 'keranjang', 'riwayat'], true)
    ? $peminjaman_view
    : 'ringkasan';
$peminjaman_pages = [
    'ringkasan' => ['index.php', 'Ringkasan', 'Pantau pengajuan dan pilih kebutuhan peminjaman.'],
    'katalog' => ['katalog.php', 'Katalog barang', 'Cari dan pilih barang yang ingin dipinjam.'],
    'keranjang' => ['keranjang.php', 'Keranjang peminjaman', 'Tinjau jumlah barang dan kirim pengajuan.'],
    'riwayat' => ['riwayat.php', 'Riwayat peminjaman', 'Pantau semua status pengajuan akun kamu.'],
];
$peminjaman_url = $peminjaman_pages[$peminjaman_view][0];
$peminjaman_title = $peminjaman_pages[$peminjaman_view][1];
$peminjaman_description = $peminjaman_pages[$peminjaman_view][2];

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
        header('Location: ' . $peminjaman_url . '?pesan=csrf');
        exit;
    }

    $items_input = $_POST['items'] ?? null;
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';
    $items = [];
    $items_valid = is_array($items_input);
    if ($items_valid) {
        foreach ($items_input as $barang_id_input => $jumlah_input) {
            $barang_id_string = (string)$barang_id_input;
            if (
                preg_match('/\A[1-9][0-9]*\z/', $barang_id_string) !== 1 ||
                !is_string($jumlah_input) ||
                !ctype_digit($jumlah_input) ||
                (int)$jumlah_input < 1 ||
                (int)$jumlah_input > 9999
            ) {
                $items_valid = false;
                break;
            }
            $items[(int)$barang_id_string] = (int)$jumlah_input;
        }
    }
    if (!$items) {
        $items_valid = false;
    }
    ksort($items, SORT_NUMERIC);
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

    if (!$items_valid || !$tanggal_valid) {
        header('Location: ' . $peminjaman_url . '?pesan=invalid');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $last_id_stmt = $pdo->query('SELECT id FROM peminjaman ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $next_peminjaman_id = (int)$last_id_stmt->fetchColumn() + 1;

        $insert = $pdo->prepare(
            "INSERT INTO peminjaman
                (id, user_id, barang_id, tanggal_pinjam, tanggal_kembali, jumlah_pinjam, status)
             VALUES
                (:id, :user_id, :barang_id, :tanggal_pinjam, :tanggal_kembali, :jumlah_pinjam, 'Pending')"
        );
        $validated_items = [];
        foreach ($items as $barang_id => $jumlah) {
            $stmt = $pdo->prepare('SELECT id, stok, diarsipkan FROM barang WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $barang_id]);
            $barang = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$barang || (int)$barang['diarsipkan'] === 1) {
                $pdo->rollBack();
                header('Location: ' . $peminjaman_url . '?pesan=barang_tidak_ada');
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
                header('Location: ' . $peminjaman_url . '?pesan=stok_tidak_cukup');
                exit;
            }

            $validated_items[] = [$barang_id, $jumlah];
        }

        foreach ($validated_items as [$barang_id, $jumlah]) {
            $insert->execute([
                ':id' => $next_peminjaman_id++,
                ':user_id' => (int)$_SESSION['user_id'],
                ':barang_id' => $barang_id,
                ':tanggal_pinjam' => $tanggal_pinjam,
                ':tanggal_kembali' => $tanggal_kembali,
                ':jumlah_pinjam' => $jumlah,
            ]);
        }

        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . $peminjaman_url . '?pesan=sukses');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('SIMBAR borrowing request failed: ' . $e->getMessage());
        header('Location: ' . $peminjaman_url . '?pesan=gagal');
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
         WHERE b.diarsipkan = 0
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
$cart_products = array_map(static function (array $barang): array {
    return [
        'id' => (string)$barang['id'],
        'name' => (string)$barang['nama_barang'],
        'category' => (string)$barang['kategori'],
        'condition' => (string)$barang['kondisi'],
        'stock' => (int)$barang['stok_tersedia'],
        'photo' => !empty($barang['foto']) ? '../uploads/barang/' . $barang['foto'] : '',
    ];
}, $barang_list);
$cart_products_json = json_encode(
    $cart_products,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?: '[]';

$status_class = [
    'Pending' => 'pending',
    'Disetujui' => 'approved',
    'Ditolak' => 'rejected',
    'Dikembalikan' => 'returned',
];
$kategori_barang = [];
foreach ($barang_list as $barang) {
    $kategori = trim((string)$barang['kategori']);
    if ($kategori !== '' && !in_array($kategori, $kategori_barang, true)) {
        $kategori_barang[] = $kategori;
    }
}
sort($kategori_barang, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ajukan dan pantau peminjaman barang SIMBAR.">
    <title>Peminjaman — SIMBAR</title>
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
        .nav-links a { min-height: 36px; display: inline-flex; align-items: center; justify-content: center; padding: 0 11px; border: 1px solid rgba(255,255,255,.55); border-radius: 9px; color: var(--white); font-size: 11px; font-weight: 700; }
        .nav-links a:hover,.nav-links a[aria-current="page"] { background: rgba(255,255,255,.12); }
        .sidebar-nav,.sidebar-footer { display:none; }
        .mobile-bottom-nav { display:none; }
        .sidebar-nav a,
        .sidebar-footer a,
        .mobile-bottom-nav a { transition:none !important; animation:none !important; }

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
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 100px;
            min-width: 0;
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
            line-height: 1.35;
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
        .content-grid.single-panel { grid-template-columns: minmax(0,1fr); }
        .content-grid.single-panel > .panel { width: min(100%, 820px); }
        .borrower-shortcuts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:11px; margin:4px 0 24px; }
        .borrower-shortcuts a { min-height:72px; display:flex; align-items:center; gap:11px; padding:13px 15px; border:1px solid var(--line); border-radius:11px; background:#fff; box-shadow:0 6px 18px rgba(29,55,62,.035); }
        .borrower-shortcuts a > i:first-child { width:36px; height:36px; display:grid; flex:0 0 auto; place-items:center; border-radius:10px; background:var(--mint); color:var(--brand); }
        .borrower-shortcuts a > span { display:grid; min-width:0; gap:2px; }
        .borrower-shortcuts strong { font-size:12px; }
        .borrower-shortcuts small { color:var(--muted); font-size:10px; }
        .borrower-shortcuts a > i:last-child { margin-left:auto; color:var(--muted); }

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
        .catalog-tools { display:grid; grid-template-columns:minmax(0,1fr) 210px auto; gap:9px; margin:15px 0; }
        .catalog-tools input,.catalog-tools select { width:100%; min-height:42px; padding:0 12px; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--ink); font:inherit; font-size:12px; }
        .catalog-tools input:focus,.catalog-tools select:focus { border-color:var(--brand); outline:3px solid rgba(20,107,104,.12); }
        .catalog-count { align-self:center; color:var(--muted); font-size:11px; white-space:nowrap; }
        .product-card.is-selected { border-color:var(--brand); box-shadow:0 0 0 2px rgba(20,107,104,.11),0 8px 24px rgba(29,55,62,.045); }
        .product-button.is-selected { background:var(--mint); color:var(--brand-dark); }
        .cart-panel { position:sticky; top:18px; }
        .cart-heading-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .cart-count { display:inline-flex; min-width:27px; height:27px; align-items:center; justify-content:center; padding:0 8px; border-radius:99px; background:var(--mint); color:var(--brand-dark); font-size:11px; font-weight:800; }
        .cart-empty { display:grid; min-height:125px; place-content:center; justify-items:center; gap:7px; color:var(--muted); text-align:center; font-size:12px; }
        .cart-empty-icon { width:38px; height:38px; display:grid; place-items:center; border-radius:12px; background:var(--mint); color:var(--brand); font-size:18px; }
        .cart-items { display:grid; gap:10px; }
        .cart-item { display:grid; grid-template-columns:58px minmax(0,1fr); gap:11px; padding:10px 0; border-bottom:1px solid var(--line); }
        .cart-item-image { width:58px; height:58px; display:grid; place-items:center; overflow:hidden; border-radius:8px; background:var(--mint); color:var(--brand); }
        .cart-item-image img { width:100%; height:100%; object-fit:cover; }
        .cart-item-name { margin:0 0 3px; font-size:12px; font-weight:750; line-height:1.35; }
        .cart-item-meta { margin:0; color:var(--muted); font-size:10px; }
        .cart-item-controls { display:flex; align-items:center; gap:7px; margin-top:8px; }
        .quantity-button { width:27px; height:27px; display:grid; place-items:center; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--brand); cursor:pointer; font:inherit; font-size:15px; font-weight:700; }
        .quantity-button:disabled { color:#aab5b0; cursor:not-allowed; }
        .cart-quantity { min-width:20px; font-size:12px; font-weight:750; text-align:center; }
        .cart-remove { margin-left:auto; border:0; background:transparent; color:#a43737; cursor:pointer; font:inherit; font-size:10px; font-weight:700; }
        .cart-total { display:flex; justify-content:space-between; gap:12px; padding:13px 0; color:var(--muted); font-size:12px; }
        .cart-total strong { color:var(--ink); }
        .checkout-fields { display:grid; gap:11px; margin-top:4px; }
        .checkout-submit { min-height:44px; margin-top:2px; }
        .checkout-submit:disabled { background:#9aaba6; cursor:not-allowed; transform:none; }
        .cart-empty[hidden],.cart-items[hidden],.cart-total[hidden],.cart-dock[hidden] { display:none !important; }
        .cart-dock { display:none; color:inherit; text-decoration:none; }
        .cart-dock-button { min-height:40px; display:inline-flex; align-items:center; padding:0 14px; border:0; border-radius:8px; background:#fff; color:var(--brand-dark); font-size:11px; font-weight:800; }

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
            .content-grid.single-panel > .panel { width:100%; }
            .catalog-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
            .cart-panel { position: static; }
            .borrower-shortcuts { grid-template-columns:1fr; }
        }

        @media (max-width: 600px) {
            .container { width: min(100% - 28px, 1120px); }
            .topbar-inner { min-height: 64px; flex-wrap: wrap; padding: 10px 0; }
            .topbar-user { width: 100%; justify-content: space-between; gap: 8px; font-size: 11px; }
            .topbar-user .nav-links { display:none; }
            .logout { min-height: 35px; padding: 0 11px; }
            main { padding: 27px 0 42px; }
            .welcome { align-items: flex-start; flex-direction: column; }
            .catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
            .catalog-tools { grid-template-columns: 1fr 1fr; gap: 7px; }
            .catalog-tools input { grid-column: 1 / -1; }
            .catalog-count { grid-column: 1 / -1; }
            .product-copy { padding: 11px; }
            .product-copy h3 { font-size: 13px; }
            .product-meta { align-items: flex-start; flex-direction: column; gap: 5px; }
            .cart-item { grid-template-columns:48px minmax(0,1fr); gap:9px; }
            .cart-item-image { width:48px; height:48px; }
            .summary { gap: 9px; }
            .summary-card { min-height: 85px; padding: 13px; }
            .summary-label { font-size: 9px; letter-spacing: .04em; overflow-wrap:anywhere; }
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
            .topbar-user { gap: 6px; }
            .user-name { margin-right: auto; max-width: 125px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .logout { padding-inline: 9px; }
        }

        @media (max-width: 390px) {
            .topbar-user .user-name { display: none; }
            .summary-icon { display: none; }
            .date-grid { grid-template-columns: 1fr; gap: 0; }
        }

        @media (max-width: 860px) {
            .mobile-bottom-nav {
                position:fixed;
                inset:auto 0 0;
                z-index:30;
                min-height:66px;
                display:grid;
                grid-template-columns:repeat(5,minmax(0,1fr));
                align-items:stretch;
                padding:6px 5px calc(6px + env(safe-area-inset-bottom));
                border-top:1px solid var(--line);
                background:rgba(255,255,255,.97);
                box-shadow:0 -7px 22px rgba(29,55,62,.09);
                backdrop-filter:blur(12px);
            }
            .mobile-bottom-nav a {
                min-width:0;
                display:flex;
                flex-direction:column;
                align-items:center;
                justify-content:center;
                gap:3px;
                border-radius:8px;
                color:var(--muted);
                font-size:9px;
                font-weight:700;
                text-align:center;
            }
            .mobile-bottom-nav a i { font-size:16px; line-height:1; }
            .mobile-cart-count { min-width:17px; height:17px; display:inline-grid; place-items:center; padding:0 4px; border-radius:99px; background:var(--mint); color:var(--brand-dark); font-size:8px; }
            .mobile-bottom-nav a:hover,.mobile-bottom-nav a[aria-current="location"] { background:var(--mint); color:var(--brand-dark); }
            body { padding-bottom:82px; }
            .cart-dock {
                position: fixed;
                inset: auto 12px calc(76px + env(safe-area-inset-bottom));
                z-index: 20;
                min-height: 58px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 8px 10px 8px 15px;
                border: 1px solid rgba(255,255,255,.15);
                border-radius: 12px;
                background: var(--brand-dark);
                color: var(--white);
                box-shadow: 0 8px 25px rgba(16,86,83,.25);
            }
            .cart-dock-copy { display:grid; line-height:1.25; }
            .cart-dock-copy strong { font-size:12px; }
            .cart-dock-copy small { color:rgba(255,255,255,.72); font-size:10px; }
            .cart-dock-button { cursor:pointer; }
            body.has-cart-dock { padding-bottom:148px; }
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
        .topbar { border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); color:var(--ink); box-shadow:none; backdrop-filter:saturate(180%) blur(18px); }
        .brand { color:var(--ink); }
        .brand-mark { border-color:var(--line); background:var(--mint); color:var(--brand); }
        .topbar-user { color:var(--muted); }
        .logout { border-color:var(--line); background:var(--mint); color:var(--brand-dark); }
        .summary-card,.product-card,.panel,.updates { border-radius:20px; box-shadow:0 16px 42px rgba(29,55,62,.055); }
        .product-card:hover { box-shadow:0 22px 48px rgba(29,55,62,.1); }
        .product-image { background:#f5f7f6; }
        .field input,.field select { border-radius:11px; }
        th { background:#f7f8f7; }
        @media (max-width:600px) { .topbar { backdrop-filter:none; } }
    </style>
    <style>
        @media (min-width: 992px) {
            body { padding-left: 250px; }
            .topbar { position: fixed; inset: 0 auto 0 0; z-index: 1000; width: 250px; height: 100vh; }
            .topbar-inner { width: 100%; height: 100%; align-items: stretch; justify-content: flex-start; flex-direction: column; padding: 24px 18px; }
            .topbar-user { align-items: stretch; flex-direction: column; gap: 0; margin-top: 27px; }
            .topbar-user .nav-links,.topbar-user .logout { display:none; }
            .topbar-user .user-name { padding:10px 11px; border-radius:9px; background:rgba(255,255,255,.12); color:rgba(255,255,255,.92); font-size:11px; font-weight:700; }
            .sidebar-nav { display:grid; gap:5px; margin-top:15px; }
            .sidebar-nav a { min-height:42px; display:flex; align-items:center; gap:11px; padding:0 12px; border:1px solid transparent; border-radius:9px; color:rgba(255,255,255,.84); font-size:12px; font-weight:700; }
            .sidebar-nav a:hover,.sidebar-nav a[aria-current="location"] { border-color:rgba(255,255,255,.16); background:rgba(255,255,255,.15); color:#fff; }
            .sidebar-nav i { width:16px; font-size:14px; text-align:center; }
            .sidebar-cart-count { min-width:21px; height:21px; display:grid; place-items:center; margin-left:auto; padding:0 5px; border-radius:99px; background:rgba(255,255,255,.17); font-size:10px; }
            .sidebar-footer { display:grid; gap:6px; margin-top:auto; padding-top:16px; border-top:1px solid rgba(255,255,255,.17); }
            .sidebar-footer a { min-height:39px; display:flex; align-items:center; gap:10px; padding:0 11px; border:1px solid rgba(255,255,255,.2); border-radius:8px; color:rgba(255,255,255,.9); font-size:11px; font-weight:700; }
            .sidebar-footer a:last-child { background:#fff; color:var(--brand); }
            .sidebar-footer a:hover { background:rgba(255,255,255,.12); color:#fff; }
            .sidebar-footer a:last-child:hover { background:var(--mint); color:var(--brand-dark); }
            .sidebar-footer i { width:15px; text-align:center; }
            .container { width: min(1120px, calc(100% - 40px)); }
        }
    </style>
</head>
<body data-view="<?= h($peminjaman_view) ?>">
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
                <nav class="nav-links" aria-label="Navigasi peminjam">
                    <a href="index.php" aria-current="page">Dashboard</a>
                    <a href="../akun.php">Profil</a>
                </nav>
                <a class="logout" href="../logout.php">Keluar</a>
            </div>
            <nav class="sidebar-nav" aria-label="Menu halaman peminjaman">
                <a href="index.php"<?= $peminjaman_view === 'ringkasan' ? ' aria-current="page"' : '' ?>><i class="bi bi-grid" aria-hidden="true"></i><span>Ringkasan</span></a>
                <a href="katalog.php"<?= $peminjaman_view === 'katalog' ? ' aria-current="page"' : '' ?>><i class="bi bi-box-seam" aria-hidden="true"></i><span>Katalog barang</span></a>
                <a href="keranjang.php"<?= $peminjaman_view === 'keranjang' ? ' aria-current="page"' : '' ?>><i class="bi bi-bag" aria-hidden="true"></i><span>Keranjang</span><span class="sidebar-cart-count" id="sidebarCartCount">0</span></a>
                <a href="riwayat.php"<?= $peminjaman_view === 'riwayat' ? ' aria-current="page"' : '' ?>><i class="bi bi-clock-history" aria-hidden="true"></i><span>Riwayat</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="../akun.php"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Profil saya</span></a>
                <a href="../logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Keluar</span></a>
            </div>
        </div>
    </header>

    <nav class="mobile-bottom-nav" aria-label="Menu peminjaman">
        <a href="index.php"<?= $peminjaman_view === 'ringkasan' ? ' aria-current="page"' : '' ?>><i class="bi bi-grid" aria-hidden="true"></i><span>Ringkasan</span></a>
        <a href="katalog.php"<?= $peminjaman_view === 'katalog' ? ' aria-current="page"' : '' ?>><i class="bi bi-box-seam" aria-hidden="true"></i><span>Katalog</span></a>
        <a href="keranjang.php"<?= $peminjaman_view === 'keranjang' ? ' aria-current="page"' : '' ?>><i class="bi bi-bag" aria-hidden="true"></i><span>Keranjang</span><span class="mobile-cart-count" id="mobileCartCount">0</span></a>
        <a href="riwayat.php"<?= $peminjaman_view === 'riwayat' ? ' aria-current="page"' : '' ?>><i class="bi bi-clock-history" aria-hidden="true"></i><span>Riwayat</span></a>
        <a href="../akun.php"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Profil</span></a>
    </nav>

    <main class="container">
        <section class="welcome" aria-labelledby="page-title">
            <div>
                <p class="eyebrow">Area peminjam</p>
                <h1 id="page-title"><?= h($peminjaman_title) ?></h1>
                <p class="welcome-copy"><?= h($peminjaman_description) ?></p>
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

        <?php if ($peminjaman_view === 'ringkasan' && $pembaruan): ?>
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

        <?php if ($peminjaman_view === 'ringkasan'): ?>
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

        <nav class="borrower-shortcuts" aria-label="Navigasi peminjaman">
            <a href="katalog.php"><i class="bi bi-box-seam" aria-hidden="true"></i><span><strong>Katalog barang</strong><small>Temukan barang yang tersedia</small></span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <a href="keranjang.php"><i class="bi bi-bag" aria-hidden="true"></i><span><strong>Keranjang</strong><small>Tinjau dan kirim pengajuan</small></span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <a href="riwayat.php"><i class="bi bi-clock-history" aria-hidden="true"></i><span><strong>Riwayat</strong><small>Pantau status peminjaman</small></span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </nav>
        <?php endif; ?>

        <?php if ($peminjaman_view === 'katalog'): ?>
        <section class="catalog-section" aria-labelledby="catalog-title">
            <div class="catalog-heading">
                <div>
                    <h2 id="catalog-title">Katalog barang</h2>
                    <p>Pilih barang yang dibutuhkan lalu atur jumlahnya di keranjang.</p>
                </div>
            </div>
            <?php if ($error_message): ?>
                <div class="empty-state">Katalog barang belum dapat dimuat.</div>
            <?php elseif (!$barang_list): ?>
                <div class="empty-state">Belum ada barang di inventaris.</div>
            <?php else: ?>
                <div class="catalog-tools">
                    <input type="search" id="catalogSearch" placeholder="Cari nama barang..." aria-label="Cari barang">
                    <select id="categoryFilter" aria-label="Filter kategori">
                        <option value="">Semua kategori</option>
                        <?php foreach ($kategori_barang as $kategori): ?>
                            <option value="<?= h($kategori) ?>"><?= h($kategori) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="catalog-count" id="catalogCount" aria-live="polite"><?= count($barang_list) ?> barang</span>
                </div>
                <div class="catalog-grid">
                    <?php foreach ($barang_list as $barang): ?>
                        <?php $stok_kartu = (int)$barang['stok_tersedia']; ?>
                        <article
                            class="product-card"
                            data-product-card
                            data-id="<?= (int)$barang['id'] ?>"
                            data-name="<?= h($barang['nama_barang']) ?>"
                            data-category="<?= h($barang['kategori']) ?>"
                            data-condition="<?= h($barang['kondisi']) ?>"
                            data-stock="<?= $stok_kartu ?>"
                            data-photo="<?= !empty($barang['foto']) ? h('../uploads/barang/' . $barang['foto']) : '' ?>">
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
                                    data-cart-add="<?= (int)$barang['id'] ?>"
                                    <?= $stok_kartu < 1 ? 'disabled' : '' ?>>
                                    <?= $stok_kartu > 0 ? 'Tambah ke keranjang' : 'Stok habis' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (in_array($peminjaman_view, ['keranjang', 'riwayat'], true)): ?>
        <div class="content-grid single-panel">
            <?php if ($peminjaman_view === 'keranjang'): ?>
            <section class="panel cart-panel" aria-labelledby="cart-title">
                <div class="panel-heading">
                    <div class="cart-heading-row">
                        <h2 id="cart-title">Keranjang peminjaman</h2>
                        <span class="cart-count" id="cartItemCount" aria-live="polite">0</span>
                    </div>
                    <p id="cartStatus" aria-live="polite">Belum ada barang dipilih.</p>
                </div>
                <div class="panel-body">
                    <?php if (!$error_message && empty($barang_tersedia)): ?>
                        <div class="empty-state">
                            <span class="empty-icon" aria-hidden="true">□</span>
                            Saat ini belum ada stok barang yang tersedia untuk dipinjam.
                        </div>
                    <?php elseif (!$error_message): ?>
                        <div class="cart-empty" id="cartEmpty">
                            <span class="cart-empty-icon" aria-hidden="true">▤</span>
                            <span>Keranjang masih kosong.</span>
                        </div>
                        <div class="cart-items" id="cartItems"></div>
                        <div class="cart-total">
                            <span>Total unit</span>
                            <strong id="cartUnitCount">0 unit</strong>
                        </div>
                        <form method="post" action="keranjang.php" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <div id="cartInputs"></div>
                            <div class="checkout-fields">
                                <div class="field">
                                    <label for="tanggal_pinjam">Tanggal pinjam</label>
                                    <input type="date" id="tanggal_pinjam" name="tanggal_pinjam" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="field">
                                    <label for="tanggal_kembali">Tanggal kembali</label>
                                    <input type="date" id="tanggal_kembali" name="tanggal_kembali" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                </div>
                                <button class="submit-button checkout-submit" id="checkoutButton" type="submit" disabled>Lanjutkan pengajuan</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($peminjaman_view === 'riwayat'): ?>
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
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <a class="cart-dock" id="cartDock" href="keranjang.php" hidden>
            <span class="cart-dock-copy"><strong id="cartDockTitle">Keranjang kosong</strong><small id="cartDockUnits">0 unit</small></span>
            <span class="cart-dock-button">Buka keranjang</span>
        </a>
    </main>

    <footer class="page-footer">
        <div class="container">SIMBAR · Sistem Inventaris &amp; Peminjaman Barang</div>
    </footer>
<script>
    (function () {
        const cards = Array.from(document.querySelectorAll('[data-product-card]'));
        const cartItems = document.getElementById('cartItems');
        const cartEmpty = document.getElementById('cartEmpty');
        const cartInputs = document.getElementById('cartInputs');
        const itemCount = document.getElementById('cartItemCount');
        const unitCount = document.getElementById('cartUnitCount');
        const cartStatus = document.getElementById('cartStatus');
        const checkoutButton = document.getElementById('checkoutButton');
        const sidebarCartCount = document.getElementById('sidebarCartCount');
        const mobileCartCount = document.getElementById('mobileCartCount');
        const cartDock = document.getElementById('cartDock');
        const cartDockTitle = document.getElementById('cartDockTitle');
        const cartDockUnits = document.getElementById('cartDockUnits');
        const search = document.getElementById('catalogSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const catalogCount = document.getElementById('catalogCount');
        const dateFrom = document.getElementById('tanggal_pinjam');
        const dateTo = document.getElementById('tanggal_kembali');
        const storageKey = 'simbar-borrow-cart-<?= $user_id ?>';
        const cart = new Map();
        const products = new Map(<?= $cart_products_json ?>.map((product) => [product.id, product]));

        try {
            if (new URLSearchParams(window.location.search).get('pesan') === 'sukses') {
                sessionStorage.removeItem(storageKey);
            }
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
            Object.entries(saved).forEach(([id, quantity]) => {
                const product = products.get(id);
                if (!product || product.stock < 1 || !Number.isInteger(quantity) || quantity < 1) return;
                cart.set(id, { ...product, quantity: Math.min(quantity, product.stock) });
            });
        } catch (error) {
            cart.clear();
        }

        function storeCart() {
            try {
                sessionStorage.setItem(storageKey, JSON.stringify(
                    Object.fromEntries(Array.from(cart, ([id, item]) => [id, item.quantity]))
                ));
            } catch (error) {
                if (cartStatus) cartStatus.textContent = 'Keranjang hanya tersimpan selama halaman ini terbuka.';
            }
        }

        function createCartItem(item) {
            const row = document.createElement('article');
            row.className = 'cart-item';

            const imageWrap = document.createElement('div');
            imageWrap.className = 'cart-item-image';
            if (item.photo) {
                const image = document.createElement('img');
                image.src = item.photo;
                image.alt = '';
                image.loading = 'lazy';
                imageWrap.append(image);
            } else {
                imageWrap.textContent = '▦';
                imageWrap.setAttribute('aria-hidden', 'true');
            }

            const detail = document.createElement('div');
            const name = document.createElement('p');
            name.className = 'cart-item-name';
            name.textContent = item.name;
            const meta = document.createElement('p');
            meta.className = 'cart-item-meta';
            meta.textContent = `${item.category} · tersedia ${item.stock}`;

            const controls = document.createElement('div');
            controls.className = 'cart-item-controls';
            const decrease = document.createElement('button');
            decrease.type = 'button';
            decrease.className = 'quantity-button';
            decrease.dataset.cartAction = 'decrease';
            decrease.dataset.id = item.id;
            decrease.setAttribute('aria-label', `Kurangi ${item.name}`);
            decrease.textContent = '−';
            decrease.disabled = item.quantity <= 1;

            const quantity = document.createElement('span');
            quantity.className = 'cart-quantity';
            quantity.textContent = String(item.quantity);
            quantity.setAttribute('aria-label', `${item.quantity} unit`);

            const increase = document.createElement('button');
            increase.type = 'button';
            increase.className = 'quantity-button';
            increase.dataset.cartAction = 'increase';
            increase.dataset.id = item.id;
            increase.setAttribute('aria-label', `Tambah ${item.name}`);
            increase.textContent = '+';
            increase.disabled = item.quantity >= item.stock;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'cart-remove';
            remove.dataset.cartAction = 'remove';
            remove.dataset.id = item.id;
            remove.textContent = 'Hapus';

            controls.append(decrease, quantity, increase, remove);
            detail.append(name, meta, controls);
            row.append(imageWrap, detail);
            return row;
        }

        function renderCart() {
            if (cartItems) cartItems.replaceChildren();
            if (cartInputs) cartInputs.replaceChildren();
            let totalUnits = 0;

            cart.forEach((item) => {
                totalUnits += item.quantity;
                if (cartItems) cartItems.append(createCartItem(item));
                if (cartInputs) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${item.id}]`;
                    input.value = String(item.quantity);
                    cartInputs.append(input);
                }
            });

            const hasItems = cart.size > 0;
            if (cartEmpty) cartEmpty.hidden = hasItems;
            if (cartItems) cartItems.hidden = !hasItems;
            const cartTotal = document.querySelector('.cart-total');
            if (cartTotal) cartTotal.hidden = !hasItems;
            if (itemCount) itemCount.textContent = String(cart.size);
            if (sidebarCartCount) sidebarCartCount.textContent = String(cart.size);
            if (mobileCartCount) mobileCartCount.textContent = String(cart.size);
            if (unitCount) unitCount.textContent = `${totalUnits} unit`;
            if (cartStatus) cartStatus.textContent = hasItems
                ? `${cart.size} jenis barang · pengajuan menunggu persetujuan admin.`
                : 'Belum ada barang dipilih.';
            if (checkoutButton) {
                checkoutButton.disabled = !hasItems;
                checkoutButton.textContent = hasItems ? `Ajukan ${cart.size} barang` : 'Pilih barang dahulu';
            }
            const showCartDock = hasItems && document.body.dataset.view !== 'keranjang';
            cartDock.hidden = !showCartDock;
            cartDockTitle.textContent = `${cart.size} jenis barang dipilih`;
            cartDockUnits.textContent = `${totalUnits} unit di keranjang`;
            document.body.classList.toggle('has-cart-dock', showCartDock);

            cards.forEach((card) => {
                const selected = cart.has(card.dataset.id);
                card.classList.toggle('is-selected', selected);
                const button = card.querySelector('[data-cart-add]');
                if (button && !button.disabled) {
                    button.classList.toggle('is-selected', selected);
                    button.textContent = selected ? 'Tambah satu lagi' : 'Tambah ke keranjang';
                }
            });
            storeCart();
        }

        function filterCatalog() {
            if (!search || !categoryFilter || !catalogCount) return;
            const query = search.value.trim().toLocaleLowerCase('id');
            const category = categoryFilter.value.toLocaleLowerCase('id');
            let visible = 0;
            cards.forEach((card) => {
                const matches = card.dataset.name.toLocaleLowerCase('id').includes(query)
                    && (!category || card.dataset.category.toLocaleLowerCase('id') === category);
                card.hidden = !matches;
                if (matches) visible++;
            });
            catalogCount.textContent = `${visible} barang`;
        }

        document.querySelectorAll('[data-cart-add]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.dataset.cartAdd;
                const product = products.get(id);
                if (!product || product.stock < 1) return;
                const current = cart.get(id);
                if (current) {
                    if (current.quantity < current.stock) current.quantity++;
                } else {
                    cart.set(id, { ...product, quantity: 1 });
                }
                renderCart();
            });
        });

        if (cartItems) cartItems.addEventListener('click', (event) => {
            const button = event.target.closest('[data-cart-action]');
            if (!button) return;
            const item = cart.get(button.dataset.id);
            if (!item) return;
            if (button.dataset.cartAction === 'remove') {
                cart.delete(item.id);
            } else if (button.dataset.cartAction === 'increase' && item.quantity < item.stock) {
                item.quantity++;
            } else if (button.dataset.cartAction === 'decrease' && item.quantity > 1) {
                item.quantity--;
            }
            renderCart();
        });

        if (search) search.addEventListener('input', filterCatalog);
        if (categoryFilter) categoryFilter.addEventListener('change', filterCatalog);
        if (dateFrom) dateFrom.addEventListener('change', () => {
            if (!dateFrom.value) return;
            const nextDay = new Date(`${dateFrom.value}T00:00:00`);
            nextDay.setDate(nextDay.getDate() + 1);
            dateTo.min = nextDay.toISOString().slice(0, 10);
            if (dateTo.value && dateTo.value <= dateFrom.value) dateTo.value = '';
        });
        const checkoutForm = document.getElementById('checkoutForm');
        if (checkoutForm) checkoutForm.addEventListener('submit', (event) => {
            if (!cart.size || !dateFrom.value || !dateTo.value || dateTo.value <= dateFrom.value) {
                event.preventDefault();
            }
        });

        renderCart();
    }());
</script>
</body>
</html>
