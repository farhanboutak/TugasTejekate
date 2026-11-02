<?php
require_once 'config/koneksi.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'petugas', 'peminjam'], true)) {
    header('Location: login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$dashboard_url = [
    'admin' => 'admin/index.php',
    'petugas' => 'petugas/index.php',
    'peminjam' => 'peminjaman/index.php',
][$role];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? null;
    if (
        !is_string($csrf_token) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        header('Location: akun.php?pesan=csrf');
        exit;
    }

    $nama = isset($_POST['nama']) && is_string($_POST['nama']) ? trim($_POST['nama']) : '';
    $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
    $current_password = isset($_POST['current_password']) && is_string($_POST['current_password'])
        ? $_POST['current_password']
        : '';
    $new_password = isset($_POST['new_password']) && is_string($_POST['new_password'])
        ? $_POST['new_password']
        : '';
    $confirm_password = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';

    if (
        $nama === '' ||
        mb_strlen($nama, 'UTF-8') > 100 ||
        preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1 ||
        $current_password === '' ||
        ($new_password !== '' && (strlen($new_password) < 8 || strlen($new_password) > 72)) ||
        ($new_password !== '' && $new_password !== $confirm_password) ||
        ($new_password === '' && $confirm_password !== '')
    ) {
        header('Location: akun.php?pesan=invalid');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            header('Location: akun.php?pesan=password_salah');
            exit;
        }

        if ($new_password !== '') {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($password_hash === false) {
                error_log('SIMBAR: Password hashing failed while updating profile.');
                header('Location: akun.php?pesan=gagal');
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET nama = :nama, username = :username, password = :password
                 WHERE id = :id'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':username' => $username,
                ':password' => $password_hash,
                ':id' => $user_id,
            ]);
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $user_id]);
            remember_clear_cookie($pdo);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET nama = :nama, username = :username WHERE id = :id'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':username' => $username,
                ':id' => $user_id,
            ]);
        }

        $_SESSION['nama'] = $nama;
        $_SESSION['username'] = $username;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: akun.php?pesan=sukses');
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            header('Location: akun.php?pesan=username_terpakai');
            exit;
        }

        error_log('SIMBAR profile update failed: ' . $e->getMessage());
        header('Location: akun.php?pesan=gagal');
        exit;
    }
}

