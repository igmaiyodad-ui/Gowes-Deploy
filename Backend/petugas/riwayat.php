<?php
session_start();

// 1. Proteksi Halaman: Hanya Petugas yang bisa masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
  header("Location: ../auth/login.php");
  exit;
}

// 2. PERBAIKAN PATH KONEKSI
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
    /* ========================================= */
    /* CSS MODAL LOGOUT (SESUAI SCREENSHOT)      */
    /* ========================================= */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.6);
      /* Latar belakang gelap transparan */
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: blur(3px);
      /* Efek blur latar belakang */
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-content {
      background: #fff;
      padding: 32px 24px;
      border-radius: 20px;
      width: 90%;
      max-width: 420px;
      text-align: center;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
      animation: modalFadeIn 0.2s ease-out;
    }

    @keyframes modalFadeIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .modal-icon-wrapper {
      width: 64px;
      height: 64px;
      background: #fee2e2;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
    }

    .modal-icon-wrapper svg {
      width: 32px;
      height: 32px;
      color: #ef4444;
    }

    .modal-title {
      font-size: 20px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 12px 0;
    }

    .modal-desc {
      font-size: 14px;
      color: #64748b;
      line-height: 1.6;
      margin: 0 0 24px 0;
      padding: 0 10px;
    }

    .modal-actions {
      display: flex;
      gap: 12px;
    }

    .btn-cancel {
      flex: 1;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #cbd5e1;
      background: #ffffff;
      color: #475569;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-cancel:hover {
      background: #f8fafc;
    }

    .btn-confirm {
      flex: 1;
      padding: 12px;
      border-radius: 10px;
      border: none;
      background: #ef4444;
      color: white;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-confirm:hover {
      background: #dc2626;
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
            <form method="GET" action="riwayat.php" class="input-wrapper">
              <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tgl_filter) ?>">
              <input type="text" name="cari" class="inp-cari" placeholder="Cari NIM / Unit..."
                value="<?= htmlspecialchars($search) ?>" oninput="if(this.value.trim() === '') this.form.submit()">
              <button type="submit" class="btn-cari">Cari</button>
            </form>
            <div style="position:relative; display:inline-block;">
              <button type="button" class="btn-filter" id="btnTanggal">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                  stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                Tanggal
              </button>

              <form id="dateForm" method="GET" action="riwayat.php" style="margin:0;">
                <input type="hidden" name="cari" value="<?= htmlspecialchars($search) ?>">
                <input type="date" id="hiddenDateInput" name="tanggal" value="<?= htmlspecialchars($tgl_filter) ?>"
                  onchange="document.getElementById('dateForm').submit()" tabindex="-1" aria-hidden="true"
                  style="position:absolute; left:0; top:0; width:100%; height:100%; opacity:0; pointer-events:none; border:0; padding:0;">
              </form>
            </div>

            <script>
              document.getElementById('btnTanggal').addEventListener('click', function () {
                var input = document.getElementById('hiddenDateInput');
                if (input.showPicker) { input.showPicker(); } else { input.focus(); input.click(); }
              });
            </script>

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

                  // Logika Status 
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
                    <td><?= htmlspecialchars($row['kode_qr']) ?></td>
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

  <!-- ========================================== -->
  <!-- MODAL POP-UP LOGOUT (SESUAI SCREENSHOT)    -->
  <!-- ========================================== -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal-content">
      <div class="modal-icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </div>
      <h2 class="modal-title">Konfirmasi Keluar</h2>
      <p class="modal-desc">Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses
        halaman petugas.</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
        <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
      </div>
    </div>
  </div>

  <!-- JAVASCRIPT EKSTERNAL -->
  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/datepicker.js"></script>
  <script src="../assets/js/loader.js"></script>

  <!-- SCRIPT UNTUK MEMBUKA & MENUTUP MODAL -->
  <script>
    function openLogoutModal() {
      document.getElementById('logoutModal').classList.add('active');
    }
    function closeLogoutModal() {
      document.getElementById('logoutModal').classList.remove('active');
    }
  </script>
</body>

</html>