<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIMBAR membantu mengelola inventaris dan peminjaman barang dengan lebih mudah dan teratur.">
    <title>SIMBAR — Inventaris dan Peminjaman Barang</title>
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

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(1120px, calc(100% - 48px));
            margin: 0 auto;
        }

        .site-header {
            border-bottom: 1px solid rgba(23, 42, 58, .08);
            background: rgba(251, 250, 246, .94);
        }

        .navbar {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--brand);
            color: var(--white);
        }

        .brand-mark svg {
            width: 23px;
            height: 23px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
        }

        .nav-links a:hover {
            color: var(--brand);
        }

        .button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 21px;
            border: 1px solid var(--brand);
            border-radius: 9px;
            background: var(--brand);
            color: var(--white);
            font-size: 14px;
            font-weight: 700;
            transition: background .2s ease, transform .2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
            background: var(--brand-dark);
        }

        .button-outline {
            background: transparent;
            color: var(--brand);
        }

        .button-outline:hover {
            background: var(--mint);
            color: var(--brand-dark);
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 82px 0 88px;
        }

        .hero::after {
            position: absolute;
            z-index: -1;
            top: 26px;
            right: -120px;
            width: 510px;
            height: 510px;
            border: 1px solid rgba(20, 107, 104, .1);
            border-radius: 50%;
            box-shadow: 0 0 0 62px rgba(20, 107, 104, .025), 0 0 0 124px rgba(20, 107, 104, .02);
            content: "";
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            align-items: center;
            gap: 64px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 19px;
            color: var(--brand);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 23px;
            height: 2px;
            background: currentColor;
            content: "";
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        h1 {
            max-width: 610px;
            margin-bottom: 20px;
            font-size: clamp(42px, 5.3vw, 66px);
            line-height: 1.08;
            letter-spacing: -.055em;
        }

        h1 span {
            color: var(--brand);
        }

        .hero-copy {
            max-width: 510px;
            margin-bottom: 29px;
            color: var(--muted);
            font-size: 17px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-note {
            margin: 20px 0 0;
            color: #78868e;
            font-size: 12px;
        }

        .preview {
            position: relative;
            padding: 23px;
            border: 1px solid rgba(23, 42, 58, .08);
            border-radius: 19px;
            background: var(--white);
            box-shadow: 0 24px 70px rgba(29, 55, 62, .12);
            transform: rotate(1deg);
        }

        .preview-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .preview-title {
            margin: 0;
            font-size: 15px;
            font-weight: 750;
        }

        .preview-date {
            color: #87949b;
            font-size: 11px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 19px;
        }

        .stat {
            padding: 13px 12px;
            border-radius: 11px;
            background: #f7f8f5;
        }

        .stat-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .stat strong {
            font-size: 21px;
            letter-spacing: -.04em;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .preview-table th {
            padding: 10px 7px;
            color: #87949b;
            font-size: 9px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
        }

        .preview-table td {
            padding: 12px 7px;
            border-top: 1px solid #eef0ec;
        }

        .preview-table td:first-child {
            font-weight: 650;
        }

        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            background: #e9f5ed;
            color: #34734c;
            font-size: 9px;
            font-weight: 700;
        }

        .status.pending {
            background: #fff3da;
            color: #a26d15;
        }

        .floating-note {
            position: absolute;
            right: -23px;
            bottom: -27px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 16px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--white);
            box-shadow: 0 12px 32px rgba(29, 55, 62, .1);
            font-size: 11px;
            font-weight: 700;
        }

        .check-icon {
            width: 29px;
            height: 29px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--mint);
            color: var(--brand);
        }

        .section {
            padding: 76px 0 84px;
            border-top: 1px solid var(--line);
            background: #fff;
        }

        .section-heading {
            max-width: 570px;
            margin: 0 auto 39px;
            text-align: center;
        }

        .section-heading .eyebrow {
            justify-content: center;
        }

        h2 {
            margin-bottom: 12px;
            font-size: clamp(29px, 3.2vw, 39px);
            line-height: 1.2;
            letter-spacing: -.04em;
        }

        .section-heading p:last-child {
            margin-bottom: 0;
            color: var(--muted);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .feature {
            padding: 25px 24px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            margin-bottom: 19px;
            border-radius: 12px;
            background: var(--mint);
            color: var(--brand);
        }

        .feature-icon svg {
            width: 22px;
            height: 22px;
        }

        .feature h3 {
            margin-bottom: 8px;
            font-size: 17px;
        }

        .feature p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .closing {
            padding: 56px 0;
            background: var(--brand);
            color: var(--white);
        }

        .closing-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .closing h2 {
            margin-bottom: 7px;
        }

        .closing p {
            margin-bottom: 0;
            color: rgba(255, 255, 255, .76);
        }

        .closing .button {
            flex-shrink: 0;
            border-color: var(--white);
            background: var(--white);
            color: var(--brand);
        }

        .closing .button:hover {
            background: var(--mint);
        }

        footer {
            padding: 21px 0;
            background: #105653;
            color: rgba(255, 255, 255, .72);
            font-size: 12px;
        }

        .footer-inner {
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        footer p {
            margin: 0;
        }

        @media (max-width: 820px) {
            .hero {
                padding: 60px 0 72px;
            }

            .hero-grid {
                grid-template-columns: 1fr;
                gap: 47px;
            }

            .hero-copy {
                max-width: 600px;
            }

            .preview {
                width: min(100%, 560px);
                margin: 0 auto;
                transform: none;
            }
        }

        @media (max-width: 600px) {
            .container {
                width: min(100% - 32px, 1120px);
            }

            .navbar {
                min-height: 68px;
            }

            .nav-links {
                display: none;
            }

            .navbar > .button {
                min-height: 41px;
                padding: 0 15px;
            }

            .hero {
                padding: 49px 0 62px;
            }

            h1 {
                font-size: 43px;
            }

            .hero-copy {
                font-size: 15px;
            }

            .preview {
                padding: 17px 13px;
            }

            .stats {
                gap: 6px;
            }

            .stat {
                padding: 10px 8px;
            }

            .stat strong {
                font-size: 18px;
            }

            .preview-table {
                font-size: 10px;
            }

            .preview-table th,
            .preview-table td {
                padding-right: 4px;
                padding-left: 4px;
            }

            .floating-note {
                right: 7px;
                bottom: -34px;
            }

            .section {
                padding: 62px 0;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .feature {
                padding: 21px;
            }

            .closing-inner,
            .footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .closing-inner {
                padding: 0 3px;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="navbar container" aria-label="Navigasi utama">
            <a class="brand" href="#beranda" aria-label="SIMBAR, beranda">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 8.5 12 4l8 4.5v9L12 22l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="m4.5 8.8 7.5 4.3 7.5-4.3M12 13.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>SIMBAR</span>
            </a>
            <div class="nav-links">
                <a href="#beranda">Beranda</a>
                <a href="#fitur">Fitur</a>
                <a href="#tentang">Tentang</a>
            </div>
            <a class="button" href="login.php">Masuk ke aplikasi</a>
        </nav>
    </header>

    <main>
        <section class="hero" id="beranda">
            <div class="hero-grid container">
                <div>
                    <p class="eyebrow">Kelola barang lebih mudah</p>
                    <h1>Semua inventaris, <span>lebih teratur.</span></h1>
                    <p class="hero-copy">
                        SIMBAR membantu pencatatan inventaris dan peminjaman barang jadi lebih praktis,
                        transparan, dan mudah dipantau.
                    </p>
                    <div class="hero-actions">
                        <a class="button" href="login.php">Mulai sekarang <span aria-hidden="true">&nbsp;→</span></a>
                        <a class="button button-outline" href="#fitur">Kenali fitur</a>
                    </div>
                    <p class="hero-note">Satu tempat untuk pencatatan barang dan transaksi peminjaman.</p>
                </div>

                <div class="preview" aria-label="Contoh ringkasan transaksi SIMBAR">
                    <div class="preview-top">
                        <p class="preview-title">Ringkasan peminjaman</p>
                        <span class="preview-date">Aktivitas terbaru</span>
                    </div>
                    <div class="stats">
                        <div class="stat"><span class="stat-label">Total barang</span><strong>128</strong></div>
                        <div class="stat"><span class="stat-label">Dipinjam</span><strong>16</strong></div>
                        <div class="stat"><span class="stat-label">Menunggu</span><strong>03</strong></div>
                    </div>
                    <table class="preview-table">
                        <thead>
                            <tr><th>Peminjam</th><th>Barang</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Andi Saputra</td><td>Proyektor</td><td><span class="status">Disetujui</span></td></tr>
                            <tr><td>Nadia Putri</td><td>Laptop</td><td><span class="status pending">Menunggu</span></td></tr>
                            <tr><td>Rizky Maulana</td><td>Kamera</td><td><span class="status">Disetujui</span></td></tr>
                        </tbody>
                    </table>
                    <div class="floating-note">
                        <span class="check-icon" aria-hidden="true">✓</span>
                        Data tersimpan dengan rapi
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="fitur">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">Dirancang untuk kebutuhanmu</p>
                    <h2>Pengelolaan barang tanpa ribet</h2>
                    <p>Catat, pantau, dan kelola proses peminjaman dalam satu alur yang jelas.</p>
                </div>
                <div class="features">
                    <article class="feature">
                        <div class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12.2V21" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>Data inventaris</h3>
                        <p>Informasi barang tersusun rapi sehingga lebih mudah dicari dan dikelola.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="m9 14 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>Peminjaman terpantau</h3>
                        <p>Lihat status pengajuan dan catatan transaksi tanpa mencari data satu per satu.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 19V5m0 14h16M8 15v-4m4 4V7m4 8V9m4 6V5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>Informasi lebih jelas</h3>
                        <p>Ringkasan data membantu admin dan petugas memahami aktivitas barang dengan cepat.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="closing" id="tentang">
            <div class="closing-inner container">
                <div>
                    <h2>Siap mengelola barang lebih baik?</h2>
                    <p>Masuk ke SIMBAR untuk mulai mengelola inventaris dan peminjaman.</p>
                </div>
                <a class="button" href="simbar/login.php">Masuk ke SIMBAR <span aria-hidden="true">&nbsp;→</span></a>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-inner container">
            <p><strong>SIMBAR</strong> · Sistem Inventaris &amp; Peminjaman Barang</p>
            <p>&copy; <?= date('Y'); ?> SIMBAR. Dibuat untuk pengelolaan yang lebih tertata.</p>
        </div>
    </footer>
</body>
</html>
