<?php
session_start();

// ==========================================
// 1. ATUR ZONA WAKTU KE WIB (MALANG)
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

$nama_user = $_SESSION['nama'];
$inisial   = strtoupper(substr($nama_user, 0, 1));

// ==========================================
// 3. AMBIL DATA SEPEDA (PERBAIKAN UTAMA)
// ==========================================
// Menangkap parameter 'id' dari URL, atau 'kode_qr' sebagai cadangan
$id_sepeda = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$kode_qr_param = isset($_GET['kode_qr']) ? trim($_GET['kode_qr']) : '';

try {
    if ($id_sepeda > 0) {
        // Jika URL menggunakan ?id=2 (seperti dari halaman hasil scan)
        $stmt = $pdo->prepare("SELECT sp.id_sepeda, sp.kode_qr, sh.nama_shelter 
                               FROM sepeda sp 
                               JOIN shelter sh ON sp.id_shelter = sh.id_shelter 
                               WHERE sp.id_sepeda = :id AND sp.status_ketersediaan = 'Tersedia'");
        $stmt->execute([':id' => $id_sepeda]);
    } else {
        // Jika URL menggunakan ?kode_qr=GB-02
        $stmt = $pdo->prepare("SELECT sp.id_sepeda, sp.kode_qr, sh.nama_shelter 
                               FROM sepeda sp 
                               JOIN shelter sh ON sp.id_shelter = sh.id_shelter 
                               WHERE sp.kode_qr = :kode_qr AND sp.status_ketersediaan = 'Tersedia'");
        $stmt->execute([':kode_qr' => $kode_qr_param]);
    }
    
    $sepeda = $stmt->fetch();

    // Jika sepeda tidak ditemukan atau tidak tersedia
    if (!$sepeda) {
        echo "<script>alert('Sepeda tidak tersedia atau tidak ditemukan!'); window.location.href='scan.php';</script>";
        exit;
    }
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// Waktu saat ini format PHP (sebagai fallback awal)
$waktu_sekarang = date('H:i');
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pilih durasi · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <!-- PATH CSS -->
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/mahasiswa.css">
  
  <style>
      /* --- PERBAIKAN JARAK (MARGIN) --- */
      .durations {
          display: flex;
          gap: 10px;
          margin-bottom: 24px; 
      }
      .card {
          margin-bottom: 24px; 
      }
      /* Style tombol durasi */
      .dur {
          flex: 1;
          padding: 12px;
          border-radius: 12px;
          border: 2px solid transparent;
          background-color: #f0f0f0;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.2s ease;
      }
      .dur.active {
          background-color: var(--primary);
          color: white;
          border-color: var(--primary);
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

      <!-- Link Kembali diarahkan ke scan.php agar aman -->
      <a class="back" href="scan.php">‹ Kembali</a>
      <h1 class="title">
        <span class="m">Pilih durasi</span>
        <span class="d">Pilih Durasi</span>
      </h1>
      <p class="lead">Maksimal 3 jam dan tidak melewati pukul 15.30 WIB.</p>

      <form action="konfirmasi.php" method="POST" id="formPeminjaman">
          <input type="hidden" name="id_sepeda" value="<?= $sepeda['id_sepeda'] ?>">
          <input type="hidden" name="durasi_pinjam" id="input-durasi" value="1">

          <!-- Tombol Durasi -->
          <div class="durations" id="pilih-durasi" role="group" aria-label="Durasi peminjaman">
            <button type="button" class="dur active" data-durasi="1">1 jam</button>
            <button type="button" class="dur" data-durasi="2">2 jam</button>
            <button type="button" class="dur" data-durasi="3">3 jam</button>
          </div>

          <!-- Card Detail -->
          <section class="card">
            <div class="rows">
              <div class="row"><span class="k">ID Go-bike</span><span class="v"><?= htmlspecialchars($sepeda['kode_qr']) ?></span></div>
              <div class="row"><span class="k">Shelter</span><span class="v"><?= htmlspecialchars($sepeda['nama_shelter']) ?></span></div>
              <div class="row">
                  <span class="k">Waktu mulai</span>
                  <span class="v" id="waktu-mulai"><?= $waktu_sekarang ?> WIB</span>
              </div>
              <div class="row"><span class="k">Batas pengembalian</span><span class="v" id="batas">--.-- WIB</span></div>
            </div>
          </section>

          <button type="submit" class="btn btn-primary" style="width:100%; border:none; cursor:pointer;">Lanjut ke konfirmasi</button>
      </form>

    </div>
  </main>

  <script src="../assets/js/common.js"></script>
  
  <script>
      document.addEventListener("DOMContentLoaded", function() {
          const buttons = document.querySelectorAll('.dur');
          const batasText = document.getElementById('batas');
          const waktuMulaiText = document.getElementById('waktu-mulai');
          const inputDurasi = document.getElementById('input-durasi');

          // Format waktu spesifik ke zona waktu Asia/Jakarta (WIB)
          const formatterWIB = new Intl.DateTimeFormat('id-ID', {
              timeZone: 'Asia/Jakarta',
              hour: '2-digit',
              minute: '2-digit',
              hour12: false
          });

          // Fungsi update waktu mulai secara realtime
          function updateWaktuMulai() {
              const now = new Date();
              // Ganti titik dua (:) menjadi titik (.) agar formatnya 11.00
              const formatted = formatterWIB.format(now).replace(':', '.');
              waktuMulaiText.textContent = formatted + ' WIB';
          }

          // Fungsi hitung batas waktu berdasarkan durasi
          function updateBatasWaktu(durasi) {
              const now = new Date();
              // Tambahkan durasi dalam milidetik (jam * 60 menit * 60 detik * 1000)
              const batasDate = new Date(now.getTime() + (durasi * 60 * 60 * 1000));
              
              const formattedBatas = formatterWIB.format(batasDate).replace(':', '.');
              batasText.textContent = formattedBatas + ' WIB';

              // Cek apakah melewati jam 15:30 WIB
              const timeParts = formatterWIB.format(batasDate).split(':');
              const jamBatas = parseInt(timeParts[0]);
              const menitBatas = parseInt(timeParts[1]);

              if (jamBatas > 15 || (jamBatas === 15 && menitBatas > 30)) {
                  batasText.style.color = 'red';
                  batasText.style.fontWeight = 'bold';
              } else {
                  batasText.style.color = ''; 
                  batasText.style.fontWeight = '';
              }
          }

          // Jalankan saat halaman pertama kali dimuat
          updateWaktuMulai();
          updateBatasWaktu(1); // Default 1 jam

          // Update jam mulai setiap detik (realtime)
          setInterval(updateWaktuMulai, 1000);

          // Event listener saat tombol 1, 2, 3 jam diklik
          buttons.forEach(btn => {
              btn.addEventListener('click', function() {
                  buttons.forEach(b => b.classList.remove('active'));
                  this.classList.add('active');

                  const durasi = parseInt(this.getAttribute('data-durasi'));
                  inputDurasi.value = durasi;

                  updateBatasWaktu(durasi);
              });
          });
      });
  </script>
</body>
</html>