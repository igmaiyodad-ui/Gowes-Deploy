<?php
session_start();

// ==========================================
// 1. ATUR ZONA WAKTU & KONEKSI DATABASE
// ==========================================
date_default_timezone_set('Asia/Jakarta');
require_once '../config/koneksi.php'; 

if (!isset($_SESSION['id_peminjam'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_peminjam = $_SESSION['id_peminjam'];
$nama_user   = $_SESSION['nama'];
$inisial     = strtoupper(substr($nama_user, 0, 1));

// ==========================================
// 2. AMBIL DATA PENGEMBALIAN TERAKHIR
// ==========================================
// Mengambil data peminjaman yang baru saja dikembalikan (waktu_kembali tidak null)
// dan belum memiliki laporan kondisi.
$id_peminjaman = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if ($id_peminjaman > 0) {
        $sql = "SELECT p.id_peminjaman, sp.kode_qr, sp.status_ketersediaan, p.waktu_kembali 
                FROM peminjaman p
                JOIN sepeda sp ON p.id_sepeda = sp.id_sepeda
                WHERE p.id_peminjaman = :id AND p.id_peminjam = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_peminjaman, ':id_user' => $id_peminjam]);
    } else {
        // Jika tidak ada ID di URL, cari yang terbaru
        $sql = "SELECT p.id_peminjaman, sp.kode_qr, sp.status_ketersediaan, p.waktu_kembali 
                FROM peminjaman p
                JOIN sepeda sp ON p.id_sepeda = sp.id_sepeda
                LEFT JOIN laporan_kondisi lk ON p.id_peminjaman = lk.id_peminjaman
                WHERE p.id_peminjam = :id_user AND p.waktu_kembali IS NOT NULL AND lk.id_laporankondisi IS NULL
                ORDER BY p.waktu_kembali DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_user' => $id_peminjam]);
    }
    
    $data = $stmt->fetch();

    if (!$data) {
        // Jika tidak ada data pengembalian, lempar ke riwayat
        header("Location: riwayat.php");
        exit;
    }

    $id_peminjaman = $data['id_peminjaman'];
    $waktu_kembali = date('H.i', strtotime($data['waktu_kembali']));
    $status_sepeda = $data['status_ketersediaan'];

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Go-bike dikembalikan · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <!-- PATH CSS DIPERBAIKI KE FOLDER ASSETS -->
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/mahasiswa.css">
  <link rel="stylesheet" href="../assets/css/loader.css">
</head>

<body class="user ">

  <header class="topbar">
    <a class="brand" href="dashboard.php"><img src="../img/logo.png" alt="GOWES"></a>
    <nav class="topnav" aria-label="Menu utama">
      <a class="navitem" href="dashboard.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.6 10 3.5l7 6.1V16a1 1 0 0 1-1 1h-3.4v-4.6H7.4V17H4a1 1 0 0 1-1-1z" /></svg></span>Dashboard</a>
      <a class="navitem on" href="peminjaman.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="4.8" cy="13" r="3" /><circle cx="15.2" cy="13" r="3" /><path d="M4.8 13 8 7.2h4.2l3 5.8M8 7.2 7 5H5M12.2 7.2 10.6 13" /></svg></span>Peminjaman</a>
      <a class="navitem" href="scan.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V4.5A1.5 1.5 0 0 1 4.5 3H7M13 3h2.5A1.5 1.5 0 0 1 17 4.5V7M17 13v2.5a1.5 1.5 0 0 1-1.5 1.5H13M7 17H4.5A1.5 1.5 0 0 1 3 15.5V13M6.5 10h7" /></svg></span>Scan QR</a>
      <a class="navitem" href="profil.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="6.6" r="3.2" /><path d="M3.8 17c.9-3.1 3.2-4.6 6.2-4.6s5.3 1.5 6.2 4.6" /></svg></span>Profil</a>
    </nav>
    <div class="who">
      <span><?= htmlspecialchars($nama_user) ?></span>
      <span class="avatar-sm"><?= $inisial ?></span>
    </div>
  </header>

  <main class="page no-tab centered">
    <div class="col ">

      <div class="success-head">
        <div class="ok-circle">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"
            stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m4.5 10.5 3.6 3.6L15.5 6.4" />
          </svg>
        </div>
        <h1 class="title">
          <span class="m">Go-bike dikembalikan</span>
          <span class="d">Go-bike Dikembalikan</span>
        </h1>
      </div>

      <section class="card">
        <div class="rows">
          <div class="row">
              <span class="k">ID Go-bike</span>
              <span class="v"><?= htmlspecialchars($data['kode_qr']) ?></span>
          </div>
          <div class="row">
              <span class="k">Waktu kembali</span>
              <span class="v"><?= $waktu_kembali ?> WIB</span>
          </div>
          <div class="row">
              <span class="k">Status Go-bike</span>
              <span class="chip chip-ok"><?= htmlspecialchars($status_sepeda) ?></span>
          </div>
        </div>
      </section>

      <!-- Mengarah ke laporan.php dengan membawa ID peminjaman -->
      <a class="btn btn-primary" href="laporan.php?id=<?= $id_peminjaman ?>">Isi laporan kondisi</a>

    </div>
  </main>

  <script src="../assets/js/common.js"></script>
</body>

</html>