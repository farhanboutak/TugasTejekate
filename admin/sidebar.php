<?php
$admin_sidebar_from_profile = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'akun.php';
$admin_sidebar_current_dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$admin_sidebar_admin_base = $admin_sidebar_from_profile ? 'admin/' : ($admin_sidebar_current_dir === 'admin' ? '' : '../admin/');
$admin_sidebar_borrow_href = $admin_sidebar_from_profile
    ? 'peminjaman/index.php'
    : ($admin_sidebar_current_dir === 'peminjaman' ? 'index.php' : '../peminjaman/index.php');
$admin_sidebar_profile_href = $admin_sidebar_from_profile ? 'akun.php' : '../akun.php';
$admin_sidebar_logout_href = $admin_sidebar_from_profile ? 'logout.php' : '../logout.php';
$admin_sidebar_dashboard = simbar_dashboard_url($pdo, (string)($_SESSION['role'] ?? ''));
$admin_sidebar_dashboard_dir = basename(dirname($admin_sidebar_dashboard));
$admin_sidebar_dashboard_href = $admin_sidebar_from_profile || $admin_sidebar_current_dir === '.'
    ? $admin_sidebar_dashboard
    : ($admin_sidebar_current_dir === $admin_sidebar_dashboard_dir
        ? basename($admin_sidebar_dashboard)
        : '../' . $admin_sidebar_dashboard);
$admin_sidebar_brand_href = $admin_sidebar_dashboard_href;
$admin_sidebar_items = [
    ['dashboard', $admin_sidebar_dashboard_href, 'bi-grid', 'Dashboard', 'transactions.view'],
    ['inventory', $admin_sidebar_admin_base . 'barang.php', 'bi-box-seam', 'Inventaris', 'inventory.manage'],
    ['users', $admin_sidebar_admin_base . 'pengguna.php', 'bi-people', 'Pengguna', 'users.manage'],
    ['roles', $admin_sidebar_admin_base . 'peran.php', 'bi-shield-lock', 'Role & izin', 'users.manage'],
    ['log', $admin_sidebar_admin_base . 'log.php', 'bi-clock-history', 'Log aktivitas', 'audit.view'],
    ['borrow', $admin_sidebar_borrow_href, 'bi-bag', 'Peminjaman', 'borrow.use'],
    ['profile', $admin_sidebar_profile_href, 'bi-person-gear', 'Profil', null],
];
$admin_sidebar_name = htmlspecialchars((string)($_SESSION['nama'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
?>
<aside class="admin-sidebar" aria-label="Menu admin">
    <a class="admin-sidebar-brand" href="<?= htmlspecialchars($admin_sidebar_brand_href, ENT_QUOTES, 'UTF-8') ?>">
        <span class="admin-sidebar-mark"><i class="bi bi-box-seam-fill" aria-hidden="true"></i></span>
        <span>SIMBAR<small><?= ($_SESSION['role'] ?? '') === 'petugas' ? 'AREA PETUGAS' : 'ADMINISTRASI' ?></small></span>
    </a>
    <div class="admin-sidebar-user">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span><?= $admin_sidebar_name ?></span>
    </div>
    <nav class="admin-sidebar-nav" aria-label="Navigasi admin">
        <?php foreach ($admin_sidebar_items as [$key, $href, $icon, $label, $permission]): ?>
            <?php if ($permission === null || simbar_user_has_permission($permission)): ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $admin_sidebar_active === $key ? ' aria-current="page"' : '' ?>>
                    <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <a class="admin-sidebar-logout" href="<?= htmlspecialchars($admin_sidebar_logout_href, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Keluar</span>
    </a>
</aside>
<nav class="admin-mobile-nav" aria-label="Navigasi aplikasi">
    <?php foreach ($admin_sidebar_items as [$key, $href, $icon, $label, $permission]): ?>
        <?php if ($permission === null || simbar_user_has_permission($permission)): ?>
            <?php $mobile_label = ['dashboard' => 'Home', 'inventory' => 'Barang', 'users' => 'User', 'roles' => 'Role', 'log' => 'Log', 'profile' => 'Profil'][$key] ?? $label; ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $admin_sidebar_active === $key ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($mobile_label, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
    <a href="<?= htmlspecialchars($admin_sidebar_logout_href, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Keluar</span>
    </a>
</nav>