<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses('audit.view');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$kategori = isset($_GET['kategori']) && is_string($_GET['kategori']) ? $_GET['kategori'] : '';
$cari = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$halaman = isset($_GET['halaman']) && is_string($_GET['halaman']) && ctype_digit($_GET['halaman'])
    ? max(1, (int)$_GET['halaman'])
    : 1;
$perHalaman = 50;
$kategoriValid = ['auth', 'transaksi', 'barang', 'pengguna'];
if ($kategori !== '' && !in_array($kategori, $kategoriValid, true)) {
    $kategori = '';
}

$kondisi = [];
$parameter = [];
if ($kategori !== '') {
    $kondisi[] = 'entity = :entity';
    $parameter[':entity'] = $kategori;
}
if ($cari !== '') {
    $kondisi[] = '(actor_name LIKE :actor OR summary LIKE :summary OR action LIKE :action OR ip_address LIKE :ip)';
    $kata = '%' . $cari . '%';
    $parameter[':actor'] = $kata;
    $parameter[':summary'] = $kata;
    $parameter[':action'] = $kata;
    $parameter[':ip'] = $kata;
}
$where = $kondisi ? ' WHERE ' . implode(' AND ', $kondisi) : '';
$logs = [];
$total = 0;
$errorMessage = null;

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs' . $where);
    $stmt->execute($parameter);
    $total = (int)$stmt->fetchColumn();
    $totalHalaman = max(1, (int)ceil($total / $perHalaman));
    $halaman = min($halaman, $totalHalaman);
    $offset = ($halaman - 1) * $perHalaman;

    $stmt = $pdo->prepare(
        'SELECT id, actor_name, actor_role, action, entity, entity_id, summary, metadata, ip_address, created_at
         FROM audit_logs' . $where . '
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($parameter as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR audit log page query failed: ' . $e->getMessage());
    $errorMessage = 'Log aktivitas belum dapat dimuat. Pastikan migrasi audit log sudah diterapkan.';
}

$kategoriLabel = [
    'auth' => 'Login',
    'transaksi' => 'Transaksi',
    'barang' => 'Inventaris',
    'pengguna' => 'Pengguna',
];
$csrf = $_SESSION['csrf_token'] ?? '';
$baseParams = [];
if ($kategori !== '') {
    $baseParams['kategori'] = $kategori;
}
if ($cari !== '') {
    $baseParams['q'] = $cari;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Jejak aktivitas administrator SIMBAR.">
    <title>Log aktivitas — SIMBAR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { color-scheme: light; --ink:#172a3a; --muted:#647587; --brand:#146b68; --brand-dark:#105653; --mint:#e8f4ef; --paper:#fbfaf6; --line:#e7e9e3; --white:#fff; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; background:radial-gradient(ellipse at 12% 0,rgba(20,107,104,.07),transparent 34%),var(--paper); color:var(--ink); font-family:"Segoe UI",Arial,sans-serif; line-height:1.55; }
        a { color:inherit; text-decoration:none; }
        .container { width:min(1120px,calc(100% - 40px)); margin:0 auto; }
        .topbar { background:var(--brand); color:var(--white); box-shadow:0 8px 24px rgba(16,86,83,.16); }
        .topbar-inner { min-height:70px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .brand { display:flex; align-items:center; gap:11px; font-size:15px; font-weight:800; letter-spacing:.04em; }
        .brand-mark { width:39px; height:39px; display:grid; place-items:center; border:1px solid rgba(255,255,255,.25); border-radius:12px; background:rgba(255,255,255,.12); font-size:17px; }
        .nav-links { display:flex; align-items:center; gap:5px; }
        .nav-links a,.logout { min-height:37px; display:inline-flex; align-items:center; gap:7px; padding:0 12px; border:1px solid transparent; border-radius:9px; color:rgba(255,255,255,.82); font-size:12px; font-weight:700; }
        .nav-links a:hover,.nav-links a.active { border-color:rgba(255,255,255,.18); background:rgba(255,255,255,.13); color:#fff; }
        .logout { border-color:rgba(255,255,255,.35); background:#fff; color:var(--brand); }
        main { padding:30px 0 56px; }
        .heading { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; margin-bottom:19px; padding:23px 25px; border:1px solid #d5e7df; border-radius:17px; background:linear-gradient(115deg,#eff7f2,#fff 72%); }
        .eyebrow { margin:0 0 4px; color:var(--brand); font-size:10px; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
        h1 { margin:0 0 5px; font-size:clamp(25px,4vw,34px); letter-spacing:-.045em; }
        .description { margin:0; color:var(--muted); font-size:13px; }
        .count { color:var(--muted); font-size:12px; font-weight:700; white-space:nowrap; }
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:15px; background:var(--white); box-shadow:0 8px 24px rgba(29,55,62,.045); }
        .filters { display:grid; grid-template-columns:minmax(0,1fr) 200px auto; gap:10px; padding:16px; border-bottom:1px solid var(--line); }
        input,select { width:100%; min-height:43px; padding:0 12px; border:1px solid #dfe5e3; border-radius:9px; background:#fff; color:var(--ink); font:inherit; font-size:13px; }
        input:focus,select:focus { outline:3px solid rgba(20,107,104,.17); border-color:var(--brand); }
        button,.button { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:0 14px; border:1px solid var(--brand); border-radius:9px; background:var(--brand); color:#fff; cursor:pointer; font:inherit; font-size:12px; font-weight:750; }
        .button.secondary { border-color:var(--line); background:white; color:var(--muted); }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; }
        th { padding:12px 15px; background:#f5f8f5; color:var(--muted); font-size:10px; font-weight:800; letter-spacing:.08em; text-align:left; text-transform:uppercase; white-space:nowrap; }
        td { padding:13px 15px; border-top:1px solid #eef1ed; font-size:12px; vertical-align:top; }
        tr:hover td { background:#fbfcfa; }
        .actor { font-weight:750; }
        .actor small,.date small { display:block; color:var(--muted); font-size:10px; font-weight:500; }
        .category { display:inline-flex; padding:4px 8px; border-radius:99px; background:var(--mint); color:var(--brand-dark); font-size:10px; font-weight:750; white-space:nowrap; }
        .summary { min-width:220px; max-width:440px; }
        details { margin-top:5px; color:var(--muted); font-size:10px; }
        details pre { max-width:400px; overflow:auto; margin:6px 0 0; padding:9px; border-radius:8px; background:#f5f8f5; color:#405366; white-space:pre-wrap; word-break:break-word; }
        .empty { padding:35px 20px; color:var(--muted); text-align:center; }
        .pagination { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:13px 16px; border-top:1px solid var(--line); color:var(--muted); font-size:11px; }
        .pagination-links { display:flex; gap:6px; }
        .pagination-links a { min-height:32px; display:grid; place-items:center; padding:0 10px; border:1px solid var(--line); border-radius:8px; background:white; color:var(--brand); font-weight:700; }
        .alert { margin-bottom:14px; padding:12px 14px; border:1px solid #f1caca; border-radius:10px; background:#fff3f2; color:#a43737; font-size:13px; }
        @media(min-width:992px) { body{padding-left:250px} .topbar{position:fixed;inset:0 auto 0 0;z-index:1000;width:250px;height:100vh} .topbar-inner{width:100%;height:100%;align-items:stretch;justify-content:flex-start;flex-direction:column;padding:24px 18px} .nav-links{align-items:stretch;flex-direction:column;margin-top:28px} .nav-links a,.logout{justify-content:flex-start} .container{width:min(1120px,calc(100% - 40px))} }
        @media(max-width:700px) { .container{width:min(100% - 28px,1120px)} .topbar-inner{align-items:flex-start; flex-direction:column; padding:12px 0} .nav-links{width:100%; overflow:auto} .nav-links a{flex:0 0 auto; justify-content:center; padding:0 8px; white-space:nowrap} .heading{align-items:flex-start; flex-direction:column; padding:19px} .filters{grid-template-columns:1fr} .table-wrap{overflow:visible} table,tbody{display:block} thead{display:none} tr{display:grid; grid-template-columns:1fr 1fr; gap:4px; margin:10px; padding:9px; border:1px solid var(--line); border-radius:11px} td{display:block; min-width:0; padding:6px; border:0; overflow-wrap:anywhere} td:first-child{display:none} td:nth-child(3),td:nth-child(4){grid-column:1/-1} .summary{min-width:0} .pagination{align-items:flex-start; flex-direction:column} }
        @media(prefers-reduced-motion:reduce) { *,*::before,*::after{scroll-behavior:auto!important; transition-duration:.01ms!important} }
        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; }
        .topbar { border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); color:var(--ink); box-shadow:none; backdrop-filter:saturate(180%) blur(18px); }
        .brand { color:var(--ink); }
        .brand-mark { border-color:var(--line); background:var(--mint); color:var(--brand); }
        .nav-links a,.logout { color:var(--muted); }
        .nav-links a:hover,.nav-links a.active { border-color:var(--line); background:var(--mint); color:var(--brand-dark); }
        .logout { border-color:var(--line); background:#fff; color:var(--brand); }
        .heading { border:1px solid var(--line); border-radius:22px; background:linear-gradient(120deg,#f1f7f3,#fff 72%); }
        .panel { border-radius:20px; box-shadow:0 16px 42px rgba(29,55,62,.055); }
        input,select { border-radius:11px; }
        th { background:#f7f8f7; }
        @media(max-width:700px) { .topbar { backdrop-filter:none; } .heading { border-radius:18px; } }
    </style>
    <?php require __DIR__ . '/sidebar_styles.php'; ?>
</head>
<body>
<?php $admin_sidebar_active = 'log'; require __DIR__ . '/sidebar.php'; ?>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="log.php"><span class="brand-mark" aria-hidden="true"><i class="bi bi-box-seam"></i></span>SIMBAR <span style="font-weight:500;opacity:.72">/ Admin</span></a>
            <nav class="nav-links" aria-label="Navigasi admin">
                <?php if (simbar_user_has_permission('transactions.view')): ?><a href="index.php"><i class="bi bi-grid"></i> Dashboard</a><?php endif; ?>
                <?php if (simbar_user_has_permission('inventory.manage')): ?><a href="barang.php"><i class="bi bi-box-seam"></i> Inventaris</a><?php endif; ?>
                <?php if (simbar_user_has_permission('users.manage')): ?>
                <a href="pengguna.php"><i class="bi bi-people"></i> Pengguna</a>
                <a href="peran.php"><i class="bi bi-shield-lock"></i> Role &amp; izin</a>
                <?php endif; ?>
                <a class="active" href="log.php" aria-current="page"><i class="bi bi-clock-history"></i> Log aktivitas</a>
                <a href="../akun.php"><i class="bi bi-person-gear"></i> Profil</a>
                <a class="logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
            </nav>
        </div>
    </header>
    <main class="container">
        <section class="heading">
            <div><p class="eyebrow">Keamanan &amp; akuntabilitas</p><h1>Log aktivitas</h1><p class="description">Jejak login staf, perubahan transaksi, inventaris, dan akun pengguna.</p></div>
            <span class="count"><?= number_format($total, 0, ',', '.') ?> aktivitas tercatat</span>
        </section>
        <?php if ($errorMessage): ?><div class="alert" role="alert"><?= h($errorMessage) ?></div><?php endif; ?>
        <section class="panel" aria-label="Riwayat aktivitas">
            <form class="filters" method="get">
                <input type="search" name="q" value="<?= h($cari) ?>" placeholder="Cari nama, ringkasan, atau alamat IP..." aria-label="Cari log">
                <select name="kategori" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($kategoriLabel as $key => $label): ?>
                        <option value="<?= h($key) ?>"<?= $kategori === $key ? ' selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="display:flex;gap:7px"><button type="submit"><i class="bi bi-search"></i>Filter</button><a class="button secondary" href="log.php">Reset</a></div>
            </form>
            <?php if ($errorMessage): ?>
                <div class="empty">Log belum tersedia.</div>
            <?php elseif (!$logs): ?>
                <div class="empty"><i class="bi bi-inbox" style="font-size:28px"></i><p>Belum ada aktivitas yang cocok.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Kategori</th><th>Aktivitas</th><th>Alamat IP</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $metadata = json_decode($log['metadata'], true);
                            $tanggal = strtotime($log['created_at']);
                            ?>
                            <tr>
                                <td class="date"><?= $tanggal ? h(date('d M Y, H:i', $tanggal)) : h($log['created_at']) ?><small>#<?= (int)$log['id'] ?></small></td>
                                <td><span class="actor"><?= h($log['actor_name']) ?></span><small class="actor"><?= h(ucfirst($log['actor_role'])) ?></small></td>
                                <td><span class="category"><?= h($kategoriLabel[$log['entity']] ?? ucfirst($log['entity'])) ?></span></td>
                                <td class="summary">
                                    <?= h($log['summary']) ?>
                                    <?php if (is_array($metadata) && $metadata): ?>
                                        <details><summary>Detail perubahan</summary><pre><?= h(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($log['ip_address'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $totalHalaman = max(1, (int)ceil($total / $perHalaman));
                $tautanHalaman = static function (int $number) use ($baseParams): string {
                    return 'log.php?' . http_build_query($baseParams + ['halaman' => $number]);
                };
                ?>
                <div class="pagination">
                    <span>Halaman <?= $halaman ?> dari <?= $totalHalaman ?> · menampilkan <?= count($logs) ?> dari <?= number_format($total, 0, ',', '.') ?></span>
                    <div class="pagination-links">
                        <?php if ($halaman > 1): ?><a href="<?= h($tautanHalaman($halaman - 1)) ?>">← Sebelumnya</a><?php endif; ?>
                        <?php if ($halaman < $totalHalaman): ?><a href="<?= h($tautanHalaman($halaman + 1)) ?>">Berikutnya →</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
