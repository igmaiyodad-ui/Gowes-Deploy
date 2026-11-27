<?php
session_start();

// ==========================================
// 1. KONEKSI DATABASE & PROTEKSI HALAMAN
// ==========================================
require_once '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';

// ==========================================
// 2. LOGIKA FILTER & PENCARIAN RIWAYAT
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

try {
  $sql = "SELECT 
                pm.id_peminjaman,
                p.no_identitas,
                p.nama,
                sp.kode_qr,
                pm.waktu_mulai,
                pm.waktu_kembali,
                pm.batas_kembali, 
                pm.status_keterlambatan
            FROM peminjaman pm
            JOIN peminjam p ON pm.id_peminjam = p.id_peminjam
            JOIN sepeda sp ON pm.id_sepeda = sp.id_sepeda";

  $where_clauses = ["pm.waktu_kembali IS NOT NULL"];
  $params = [];

  if (!empty($search)) {
    $where_clauses[] = "(p.no_identitas ILIKE :search OR p.nama ILIKE :search OR sp.kode_qr ILIKE :search)";
    $params[':search'] = "%$search%";
  }

  if (!empty($filter_date)) {
    $where_clauses[] = "DATE(pm.waktu_kembali) = :filter_date";
    $params[':filter_date'] = $filter_date;
  }

  $sql .= " WHERE " . implode(" AND ", $where_clauses);
  $sql .= " ORDER BY pm.waktu_kembali DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $riwayat = $stmt->fetchAll();

} catch (PDOException $e) {
  die("Error Database: " . $e->getMessage());
}

function formatTanggalWaktuIndo($dateString)
{
  if (!$dateString)
    return '-';
  $bulan = [
    1 => 'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des'
  ];
  $date = new DateTime($dateString);
  return $date->format('d') . ' ' . $bulan[(int) $date->format('n')] . ' ' . $date->format('Y') . ' • ' . $date->format('H:i');
}
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
    /* Styling untuk Badge Status */
    .chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
      letter-spacing: 0.3px;
    }

    .chip-ok {
      background: #dcfce7;
      color: #15803d;
    }

    .chip-bad {
      background: #fee2e2;
      color: #b91c1c;
    }

    /* Styling Tabel Modern */
    .table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
      margin-top: 16px;
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    .tbl th {
      background: #f8fafc;
      padding: 16px 20px;
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
      border-bottom: 1px solid #e2e8f0;
      white-space: nowrap;
    }

    .tbl td {
      padding: 16px 20px;
      font-size: 14px;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
      white-space: nowrap;
    }

    .tbl tbody tr {
      transition: background-color 0.2s;
    }

    .tbl tbody tr:hover {
      background-color: #f8fafc;
    }

    .tbl tr:last-child td {
      border-bottom: none;
    }

    /* ========================================= */
    /* TOMBOL IKON KALENDER & SEARCH BAR         */
    /* ========================================= */
    .toolbar-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .calendar-btn-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
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
      width: 18px;
      height: 18px;
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

    .input-wrapper {
      display: flex;
      align-items: center;
      position: relative;
      border: none !important;
      background: transparent !important;
      height: auto !important;
    }

    .inp-cari {
      height: 38px !important;
      padding: 0 16px !important;
      border: 1.5px solid #e2e8f0 !important;
      border-radius: 8px !important;
      background: #f8fafc !important;
      font-size: 13px !important;
      outline: none;
      transition: 0.2s;
      width: 250px;
    }

    .inp-cari:focus {
      border-color: var(--primary) !important;
      background: #fff !important;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .btn-cari {
      height: 38px !important;
      padding: 0 20px !important;
      margin-left: 8px;
      border-radius: 8px !important;
      font-size: 13px !important;
      flex: 0 0 auto;
    }

    /* ========================================= */
    /* RESPONSIVE MOBILE                         */
    /* ========================================= */
    @media (max-width: 768px) {
      .main {
        padding: 16px !important;
        min-width: 0 !important;
      }

      .topbar-a {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
        margin-bottom: 16px !important;
      }

      .topbar-a h1 {
        font-size: 22px !important;
        line-height: 1.25 !important;
      }

      .panel {
        padding: 16px !important;
      }

      .panel-head-tools {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 14px !important;
      }

      .toolbar-group {
        width: 100% !important;
        gap: 8px !important;
      }

      .calendar-btn-wrap {
        width: 42px !important;
        height: 42px !important;
      }

      .input-wrapper {
        flex: 1 1 auto !important;
        min-width: 0 !important;
      }

      .inp-cari {
        flex: 1 1 auto !important;
        width: 100% !important;
        min-width: 0 !important;
        height: 42px !important;
        font-size: 14px !important;
      }

      .btn-cari {
        height: 42px !important;
        padding: 0 18px !important;
      }

      .tbl {
        min-width: 720px !important;
      }

      .tbl th,
      .tbl td {
        padding: 12px 14px !important;
      }
    }
  </style>
