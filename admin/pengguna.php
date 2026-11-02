<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['admin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pesan_map = [
    'ditambahkan' => ['success', 'Pengguna berhasil ditambahkan.'],
    'diperbarui' => ['success', 'Data pengguna berhasil diperbarui.'],
    'dihapus' => ['success', 'Pengguna berhasil dihapus.'],
    'tidak_ada' => ['warning', 'Pengguna yang dipilih tidak ditemukan.'],
    'invalid' => ['danger', 'Data tidak valid. Periksa kembali isian formulir.'],
    'password_invalid' => ['danger', 'Password harus berisi 8 sampai 72 byte.'],
    'username_terpakai' => ['danger', 'Username sudah digunakan. Pilih username lain.'],
    'admin_terakhir' => ['danger', 'Perubahan ditolak agar setidaknya satu akun admin tetap tersedia.'],
    'hapus_ditolak' => ['danger', 'Kamu tidak dapat menghapus akun yang sedang digunakan.'],
    'role_ditolak' => ['danger', 'Role akun admin yang sedang digunakan tidak dapat diubah.'],
    'memiliki_transaksi' => ['danger', 'Akun ini memiliki riwayat peminjaman dan tidak dapat dihapus.'],
    'csrf' => ['danger', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'gagal' => ['danger', 'Terjadi kesalahan saat menyimpan data pengguna. Silakan coba lagi.'],
];
$pesan_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$pesan_info = $pesan_map[$pesan_key] ?? null;

$users = [];
$error_message = null;
$current_user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->query(
        'SELECT id, nama, username, role, created_at
         FROM users
         ORDER BY FIELD(role, "admin", "petugas", "peminjam"), nama ASC'
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR user list query failed: ' . $e->getMessage());
    $error_message = 'Daftar pengguna belum dapat dimuat. Silakan muat ulang halaman.';
}

$role_labels = [
    'admin' => 'Admin',
    'petugas' => 'Petugas',
    'peminjam' => 'Peminjam',
];
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kelola akun dan role pengguna SIMBAR.">
    <title>Pengguna — SIMBAR Admin</title>
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

        .navbar-brand {
            display: inline-flex;
            align-items: center;
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

        .user-chip {
            padding: .35rem .8rem;
            border-radius: 999px;
            background: rgba(255,255,255,.15);
            color: var(--white);
            font-size: .85rem;
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .eyebrow {
            margin: 0 0 6px;
            color: var(--brand);
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 6px;
            color: var(--ink);
            font-size: clamp(1.55rem, 3vw, 2rem);
            font-weight: 750;
            letter-spacing: -.045em;
        }

        .page-description {
            margin: 0;
            color: var(--muted);
            font-size: .9rem;
        }

        .panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 8px 24px rgba(29, 55, 62, .045);
        }

        .panel-heading {
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
        }

        .panel-heading h2 {
            margin: 0 0 3px;
            font-size: 1rem;
            font-weight: 750;
        }

        .panel-heading p {
            margin: 0;
            color: var(--muted);
            font-size: .78rem;
        }

        .panel-body { padding: 20px; }

        .form-label {
            margin-bottom: .4rem;
            color: #314556;
            font-size: .8rem;
            font-weight: 700;
        }

        .form-control,
        .form-select {
            min-height: 43px;
            border-color: #dfe5e3;
            border-radius: 9px;
            color: var(--ink);
            font-size: .86rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem rgba(20, 107, 104, .13);
        }

        .form-text { color: var(--muted); font-size: .72rem; }

        .btn {
            border-radius: 9px;
            font-size: .84rem;
            font-weight: 650;
        }

        .btn-primary {
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: var(--brand-dark);
            --bs-btn-hover-border-color: var(--brand-dark);
            --bs-btn-active-bg: var(--brand-dark);
            --bs-btn-active-border-color: var(--brand-dark);
        }

        .btn-outline-secondary {
            --bs-btn-color: var(--muted);
            --bs-btn-border-color: var(--line);
            --bs-btn-hover-bg: var(--mint);
            --bs-btn-hover-color: var(--brand-dark);
            --bs-btn-hover-border-color: var(--brand);
        }

        .btn:focus-visible,
        .form-control:focus-visible,
        .form-select:focus-visible {
            outline: 3px solid rgba(20, 107, 104, .25);
            outline-offset: 2px;
            box-shadow: none;
        }

        .alert {
            border: 0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(29, 55, 62, .05);
            font-size: .86rem;
        }

        .table-panel { margin-top: 20px; }
        .table-responsive { overflow-x: auto; }
        .table { margin: 0; }

        .table thead th {
            padding: .8rem 1rem;
            border-bottom: 1px solid var(--line);
            background: #f5f8f5;
            color: var(--muted);
            font-size: .69rem;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table tbody td {
            padding: .85rem 1rem;
            border-color: #eef1ed;
            color: var(--ink);
            font-size: .86rem;
            vertical-align: middle;
        }

        .table tbody tr:hover { background: #f8faf8; }
        .user-name { font-weight: 700; }
        .user-username { color: var(--muted); font-size: .78rem; }

        .role-badge {
            display: inline-flex;
            padding: .35rem .65rem;
            border-radius: 999px;
            background: var(--mint);
            color: var(--brand-dark);
            font-size: .72rem;
            font-weight: 750;
        }

        .role-badge.admin { background: #e8eef8; color: #38547a; }
        .role-badge.petugas { background: #fff3da; color: #95620e; }

        .modal-content {
            border: 1px solid var(--line);
            border-radius: 15px;
            box-shadow: 0 24px 70px rgba(29, 55, 62, .18);
        }

        .modal-header,
        .modal-footer { border-color: var(--line); }

        .empty-state {
            padding: 38px 20px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .navbar-custom .container { min-height: 64px; }
            .user-chip { display: none; }
            .page-heading { align-items: flex-start; flex-direction: column; }
            .panel-body { padding: 16px; }
            .table { min-width: 720px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark navbar-custom">
        <div class="container d-flex justify-content-between align-items-center gap-3">
            <a class="navbar-brand fw-bold text-white" href="index.php">
                <span class="brand-mark me-2"><i class="bi bi-box-seam-fill"></i></span>
                SIMBAR Admin
            </a>
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <span class="user-chip d-none d-md-inline-flex align-items-center">
                    <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['nama'] ?? 'Admin') ?>
                </span>
                <a href="../logout.php" class="btn btn-sm btn-light px-3">
                    <i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Keluar</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container my-4 my-md-5">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Administrasi SIMBAR</p>
                <h1>Kelola pengguna</h1>
                <p class="page-description">Tambah akun, atur role, perbarui profil, atau reset password.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke dashboard
            </a>
        </div>

        <?php if ($pesan_info): ?>
            <div class="alert alert-<?= h($pesan_info[0]) ?> alert-dismissible fade show" role="alert">
                <?= h($pesan_info[1]) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert"><?= h($error_message) ?></div>
        <?php endif; ?>

        <section class="panel" aria-labelledby="create-user-heading">
            <div class="panel-heading">
                <h2 id="create-user-heading">Tambah pengguna</h2>
                <p>Buat akun baru dan tentukan hak akses awalnya.</p>
            </div>
            <div class="panel-body">
                <form action="pengguna_aksi.php" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label" for="nama">Nama lengkap</label>
                        <input class="form-control" type="text" id="nama" name="nama" maxlength="100" required>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" type="text" id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" autocomplete="off" required>
                        <div class="form-text">3–50 karakter: huruf, angka, titik, garis bawah, atau strip.</div>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="peminjam">Peminjam</option>
                            <option value="petugas">Petugas</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label" for="password">Password awal</label>
                        <input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <div class="form-text">Minimal 8 karakter.</div>
                    </div>
                    <div class="col-lg-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="bi bi-person-plus me-1"></i>Tambah akun
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel table-panel" aria-labelledby="users-heading">
            <div class="panel-heading d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h2 id="users-heading">Daftar pengguna</h2>
                    <p><?= count($users) ?> akun terdaftar</p>
                </div>
                <span class="role-badge"><i class="bi bi-shield-check me-1"></i>Admin</span>
            </div>
            <?php if (empty($users) && !$error_message): ?>
                <div class="empty-state">Belum ada data pengguna.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Role</th>
                                <th>Terdaftar</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $is_current_user = (int)$user['id'] === $current_user_id;
                                $role_class = $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'petugas' ? 'petugas' : '');
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-name"><?= h($user['nama']) ?><?= $is_current_user ? ' <span class="text-muted fw-normal">(kamu)</span>' : '' ?></div>
                                        <div class="user-username">@<?= h($user['username']) ?></div>
                                    </td>
                                    <td><span class="role-badge <?= h($role_class) ?>"><?= h($role_labels[$user['role']] ?? $user['role']) ?></span></td>
                                    <td><?= h(date('d M Y', strtotime($user['created_at']))) ?></td>
                                    <td class="text-end text-nowrap">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUserModal"
                                            data-id="<?= (int)$user['id'] ?>"
                                            data-nama="<?= h($user['nama']) ?>"
                                            data-username="<?= h($user['username']) ?>"
                                            data-role="<?= h($user['role']) ?>"
                                            data-current="<?= $is_current_user ? '1' : '0' ?>">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </button>
                                        <?php if (!$is_current_user): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteUserModal"
                                                data-id="<?= (int)$user['id'] ?>"
                                                data-nama="<?= h($user['nama']) ?>"
                                                data-username="<?= h($user['username']) ?>">
                                                <i class="bi bi-trash me-1"></i>Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="pengguna_aksi.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5 fw-bold" id="editUserTitle">Edit pengguna</h2>
                        <p class="form-text mb-0">Kosongkan password jika tidak ingin menggantinya.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="editNama">Nama lengkap</label>
                        <input class="form-control" type="text" id="editNama" name="nama" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editUsername">Username</label>
                        <input class="form-control" type="text" id="editUsername" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" required>
                    </div>
                    <div class="mb-3" id="editRoleGroup">
                        <label class="form-label" for="editRole">Role</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="petugas">Petugas</option>
                            <option value="peminjam">Peminjam</option>
                        </select>
                    </div>
                    <input type="hidden" name="role" id="editOwnRole" value="admin" disabled>
                    <div>
                        <label class="form-label" for="editPassword">Password baru</label>
                        <input class="form-control" type="password" id="editPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password">
                        <div class="form-text">Minimal 8 karakter jika ingin mengatur password baru.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="pengguna_aksi.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" id="deleteUserId">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold text-danger" id="deleteUserTitle">
                        <i class="bi bi-trash me-2"></i>Hapus pengguna
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin menghapus akun ini?</p>
                    <div class="rounded p-3" style="background:var(--paper)">
                        <strong id="deleteUserName"></strong>
                        <div class="user-username" id="deleteUserUsername"></div>
                    </div>
                    <p class="form-text mt-3 mb-0">Akun yang memiliki riwayat peminjaman tidak dapat dihapus.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Hapus akun</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const isCurrentUser = button.dataset.current === '1';

            document.getElementById('editUserId').value = button.dataset.id;
            document.getElementById('editNama').value = button.dataset.nama;
            document.getElementById('editUsername').value = button.dataset.username;
            document.getElementById('editPassword').value = '';
            document.getElementById('editRole').value = button.dataset.role;
            document.getElementById('editRoleGroup').hidden = isCurrentUser;
            document.getElementById('editRole').disabled = isCurrentUser;
            document.getElementById('editOwnRole').disabled = !isCurrentUser;
        });

        document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('deleteUserId').value = button.dataset.id;
            document.getElementById('deleteUserName').textContent = button.dataset.nama;
            document.getElementById('deleteUserUsername').textContent = '@' + button.dataset.username;
        });
    </script>
</body>
</html>
