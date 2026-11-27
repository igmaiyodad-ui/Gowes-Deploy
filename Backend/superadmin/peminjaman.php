<?php
session_start();

// ==========================================
// 1. PROTEKSI HALAMAN (SUPERADMIN)
// ==========================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php"); 
    exit;
}

// ==========================================
// 2. KONEKSI DATABASE
// ==========================================
require_once '../config/koneksi.php';

// ==========================================
// 3. FITUR PENCARIAN
// ==========================================
$search = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = " AND (pm.no_identitas ILIKE :search OR s.kode_qr ILIKE :search)";
    $params['search'] = "%$search%";
}

// ==========================================
// 4. AMBIL DATA PEMINJAMAN AKTIF
// ==========================================
$sql = "SELECT p.*, pm.no_identitas, s.kode_qr 
        FROM peminjaman p
        JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam
        JOIN sepeda s ON p.id_sepeda = s.id_sepeda
        WHERE p.waktu_kembali IS NULL $where_clause
        ORDER BY p.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peminjaman = $stmt->fetchAll();
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="15">
  <title>Peminjaman · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">
  
  <style>
    .search-row { display: flex; align-items: center; gap: 12px; }
    .search-row form { display: flex; flex: 1; gap: 8px; }
    .search-row .inp { flex: 1; height: 38px; border: 0; border-radius: 8px; background: #f4f5f7; padding: 0 16px; font-family: inherit; font-size: 13px; outline: none; min-width: 0; }
    .search-row .inp:focus { box-shadow: 0 0 0 2px var(--primary); }
    .btn-cari { background: var(--primary); color: white; border: none; padding: 0 20px; height: 38px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; white-space: nowrap; }
    .tbl tbody tr { transition: background 0.2s; }
    .tbl tbody tr:hover { background-color: #f8fafc; }
    .actions { display: flex; gap: 8px; }

    @media (max-width: 600px) {
      .search-row { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body class="admin" data-peran="superadmin">
  <div class="admin-shell">
    
    <!-- ===== SIDEBAR SUPER ADMIN ===== -->
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
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php" class="on">Peminjaman</a>
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
          <h1>Peminjaman</h1>
          <p>Pantau peminjaman aktif secara realtime</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <!-- FORM PENCARIAN -->
      <section class="panel" style="padding: 16px 24px;">
        <div class="search-row">
          <h2>Cari Peminjaman</h2>
          <form method="GET" action="">
            <input class="inp" type="search" name="cari" placeholder="Cari Nomor Identitas atau ID Go-bike" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-cari">Cari</button>
          </form>
        </div>
      </section>

      <!-- TABEL PEMINJAMAN -->
      <section class="panel">
        <div class="panel-head"><h2>Peminjaman Aktif</h2></div>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Nomor Identitas</th>
                <th>Unit</th>
                <th>Mulai</th>
                <th>Batas</th>
                <th>Sisa waktu</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($peminjaman) > 0): ?>
                <?php foreach ($peminjaman as $row): ?>
                  <?php 
                    $jam_mulai = date('H.i', strtotime($row['waktu_mulai']));
                    $jam_batas = date('H.i', strtotime($row['batas_kembali']));
                    
                    $status = $row['status_keterlambatan'];
                    if (empty($status)) {
                        $status = (strtotime($row['batas_kembali']) < time()) ? 'Terlambat' : 'Aktif';
                    }
                    
                    $chip_class = ($status === 'Terlambat') ? 'chip-bad' : ((strtotime($row['batas_kembali']) - time() < 1800) ? 'chip-warn' : 'chip-ok');
                    $batas_iso = date('Y-m-d\TH:i:s', strtotime($row['batas_kembali']));
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($row['no_identitas']) ?></strong></td>
                    <td><?= htmlspecialchars($row['kode_qr']) ?></td>
                    <td><?= $jam_mulai ?></td>
                    <td><?= $jam_batas ?></td>
                    <td><span class="countdown" data-target="<?= $batas_iso ?>">Menghitung...</span></td>
                    <td><span class="chip <?= $chip_class ?>"><?= htmlspecialchars($status) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 20px;">Tidak ada peminjaman aktif.</td></tr>
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

  <script src="../assets/js/countdown.js"></script>
  <script src="../assets/js/logout-modal.js"></script>
</body>
</html>