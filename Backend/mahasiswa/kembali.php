<?php
session_start();

// ==========================================
// 1. ATUR ZONA WAKTU KE WIB
// ==========================================
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 2. KONEKSI DATABASE & PROTEKSI HALAMAN
// ==========================================
require_once '../config/koneksi.php'; 

if (!isset($_SESSION['id_peminjam'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_peminjam = $_SESSION['id_peminjam'];
$nama_user   = $_SESSION['nama'];
$inisial     = strtoupper(substr($nama_user, 0, 1));

// ==========================================
// 3. PROSES PENGEMBALIAN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    try {
        $pdo->beginTransaction();

        // Ambil data peminjaman aktif
        $stmt_cek = $pdo->prepare("SELECT id_peminjaman, id_sepeda FROM peminjaman 
                                   WHERE id_peminjam = :id AND waktu_kembali IS NULL");
        $stmt_cek->execute([':id' => $id_peminjam]);
        $peminjaman_aktif = $stmt_cek->fetch();

        if ($peminjaman_aktif) {
            // 1. Update waktu_kembali dengan waktu saat ini (WIB)
            $waktu_kembali_wib = date('Y-m-d H:i:s');
            $stmt_update_pinjam = $pdo->prepare("UPDATE peminjaman 
                                                 SET waktu_kembali = :waktu_kembali 
                                                 WHERE id_peminjaman = :id_pinjam");
            $stmt_update_pinjam->execute([
                ':waktu_kembali' => $waktu_kembali_wib,
                ':id_pinjam'     => $peminjaman_aktif['id_peminjaman']
            ]);

            // 2. Update status sepeda menjadi Tersedia kembali
            $stmt_update_sepeda = $pdo->prepare("UPDATE sepeda 
                                                 SET status_ketersediaan = 'Tersedia' 
                                                 WHERE id_sepeda = :id_sepeda");
            $stmt_update_sepeda->execute([':id_sepeda' => $peminjaman_aktif['id_sepeda']]);
        }

        $pdo->commit();
        
        // Redirect ke halaman selesai
        header("Location: selesai.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Gagal memproses pengembalian: " . $e->getMessage());
    }
}

// ==========================================
// 4. AMBIL DATA PEMINJAMAN AKTIF
// ==========================================
try {
    $sql = "SELECT 
                p.id_peminjaman,
                sp.kode_qr, 
                sh.nama_shelter, 
                p.waktu_mulai, 
                p.batas_kembali 
            FROM peminjaman p
            JOIN sepeda sp ON p.id_sepeda = sp.id_sepeda
            JOIN shelter sh ON sp.id_shelter = sh.id_shelter
            WHERE p.id_peminjam = :id_peminjam AND p.waktu_kembali IS NULL
            ORDER BY p.waktu_mulai DESC LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_peminjam' => $id_peminjam]);
    $peminjaman = $stmt->fetch();

    if (!$peminjaman) {
        header("Location: dashboard.php");
        exit;
    }

    // Format Waktu untuk tampilan
    $waktu_mulai   = date('H.i', strtotime($peminjaman['waktu_mulai']));
    $batas_kembali = date('H.i', strtotime($peminjaman['batas_kembali']));
    
    // Format ISO untuk JS Countdown
    $batas_kembali_iso = date('Y-m-d\TH:i:s', strtotime($peminjaman['batas_kembali']));

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Peminjaman Saya · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <!-- PATH CSS DIPERBAIKI KE FOLDER ASSETS -->
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/mahasiswa.css">
  
  <style>
      /* Merapikan desain overlay modal agar tidak menggunakan inline-style yang berantakan */
      .overlay {
          position: fixed; top: 0; left: 0; width: 100%; height: 100%;
          background: rgba(0,0,0,0.5); z-index: 999;
          display: none; align-items: center; justify-content: center;
      }
      .overlay.show { display: flex; }
      .dialog {
          background: white; padding: 24px; border-radius: 16px; 
          max-width: 400px; width: 90%; text-align: center;
          box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      }
      .dialog h2 { font-size: 20px; margin-bottom: 10px; color: #1e293b; }
      .dialog p { font-size: 14px; color: #64748b; margin-bottom: 20px; line-height: 1.5; }
  </style>
</head>

<body class="user">

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
    <a href="profil.php" class="user-pill-btn logged-in" title="Buka Profil" style="text-decoration: none;">
        <span><?= htmlspecialchars($nama_user) ?></span>
        <div class="avatar-sm"><?= $inisial ?></div>
    </a>
  </header>

  <main class="page no-tab">
    <div class="col bg-content">

      <div style="display:contents">
        <h1 class="title">
          <span class="m">Peminjaman saya</span>
          <span class="d">Peminjaman Saya</span>
        </h1>

        <section class="countdown">
          <div class="lbl">
            <span class="m">Sisa waktu</span>
            <span class="d">Sisa Waktu Peminjaman</span>
          </div>
          <!-- Atribut data-batas diisi oleh PHP -->
          <div class="time" id="countdown" data-batas="<?= $batas_kembali_iso ?>">--:--:--</div>
          <div class="foot">Kembali sebelum <span><?= $batas_kembali ?></span> WIB</div>
        </section>

        <section class="card">
          <div class="rows">
            <div class="row"><span class="k">ID Go-bike</span><span class="v"><?= htmlspecialchars($peminjaman['kode_qr']) ?></span></div>
            <div class="row"><span class="k">Shelter</span><span class="v"><?= htmlspecialchars($peminjaman['nama_shelter']) ?></span></div>
            <div class="row"><span class="k">Waktu mulai</span><span class="v"><?= $waktu_mulai ?> WIB</span></div>
            <div class="row"><span class="k">Batas pengembalian</span><span class="v"><?= $batas_kembali ?> WIB</span></div>
          </div>
        </section>

        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-kembali').classList.add('show')">Kembalikan Go-bike</button>
      </div>

    </div>
  </main>

  <!-- MODAL OVERLAY UNTUK KONFIRMASI -->
  <div class="overlay" id="modal-kembali" role="dialog" aria-modal="true">
    <div class="dialog">
      <h2>Kembalikan Go-bike?</h2>
      <p>Pastikan Go-bike sudah diparkir di shelter dan kunci diserahkan ke petugas.</p>
      
      <form method="POST" action="" style="margin-top: 20px;">
          <input type="hidden" name="action" value="return">
          <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:10px; cursor:pointer;">Ya, kembalikan</button>
      </form>
      
      <button type="button" class="btn btn-soft" onclick="document.getElementById('modal-kembali').classList.remove('show')" style="width:100%; cursor:pointer;">Batal</button>
    </div>
  </div>

  <!-- PATH JS DIPERBAIKI KE FOLDER ASSETS -->
  <script src="../assets/js/common.js"></script>
  
  <!-- SCRIPT COUNTDOWN INLINE UNTUK MEMASTIKAN BERJALAN -->
  <script>
      document.addEventListener("DOMContentLoaded", function() {
          const countdownEl = document.getElementById('countdown');
          if (!countdownEl) return;

          const targetDateStr = countdownEl.getAttribute('data-batas');
          if (!targetDateStr) return;

          const targetDate = new Date(targetDateStr).getTime();

          const interval = setInterval(function() {
              const now = new Date().getTime();
              const distance = targetDate - now;

              if (distance < 0) {
                  clearInterval(interval);
                  countdownEl.innerHTML = "Waktu Habis";
                  countdownEl.style.color = "red";
                  countdownEl.style.fontSize = "24px"; // Sesuaikan ukuran font jika perlu
                  return;
              }

              const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
              const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
              const seconds = Math.floor((distance % (1000 * 60)) / 1000);

              countdownEl.innerHTML = 
                  String(hours).padStart(2, '0') + ":" + 
                  String(minutes).padStart(2, '0') + ":" + 
                  String(seconds).padStart(2, '0');
          }, 1000);
      });
  </script>
</body>

</html>