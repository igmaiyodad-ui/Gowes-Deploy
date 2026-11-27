<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once '../config/koneksi.php'; 

if (!isset($_SESSION['id_peminjam'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_peminjam = $_SESSION['id_peminjam'];
$nama_user   = $_SESSION['nama'];
$inisial     = strtoupper(substr($nama_user, 0, 1));

// Tangkap data dari halaman durasi
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_sepeda']) || !isset($_POST['durasi_pinjam'])) {
    header("Location: scan.php");
    exit;
}

$id_sepeda = (int)$_POST['id_sepeda'];
$durasi    = (int)$_POST['durasi_pinjam'];

// ==========================================
// PROSES KONFIRMASI (MAHASISWA MENGAJUKAN)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'confirm') {
    try {
        $pdo->beginTransaction();

        // 1. Kunci sepeda secara atomik: hanya berhasil kalau masih 'Tersedia'.
        //    RETURNING id_shelter dipakai untuk kolom id_shelter di tabel peminjaman (NOT NULL).
        $stmt_lock = $pdo->prepare("UPDATE sepeda
                                    SET status_ketersediaan = 'Dipinjam'
                                    WHERE id_sepeda = :id_sepeda AND status_ketersediaan = 'Tersedia'
                                    RETURNING id_shelter");
        $stmt_lock->execute([':id_sepeda' => $id_sepeda]);
        $id_shelter = $stmt_lock->fetchColumn();

        if (!$id_shelter) {
            $pdo->rollBack();
            echo "<script>alert('Sepeda sudah tidak tersedia.'); window.location.href='scan.php';</script>";
            exit;
        }

        // 2. Catat pengajuan peminjaman.
        //    batas_kembali TIDAK diisi dari PHP: dihitung otomatis oleh trigger trg_batas_kembali
        //    (waktu_mulai + durasi_pinjam jam). waktu_kembali default NULL.
        $waktu_sekarang = date('Y-m-d H:i:s');

        $sql_insert = "INSERT INTO peminjaman (id_peminjam, id_shelter, id_sepeda, waktu_mulai, durasi_pinjam, status_peminjaman)
                       VALUES (:id_peminjam, :id_shelter, :id_sepeda, :waktu_mulai, :durasi, :status)";

        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            ':id_peminjam' => $id_peminjam,
            ':id_shelter'  => $id_shelter,
            ':id_sepeda'   => $id_sepeda,
            ':waktu_mulai' => $waktu_sekarang,
            ':durasi'      => $durasi,
            ':status'      => 'Menunggu' // menunggu ACC petugas
        ]);

        $pdo->commit();
        
        // Arahkan ke halaman peminjaman (layar menunggu Satpam)
        header("Location: peminjaman.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Gagal memproses peminjaman: " . $e->getMessage());
    }
}

