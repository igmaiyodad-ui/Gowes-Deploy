<?php
session_start();

// 1. Proteksi Halaman: Hanya Admin yang bisa masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit;
}

// 2. Koneksi Database
require_once '../config/koneksi.php';

// Logika kondisi (harus sama persis dengan unit.php)
if (!function_exists('labelKondisi')) {
  function labelKondisi($ban, $rem, $rantai, $rangka): array
  {
    $rata = ($ban + $rem + $rantai + $rangka) / 4;
    if ($rata >= 4)
      return ['Baik', 'chip-ok'];
    if ($rata >= 2.5)
      return ['Perlu perawatan', 'chip-warn'];
    return ['Rusak', 'chip-bad'];
  }
}

// 3. Logika Filter Pencarian & Tanggal
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';
$where_clauses = [];
$params = [];

if (!empty($search)) {
  $where_clauses[] = "(pm.no_identitas ILIKE :search OR pm.nama ILIKE :search OR s.kode_qr ILIKE :search)";
  $params[':search'] = "%$search%";
}
if (!empty($filter_date)) {
  $where_clauses[] = "DATE(lk.tgl_lapor) = :filter_date";
  $params[':filter_date'] = $filter_date;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 4. Ambil Data Laporan
try {
  $sql = "SELECT lk.*, s.kode_qr, pm.no_identitas, pm.nama
            FROM laporan_kondisi lk
            JOIN peminjaman p ON lk.id_peminjaman = p.id_peminjaman
            JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam
            JOIN sepeda s ON p.id_sepeda = s.id_sepeda
            $where_sql
            ORDER BY lk.tgl_lapor DESC, lk.id_laporankondisi DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $laporan = $stmt->fetchAll();
} catch (PDOException $e) {
  die("Error mengambil data laporan: " . $e->getMessage());
}

$bulan_indo = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan kondisi · GOWES</title>
  <link rel="icon" href="../img/logo.png">

  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">
  <link rel="stylesheet" href="../assets/css/loader.css">
  <style>
    @media (max-width: 768px) {

      /* 1. Area konten: kurangi padding luar */
      .main {
        padding: 16px !important;
        min-width: 0 !important;
      }

      /* 2. Header halaman: judul di atas, badge di bawah, tidak terpotong */
      .topbar-a {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        margin-bottom: 16px !important;
      }

      .topbar-a>div {
        width: 100% !important;
      }

      .topbar-a h1 {
        font-size: 22px !important;
        line-height: 1.25 !important;
        margin: 0 0 4px !important;
      }

      .topbar-a p {
        font-size: 13px !important;
        margin: 0 !important;
      }

      /* 3. Panel */
      .panel {
        padding: 16px !important;
      }

      /* 4. Header panel + toolbar: tumpuk vertikal, lebar penuh */
      .panel-head-tools {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 14px !important;
        height: auto !important;
        overflow: visible !important;
      }

      .panel-head-tools h2 {
        margin: 0 !important;
      }

      .panel-head-tools .toolbar-group {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 10px !important;
      }

      /* Input tanggal (wrapper inline style) */
      .panel-head-tools .toolbar-group>div:first-child {
        width: 100% !important;
        height: 42px !important;
      }

      .panel-head-tools .toolbar-group input[type="date"] {
        width: 100% !important;
        font-size: 14px !important;
      }

      /* Input pencarian + tombol Cari */
      .panel-head-tools .input-wrapper {
        display: flex !important;
        width: 100% !important;
      }

      .panel-head-tools .inp-cari {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        height: 42px !important;
        font-size: 14px !important;
      }

      .panel-head-tools .btn-cari {
        flex: 0 0 auto !important;
        height: 42px !important;
        padding: 0 18px !important;
      }

      /* 5. Tabel: bisa digeser ke samping, teks tidak terpotong */
      .table-wrap {
        width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        margin-top: 14px !important;
        border-radius: 8px;
      }

      .tbl {
        min-width: 720px !important;
      }

      .tbl th,
      .tbl td {
        white-space: nowrap;
        padding: 12px 14px !important;
      }

      /* Kolom Keterangan boleh turun baris karena teksnya panjang */
      .tbl th:last-child,
      .tbl td:last-child {
        white-space: normal;
        min-width: 180px;
      }
    }
  </style>
</head>

<body class="admin" data-peran="admin">
  <div class="admin-shell">

    <!-- SIDEBAR -->
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
        <a href="laporan.php" class="on">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
      </nav>

      <!-- Logout Memanggil Modal -->
      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main">
      <header class="topbar-a">
        <div>
          <h1>Laporan Kondisi</h1>
          <p>Laporan yang dikirim mahasiswa setelah pengembalian</p>
        </div>
        <span class="pill">Admin - RT POLINEMA</span>
      </header>

      <section class="panel">

        <!-- HEADER PANEL & PENCARIAN (sama seperti Riwayat Peminjaman) -->
        <div class="panel-head-tools">
          <h2>Laporan Terbaru</h2>

          <form method="GET" action="" class="toolbar-group">
            <!-- Input Tanggal -->
            <div
              style="position: relative; display: flex; align-items: center; background: #fff; border: 1.5px solid var(--primary); border-radius: 8px; height: 36px; padding: 0 10px;">
              <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()"
                style="border: none; outline: none; font-family: inherit; font-size: 12px; color: var(--ink); background: transparent;">
            </div>

            <!-- Input Pencarian -->
            <div class="input-wrapper">
              <input type="search" name="search" class="inp-cari" placeholder="Cari NIM / Nama / Unit..."
                value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn-cari">Cari</button>
            </div>
          </form>
        </div>

        <!-- TABEL -->
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Unit</th>
                <th>Nomor Identitas</th>
                <th>Kondisi</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($laporan) > 0): ?>
                <?php foreach ($laporan as $row): ?>
                  <?php
                  $ts = strtotime($row['tgl_lapor']);
                  $tgl = date('d', $ts) . ' ' . $bulan_indo[(int) date('m', $ts)] . ' ' . date('Y', $ts);

                  // Kondisi dihitung dengan fungsi yang sama seperti di unit.php
                  [$status_text, $chip_class] = labelKondisi(
                    $row['nilai_ban'],
                    $row['nilai_rem'],
                    $row['nilai_rantai'],
                    $row['nilai_rangka_bodi']
                  );
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
                <tr>
                  <td colspan="5" style="text-align:center; color: var(--muted); padding: 30px;">Belum ada laporan
                    kondisi.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- ========================================== -->
  <!-- MODAL POP-UP LOGOUT                        -->
  <!-- ========================================== -->
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

  <!-- JAVASCRIPT EKSTERNAL -->
  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/logout-modal.js"></script>
</body>

</html>