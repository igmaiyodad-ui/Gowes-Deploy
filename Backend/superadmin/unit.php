<?php
session_start();

// ==========================================
// 1. KONEKSI DATABASE & PROTEKSI HALAMAN
// ==========================================
require_once '../config/koneksi.php'; 

// Cek apakah user sudah login sebagai superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Super Admin';

// ==========================================
// 2. AMBIL DATA UNIT SEPEDA
// ==========================================
try {
    $sql = "SELECT 
                sp.kode_qr, 
                sh.nama_shelter, 
                sp.status_ketersediaan
            FROM sepeda sp
            JOIN shelter sh ON sp.id_shelter = sh.id_shelter
            ORDER BY sp.kode_qr ASC";
            
    $stmt = $pdo->query($sql);
    $units = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Unit Go-bike · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">
</head>

<body class="admin" data-peran="superadmin">

  <div class="admin-shell">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">

      <div class="sidebar-header">
        <div>
          <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
          <div class="role">Super Admin</div>
        </div>

        <label class="hamburger" for="nav-toggle" aria-label="Buka menu">
          <span></span>
          <span></span>
          <span></span>
        </label>
      </div>

      <nav class="menu" aria-label="Menu Super Admin">
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php" class="on">Unit Go-bike</a>
        <a href="peminjaman.php">Peminjaman</a>
        <a href="sanksi.php">Sanksi</a>
        <a href="laporan.php">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
        <a href="tambah-petugas-admin.php">Tambah Petugas/Admin</a>
      </nav>

      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <!-- ===== KONTEN UTAMA ===== -->
    <main class="main">
      <header class="topbar-a">
        <div>
          <h1>Unit Go-bike</h1>
          <p>Kelola data unit dan status ketersediaan</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <section class="panel">
        <div class="panel-head">
          <h2>Daftar Unit</h2>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>ID Go-bike</th>
                <th>Shelter</th>
                <th>Status</th>
                <th>Kondisi terakhir</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($units) > 0): ?>
                  <?php foreach ($units as $unit): ?>
                      <tr>
                        <td><?= htmlspecialchars($unit['kode_qr']) ?></td>
                        <td><?= htmlspecialchars($unit['nama_shelter']) ?></td>
                        
                        <?php 
                            $status = $unit['status_ketersediaan'];
                            $chip_class = 'chip-ok';
                            
                            if ($status === 'Dipinjam') {
                                $chip_class = 'chip-info';
                            } elseif ($status === 'Perawatan' || $status === 'Rusak') {
                                $chip_class = 'chip-warn';
                            }
                        ?>
                        <td><span class="chip <?= $chip_class ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td>Baik</td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="4" style="text-align: center; padding: 20px; color: var(--muted);">
                          Belum ada data unit sepeda.
                      </td>
                  </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <!-- MODAL POP-UP LOGOUT -->
  <div class="modal-overlay" id="logoutModal">
      <div class="modal-content">
          <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                  <polyline points="16 17 21 12 16 7"></polyline>
                  <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
          </div>
          <h2>Konfirmasi Keluar</h2>
          <p>Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses halaman superadmin.</p>
          <div class="modal-actions">
              <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
              <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
          </div>
      </div>
  </div>

  <script>
      function openLogoutModal() {
          document.getElementById('logoutModal').classList.add('active');
      }
      function closeLogoutModal() {
          document.getElementById('logoutModal').classList.remove('active');
      }
  </script>
  <script src="../assets/js/tabel.js"></script>
</body>

</html>