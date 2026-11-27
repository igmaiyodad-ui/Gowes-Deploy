<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'petugas')) {
  header("Location: ../auth/login.php");
  exit;
}
require_once '../config/koneksi.php';

$is_admin = ($_SESSION['role'] === 'admin');
$search = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$where_clause = "";
$params = [];

if (!empty($search)) {
  $where_clause = " AND (pm.no_identitas ILIKE :search OR s.kode_qr ILIKE :search)";
  $params['search'] = "%$search%";
}

$sql = "SELECT p.*, pm.no_identitas, s.kode_qr FROM peminjaman p
        JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam
        JOIN sepeda s ON p.id_sepeda = s.id_sepeda
        WHERE p.waktu_kembali IS NULL $where_clause ORDER BY p.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peminjaman = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Peminjaman · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">

  <style>
    /* --- STYLING BADGE / CHIP --- */
    .chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .chip-ok {
      background: #dcfce7;
      color: #15803d;
    }

    .chip-warn {
      background: #fef08a;
      color: #854d0e;
    }

    .chip-bad {
      background: #fee2e2;
      color: #b91c1c;
    }

    .chip-info {
      background: #e0e7ff;
      color: #4338ca;
    }

    /* --- STYLING HEADER & SEARCH (RESPONSIVE) --- */
    .header-flex {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }

    .search-container {
      display: flex;
      align-items: center;
      gap: 16px;
      width: 100%;
    }

    .search-form-custom {
      display: flex;
      flex: 1;
      gap: 8px;
      max-width: 500px;
    }

    .search-input-custom {
      flex: 1;
      height: 38px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0 16px;
      font-size: 13px;
      outline: none;
      transition: 0.2s;
    }

    .search-input-custom:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .btn-cari-custom {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0 20px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-cari-custom:hover {
      background: #4338ca;
    }

    /* --- RESPONSIVE TAMPILAN HP --- */
    @media (max-width: 768px) {
      .search-container {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }

      .search-form-custom {
        max-width: 100%;
      }

      .header-flex {
        flex-direction: column;
        align-items: flex-start;
      }

      .topbar-a {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 12px;
      }
    }

    /* --- TABLE RESPONSIVE (BISA SCROLL KE SAMPING) --- */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .table-responsive .tbl {
      min-width: 700px;
      /* Mencegah tabel terlalu sempit di HP */
    }

    /* --- MODAL LOGOUT --- */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: blur(3px);
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
    }
  </style>
</head>

<body class="admin" data-peran="admin">
  <div class="admin-shell">

    <!-- ========================================== -->
    <!-- SIDEBAR DENGAN TOMBOL BURGER (NAV-TOGGLE)  -->
    <!-- ========================================== -->
    <aside class="sidebar">
      <!-- Input Checkbox Tersembunyi untuk Toggle -->
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">

      <div class="sidebar-header">
        <div>
          <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
          <div class="role">Admin / RT POLINEMA</div>
        </div>
        <!-- Label Burger (Tombol Garis Tiga) -->
        <label class="hamburger" for="nav-toggle" aria-label="Buka menu">
          <span></span><span></span><span></span>
        </label>
      </div>

      <nav class="menu">
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php" class="on">Peminjaman</a>
        <a href="sanksi.php">Sanksi</a>
        <a href="laporan.php">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
      </nav>

      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <main class="main">
      <!-- HEADER -->
      <header class="topbar-a header-flex" style="margin-bottom: 24px;">
        <div>
          <h1 style="font-size: 24px; color: #1e293b; margin-bottom: 4px;">Peminjaman</h1>
          <p style="color: #64748b; font-size: 14px;">Pantau peminjaman aktif secara realtime</p>
        </div>
        <span class="pill"
          style="background: #e0e7ff; color: #4338ca; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;">Admin
          - RT POLINEMA</span>
      </header>

      <!-- SEARCH PANEL -->
      <section class="panel" style="padding: 20px 24px;">
        <div class="search-container">
          <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; white-space: nowrap;">Cari Peminjaman
          </h2>
          <form method="GET" action="" class="search-form-custom">
            <input class="search-input-custom" type="search" name="cari" placeholder="Cari NIM atau ID Go-bike"
              value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-cari-custom">Cari</button>
          </form>
        </div>
      </section>

      <!-- TABLE PANEL -->
      <section class="panel">
        <div class="panel-head">
          <h2 style="font-size: 16px; font-weight: 700; color: #1e293b;">Peminjaman Aktif</h2>
        </div>
        <div class="table-responsive">
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
                  $status_db = $row['status_peminjaman'] ?? 'Menunggu';
                  $is_menunggu = ($status_db === 'Menunggu');
                  $is_menunggu_kembali = ($status_db === 'Menunggu Kembali');

                  $jam_mulai = date('H.i', strtotime($row['waktu_mulai']));
                  $jam_batas = date('H.i', strtotime($row['batas_kembali']));

                  $now = time();
                  $batas_time = strtotime($row['batas_kembali']);
                  $selisih = $batas_time - $now;

                  if ($is_menunggu) {
                    $chip_class = 'chip-info';
                    $chip_text = 'Menunggu Pinjam';
                    $sisa_html = '<span style="color: #f59e0b; font-weight: 600; font-size: 12px; white-space: nowrap;">Belum Mulai</span>';
                  } elseif ($is_menunggu_kembali) {
                    $chip_class = 'chip-info';
                    $chip_text = 'Menunggu Kembali';
                    $sisa_html = '<span style="color: #f59e0b; font-weight: 600; font-size: 12px; white-space: nowrap;">Menunggu ACC</span>';
                  } elseif ($selisih < 0) {
                    $chip_class = 'chip-bad';
                    $chip_text = 'Terlambat';
                    $diff = abs($selisih);
                    $sisa_html = '<span style="color: #dc2626; font-weight: 600; font-size: 12px; white-space: nowrap;">Lewat ' . sprintf("%02d:%02d:%02d", floor($diff / 3600), floor(($diff % 3600) / 60), $diff % 60) . '</span>';
                  } elseif ($selisih > 0 && $selisih < 1800) {
                    $chip_class = 'chip-warn';
                    $chip_text = 'Mendekati Batas';
                    $sisa_html = '<span class="countdown" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                  } else {
                    $chip_class = 'chip-ok';
                    $chip_text = 'Dipinjam';
                    $sisa_html = '<span class="countdown" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                  }
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($row['no_identitas']) ?></strong></td>
                    <td><?= htmlspecialchars($row['kode_qr']) ?></td>
                    <td><?= $jam_mulai ?></td>
                    <td><?= $jam_batas ?></td>
                    <td><?= $sisa_html ?></td>
                    <td><span class="chip <?= $chip_class ?>"><?= $chip_text ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--muted); padding: 20px;">Tidak ada peminjaman
                    aktif.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- MODAL LOGOUT -->
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
        halaman admin.</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
        <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
      </div>
    </div>
  </div>

  <script src="../assets/js/countdown.js"></script>
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