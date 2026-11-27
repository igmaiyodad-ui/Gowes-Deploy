<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php"); 
    exit;
}
require_once '../config/koneksi.php';

// Logika Filter Pencarian & Tanggal
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(pm.no_identitas ILIKE :search OR s.kode_qr ILIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($filter_date)) {
    $where_clauses[] = "DATE(lk.tgl_lapor) = :filter_date";
    $params[':filter_date'] = $filter_date;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT lk.*, s.kode_qr, pm.no_identitas 
        FROM laporan_kondisi lk 
        JOIN peminjaman p ON lk.id_peminjaman = p.id_peminjaman 
        JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam 
        JOIN sepeda s ON p.id_sepeda = s.id_sepeda 
        $where_sql
        ORDER BY lk.tgl_lapor DESC, lk.id_laporankondisi DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan = $stmt->fetchAll();

$bulan_indo = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan kondisi · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  <link rel="stylesheet" href="../assets/css/fonts.css"><link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css"><link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css"><link rel="stylesheet" href="../assets/css/loader.css">
  <style>
    /* Tombol Ikon Kalender Saja */
    .calendar-btn-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      background: #fff;
      border: 1.5px solid var(--primary);
      border-radius: 8px;
      color: var(--primary);
      cursor: pointer;
      flex: 0 0 auto;
      transition: background 0.2s, color 0.2s;
    }
    .calendar-btn-wrap:hover,
    .calendar-btn-wrap.has-date {
      background: var(--primary);
      color: #fff;
    }
    .calendar-btn-wrap svg { width: 17px; height: 17px; pointer-events: none; }
    .calendar-btn-wrap input[type="date"] {
      position: absolute; inset: 0; width: 100%; height: 100%;
      opacity: 0; cursor: pointer; border: none; margin: 0; padding: 0;
    }
    .calendar-btn-wrap input[type="date"]::-webkit-calendar-picker-indicator {
      position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;
    }

    @media (max-width: 768px) {
      .main { padding: 16px !important; }
      .topbar-a { flex-direction: column; align-items: flex-start; gap: 10px; }
      .topbar-a h1 { font-size: 22px; line-height: 1.25; }
      .panel { padding: 16px; margin-top: 16px; }
      .panel-head-tools { flex-direction: column; align-items: stretch; gap: 12px; }
      .panel-head-tools .toolbar-group { display: flex; flex-direction: row; align-items: center; width: 100%; gap: 8px; }
      .calendar-btn-wrap { width: 42px; height: 42px; }
      .panel-head-tools .input-wrapper { flex: 1; min-width: 0; height: 42px; }
      .panel-head-tools .inp-cari { flex: 1; min-width: 0; font-size: 14px; }
      .panel-head-tools .btn-cari { height: 100%; padding: 0 18px; }
      .tbl th:last-child, .tbl td:last-child { white-space: normal; min-width: 180px; }
    }
  </style>
</head>
<body class="admin" data-peran="petugas">
  <div class="admin-shell">
    <aside class="sidebar">
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">
      <div class="sidebar-header">
        <div>
          <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
          <div class="role">Petugas / Satpam</div>
        </div>
        <label class="hamburger" for="nav-toggle" aria-label="Buka menu"><span></span><span></span><span></span></label>
      </div>
      <nav class="menu" aria-label="Menu Petugas / Satpam">
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php">Peminjaman</a>
        <a href="laporan.php" class="on">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
      </nav>
      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <main class="main">
      <header class="topbar-a">
        <div><h1>Laporan Kondisi</h1><p>Laporan yang dikirim mahasiswa setelah pengembalian</p></div>
        <span class="pill">Petugas - Satpam</span>
      </header>

      <section class="panel">
        <div class="panel-head-tools">
          <h2>Laporan Terbaru</h2>
          <form method="GET" action="" class="toolbar-group">
            <!-- Tombol Ikon Kalender Saja -->
            <div class="calendar-btn-wrap <?= !empty($filter_date) ? 'has-date' : '' ?>" title="<?= !empty($filter_date) ? 'Tanggal: ' . htmlspecialchars($filter_date) : 'Filter Tanggal' ?>" onclick="bukaKalender(this)">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" aria-label="Filter Tanggal">
            </div>

            <!-- Input Pencarian -->
            <div class="input-wrapper">
              <input type="search" name="search" class="inp-cari" placeholder="Cari NIM / Unit..." value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn-cari">Cari</button>
            </div>
          </form>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Tanggal</th><th>Unit</th><th>Nomor Identitas</th><th>Kondisi</th><th>Keterangan</th></tr></thead>
            <tbody>
              <?php if (count($laporan) > 0): ?>
                <?php foreach ($laporan as $row): ?>
                  <?php 
                    $ts = strtotime($row['tgl_lapor']);
                    $tgl = date('d', $ts) . ' ' . $bulan_indo[(int)date('m', $ts)] . ' ' . date('Y', $ts);
                    $rata = ($row['nilai_ban'] + $row['nilai_rem'] + $row['nilai_rantai'] + $row['nilai_rangka_bodi']) / 4;
                    
                    if ($rata >= 4) { $chip_class = 'chip-ok'; $status_text = 'Baik'; }
                    elseif ($rata >= 2.5) { $chip_class = 'chip-warn'; $status_text = 'Perlu perawatan'; }
                    else { $chip_class = 'chip-bad'; $status_text = 'Rusak'; }
                  ?>
                  <tr>
                    <td><?= $tgl ?></td>
                    <td><strong><?= htmlspecialchars($row['kode_qr']) ?></strong></td>
                    <td><?= htmlspecialchars($row['no_identitas']) ?></td>
                    <td><span class="chip <?= $chip_class ?>"><?= $status_text ?></span></td>
                    <td><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5" style="text-align:center; color: var(--muted); padding: 30px;">Belum ada laporan kondisi.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <div class="modal-overlay" id="logoutModal">
      <div class="modal-content">
          <div class="modal-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></div>
          <h2>Konfirmasi Keluar</h2><p>Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses halaman petugas.</p>
          <div class="modal-actions"><button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button><a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a></div>
      </div>
  </div>

  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/logout-modal.js"></script>
  <script src="../assets/js/loader.js"></script>
  <script>
    function bukaKalender(el) {
      const input = el.querySelector('input[type="date"]');
      if (input && typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch (e) { input.focus(); }
      }
    }
  </script>
</body>
</html>