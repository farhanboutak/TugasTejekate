<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once '../config/audit.php';

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

    return simpan_foto_tervalidasi($upload['tmp_name'], true);
}

function simpan_foto_terpilih($upload, $url, $mode)
{
    if (!is_string($mode) || !in_array($mode, ['device', 'url'], true)) {
        throw new FotoBarangException('foto_url_invalid');
    }
    if (!is_string($url)) {
        throw new FotoBarangException('foto_url_invalid');
    }

    $ada_file = is_array($upload) && isset($upload['error']) && $upload['error'] !== UPLOAD_ERR_NO_FILE;
    $url = trim($url);
    if ($ada_file && $url !== '') {
        throw new FotoBarangException('foto_pilih_satu');
    }

    if ($mode === 'device') {
        if ($url !== '') {
            throw new FotoBarangException('foto_pilih_satu');
        }
        return simpan_foto_barang($upload);
    }

    if ($ada_file) {
        throw new FotoBarangException('foto_pilih_satu');
    }

    return $url === '' ? null : simpan_foto_dari_url($url);
}

function simpan_foto_tervalidasi($path, $unggahan)
{
    if (!function_exists('finfo_open')) {
        throw new FotoBarangException('foto_gagal');
    }

    $ukuran = @filesize($path);
    if (!is_int($ukuran) || $ukuran <= 0 || $ukuran > 5 * 1024 * 1024) {
        throw new FotoBarangException(is_int($ukuran) && $ukuran > 5 * 1024 * 1024 ? 'foto_terlalu_besar' : 'foto_invalid');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new FotoBarangException('foto_gagal');
    }
    $mime = @finfo_file($finfo, $path);
    finfo_close($finfo);

    $ekstensi = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($ekstensi[$mime])) {
        throw new FotoBarangException('foto_invalid');
    }
    $gambar = @getimagesize($path);
    if ($gambar === false || $gambar['mime'] !== $mime) {
        throw new FotoBarangException('foto_invalid');
    }

    $direktori = __DIR__ . '/../uploads/barang';
    if (!is_dir($direktori) && !@mkdir($direktori, 0755, true) && !is_dir($direktori)) {
        throw new FotoBarangException('foto_gagal');
    }

    $nama_file = bin2hex(random_bytes(16)) . '.' . $ekstensi[$mime];
    $tujuan = $direktori . '/' . $nama_file;
    $berhasil = $unggahan
        ? @move_uploaded_file($path, $tujuan)
        : @rename($path, $tujuan);
    if (!$berhasil) {
        throw new FotoBarangException('foto_gagal');
    }

    return $nama_file;
}

