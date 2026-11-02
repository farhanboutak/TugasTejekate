<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

cek_akses(['admin', 'petugas']);

class FotoBarangException extends RuntimeException
{
}

function kembali_ke_barang($pesan)
{
    header('Location: barang.php?pesan=' . urlencode($pesan));
    exit;
}

function simpan_foto_barang($upload)
{
    if ($upload === null) {
        return null;
    }

    if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name'], $upload['size'])) {
        throw new FotoBarangException('foto_invalid');
    }

    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new FotoBarangException('foto_terlalu_besar');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new FotoBarangException('foto_gagal');
    }

    $maksimum_ukuran = 5 * 1024 * 1024;
    if (!is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new FotoBarangException('foto_invalid');
    }
    if (!is_int($upload['size']) || $upload['size'] <= 0 || $upload['size'] > $maksimum_ukuran) {
        throw new FotoBarangException('foto_terlalu_besar');
    }

    if (!function_exists('finfo_open')) {
        throw new FotoBarangException('foto_gagal');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new FotoBarangException('foto_gagal');
    }
    $mime = @finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);

    $ekstensi = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($ekstensi[$mime])) {
        throw new FotoBarangException('foto_invalid');
    }
    $gambar = @getimagesize($upload['tmp_name']);
    if ($gambar === false || $gambar['mime'] !== $mime) {
        throw new FotoBarangException('foto_invalid');
    }

    $direktori = __DIR__ . '/../uploads/barang';
    if (!is_dir($direktori) && !@mkdir($direktori, 0755, true) && !is_dir($direktori)) {
        throw new FotoBarangException('foto_gagal');
    }

    $nama_file = bin2hex(random_bytes(16)) . '.' . $ekstensi[$mime];
    if (!@move_uploaded_file($upload['tmp_name'], $direktori . '/' . $nama_file)) {
        throw new FotoBarangException('foto_gagal');
    }

    return $nama_file;
}

function hapus_foto_barang($nama_file)
{
    if (!is_string($nama_file) || preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $nama_file) !== 1) {
        return;
    }

    $path = __DIR__ . '/../uploads/barang/' . $nama_file;
    if (is_file($path) && !@unlink($path)) {
        error_log('SIMBAR could not remove inventory photo: ' . $nama_file);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali_ke_barang('invalid');
}

$csrf_token = $_POST['csrf_token'] ?? null;
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    kembali_ke_barang('csrf');
}

$aksi = isset($_POST['aksi']) && is_string($_POST['aksi']) ? $_POST['aksi'] : '';
$foto_baru = null;

try {
    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $nama = isset($_POST['nama_barang']) && is_string($_POST['nama_barang']) ? trim($_POST['nama_barang']) : '';
        $kategori = isset($_POST['kategori']) && is_string($_POST['kategori']) ? trim($_POST['kategori']) : '';
        $stok_input = $_POST['stok'] ?? '';
        $kondisi = isset($_POST['kondisi']) && is_string($_POST['kondisi']) ? $_POST['kondisi'] : '';

        $stok_valid = is_string($stok_input)
            && preg_match('/\A\d{1,10}\z/', $stok_input) === 1
            && (int)$stok_input <= 2147483647;
        if (
            $nama === '' ||
            mb_strlen($nama, 'UTF-8') > 100 ||
            $kategori === '' ||
            mb_strlen($kategori, 'UTF-8') > 50 ||
            !$stok_valid ||
            !in_array($kondisi, ['Baik', 'Rusak Ringan'], true)
        ) {
            kembali_ke_barang('invalid');
        }

        if ($aksi === 'tambah') {
            $foto_baru = simpan_foto_barang($_FILES['foto'] ?? null);
            $stmt = $pdo->prepare(
                'INSERT INTO barang (nama_barang, kategori, stok, kondisi, foto)
                 VALUES (:nama_barang, :kategori, :stok, :kondisi, :foto)'
            );
            $stmt->execute([
                ':nama_barang' => $nama,
                ':kategori' => $kategori,
                ':stok' => (int)$stok_input,
                ':kondisi' => $kondisi,
                ':foto' => $foto_baru,
            ]);
            $foto_baru = null;
            kembali_ke_barang('ditambahkan');
        }

        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_barang('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, foto FROM barang WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $barang_lama = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang_lama) {
            $pdo->rollBack();
            kembali_ke_barang('tidak_ada');
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(jumlah_pinjam), 0)
             FROM peminjaman
             WHERE barang_id = :barang_id AND status IN ('Pending', 'Disetujui')"
        );
        $stmt->execute([':barang_id' => $id]);
        $stok_dipesan = (int)$stmt->fetchColumn();
        if ((int)$stok_input < $stok_dipesan) {
            $pdo->rollBack();
            kembali_ke_barang('stok_dipesan');
        }

        $foto_baru = simpan_foto_barang($_FILES['foto'] ?? null);
        $hapus_foto = isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1' && $foto_baru === null;
        $foto_simpan = $foto_baru ?? ($hapus_foto ? null : $barang_lama['foto']);
        $stmt = $pdo->prepare(
            'UPDATE barang
             SET nama_barang = :nama_barang, kategori = :kategori, stok = :stok,
                 kondisi = :kondisi, foto = :foto
             WHERE id = :id'
        );
        $stmt->execute([
            ':nama_barang' => $nama,
            ':kategori' => $kategori,
            ':stok' => (int)$stok_input,
            ':kondisi' => $kondisi,
            ':foto' => $foto_simpan,
            ':id' => $id,
        ]);
        $pdo->commit();

        if ($foto_baru !== null || $hapus_foto) {
            hapus_foto_barang($barang_lama['foto']);
        }
        $foto_baru = null;
        kembali_ke_barang('diperbarui');
    }

    if ($aksi === 'hapus') {
        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_barang('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, foto FROM barang WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $barang_lama = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang_lama) {
            $pdo->rollBack();
            kembali_ke_barang('tidak_ada');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM peminjaman WHERE barang_id = :barang_id');
        $stmt->execute([':barang_id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            kembali_ke_barang('memiliki_transaksi');
        }

        $stmt = $pdo->prepare('DELETE FROM barang WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $pdo->commit();
        hapus_foto_barang($barang_lama['foto']);
        kembali_ke_barang('dihapus');
    }

    kembali_ke_barang('invalid');
} catch (FotoBarangException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    hapus_foto_barang($foto_baru);
    kembali_ke_barang($e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    hapus_foto_barang($foto_baru);
    error_log('SIMBAR inventory update failed: ' . $e->getMessage());
    kembali_ke_barang('gagal');
}
