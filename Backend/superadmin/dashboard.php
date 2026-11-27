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
// 2. LOGIKA PENGAMBILAN DATA STATISTIK
// ==========================================
try {
    // Unit tersedia
    $stmt = $pdo->query("SELECT COUNT(*) FROM sepeda WHERE status_ketersediaan = 'Tersedia'");
    $unit_tersedia = $stmt->fetchColumn();

    // Unit tidak tersedia (Dipinjam / Perawatan)
    $stmt = $pdo->query("SELECT COUNT(*) FROM sepeda WHERE status_ketersediaan != 'Tersedia'");
    $unit_tidak_tersedia = $stmt->fetchColumn();

    // Peminjaman terlambat aktif
    $stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE waktu_kembali IS NULL AND CURRENT_TIMESTAMP > batas_kembali");
    $peminjaman_terlambat = $stmt->fetchColumn();

    // User suspen aktif
    $stmt = $pdo->query("SELECT COUNT(*) FROM sanksi WHERE CURRENT_DATE BETWEEN tgl_mulai AND tgl_selesai");
    $user_suspen = $stmt->fetchColumn();

    // Petugas aktif
    $stmt = $pdo->query("SELECT COUNT(*) FROM petugas");
    $petugas_aktif = $stmt->fetchColumn();

    // Admin aktif
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
    $admin_aktif = $stmt->fetchColumn();

    // ==========================================
    // 3. LOGIKA PENGAMBILAN DATA SHELTER
    // ==========================================
    $sql_shelter = "SELECT 
                        s.nama_shelter, 
                        s.lokasi, 
                        COUNT(sp.id_sepeda) AS total_unit,
                        SUM(CASE WHEN sp.status_ketersediaan = 'Tersedia' THEN 1 ELSE 0 END) AS unit_tersedia
                    FROM shelter s
                    LEFT JOIN sepeda sp ON s.id_shelter = sp.id_shelter
                    GROUP BY s.id_shelter, s.nama_shelter, s.lokasi";
    $stmt = $pdo->query($sql_shelter);
    $shelters = $stmt->fetchAll();

    // ==========================================
    // 4. LOGIKA PENGAMBILAN DATA PEMINJAMAN AKTIF
    // ==========================================
    $sql_peminjaman = "SELECT 
                            p.no_identitas, 
                            sp.kode_qr, 
                            s.nama_shelter, 
                            pm.batas_kembali 
                       FROM peminjaman pm
                       JOIN peminjam p ON pm.id_peminjam = p.id_peminjam
                       JOIN sepeda sp ON pm.id_sepeda = sp.id_sepeda
                       JOIN shelter s ON sp.id_shelter = s.id_shelter
                       WHERE pm.waktu_kembali IS NULL
                       ORDER BY pm.batas_kembali ASC
                       LIMIT 5";
    $stmt = $pdo->query($sql_peminjaman);
    $peminjaman_aktif = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Super Admin · GOWES</title>
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
        <a href="dashboard.php" class="on">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
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
          <h1>Dashboard Super Admin</h1>
          <p>Senin–Jumat, 08.00–15.30 WIB</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <section class="stats">
        <div class="stat">
          <div class="k">Unit sepeda tersedia</div>
          <div class="v" style="color:var(--primary)"><?= $unit_tersedia ?></div>
        </div>
        <div class="stat">
          <div class="k">Unit sepeda tidak tersedia</div>
          <div class="v" style="color:var(--ink)"><?= $unit_tidak_tersedia ?></div>
        </div>
        <div class="stat">
          <div class="k">Pengembalian Terlambat</div>
          <div class="v" style="color:var(--orange)"><?= $peminjaman_terlambat ?></div>
        </div>
        <div class="stat">
          <div class="k">User suspen aktif</div>
          <div class="v" style="color:var(--red)"><?= $user_suspen ?></div>
        </div>
        <div class="stat">
          <div class="k">Petugas Aktif</div>
          <div class="v" style="color:var(--primary)"><?= $petugas_aktif ?></div>
        </div>
        <div class="stat">
          <div class="k">Admin Aktif</div>
          <div class="v" style="color:var(--primary)"><?= $admin_aktif ?></div>
        </div>
      </section>

      <section class="grid-2">
        <?php if(count($shelters) > 0): ?>
            <?php foreach($shelters as $shelter): ?>
                <div class="shelter-card">
                  <h3><?= htmlspecialchars($shelter['nama_shelter']) ?></h3>
                  <p><?= htmlspecialchars($shelter['lokasi']) ?></p>
                  <b><?= $shelter['unit_tersedia'] ?> dari <?= $shelter['total_unit'] ?> unit tersedia</b>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="shelter-card"><p>Belum ada data shelter.</p></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2>Peminjaman Aktif</h2>
          <a class="link" href="peminjaman.php">Lihat semua</a>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Nomor Identitas</th>
                <th>Unit</th>
                <th>Shelter</th>
                <th>Batas Kembali</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($peminjaman_aktif) > 0): ?>
                  <?php foreach($peminjaman_aktif as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars($row['no_identitas']) ?></td>
                        <td><?= htmlspecialchars($row['kode_qr']) ?></td>
                        <td><?= htmlspecialchars($row['nama_shelter']) ?></td>
                        <td><?= date('H:i', strtotime($row['batas_kembali'])) ?> WIB</td>
                        
                        <?php 
                            $sekarang = new DateTime();
                            $batas = new DateTime($row['batas_kembali']);
                            $selisih = $sekarang->diff($batas);
                            
                            if ($sekarang > $batas) {
                                echo '<td><span class="chip chip-bad">Terlambat</span></td>';
                            } elseif ($selisih->h == 0 && $selisih->i <= 30 && $sekarang < $batas) {
                                echo '<td><span class="chip chip-warn">Mendekati batas</span></td>';
                            } else {
                                echo '<td><span class="chip chip-ok">Aktif</span></td>';
                            }
                        ?>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="5" style="text-align: center;">Tidak ada peminjaman aktif saat ini.</td>
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