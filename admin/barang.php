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
    'dihapus' => ['success', 'Barang berhasil dihapus.'],
    'invalid' => ['error', 'Data barang tidak valid. Periksa kembali isian.'],
    'stok_dipesan' => ['error', 'Stok baru tidak boleh lebih kecil dari jumlah barang yang sedang dipesan.'],
    'memiliki_transaksi' => ['error', 'Barang yang sudah tercatat dalam transaksi tidak dapat dihapus.'],
    'tidak_ada' => ['error', 'Barang tidak ditemukan.'],
    'csrf' => ['error', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'],
    'gagal' => ['error', 'Perubahan belum berhasil disimpan. Silakan coba kembali.'],
    'foto_invalid' => ['error', 'Format foto tidak didukung. Gunakan gambar JPG, PNG, atau WebP.'],
    'foto_terlalu_besar' => ['error', 'Ukuran foto maksimal 5 MB.'],
    'foto_gagal' => ['error', 'Foto gagal diunggah. Silakan coba kembali.'],
];
$pesan_key = isset($_GET['pesan']) && is_string($_GET['pesan']) ? $_GET['pesan'] : '';
$pesan_info = $pesan_map[$pesan_key] ?? null;

$barang_list = [];
$error_message = null;
try {
    $stmt = $pdo->query(
        "SELECT b.id, b.nama_barang, b.kategori, b.stok, b.kondisi, b.foto,
                COALESCE(SUM(
                    CASE WHEN p.status IN ('Pending', 'Disetujui')
                    THEN p.jumlah_pinjam ELSE 0 END
                ), 0) AS stok_dipesan,
                COUNT(p.id) AS jumlah_transaksi
         FROM barang b
         LEFT JOIN peminjaman p ON p.barang_id = b.id
         GROUP BY b.id, b.nama_barang, b.kategori, b.stok, b.kondisi, b.foto
         ORDER BY b.nama_barang ASC"
    );
    $barang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('SIMBAR inventory list query failed: ' . $e->getMessage());
    $error_message = 'Daftar barang belum dapat dimuat. Silakan muat ulang halaman.';
}

