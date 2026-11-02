<?php
require_once 'config/koneksi.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_register = isset($_GET['mode']) && $_GET['mode'] === 'register';
$pesan = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$notifikasi = [
    'gagal' => ['error', 'Login belum berhasil', 'Username atau password yang kamu masukkan salah.'],
    'login_csrf' => ['error', 'Sesi formulir kedaluwarsa', 'Muat ulang halaman, lalu coba masuk kembali.'],
    'sistem' => ['error', 'Login belum dapat diproses', 'Terjadi kendala sementara. Silakan coba beberapa saat lagi.'],
    'daftar_sukses' => ['success', 'Pendaftaran berhasil', 'Akun kamu sudah dibuat. Silakan masuk menggunakan akun tersebut.'],
    'username_terpakai' => ['error', 'Username sudah digunakan', 'Silakan pilih username lain.'],
    'daftar_invalid' => ['error', 'Data belum valid', 'Periksa kembali nama, username, dan password yang kamu masukkan.'],
    'daftar_csrf' => ['error', 'Permintaan tidak valid', 'Muat ulang halaman lalu coba daftar kembali.'],
    'daftar_gagal' => ['error', 'Pendaftaran belum berhasil', 'Terjadi kesalahan saat membuat akun. Silakan coba lagi.'],
][$pesan] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $is_register ? 'Daftar akun SIMBAR untuk meminjam barang.' : 'Masuk ke SIMBAR untuk mengelola inventaris dan peminjaman barang.' ?>">
    <title><?= $is_register ? 'Daftar' : 'Masuk' ?> — SIMBAR</title>
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

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 32px 20px;
            background:
                radial-gradient(ellipse at 12% 13%, rgba(20, 107, 104, .09), transparent 34%),
                var(--paper);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.55;
        }

        body::before {
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image: linear-gradient(rgba(23, 42, 58, .025) 1px, transparent 1px), linear-gradient(90deg, rgba(23, 42, 58, .025) 1px, transparent 1px);
            background-size: 34px 34px;
            content: "";
            mask-image: linear-gradient(to bottom, black, transparent 80%);
            pointer-events: none;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .login-layout {
            width: min(1000px, 100%);
            min-height: 600px;
            display: grid;
            grid-template-columns: 1.03fr .97fr;
            overflow: hidden;
            border: 1px solid rgba(23, 42, 58, .09);
            border-radius: 24px;
            background: var(--white);
            box-shadow: 0 30px 90px rgba(29, 55, 62, .14), 0 4px 12px rgba(29, 55, 62, .04);
        }

        .intro {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px;
            background: linear-gradient(145deg, #146b68 0%, #105653 100%);
            color: var(--white);
        }

        .intro::before,
        .intro::after {
            position: absolute;
            width: 310px;
            height: 310px;
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }

        .intro::before {
            right: -152px;
            bottom: 20px;
            box-shadow: 0 0 0 36px rgba(255, 255, 255, .035), 0 0 0 72px rgba(255, 255, 255, .025);
        }

        .intro::after {
            right: -205px;
            bottom: -34px;
        }

        .brand {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 11px;
            width: fit-content;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: .05em;
        }

        .brand-mark {
            width: 41px;
            height: 41px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 13px;
            background: rgba(255, 255, 255, .12);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .intro-copy {
            position: relative;
            z-index: 1;
            max-width: 390px;
            margin: 0 0 20px;
        }

        .intro-label {
            margin: 0 0 13px;
            color: #bfe2d8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .intro h1 {
            margin: 0 0 15px;
            font-size: clamp(31px, 4vw, 43px);
            line-height: 1.13;
            letter-spacing: -.045em;
        }

        .intro-copy > p:last-child {
            margin: 0;
            color: rgba(255, 255, 255, .78);
            font-size: 14px;
            line-height: 1.75;
        }

        .intro-points {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .intro-point {
            display: flex;
            align-items: center;
            gap: 11px;
            color: rgba(255, 255, 255, .88);
            font-size: 12px;
        }

        .intro-point-icon {
            width: 30px;
            height: 30px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 9px;
            background: rgba(255, 255, 255, .1);
            color: #d6f1e7;
            font-size: 13px;
        }

        .secure-note {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 9px;
            width: fit-content;
            margin-top: 28px;
            padding: 9px 12px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 10px;
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 255, 255, .82);
            font-size: 11px;
        }

        .intro-footer {
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, .68);
            font-size: 11px;
        }

        .form-panel {
            display: flex;
            align-items: center;
            padding: 50px clamp(34px, 5vw, 62px);
        }

        .form-content {
            width: 100%;
        }

        .form-content h2 {
            margin: 0 0 7px;
            font-size: 31px;
            letter-spacing: -.04em;
        }

        .form-description {
            margin: 0 0 29px;
            color: var(--muted);
            font-size: 14px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 21px;
            padding: 12px 14px;
            border: 1px solid #f1caca;
            border-radius: 9px;
            background: #fff3f2;
            color: #a43737;
            font-size: 13px;
        }

        .alert-success {
            border-color: #c9e6d3;
            background: #eff9f1;
            color: #28643a;
        }

        .alert strong {
            display: block;
            margin-bottom: 1px;
        }

        .field {
            margin-bottom: 17px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: #314556;
            font-size: 13px;
            font-weight: 700;
        }

        .field input {
            width: 100%;
            min-height: 50px;
            padding: 0 14px;
            border: 1px solid #dfe5e3;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 14px;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .field input::placeholder {
            color: #a0abb2;
        }

        .field input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(20, 107, 104, .12);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 68px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            padding: 5px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--brand);
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
        }

        .submit-button {
            width: 100%;
            min-height: 51px;
            margin-top: 7px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: var(--white);
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 750;
            box-shadow: 0 8px 18px rgba(20, 107, 104, .16);
            transition: box-shadow .2s ease, transform .2s ease, filter .2s ease;
        }

        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 11px 22px rgba(20, 107, 104, .22);
            filter: brightness(.97);
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 1px 0 16px;
        }

        .remember-choice {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            color: #526576;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .remember-choice input {
            width: 17px;
            height: 17px;
            margin: 0;
            accent-color: var(--brand);
        }

        .remember-hint {
            color: #87949d;
            font-size: 10px;
        }

        .back-link {
            display: block;
            margin-top: 24px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .back-link:hover {
            color: var(--brand);
        }

        .auth-switch {
            margin: 19px 0 0;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .auth-switch a {
            color: var(--brand);
            font-weight: 700;
        }

        .auth-switch a:hover {
            color: var(--brand-dark);
            text-decoration: underline;
        }

        @media (max-width: 680px) {
            body {
                padding: 18px 14px;
            }

            .login-layout {
                max-width: 440px;
                min-height: 0;
                grid-template-columns: 1fr;
                border-radius: 19px;
            }

            .intro {
                min-height: 220px;
                padding: 24px 25px;
            }

            .intro-copy {
                margin: 27px 0 0;
            }

            .intro h1 {
                max-width: 300px;
                margin-bottom: 8px;
                font-size: 29px;
            }

            .intro-label,
            .intro-footer,
            .intro-points,
            .secure-note {
                display: none;
            }

            .intro-copy > p:last-child {
                max-width: 320px;
                font-size: 12px;
            }

            .form-panel {
                padding: 32px 25px 29px;
            }

            .form-content h2 {
                font-size: 25px;
            }

            .remember-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 7px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <main class="login-layout">
        <section class="intro" aria-label="Tentang SIMBAR">
            <a class="brand" href="index.php" aria-label="SIMBAR, kembali ke beranda">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 8.5 12 4l8 4.5v9L12 22l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="m4.5 8.8 7.5 4.3 7.5-4.3M12 13.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>SIMBAR</span>
            </a>
            <div class="intro-copy">
                <p class="intro-label">Sistem Inventaris &amp; Peminjaman Barang</p>
                <h1>Semua barang, lebih teratur.</h1>
                <p>Kelola inventaris dan pantau peminjaman dalam satu tempat.</p>
                <div class="intro-points" aria-label="Keunggulan SIMBAR">
                    <div class="intro-point"><span class="intro-point-icon" aria-hidden="true">✓</span><span>Inventaris dan stok mudah dipantau</span></div>
                    <div class="intro-point"><span class="intro-point-icon" aria-hidden="true">↗</span><span>Pengajuan dan status tersusun rapi</span></div>
                    <div class="intro-point"><span class="intro-point-icon" aria-hidden="true">▣</span><span>Akses sesuai peran pengguna</span></div>
                </div>
            </div>
            <div class="secure-note"><span aria-hidden="true">◈</span> Ruang kerja yang aman dan terorganisir</div>
            <div class="intro-footer">Pengelolaan yang jelas dimulai dari sini.</div>
        </section>

        <section class="form-panel" aria-labelledby="form-heading">
            <div class="form-content">
                <h2 id="form-heading"><?= $is_register ? 'Buat akun baru' : 'Selamat datang' ?></h2>
                <p class="form-description"><?= $is_register ? 'Daftar sebagai peminjam untuk mulai menggunakan SIMBAR.' : 'Masuk ke akun SIMBAR untuk melanjutkan.' ?></p>

                <?php if ($notifikasi): ?>
                    <div class="alert<?= $notifikasi[0] === 'success' ? ' alert-success' : '' ?>" role="alert">
                        <span aria-hidden="true"><?= $notifikasi[0] === 'success' ? '✓' : '!' ?></span>
                        <span><strong><?= htmlspecialchars($notifikasi[1]) ?></strong><?= htmlspecialchars($notifikasi[2]) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($is_register): ?>
                <form action="register_proses.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="field">
                        <label for="nama">Nama lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" autocomplete="name" maxlength="100" required autofocus>
                    </div>
                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="3–50 karakter: huruf, angka, titik, _ atau -" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <button class="toggle-password" type="button" data-password-target="password" aria-controls="password" aria-pressed="false">Lihat</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Ulangi password</label>
                        <div class="password-wrap">
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Masukkan ulang password" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <button class="toggle-password" type="button" data-password-target="password_confirmation" aria-controls="password_confirmation" aria-pressed="false">Lihat</button>
                        </div>
                    </div>
                    <button class="submit-button" type="submit">Buat akun peminjam</button>
                </form>
                <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk</a></p>
                <?php else: ?>
                <form action="login_proses.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Masukkan username" autocomplete="username" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                            <button class="toggle-password" type="button" data-password-target="password" aria-controls="password" aria-pressed="false">Lihat</button>
                        </div>
                    </div>
                    <div class="remember-row">
                        <label class="remember-choice" for="remember_me">
                            <input type="checkbox" id="remember_me" name="remember_me" value="1">
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                        <span class="remember-hint">Tetap masuk hingga 30 hari</span>
                    </div>
                    <button class="submit-button" type="submit">Masuk ke akun</button>
                </form>
                <p class="auth-switch">Belum punya akun? <a href="login.php?mode=register">Daftar sekarang</a></p>
                <?php endif; ?>

                <a class="back-link" href="index.php">← Kembali ke beranda</a>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('.toggle-password').forEach((togglePassword) => {
            const passwordInput = document.getElementById(togglePassword.dataset.passwordTarget);

            togglePassword.addEventListener('click', () => {
                const isVisible = passwordInput.type === 'text';
                passwordInput.type = isVisible ? 'password' : 'text';
                togglePassword.textContent = isVisible ? 'Lihat' : 'Sembunyikan';
                togglePassword.setAttribute('aria-pressed', String(!isVisible));
            });
        });
    </script>
</body>
</html>
