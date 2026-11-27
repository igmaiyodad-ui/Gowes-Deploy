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
// 3. LOGIKA FILTER & PENCARIAN
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

try {
    $sql = "SELECT s.*, pm.nama, pm.no_identitas,
            (SELECT COUNT(*) FROM peminjaman p WHERE p.id_peminjam = pm.id_peminjam AND p.status_keterlambatan = 'Terlambat') as total_terlambat
            FROM sanksi s
            JOIN peminjam pm ON s.id_peminjam = pm.id_peminjam";
            
    $where_clauses = [];
    $params = [];

    if (!empty($search)) {
        $where_clauses[] = "(pm.no_identitas ILIKE :search OR pm.nama ILIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($filter_date)) {
        $where_clauses[] = ":filter_date BETWEEN s.tgl_mulai AND s.tgl_selesai";
        $params[':filter_date'] = $filter_date;
    }

    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY s.tgl_selesai DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sanksi = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error mengambil data sanksi: " . $e->getMessage());
}

$bulan_indo = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sanksi · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">

  <style>
    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .search-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .search-bar input {
        height: 38px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0 16px;
        font-family: inherit;
        font-size: 13px;
        outline: none;
        background: #f8fafc;
        color: #334155;
        transition: all 0.2s;
    }
    .search-bar input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        background: #fff;
    }
    .search-bar button {
        background: var(--primary);
        color: white;
        border: none;
        height: 38px;
        padding: 0 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .search-bar button:hover {
        opacity: 0.9;
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
        <a href="peminjaman.php">Peminjaman</a>
        <a href="sanksi.php" class="on">Sanksi</a>
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
          <h1>Sanksi</h1>
          <p>Teguran 1: suspen 3 hari · Teguran 2: 1 minggu · Teguran 3: permanen</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <section class="panel">
        <div class="panel-head">
          <h2>Rekap Sanksi</h2>
          
          <form method="GET" action="" class="search-bar">
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" title="Filter tanggal">
            <input type="search" name="search" placeholder="Cari NIM / Nama..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Cari</button>
          </form>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Nomor Identitas</th>
                <th>Nama</th>
                <th>Status Terlambat</th>
                <th>Periode Suspen</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($sanksi) > 0): ?>
                <?php foreach ($sanksi as $row): ?>
                  <?php 
                    $tgl_mulai = strtotime($row['tgl_mulai']);
                    $tgl_selesai = strtotime($row['tgl_selesai']);
                    $format_mulai = date('d', $tgl_mulai) . ' ' . $bulan_indo[(int)date('m', $tgl_mulai)];
                    $format_selesai = date('d', $tgl_selesai) . ' ' . $bulan_indo[(int)date('m', $tgl_selesai)];
                    
                    $hari_ini = date('Y-m-d');
                    $is_aktif = ($hari_ini >= $row['tgl_mulai'] && $hari_ini <= $row['tgl_selesai']);
                    $status_class = $is_aktif ? 'chip-bad' : 'chip-ok';
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($row['no_identitas']) ?></strong></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td>
                      <span style="font-size: 13px; margin-right: 8px;"><?= $row['total_terlambat'] ?> kali</span>
                      <span class="chip <?= $status_class ?>"><?= htmlspecialchars($row['jenis_tanggungan']) ?></span>
                    </td>
                    <td>
                      <?php if($is_aktif): ?>
                        <?= $format_mulai ?> – <?= $format_selesai ?> <?= date('Y', $tgl_selesai) ?>
                      <?php else: ?>
                        <span style="color: var(--muted);">Selesai (<?= $format_mulai ?> – <?= $format_selesai ?>)</span>
                      <?php endif; ?>
                    </td>
                    <td><a class="btn-sm" href="#">Detail</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; color: var(--muted); padding: 20px;">
                        Tidak ada data sanksi ditemukan.
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

  <script src="../assets/js/logout-modal.js"></script>
</body>
</html>