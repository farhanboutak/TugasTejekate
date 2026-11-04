<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses('users.manage');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$permission_catalog = simbar_permission_catalog();
$roles = [];
$error_message = null;
$messages = [
    'ditambahkan' => ['success', 'Role baru berhasil ditambahkan.'],
    'diperbarui' => ['success', 'Role dan izinnya berhasil diperbarui.'],
    'nama_terpakai' => ['danger', 'Nama role sudah digunakan. Pilih nama lain.'],
    'invalid' => ['danger', 'Nama role atau pilihan izin tidak valid.'],
    'izin_admin_wajib' => ['danger', 'Role Admin harus tetap memiliki izin mengelola pengguna dan role.'],
    'csrf' => ['danger', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'gagal' => ['danger', 'Role belum berhasil disimpan. Silakan coba kembali.'],
];
$message_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$message = $messages[$message_key] ?? null;

try {
    $roles = $pdo->query(
        'SELECT r.role_key, r.label, r.permissions, r.is_system, COUNT(u.id) AS user_count
         FROM roles r
         LEFT JOIN users u ON u.role = r.role_key
         GROUP BY r.role_key, r.label, r.permissions, r.is_system
         ORDER BY r.is_system DESC, r.label ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR role list query failed: ' . $e->getMessage());
    $error_message = 'Daftar role belum dapat dimuat. Pastikan migrasi role dinamis sudah diterapkan.';
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kelola role dan izin akses SIMBAR.">
    <title>Role &amp; izin — SIMBAR Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require __DIR__ . '/sidebar_styles.php'; ?>
    <style>
        :root { color-scheme:light; --ink:#172a3a; --muted:#647587; --brand:#146b68; --brand-dark:#105653; --mint:#e8f4ef; --paper:#fbfaf6; --line:#e7e9e3; --white:#fff; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; background:radial-gradient(ellipse at 12% 0,rgba(20,107,104,.07),transparent 34%),var(--paper); color:var(--ink); font-family:"Segoe UI",Arial,sans-serif; line-height:1.55; }
        .container { width:min(1120px,calc(100% - 40px)); margin:0 auto; }
        .topbar { background:var(--brand); color:var(--white); }
        .topbar-inner { min-height:68px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .brand { display:flex; align-items:center; gap:10px; color:var(--white); font-size:15px; font-weight:800; letter-spacing:.04em; text-decoration:none; }
        .brand-mark { width:38px; height:38px; display:grid; place-items:center; border:1px solid rgba(255,255,255,.25); border-radius:11px; background:rgba(255,255,255,.12); font-size:16px; }
        .mobile-nav { display:flex; gap:7px; }
        .mobile-nav a { min-height:36px; display:inline-flex; align-items:center; padding:0 10px; border:1px solid rgba(255,255,255,.35); border-radius:8px; color:var(--white); font-size:11px; font-weight:700; text-decoration:none; }
        main { padding:34px 0 56px; }
        .page-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:22px; }
        .eyebrow { margin:0 0 5px; color:var(--brand); font-size:10px; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
        h1 { margin:0 0 5px; font-size:30px; letter-spacing:-.03em; }
        .description { margin:0; color:var(--muted); font-size:13px; }
        .panel { overflow:hidden; margin-bottom:16px; border:1px solid var(--line); border-radius:12px; background:var(--white); box-shadow:0 7px 22px rgba(29,55,62,.045); }
        .panel-heading { padding:17px 20px; border-bottom:1px solid var(--line); }
        .panel-heading h2 { margin:0 0 3px; font-size:15px; }
        .panel-heading p { margin:0; color:var(--muted); font-size:11px; }
        .panel-body { padding:19px 20px; }
        .role-entry { padding:18px 20px; border-bottom:1px solid var(--line); }
        .role-entry:last-child { border-bottom:0; }
        .role-entry-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:13px; }
        .role-entry-heading strong { font-size:13px; }
        .role-key { display:block; margin-top:2px; color:var(--muted); font-size:10px; }
        .permission-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 14px; }
        .permission-option { min-height:42px; display:flex; align-items:center; gap:9px; padding:8px 10px; border:1px solid var(--line); border-radius:8px; background:#fff; font-size:11px; }
        .permission-option input { width:16px; height:16px; flex:0 0 auto; accent-color:var(--brand); }
        .role-actions { display:flex; justify-content:flex-end; margin-top:14px; }
        .system-note { color:var(--muted); font-size:10px; }
        .alert { margin-bottom:15px; }
        :focus-visible { outline:3px solid rgba(20,107,104,.25); outline-offset:2px; }
        @media(max-width:700px) { .container{width:min(100% - 28px,1120px)} .topbar-inner{align-items:flex-start;flex-direction:column;padding:11px 0}.mobile-nav{width:100%;overflow-x:auto}.mobile-nav a{white-space:nowrap}main{padding-top:25px}.page-heading{align-items:flex-start;flex-direction:column}h1{font-size:26px}.panel-body,.role-entry{padding:15px}.permission-grid{grid-template-columns:1fr}.role-entry-heading{align-items:flex-start;flex-direction:column}.role-actions button{width:100%} }
    </style>
</head>
<body>
<?php $admin_sidebar_active = 'roles'; require __DIR__ . '/sidebar.php'; ?>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="peran.php"><span class="brand-mark"><i class="bi bi-box-seam-fill" aria-hidden="true"></i></span><span>SIMBAR Admin</span></a>
            <nav class="mobile-nav" aria-label="Navigasi admin">
                <?php if (simbar_user_has_permission('transactions.view')): ?><a href="index.php">Dashboard</a><?php endif; ?>
                <?php if (simbar_user_has_permission('inventory.manage')): ?><a href="barang.php">Inventaris</a><?php endif; ?>
                <a href="pengguna.php">Pengguna</a>
                <a href="peran.php" aria-current="page">Role &amp; izin</a>
                <?php if (simbar_user_has_permission('audit.view')): ?><a href="log.php">Log</a><?php endif; ?>
                <a href="../logout.php">Keluar</a>
            </nav>
        </div>
    </header>
    <main class="container">
        <section class="page-heading">
            <div>
                <p class="eyebrow">Akses SIMBAR</p>
                <h1>Role &amp; izin</h1>
                <p class="description">Atur izin akses untuk tiap kelompok pengguna.</p>
            </div>
            <a class="btn btn-outline-secondary" href="pengguna.php"><i class="bi bi-people me-1"></i>Pengguna</a>
        </section>

        <?php if ($message): ?><div class="alert alert-<?= h($message[0]) ?>" role="alert"><?= h($message[1]) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger" role="alert"><?= h($error_message) ?></div><?php endif; ?>

        <section class="panel" aria-labelledby="create-role-title">
            <div class="panel-heading">
                <h2 id="create-role-title">Tambah role</h2>
                <p>Role baru akan tersedia pada formulir tambah dan edit pengguna.</p>
            </div>
            <div class="panel-body">
                <form method="post" action="peran_aksi.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="mb-3">
                        <label class="form-label" for="newRoleLabel">Nama role</label>
                        <input class="form-control" id="newRoleLabel" name="label" maxlength="100" required>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label">Izin akses</legend>
                        <div class="permission-grid">
                            <?php foreach ($permission_catalog as $permission => $label): ?>
                                <label class="permission-option"><input type="checkbox" name="permissions[]" value="<?= h($permission) ?>"><span><?= h($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Buat role</button>
                </form>
            </div>
        </section>

        <section class="panel" aria-labelledby="roles-list-title">
            <div class="panel-heading">
                <h2 id="roles-list-title">Role terdaftar</h2>
                <p><?= count($roles) ?> role · role yang sedang dipakai tidak dapat dihapus.</p>
            </div>
            <?php foreach ($roles as $role): ?>
                <?php $selected_permissions = json_decode($role['permissions'], true); $selected_permissions = is_array($selected_permissions) ? $selected_permissions : []; ?>
                <form class="role-entry" method="post" action="peran_aksi.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="ubah">
                    <input type="hidden" name="role_key" value="<?= h($role['role_key']) ?>">
                    <div class="role-entry-heading">
                        <div>
                            <strong><?= h($role['label']) ?></strong><?= (int)$role['is_system'] === 1 ? ' <span class="badge text-bg-light">Bawaan</span>' : '' ?>
                            <small class="role-key"><?= h($role['role_key']) ?> · <?= (int)$role['user_count'] ?> pengguna</small>
                        </div>
                        <label class="form-label mb-0" for="label-<?= h($role['role_key']) ?>">Nama role</label>
                    </div>
                    <input class="form-control mb-3" id="label-<?= h($role['role_key']) ?>" name="label" value="<?= h($role['label']) ?>" maxlength="100" required>
                    <fieldset>
                        <legend class="form-label">Izin akses</legend>
                        <div class="permission-grid">
                            <?php foreach ($permission_catalog as $permission => $label): ?>
                                <?php
                                                $checked = in_array($permission, $selected_permissions, true);
                                                $locked = ($role['role_key'] === 'admin' && $permission === 'users.manage')
                                                    || $role['role_key'] === 'peminjam';
                                                ?>
                                <label class="permission-option">
                                    <input type="checkbox" name="permissions[]" value="<?= h($permission) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
                                    <?php if ($locked): ?><input type="hidden" name="permissions[]" value="users.manage"><?php endif; ?>
                                    <span><?= h($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="role-actions"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan perubahan</button></div>
                </form>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>