function simpan_foto_dari_url($url)
{
    if (!is_string($url) || strlen($url) > 2048) {
        throw new FotoBarangException('foto_url_invalid');
    }

    $bagian = parse_url($url);
    if (
        !is_array($bagian) ||
        strtolower($bagian['scheme'] ?? '') !== 'https' ||
        !isset($bagian['host']) ||
        isset($bagian['user']) ||
        isset($bagian['pass']) ||
        isset($bagian['fragment']) ||
        (isset($bagian['port']) && $bagian['port'] !== 443)
    ) {
        throw new FotoBarangException('foto_url_invalid');
    }

    $host = strtolower($bagian['host']);
    if (
        filter_var($host, FILTER_VALIDATE_IP) !== false ||
        preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/', $host) !== 1
    ) {
        throw new FotoBarangException('foto_url_invalid');
    }

    $alamat_ip = gethostbynamel($host);
    if (!is_array($alamat_ip) || !$alamat_ip) {
        throw new FotoBarangException('foto_url_gagal');
    }

    $ip_publik = null;
    foreach ($alamat_ip as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ip_publik = $ip;
            break;
        }
    }
    if ($ip_publik === null) {
        throw new FotoBarangException('foto_url_invalid');
    }

    if (!function_exists('curl_init')) {
        throw new FotoBarangException('foto_url_gagal');
    }

    $temporary_path = tempnam(sys_get_temp_dir(), 'simbar-photo-');
    if ($temporary_path === false) {
        throw new FotoBarangException('foto_gagal');
    }

    $file = @fopen($temporary_path, 'wb');
    if ($file === false) {
        @unlink($temporary_path);
        throw new FotoBarangException('foto_gagal');
    }

    $jumlah_byte = 0;
    $terlalu_besar = false;
    $curl = curl_init($url);
    if ($curl === false) {
        fclose($file);
        @unlink($temporary_path);
        throw new FotoBarangException('foto_url_gagal');
    }

    $opsi_berhasil = curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROXY => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [$host . ':443:' . $ip_publik],
        CURLOPT_WRITEFUNCTION => static function ($handle, $data) use ($file, &$jumlah_byte, &$terlalu_besar) {
            $jumlah_byte += strlen($data);
            if ($jumlah_byte > 5 * 1024 * 1024) {
                $terlalu_besar = true;
                return 0;
            }

            return fwrite($file, $data);
        },
    ]);
    if (!$opsi_berhasil) {
        $error = curl_error($curl);
        curl_close($curl);
        fclose($file);
        @unlink($temporary_path);
        error_log('SIMBAR remote image download options failed: ' . $error);
        throw new FotoBarangException('foto_url_gagal');
    }

    $hasil = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($file);

    if ($terlalu_besar) {
        @unlink($temporary_path);
        throw new FotoBarangException('foto_terlalu_besar');
    }
    if ($hasil === false || $status !== 200 || $jumlah_byte === 0) {
        @unlink($temporary_path);
        error_log('SIMBAR remote image download failed: ' . $error);
        throw new FotoBarangException('foto_url_gagal');
    }

    try {
        return simpan_foto_tervalidasi($temporary_path, false);
    } finally {
        if (is_file($temporary_path)) {
            @unlink($temporary_path);
        }
    }
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
            $foto_baru = simpan_foto_terpilih(
                $_FILES['foto'] ?? null,
                $_POST['foto_url'] ?? '',
                $_POST['foto_mode'] ?? 'device'
            );
            $pdo->beginTransaction();
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
            $id_baru = (int)$pdo->lastInsertId();
            catat_aktivitas(
                $pdo,
                'barang.ditambahkan',
                'barang',
                'Barang "' . $nama . '" ditambahkan ke inventaris.',
                $id_baru,
                ['kategori' => $kategori, 'stok' => (int)$stok_input]
            );
            $pdo->commit();
            $foto_baru = null;
            kembali_ke_barang('ditambahkan');
        }

        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_barang('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT id, nama_barang, kategori, stok, kondisi, foto, diarsipkan
             FROM barang WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);
        $barang_lama = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang_lama) {
            $pdo->rollBack();
            kembali_ke_barang('tidak_ada');
        }
        if ((int)$barang_lama['diarsipkan'] === 1) {
            $pdo->rollBack();
            kembali_ke_barang('invalid');
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

        $foto_baru = simpan_foto_terpilih(
            $_FILES['foto'] ?? null,
            $_POST['foto_url'] ?? '',
            $_POST['foto_mode'] ?? 'device'
        );
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
        catat_aktivitas(
            $pdo,
            'barang.diperbarui',
            'barang',
            'Data barang "' . $nama . '" diperbarui.',
            $id,
            [
                'sebelum' => [
                    'nama_barang' => $barang_lama['nama_barang'],
                    'kategori' => $barang_lama['kategori'],
                    'stok' => (int)$barang_lama['stok'],
                    'kondisi' => $barang_lama['kondisi'],
                ],
                'sesudah' => [
                    'nama_barang' => $nama,
                    'kategori' => $kategori,
                    'stok' => (int)$stok_input,
                    'kondisi' => $kondisi,
                ],
            ]
        );
        $pdo->commit();

        if ($foto_baru !== null || $hapus_foto) {
            hapus_foto_barang($barang_lama['foto']);
        }
        $foto_baru = null;
        kembali_ke_barang('diperbarui');
    }

    if ($aksi === 'arsipkan' || $aksi === 'hapus') {
        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_barang('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT nama_barang, diarsipkan FROM barang WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $barang = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang) {
            $pdo->rollBack();
            kembali_ke_barang('tidak_ada');
        }
        if ((int)$barang['diarsipkan'] === 1) {
            $pdo->rollBack();
            kembali_ke_barang('sudah_diarsipkan');
        }
        $stmt = $pdo->prepare('UPDATE barang SET diarsipkan = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        catat_aktivitas($pdo, 'barang.diarsipkan', 'barang', 'Barang "' . $barang['nama_barang'] . '" diarsipkan.', $id);
        $pdo->commit();

        kembali_ke_barang('diarsipkan');
    }

    if ($aksi === 'pulihkan') {
        $id_input = $_POST['id'] ?? '';
        $id = is_string($id_input) && ctype_digit($id_input) ? (int)$id_input : 0;
        if ($id <= 0) {
            kembali_ke_barang('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT nama_barang, diarsipkan FROM barang WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $barang = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang) {
            $pdo->rollBack();
            kembali_ke_barang('tidak_ada');
        }
        if ((int)$barang['diarsipkan'] === 0) {
            $pdo->rollBack();
            kembali_ke_barang('sudah_aktif');
        }
        $stmt = $pdo->prepare('UPDATE barang SET diarsipkan = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        catat_aktivitas($pdo, 'barang.dipulihkan', 'barang', 'Barang "' . $barang['nama_barang'] . '" dipulihkan.', $id);
        $pdo->commit();

        kembali_ke_barang('dipulihkan');
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
