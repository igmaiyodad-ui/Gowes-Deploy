<?php
session_start();

// ==========================================
// 1. ATUR ZONA WAKTU & PROTEKSI
// ==========================================
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['id_peminjam'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}
require_once '../config/koneksi.php';

$id_peminjam = $_SESSION['id_peminjam'];
$nama_user   = $_SESSION['nama'];
$inisial     = strtoupper(substr($nama_user, 0, 1));

$id_peminjaman = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data peminjaman aktif
try {
    $sql = "SELECT p.id_peminjaman, p.batas_kembali 
            FROM peminjaman p 
            WHERE p.id_peminjam = :id_peminjam AND p.waktu_kembali IS NULL 
            ORDER BY p.waktu_mulai DESC LIMIT 1";
    $stmt = $pdo->prepare($sql); 
    $stmt->execute(['id_peminjam' => $id_peminjam]); 
    $peminjaman = $stmt->fetch();

    if (!$peminjaman) {
        // Jika tidak ada peminjaman, kembalikan ke dashboard
        header("Location: dashboard.php");
        exit;
    }

    $id_peminjaman = $peminjaman['id_peminjaman'];
    $batas_kembali_iso = date('Y-m-d\TH:i:s', strtotime($peminjaman['batas_kembali']));
} catch (PDOException $e) { 
    die("Error: " . $e->getMessage()); 
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Waktu peminjaman habis · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/mahasiswa.css">
  
  <style>
      /* Style untuk halaman peringatan waktu habis */
      .late-container {
          display: flex; align-items: center; justify-content: center;
          min-height: 100vh; padding: 20px;
          background-color: rgba(0,0,0,0.85); /* Latar belakang gelap */
          position: fixed; top: 0; left: 0; width: 100%; z-index: 9999;
      }
      .dialog.late {
          background: white; border-radius: 16px; padding: 30px 24px;
          max-width: 400px; width: 100%; text-align: center;
          box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      }
      .dialog.late h2 { color: #d32f2f; font-size: 24px; margin-bottom: 12px; }
      .dialog.late p { color: #555; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
      
      .late-box {
          display: flex; align-items: center; justify-content: space-between;
          background: #ffebee; padding: 12px 16px; border-radius: 8px;
          margin-bottom: 20px; border: 1px solid #ffcdd2;
      }
      .late-box .late-ico { color: #d32f2f; display: flex; }
      .late-box .late-st { text-align: left; flex: 1; margin-left: 12px; }
      .late-box .late-st small { display: block; color: #d32f2f; font-size: 12px; margin-bottom: 2px; }
      .late-box .late-st b { color: #d32f2f; font-size: 18px; }
      .late-box .late-time { font-weight: bold; color: #d32f2f; font-family: monospace; font-size: 18px; }
      
      .late-note {
          display: flex; align-items: center; gap: 10px;
          background: #fff3e0; color: #e65100; padding: 12px;
          border-radius: 8px; font-size: 13px; margin-bottom: 24px; text-align: left;
          line-height: 1.4;
      }
  </style>
</head>
<body class="user">

  <div class="late-container">
      <div class="dialog late">
        <h2>Waktu Peminjaman Habis!</h2>
        <p>Batas waktu pengembalian Go-bike kamu telah berakhir. Segera kembalikan sepeda ke shelter dan serahkan kunci sepeda kepada petugas.</p>

        <div class="late-box">
          <span class="late-ico">
              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10" cy="11" r="6"/><path d="M10 8v3.2l2 1.4M3.2 5.2 6 3M16.8 5.2 14 3"/></svg>
          </span>
          <div class="late-st">
              <small>Status:</small>
              <b>Terlambat</b>
          </div>
          <span class="late-time" id="late-timer" data-target="<?= $batas_kembali_iso ?>">00:00:00</span>
        </div>

        <p class="late-note">
            <span class="late-warn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3 18 16.5H2z"/><path d="M10 8.4v3.8M10 14.4v.01"/></svg>
            </span>
            Keterlambatan dapat dikenai sanksi sesuai ketentuan.
        </p>

        <a class="btn btn-primary" href="kembali.php?id=<?= $id_peminjaman ?>" style="width: 100%;">Ya, Kembalikan</a>
      </div>
  </div>

  <script>
      // Script untuk menghitung sudah berapa lama dia terlambat
      document.addEventListener("DOMContentLoaded", function() {
          const lateTimer = document.getElementById('late-timer');
          const targetDateStr = lateTimer.getAttribute('data-target');
          const targetDate = new Date(targetDateStr).getTime();

          setInterval(function() {
              const now = new Date().getTime();
              const distance = now - targetDate; // Menghitung selisih waktu lampau

              if (distance > 0) {
                  const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                  const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                  const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                  
                  lateTimer.innerHTML = 
                      String(hours).padStart(2, '0') + ":" + 
                      String(minutes).padStart(2, '0') + ":" + 
                      String(seconds).padStart(2, '0');
              }
          }, 1000);
      });
  </script>
</body>
</html>