$messages = [
    'sukses' => ['success', 'Profil berhasil diperbarui.'],
    'csrf' => ['error', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'invalid' => ['error', 'Data profil tidak valid atau konfirmasi password tidak cocok.'],
    'password_salah' => ['error', 'Password saat ini tidak sesuai. Perubahan tidak disimpan.'],
    'username_terpakai' => ['error', 'Username sudah digunakan. Pilih username yang lain.'],
    'gagal' => ['error', 'Profil belum berhasil diperbarui. Silakan coba kembali.'],
];
$message_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$message = $messages[$message_key] ?? null;
$role_label = ['admin' => 'Admin', 'petugas' => 'Petugas', 'peminjam' => 'Peminjam'][$role];
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Perbarui profil akun SIMBAR.">
    <title>Profil akun — SIMBAR</title>
    <style>
        :root { color-scheme: light; --ink:#172a3a; --muted:#647587; --brand:#146b68; --brand-dark:#105653; --mint:#e8f4ef; --paper:#fbfaf6; --line:#e7e9e3; --white:#fff; }
        * { box-sizing: border-box; }
        body { min-height:100vh; margin:0; background:radial-gradient(ellipse at 12% 0,rgba(20,107,104,.07),transparent 34%),var(--paper); color:var(--ink); font-family:"Segoe UI",Arial,sans-serif; line-height:1.55; }
        .topbar { background:var(--brand); color:var(--white); box-shadow:0 8px 24px rgba(16,86,83,.16); }
        .container { width:min(940px,calc(100% - 36px)); margin:0 auto; }
        .topbar-inner { min-height:66px; display:flex; align-items:center; justify-content:space-between; gap:15px; }
        .brand { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:800; letter-spacing:.04em; text-decoration:none; }
        .brand-mark { width:36px; height:36px; display:grid; place-items:center; border:1px solid rgba(255,255,255,.25); border-radius:11px; background:rgba(255,255,255,.12); }
        .back { color:var(--white); font-size:12px; font-weight:700; text-decoration:none; }
        main { padding:42px 0; }
        .eyebrow { margin:0 0 6px; color:var(--brand); font-size:10px; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
        h1 { margin:0 0 7px; font-size:clamp(27px,4vw,36px); letter-spacing:-.045em; }
        .intro { margin:0 0 23px; color:var(--muted); font-size:14px; }
        .card { overflow:hidden; border:1px solid var(--line); border-radius:16px; background:var(--white); box-shadow:0 12px 35px rgba(29,55,62,.07); }
        .card-header { padding:18px 22px; border-bottom:1px solid var(--line); }
        .card-header h2 { margin:0 0 3px; font-size:16px; }
        .card-header p { margin:0; color:var(--muted); font-size:12px; }
        form { padding:22px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .field { margin-bottom:15px; }
        label { display:block; margin-bottom:6px; color:#314556; font-size:12px; font-weight:700; }
        input { width:100%; min-height:44px; padding:0 12px; border:1px solid #dfe5e3; border-radius:9px; outline:none; color:var(--ink); font:inherit; font-size:13px; }
        input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(20,107,104,.12); }
        .hint { margin:5px 0 0; color:var(--muted); font-size:10px; }
        .role { display:inline-flex; margin-bottom:18px; padding:5px 10px; border-radius:99px; background:var(--mint); color:var(--brand-dark); font-size:11px; font-weight:750; }
        .message { margin-bottom:18px; padding:11px 13px; border-radius:9px; background:#fff0ef; color:#a43737; font-size:13px; }
        .message.success { background:#eff9f1; color:#28643a; }
        .actions { display:flex; justify-content:flex-end; gap:10px; margin-top:4px; }
        .actions a,.actions button { min-height:42px; display:inline-flex; align-items:center; justify-content:center; padding:0 16px; border:1px solid var(--line); border-radius:9px; background:var(--white); color:var(--muted); cursor:pointer; font:inherit; font-size:12px; font-weight:700; text-decoration:none; }
        .actions button { border-color:var(--brand); background:var(--brand); color:var(--white); }
        .actions button:hover { background:var(--brand-dark); }
        :focus-visible { outline:3px solid rgba(20,107,104,.25); outline-offset:2px; }
        @media(max-width:600px) { .topbar-inner{min-height:60px;flex-wrap:wrap;padding:10px 0}.back{margin-left:auto} main{padding:28px 0}.card-header,form{padding:17px}.grid{grid-template-columns:1fr;gap:0}.actions{flex-direction:column-reverse}.actions a,.actions button{width:100%} }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="<?= h($dashboard_url) ?>"><span class="brand-mark" aria-hidden="true">▦</span>SIMBAR · Profil</a>
            <a class="back" href="<?= h($dashboard_url) ?>">Kembali ke dashboard</a>
        </div>
    </header>
    <main class="container">
        <p class="eyebrow">Pengaturan akun</p>
        <h1>Profil saya</h1>
        <p class="intro">Perbarui identitas akun atau ganti password dengan aman.</p>
        <?php if ($message): ?>
            <div class="message<?= $message[0] === 'success' ? ' success' : '' ?>" role="alert"><?= h($message[1]) ?></div>
        <?php endif; ?>
        <section class="card" aria-labelledby="profile-title">
            <div class="card-header">
                <h2 id="profile-title">Informasi akun</h2>
                <p>Password saat ini diperlukan untuk menyimpan perubahan.</p>
            </div>
            <form method="post" action="akun.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <span class="role"><?= h($role_label) ?></span>
                <div class="grid">
                    <div class="field">
                        <label for="nama">Nama lengkap</label>
                        <input id="nama" name="nama" value="<?= h($_SESSION['nama'] ?? '') ?>" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" value="<?= h($_SESSION['username'] ?? '') ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" autocomplete="username" required>
                    </div>
                </div>
                <div class="grid">
                    <div class="field">
                        <label for="new_password">Password baru <span class="hint">(opsional)</span></label>
                        <input type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password">
                        <p class="hint">Kosongkan jika tidak ingin mengganti password.</p>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Konfirmasi password baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password">
                    </div>
                </div>
                <div class="field">
                    <label for="current_password">Password saat ini</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="actions">
                    <a href="<?= h($dashboard_url) ?>">Batal</a>
                    <button type="submit">Simpan perubahan</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