$csrf = $_SESSION['csrf_token'];
$dashboard_url = $_SESSION['role'] === 'admin' ? 'index.php' : '../petugas/index.php';
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
        .brand { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; letter-spacing: .04em; }
        .brand-mark { width: 36px; height: 36px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.25); border-radius: 11px; background: rgba(255,255,255,.12); }
        .top-actions { display: flex; align-items: center; gap: 10px; font-size: 12px; }
        .top-actions a { display: inline-flex; min-height: 36px; align-items: center; padding: 0 12px; border: 1px solid rgba(255,255,255,.5); border-radius: 8px; background: var(--white); color: var(--brand); font-weight: 700; text-decoration: none; }
        main { padding: 32px 0 55px; }
        .heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .eyebrow { margin: 0 0 5px; color: var(--brand); font-size: 10px; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        h1 { margin: 0 0 5px; font-size: clamp(26px,4vw,34px); letter-spacing: -.04em; }
        .muted { margin: 0; color: var(--muted); font-size: 13px; }
        .panel { overflow: hidden; border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: 0 8px 24px rgba(29,55,62,.045); }
        .panel-heading { padding: 17px 19px; border-bottom: 1px solid var(--line); }
        .panel-heading h2 { margin: 0 0 3px; font-size: 16px; }
        .panel-heading p { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 19px; }
        .form-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); align-items: end; gap: 12px; }
        .name-field { grid-column: span 2; }
        label { display: block; margin-bottom: 5px; color: #314556; font-size: 12px; font-weight: 700; }
        input, select { width: 100%; min-height: 42px; padding: 0 11px; border: 1px solid #dfe5e3; border-radius: 9px; outline: none; background: var(--white); color: var(--ink); font: inherit; font-size: 13px; }
        input[type="file"] { height: auto; min-height: 42px; padding: 8px; }
        input:focus, select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(20,107,104,.12); }
        button, .back { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 14px; border: 0; border-radius: 9px; background: var(--brand); color: var(--white); cursor: pointer; font: inherit; font-size: 12px; font-weight: 750; text-decoration: none; }
        button:hover, .back:hover { background: var(--brand-dark); color: var(--white); }
        .table-panel { margin-top: 20px; }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 11px 13px; border-bottom: 1px solid var(--line); background: #f5f8f5; color: var(--muted); font-size: 10px; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
        td { padding: 12px 13px; border-bottom: 1px solid #eef1ed; font-size: 12px; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        .item-name { font-weight: 700; }
        .subtext { display: block; margin-top: 2px; color: var(--muted); font-size: 10px; }
        .item-cell { display: flex; align-items: center; gap: 10px; min-width: 180px; }
        .item-photo { width: 48px; height: 48px; flex: 0 0 auto; display: grid; place-items: center; overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: #f2f7f4; color: var(--brand); }
        .item-photo img { width: 100%; height: 100%; object-fit: cover; }
        .photo-field { grid-column: span 3; }
        .photo-preview { width: 100%; height: 110px; display: grid; place-items: center; margin-top: 8px; overflow: hidden; border: 1px dashed #cbd9d3; border-radius: 10px; background: #f7faf8; color: var(--muted); font-size: 12px; }
        .photo-preview img { width: 100%; height: 100%; object-fit: contain; }
        .file-hint { display: block; margin-top: 5px; color: var(--muted); font-size: 10px; }
        .stock-pill { display: inline-flex; padding: 4px 8px; border-radius: 99px; background: var(--mint); color: var(--brand-dark); font-size: 10px; font-weight: 750; white-space: nowrap; }
        .stock-pill.empty { background: #fff0ef; color: #a43737; }
        .actions { display: flex; gap: 6px; justify-content: flex-end; }
        .actions button { min-height: 32px; padding: 0 9px; background: #eef4f1; color: var(--brand-dark); }
        .actions button:hover { background: var(--mint); }
        .actions .delete { background: #fff0ef; color: #a43737; }
        .actions .delete:hover { background: #ffe1df; }
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
        @media (max-width: 800px) { .form-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } .name-field, .photo-field { grid-column: 1 / -1; } .form-grid button { width: 100%; } }
        @media (max-width: 600px) {
            .container { width: min(100% - 28px,1120px); }
            .topbar-inner { min-height: 60px; }
            .top-actions { gap: 6px; }
            .top-actions a { min-height: 33px; padding: 0 9px; }
            main { padding-top: 24px; }
            .heading { align-items: flex-start; flex-direction: column; }
            .panel-body { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; gap: 10px; }
            .name-field, .photo-field { grid-column: auto; }
            .table-scroll table { display: block; }
            .table-scroll thead { display: none; }
            .table-scroll tbody { display: grid; gap: 10px; padding: 11px; }
            .table-scroll tbody tr { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; padding: 10px; border: 1px solid var(--line); border-radius: 11px; background: var(--white); }
            .table-scroll tbody td { display: flex; flex-direction: column; gap: 3px; padding: 7px 4px; border: 0; }
            .table-scroll tbody td::before { content: attr(data-label); color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
            .table-scroll tbody td:first-child { grid-column: 1 / -1; }
            .table-scroll tbody td:last-child { grid-column: 1 / -1; }
            .actions { justify-content: flex-start; }
        }
        @media (max-width: 390px) {
            .top-actions span { display: none; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner container">
            <a class="brand" href="<?= h($dashboard_url) ?>"><span class="brand-mark">▦</span>SIMBAR · Inventaris</a>
            <div class="top-actions">
                <span><?= h($_SESSION['nama'] ?? 'Pengguna') ?></span>
                <a href="<?= h($dashboard_url) ?>">Kembali</a>
                <a href="../logout.php">Keluar</a>
            </div>
        </div>
    </header>
    <main class="container">
        <div class="heading">
            <div>
                <p class="eyebrow">Kelola barang</p>
                <h1>Data inventaris</h1>
                <p class="muted">Tambah barang, perbarui stok, dan lihat jumlah yang sedang dipesan.</p>
            </div>
        </div>
        <?php if ($pesan_info): ?>
            <div class="alert<?= $pesan_info[0] === 'success' ? ' success' : '' ?>" role="alert"><?= h($pesan_info[1]) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?><div class="alert" role="alert"><?= h($error_message) ?></div><?php endif; ?>

        <section class="panel" aria-labelledby="create-title">
            <div class="panel-heading"><h2 id="create-title">Tambah barang</h2><p>Masukkan detail barang inventaris baru.</p></div>
            <div class="panel-body">
                <form action="barang_aksi.php" method="post" enctype="multipart/form-data" class="form-grid" id="createForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="name-field"><label for="nama_barang">Nama barang</label><input id="nama_barang" name="nama_barang" maxlength="100" required></div>
                    <div><label for="kategori">Kategori</label><input id="kategori" name="kategori" maxlength="50" required></div>
                    <div><label for="stok">Jumlah stok</label><input id="stok" name="stok" type="number" min="0" step="1" value="0" required></div>
                    <div><label for="kondisi">Kondisi</label><select id="kondisi" name="kondisi" required><option>Baik</option><option>Rusak Ringan</option></select></div>
                    <div class="photo-field">
                        <label for="createPhoto">Foto barang <span class="subtext">(opsional)</span></label>
                        <input id="createPhoto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                        <span class="file-hint">JPG, PNG, atau WebP · maksimal 5 MB</span>
                        <div class="photo-preview" id="createPhotoPreview" aria-live="polite">Pratinjau foto</div>
                    </div>
                    <button type="submit">Tambah barang</button>
                </form>
            </div>
        </section>

        <section class="panel table-panel" aria-labelledby="inventory-title">
            <div class="panel-heading"><h2 id="inventory-title">Daftar inventaris</h2><p><?= count($barang_list) ?> jenis barang terdaftar</p></div>
            <?php if ($error_message): ?>
                <div class="empty"><?= h($error_message) ?></div>
            <?php elseif (!$barang_list): ?>
                <div class="empty">Belum ada data barang. Tambahkan barang melalui formulir di atas.</div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Barang</th><th>Kategori</th><th>Stok</th><th>Tersedia</th><th>Kondisi</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($barang_list as $barang): ?>
                                <?php $tersedia = max(0, (int)$barang['stok'] - (int)$barang['stok_dipesan']); ?>
                                <tr>
                                    <td data-label="Barang">
                                        <div class="item-cell">
                                            <span class="item-photo">
                                                <?php if (!empty($barang['foto'])): ?>
                                                    <img src="../uploads/barang/<?= h($barang['foto']) ?>" alt="" loading="lazy">
                                                <?php else: ?>
                                                    <span aria-hidden="true">▦</span>
                                                <?php endif; ?>
                                            </span>
                                            <span><span class="item-name"><?= h($barang['nama_barang']) ?></span><span class="subtext"><?= (int)$barang['jumlah_transaksi'] ?> transaksi</span></span>
                                        </div>
                                    </td>
                                    <td data-label="Kategori"><?= h($barang['kategori']) ?></td>
                                    <td data-label="Stok"><?= (int)$barang['stok'] ?></td>
                                    <td data-label="Tersedia"><span class="stock-pill<?= $tersedia === 0 ? ' empty' : '' ?>"><?= $tersedia ?> unit</span></td>
                                    <td data-label="Kondisi"><?= h($barang['kondisi']) ?></td>
                                    <td data-label="Aksi">
                                        <div class="actions">
                                            <button type="button" class="edit-button"
                                                data-id="<?= (int)$barang['id'] ?>"
                                                data-name="<?= h($barang['nama_barang']) ?>"
                                                data-category="<?= h($barang['kategori']) ?>"
                                                data-stock="<?= (int)$barang['stok'] ?>"
                                                data-condition="<?= h($barang['kondisi']) ?>"
                                                data-photo="<?= h($barang['foto'] ?? '') ?>"
                                                data-reserved="<?= (int)$barang['stok_dipesan'] ?>">Edit</button>
                                            <?php if ((int)$barang['jumlah_transaksi'] === 0): ?>
                                                <form method="post" action="barang_aksi.php" onsubmit="return confirm('Hapus barang ini?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                    <input type="hidden" name="aksi" value="hapus">
                                                    <input type="hidden" name="id" value="<?= (int)$barang['id'] ?>">
                                                    <button class="delete" type="submit">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal-backdrop" id="editModal" role="presentation">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="editTitle">
            <h2 id="editTitle">Perbarui barang</h2>
            <form action="barang_aksi.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id" id="editId">
                <div class="field">
                    <label for="editPhoto">Foto barang</label>
                    <input id="editPhoto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                    <span class="file-hint">Pilih foto baru untuk mengganti foto saat ini. Maksimal 5 MB.</span>
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
        function previewSelectedPhoto(input, container, emptyText) {
            let previewUrl = null;
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = file ? URL.createObjectURL(file) : null;
                setPhotoPreview(container, previewUrl || '', emptyText);
            });
        }
        previewSelectedPhoto(
            document.getElementById('createPhoto'),
            document.getElementById('createPhotoPreview'),
            'Pratinjau foto'
        );
        previewSelectedPhoto(
            document.getElementById('editPhoto'),
            document.getElementById('editPhotoPreview'),
            'Belum ada foto'
        );
        document.querySelectorAll('.edit-button').forEach((button) => {
            button.addEventListener('click', () => {
                lastButton = button;
                document.getElementById('editPhoto').value = '';
                document.getElementById('removePhoto').checked = false;
                setPhotoPreview(
                    document.getElementById('editPhotoPreview'),
                    button.dataset.photo ? '../uploads/barang/' + button.dataset.photo : '',
                    'Belum ada foto'
                );
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
    </script>
</body>
</html>
