<?php
session_start();

// ==========================================
// 1. KONEKSI DATABASE & PROTEKSI HALAMAN
// ==========================================
require_once '../config/koneksi.php'; 

if (!isset($_SESSION['id_peminjam'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = $_SESSION['nama'];
$inisial   = strtoupper(substr($nama_user, 0, 1));

// ==========================================
// 2. AMBIL DATA SEPEDA DARI URL
// ==========================================
$kode_qr = isset($_GET['kode_qr']) ? trim($_GET['kode_qr']) : '';

try {
    $stmt = $pdo->prepare("SELECT sp.id_sepeda, sp.kode_qr, sp.status_ketersediaan, sh.nama_shelter, sh.lokasi 
                           FROM sepeda sp 
                           JOIN shelter sh ON sp.id_shelter = sh.id_shelter 
                           WHERE sp.kode_qr = :kode_qr");
    $stmt->execute([':kode_qr' => $kode_qr]);
    $sepeda = $stmt->fetch();

    if (!$sepeda) {
        echo "<script>alert('Sepeda tidak ditemukan!'); window.location.href='scan.php';</script>";
        exit;
    }
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Go-bike ditemukan · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <!-- PATH CSS DIPERBAIKI -->
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/mahasiswa.css">
  
  <style>
      /* Style tambahan untuk halaman hasil scan */
      .scan-result-box {
          text-align: center;
          margin: 30px 0;
      }
      .scan-result-box .qr-code {
          font-size: 48px;
          font-weight: 800;
          color: var(--primary);
          margin-bottom: 10px;
          letter-spacing: 2px;
      }
      .card .row {
          display: flex;
          justify-content: space-between;
          padding: 12px 0;
          border-bottom: 1px solid #eee;
      }
      .card .row:last-child {
          border-bottom: none;
      }
      .card .k {
          color: #666;
      }
      .card .v {
          font-weight: 600;
          text-align: right;
      }
  </style>
</head>

<body class="user ">

  <header class="topbar">
    <a class="brand" href="dashboard.php"><img src="../img/logo.png" alt="GOWES"></a>
    <nav class="topnav" aria-label="Menu utama">
      <a class="navitem" href="dashboard.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.6 10 3.5l7 6.1V16a1 1 0 0 1-1 1h-3.4v-4.6H7.4V17H4a1 1 0 0 1-1-1z" /></svg></span>Dashboard</a>
      <a class="navitem" href="peminjaman.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="4.8" cy="13" r="3" /><circle cx="15.2" cy="13" r="3" /><path d="M4.8 13 8 7.2h4.2l3 5.8M8 7.2 7 5H5M12.2 7.2 10.6 13" /></svg></span>Peminjaman</a>
      <a class="navitem on" href="scan.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V4.5A1.5 1.5 0 0 1 4.5 3H7M13 3h2.5A1.5 1.5 0 0 1 17 4.5V7M17 13v2.5a1.5 1.5 0 0 1-1.5 1.5H13M7 17H4.5A1.5 1.5 0 0 1 3 15.5V13M6.5 10h7" /></svg></span>Scan QR</a>
      <a class="navitem" href="profil.php">
        <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="6.6" r="3.2" /><path d="M3.8 17c.9-3.1 3.2-4.6 6.2-4.6s5.3 1.5 6.2 4.6" /></svg></span>Profil</a>
    </nav>
    <div class="who">
      <span><?= htmlspecialchars($nama_user) ?></span>
      <span class="avatar-sm"><?= $inisial ?></span>
    </div>
  </header>

  <main class="page no-tab ">
    <div class="col ">

      <a class="back" href="scan.php">‹ Kembali</a>
      
      <h1 class="title">
        <span class="m">Go-bike ditemukan</span>
        <span class="d">Go-bike Ditemukan</span>
      </h1>
      <p class="lead">Pastikan data di bawah sesuai dengan unit di depanmu.</p>

      <section class="scan-result-box">
          <div class="qr-code"><?= htmlspecialchars($sepeda['kode_qr']) ?></div>
      </section>

      <section class="card" style="margin-bottom: 30px;">
        <div class="rows">
          <div class="row">
              <span class="k">ID Go-bike</span>
              <span class="v"><?= htmlspecialchars($sepeda['kode_qr']) ?></span>
          </div>
          <div class="row">
              <span class="k">Shelter</span>
              <span class="v"><?= htmlspecialchars($sepeda['nama_shelter']) ?></span>
          </div>
          <div class="row">
              <span class="k">Lokasi</span>
              <span class="v"><?= htmlspecialchars($sepeda['lokasi']) ?></span>
          </div>
          <div class="row">
              <span class="k">Status</span>
              <span class="v">
                  <?php if($sepeda['status_ketersediaan'] == 'Tersedia'): ?>
                      <span class="chip chip-ok">Tersedia</span>
                  <?php else: ?>
                      <span class="chip chip-bad"><?= htmlspecialchars($sepeda['status_ketersediaan']) ?></span>
                  <?php endif; ?>
              </span>
          </div>
        </div>
      </section>

      <?php if($sepeda['status_ketersediaan'] == 'Tersedia'): ?>
          <a class="btn btn-primary" href="durasi.php?kode_qr=<?= urlencode($sepeda['kode_qr']) ?>" style="width:100%; text-align:center;">
            Pilih Durasi
          </a>
      <?php else: ?>
          <button class="btn btn-primary" disabled style="width:100%; opacity:0.5; cursor:not-allowed;">
            Sepeda Sedang Tidak Tersedia
          </button>
      <?php endif; ?>

    </div>
  </main>

  <script src="../js/common.js"></script>
</body>

</html>