</head>

<body class="admin" data-peran="admin">

  <div class="admin-shell">
    <aside class="sidebar">
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">
      <div class="sidebar-header">
        <div>
          <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
          <div class="role">Admin / RT POLINEMA</div>
        </div>
        <label class="hamburger" for="nav-toggle" aria-label="Buka menu">
          <span></span><span></span><span></span>
        </label>
      </div>
      <nav class="menu" aria-label="Menu Admin / RT POLINEMA">
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php">Peminjaman</a>
        <a href="sanksi.php">Sanksi</a>
        <a href="laporan.php">Laporan Kondisi</a>
        <a href="riwayat.php" class="on">Riwayat Peminjaman</a>
      </nav>
      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <main class="main">
      <header class="topbar-a" style="margin-bottom: 24px;">
        <div>
          <h1 style="font-size: 28px; color: #1e293b;">Riwayat Peminjaman</h1>
          <p style="color: #64748b; margin-top: 4px;">Catatan transaksi mahasiswa yang telah selesai</p>
        </div>
        <span class="pill">Admin - RT POLINEMA</span>
      </header>

      <section class="panel" style="padding: 24px;">

        <!-- HEADER PANEL & PENCARIAN -->
        <div class="panel-head-tools"
          style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
          <h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0;">Data Riwayat</h2>

          <form method="GET" action="" class="toolbar-group">
            <!-- Tombol Ikon Kalender Saja -->
            <div class="calendar-btn-wrap <?= !empty($filter_date) ? 'has-date' : '' ?>"
              title="<?= !empty($filter_date) ? 'Tanggal: ' . htmlspecialchars($filter_date) : 'Filter Tanggal' ?>"
              onclick="bukaKalender(this)">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                onchange="this.form.submit()" aria-label="Filter Tanggal">
            </div>

            <!-- Input Pencarian -->
            <div class="input-wrapper">
              <input type="search" name="search" class="inp-cari" placeholder="Cari NIM / Nama / Unit..."
                value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn-cari">Cari</button>
            </div>
          </form>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Nomor Identitas</th>
                <th>Nama</th>
                <th>Unit</th>
                <th>Waktu Mulai</th>
                <th>Waktu Kembali</th>
                <th style="text-align: center;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($riwayat) > 0): ?>
                <?php foreach ($riwayat as $row): ?>
                  <?php
                  $waktu_kembali = strtotime($row['waktu_kembali']);
                  $batas_kembali = strtotime($row['batas_kembali']);

                  $is_late = ($waktu_kembali > $batas_kembali);

                  $chip_class = $is_late ? 'chip-bad' : 'chip-ok';
                  $status_teks = $is_late ? 'Terlambat' : 'Tepat Waktu';
                  ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($row['no_identitas']) ?></strong></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td>
                      <span
                        style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px;">
                        <?= htmlspecialchars($row['kode_qr']) ?>
                      </span>
                    </td>
                    <td style="color: #64748b; font-size: 13px;"><?= formatTanggalWaktuIndo($row['waktu_mulai']) ?></td>
                    <td style="color: #64748b; font-size: 13px;"><?= formatTanggalWaktuIndo($row['waktu_kembali']) ?></td>
                    <td style="text-align: center;">
                      <span class="chip <?= $chip_class ?>"><?= htmlspecialchars($status_teks) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                        style="margin-bottom: 12px; opacity: 0.5;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                      <span style="font-size: 15px;">Tidak ada data riwayat peminjaman ditemukan.</span>
                    </div>
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
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </div>
      <h2>Konfirmasi Keluar</h2>
      <p>Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses halaman admin.</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
        <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
      </div>
    </div>
  </div>

  <!-- JAVASCRIPT -->
  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/logout-modal.js"></script>
  <script>
    function bukaKalender(el) {
      const input = el.querySelector('input[type="date"]');
      if (input && typeof input.showPicker === 'function') {
        try {
          input.showPicker();
        } catch (e) {
          input.focus();
        }
      }
    }
  </script>
</body>

</html>