// ==========================================
// AMBIL DATA & VALIDASI
// ==========================================
try {
    $stmt = $pdo->prepare("SELECT sp.kode_qr, sh.nama_shelter 
                           FROM sepeda sp 
                           JOIN shelter sh ON sp.id_shelter = sh.id_shelter 
                           WHERE sp.id_sepeda = :id");
    $stmt->execute([':id' => $id_sepeda]);
    $sepeda = $stmt->fetch();
    
    if (!$sepeda) die("Data sepeda tidak ditemukan.");

    $stmt_sanksi = $pdo->prepare("SELECT COUNT(*) FROM sanksi WHERE id_peminjam = :id AND CURRENT_DATE BETWEEN tgl_mulai AND tgl_selesai");
    $stmt_sanksi->execute([':id' => $id_peminjam]);
    $is_suspen = $stmt_sanksi->fetchColumn() > 0;

    $jam_sekarang = date('H:i');
    $hari_sekarang = date('N');
    $is_hari_kerja = ($hari_sekarang >= 1 && $hari_sekarang <= 5);
    $is_jam_kerja = ($jam_sekarang >= '08:00' && $jam_sekarang <= '15:30');
    $is_operasional = ($is_hari_kerja && $is_jam_kerja);

    $waktu_selesai = date('H:i', strtotime("+$durasi hours"));
    $is_durasi_valid = ($waktu_selesai <= '15:30');
    $is_all_valid = (!$is_suspen && $is_operasional && $is_durasi_valid);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Konfirmasi peminjaman · GOWES</title>
    <link rel="icon" href="../img/logo.png" />
    <link rel="stylesheet" href="../assets/css/fonts.css" />
    <link rel="stylesheet" href="../assets/css/tokens.css" />
    <link rel="stylesheet" href="../assets/css/base.css" />
    <link rel="stylesheet" href="../assets/css/components.css" />
    <link rel="stylesheet" href="../assets/css/mahasiswa.css" />
    
    <style>
        .btn-disabled { opacity: 0.5; pointer-events: none; cursor: not-allowed; filter: grayscale(100%); }
        .chip-bad { background-color: #ffebee; color: #c62828; }
        .chip-ok { background-color: #e8f5e9; color: #2e7d32; }
    </style>
  </head>

  <body class="user">
    <header class="topbar">
      <a class="brand" href="dashboard.php"><img src="../img/logo.png" alt="GOWES" /></a>
      <nav class="topnav" aria-label="Menu utama">
        <a class="navitem" href="dashboard.php"><span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.6 10 3.5l7 6.1V16a1 1 0 0 1-1 1h-3.4v-4.6H7.4V17H4a1 1 0 0 1-1-1z" /></svg></span>Dashboard</a>
        <a class="navitem" href="peminjaman.php"><span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="4.8" cy="13" r="3" /><circle cx="15.2" cy="13" r="3" /><path d="M4.8 13 8 7.2h4.2l3 5.8M8 7.2 7 5H5M12.2 7.2 10.6 13" /></svg></span>Peminjaman</a>
        <a class="navitem on" href="scan.php"><span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7V4.5A1.5 1.5 0 0 1 4.5 3H7M13 3h2.5A1.5 1.5 0 0 1 17 4.5V7M17 13v2.5a1.5 1.5 0 0 1-1.5 1.5H13M7 17H4.5A1.5 1.5 0 0 1 3 15.5V13M6.5 10h7" /></svg></span>Scan QR</a>
        <a class="navitem" href="profil.php"><span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="6.6" r="3.2" /><path d="M3.8 17c.9-3.1 3.2-4.6 6.2-4.6s5.3 1.5 6.2 4.6" /></svg></span>Profil</a>
      </nav>
      <div class="who">
        <span><?= htmlspecialchars($nama_user) ?></span>
        <span class="avatar-sm"><?= $inisial ?></span>
      </div>
    </header>

    <main class="page no-tab">
      <div class="col">
        <a class="back" href="durasi.php?id=<?= $id_sepeda ?>">‹ Kembali</a>
        
        <h1 class="title">
          <span class="m">Konfirmasi peminjaman</span>
          <span class="d">Informasi Peminjaman</span>
        </h1>
        <p class="lead">Sistem memeriksa akunmu sebelum peminjaman diproses.</p>

        <section class="card">
          <div class="checks">
            <div class="check">
              <span>Status akun tidak sedang suspen</span>
              <?php if ($is_suspen): ?>
                  <span class="chip chip-bad">Gagal</span>
              <?php else: ?>
                  <span class="chip chip-ok">Lolos</span>
              <?php endif; ?>
            </div>
            <div class="check">
              <span>Masih dalam jam operasional (08.00–15.30 WIB)</span>
              <?php if (!$is_operasional): ?>
                  <span class="chip chip-bad">Gagal</span>
              <?php else: ?>
                  <span class="chip chip-ok">Lolos</span>
              <?php endif; ?>
            </div>
            <div class="check">
              <span>Durasi <?= $durasi ?> jam, tidak melewati 15.30 WIB</span>
              <?php if (!$is_durasi_valid): ?>
                  <span class="chip chip-bad">Gagal</span>
              <?php else: ?>
                  <span class="chip chip-ok">Lolos</span>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="card">
          <div class="rows">
            <div class="row">
              <span class="k">Go-bike</span>
              <span class="v"><?= htmlspecialchars($sepeda['kode_qr']) ?> · <?= htmlspecialchars($sepeda['nama_shelter']) ?></span>
            </div>
            <div class="row">
              <span class="k">Durasi</span>
              <span class="v"><?= $durasi ?> jam</span>
            </div>
          </div>
        </section>

        <form action="" method="POST">
            <input type="hidden" name="id_sepeda" value="<?= $id_sepeda ?>">
            <input type="hidden" name="durasi_pinjam" value="<?= $durasi ?>">
            <input type="hidden" name="action" value="confirm">

            <?php 
                $btn_class = $is_all_valid ? "btn btn-primary" : "btn btn-primary btn-disabled";
            ?>
            
            <button type="submit" class="<?= $btn_class ?>" style="width:100%; border:none; cursor:pointer;" <?= $is_all_valid ? '' : 'disabled' ?>>
              <span class="m">Konfirmasi peminjaman</span>
              <span class="d">Konfirmasi Peminjaman</span>
            </button>
        </form>

        <?php if (!$is_all_valid): ?>
            <p style="text-align: center; color: #c62828; margin-top: 15px; font-size: 14px;">
                Mohon maaf, Anda tidak dapat melanjutkan peminjaman karena ada syarat yang tidak terpenuhi.
            </p>
        <?php endif; ?>

      </div>
    </main>

    <script src="../assets/js/common.js"></script>
  </body>
</html>