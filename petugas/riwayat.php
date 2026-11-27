<?php
session_start();

// 1. Proteksi Halaman: Hanya Petugas yang bisa masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
  header("Location: ../auth/login.php");
  exit;
}

// 2. Koneksi Database
require_once '../config/koneksi.php';

// 3. Logika Filter Pencarian & Tanggal
$search = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$tgl_filter = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$where_clauses = [];
$params = [];

if (!empty($search)) {
  $where_clauses[] = "(pm.no_identitas ILIKE :search OR s.kode_qr ILIKE :search)";
  $params['search'] = "%$search%";
}
if (!empty($tgl_filter)) {
  $where_clauses[] = "DATE(p.waktu_mulai) = :tanggal";
  $params['tanggal'] = $tgl_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 4. Ambil Data Riwayat
try {
  $sql = "SELECT p.*, pm.no_identitas, s.kode_qr 
            FROM peminjaman p 
            JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam 
            JOIN sepeda s ON p.id_sepeda = s.id_sepeda 
            $where_sql 
            ORDER BY p.waktu_mulai DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $riwayat = $stmt->fetchAll();
} catch (PDOException $e) {
  die("Error mengambil data riwayat: " . $e->getMessage());
}

$bulan_indo = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Riwayat Peminjaman · GOWES</title>
  <link rel="icon" href="../img/logo.png">

  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">
  <link rel="stylesheet" href="../assets/css/loader.css">

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

    .calendar-btn-wrap svg {
      width: 17px;
      height: 17px;
      pointer-events: none;
    }

    .calendar-btn-wrap input[type="date"] {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
      border: none;
      margin: 0;
      padding: 0;
    }

    .calendar-btn-wrap input[type="date"]::-webkit-calendar-picker-indicator {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
    }

    @media (max-width: 768px) {
      .main {
        padding: 16px !important;
      }

      .topbar-a {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .topbar-a h1 {
        font-size: 22px;
        line-height: 1.25;
      }

      .panel {
        padding: 16px;
        margin-top: 16px;
      }

      .panel-head-tools {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }

      .panel-head-tools .toolbar-group {
        display: flex;
        flex-direction: row;
        align-items: center;
        width: 100%;
        gap: 8px;
      }

      .calendar-btn-wrap {
        width: 42px;
        height: 42px;
      }

      .panel-head-tools .input-wrapper {
        flex: 1;
        min-width: 0;
        height: 42px;
      }

      .panel-head-tools .inp-cari {
        flex: 1;
        min-width: 0;
        font-size: 14px;
      }

      .panel-head-tools .btn-cari {
        height: 100%;
        padding: 0 18px;
      }
    }
  </style>
</head>

<body class="admin" data-peran="petugas">
  <div class="admin-shell">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">
      <div class="sidebar-header">
        <div>
          <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
          <div class="role">Petugas / Satpam</div>
        </div>
        <label class="hamburger" for="nav-toggle" aria-label="Buka menu">
          <span></span><span></span><span></span>
        </label>
      </div>
      <nav class="menu" aria-label="Menu Petugas / Satpam">
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php">Peminjaman</a>
        <a href="laporan.php">Laporan Kondisi</a>
        <a href="riwayat.php" class="on">Riwayat Peminjaman</a>
      </nav>

      <!-- TOMBOL LOGOUT -->
      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main">
      <header class="topbar-a">
        <div>
          <h1>Riwayat Peminjaman</h1>
          <p>Rekap seluruh peminjaman Go-bike</p>
        </div>
        <span class="pill">Petugas - Satpam</span>
      </header>

      <section class="panel">

        <!-- HEADER PANEL DENGAN TOMBOL FILTER -->
        <div class="panel-head-tools">
          <h2>Semua Peminjaman</h2>
          <div class="toolbar-group">
            <!-- Tombol Ikon Kalender Saja -->
            <div class="calendar-btn-wrap <?= !empty($tgl_filter) ? 'has-date' : '' ?>"
              title="<?= !empty($tgl_filter) ? 'Tanggal: ' . htmlspecialchars($tgl_filter) : 'Filter Tanggal' ?>"
              onclick="bukaKalender(this)">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <form id="dateForm" method="GET" action="riwayat.php" style="margin:0;">
                <input type="hidden" name="cari" value="<?= htmlspecialchars($search) ?>">
                <input type="date" id="hiddenDateInput" name="tanggal" value="<?= htmlspecialchars($tgl_filter) ?>"
                  onchange="document.getElementById('dateForm').submit()" aria-label="Filter Tanggal">
              </form>
            </div>

            <!-- Form Pencarian -->
            <form method="GET" action="riwayat.php" class="input-wrapper">
              <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tgl_filter) ?>">
              <input type="search" name="cari" class="inp-cari" placeholder="Cari NIM / Unit..."
                value="<?= htmlspecialchars($search) ?>" oninput="if(this.value.trim() === '') this.form.submit()">
              <button type="submit" class="btn-cari">Cari</button>
            </form>
          </div>
        </div>

        <!-- TABEL -->
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Nomor Identitas</th>
                <th>Unit</th>
                <th>Durasi</th>
                <th>Kembali</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($riwayat) > 0): ?>
                <?php foreach ($riwayat as $row): ?>
                  <?php
                  $ts_mulai = strtotime($row['waktu_mulai']);
                  $tgl = date('d', $ts_mulai) . ' ' . $bulan_indo[(int) date('m', $ts_mulai)] . ' ' . date('Y', $ts_mulai);
                  $jam_mulai = date('H.i', $ts_mulai);

                  $jam_kembali = "Belum kembali";
                  if ($row['waktu_kembali']) {
                    $jam_kembali = date('H.i', strtotime($row['waktu_kembali'])) . ' WIB';
                  }

                  $status = $row['status_keterlambatan'];
                  $chip_class = 'chip-ok';

                  if ($status === 'Terlambat') {
                    $chip_class = 'chip-bad';
                  } else if ($status === 'Mendekati Batas' || $row['waktu_kembali'] === null) {
                    $sisa = strtotime($row['batas_kembali']) - time();
                    if ($sisa < 1800 && $sisa > 0) {
                      $status = 'Mendekati batas';
                      $chip_class = 'chip-warn';
                    } else if ($sisa <= 0) {
                      $status = 'Terlambat';
                      $chip_class = 'chip-bad';
                    } else {
                      $status = 'Tepat waktu';
                      $chip_class = 'chip-ok';
                    }
                  } else {
                    $status = 'Tepat waktu';
                    $chip_class = 'chip-ok';
                  }
                  ?>
                  <tr>
                    <td><?= $tgl ?></td>
                    <td><?= $jam_mulai ?></td>
                    <td><?= htmlspecialchars($row['no_identitas']) ?></td>
                    <td><strong><?= htmlspecialchars($row['kode_qr']) ?></strong></td>
                    <td><?= htmlspecialchars($row['durasi_pinjam']) ?> jam</td>
                    <td><?= $jam_kembali ?></td>
                    <td><span class="chip <?= $chip_class ?>"><?= $status ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align:center; color: var(--muted); padding: 30px;">Tidak ada riwayat
                    peminjaman yang sesuai.</td>
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
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </div>
      <h2>Konfirmasi Keluar</h2>
      <p>Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses
        halaman petugas.</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
        <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
      </div>
    </div>
  </div>

  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/datepicker.js"></script>
  <script src="../assets/js/loader.js"></script>

  <script>
    function openLogoutModal() {
      document.getElementById('logoutModal').classList.add('active');
    }
    function closeLogoutModal() {
      document.getElementById('logoutModal').classList.remove('active');
    }
    function bukaKalender(el) {
      const input = el.querySelector('input[type="date"]');
      if (input && typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch (e) { input.focus(); }
      }
    }
  </script>
</body>

</html>