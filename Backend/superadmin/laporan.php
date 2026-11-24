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
// 2. LOGIKA FILTER & PENCARIAN
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

try {
    // Query dasar mengambil laporan kondisi dengan JOIN ke tabel terkait
    $sql = "SELECT 
                lk.tgl_lapor, 
                sp.kode_qr, 
                p.no_identitas, 
                lk.nilai_ban, 
                lk.nilai_rem, 
                lk.nilai_rantai, 
                lk.nilai_rangka_bodi, 
                lk.keterangan
            FROM laporan_kondisi lk
            JOIN peminjaman pm ON lk.id_peminjaman = pm.id_peminjaman
            JOIN sepeda sp ON pm.id_sepeda = sp.id_sepeda
            JOIN peminjam p ON pm.id_peminjam = p.id_peminjam";
            
    $where_clauses = [];
    $params = [];

    // Tambahkan filter pencarian (berdasarkan Unit atau Nomor Identitas)
    if (!empty($search)) {
        $where_clauses[] = "(sp.kode_qr ILIKE :search OR p.no_identitas ILIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Tambahkan filter tanggal
    if (!empty($filter_date)) {
        $where_clauses[] = "lk.tgl_lapor = :filter_date";
        $params[':filter_date'] = $filter_date;
    }

    // Gabungkan klausa WHERE jika ada
    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY lk.tgl_lapor DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $laporan = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// ==========================================
// FUNGSI BANTUAN
// ==========================================
// Fungsi untuk format tanggal Indonesia
function formatTanggalIndo($dateString) {
    if (!$dateString) return '-';
    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $date = new DateTime($dateString);
    return $date->format('d') . ' ' . $bulan[(int)$date->format('n')] . ' ' . $date->format('Y');
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan kondisi · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <!-- PERBAIKAN PATH CSS -->
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">

  <!-- CUSTOM CSS UNTUK SEARCH BAR AGAR SEJAJAR RAPI -->
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

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
      <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Buka atau tutup menu">

      <div>
        <a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a>
        <div class="role">Super Admin</div>
      </div>

      <label class="hamburger" for="nav-toggle" aria-label="Buka menu"><span></span><span></span><span></span></label>

      <nav class="menu" aria-label="Menu Super Admin">
        <a href="dashboard.php" class="">Dashboard</a>
        <a href="unit.php" class="">Unit Go-bike</a>
        <a href="peminjaman.php" class="">Peminjaman</a>
        <a href="sanksi.php" class="">Sanksi</a>
        <a href="laporan.php" class="on">Laporan Kondisi</a>
        <a href="riwayat.php" class="">Riwayat Peminjaman</a>
        <a href="tambah-petugas-admin.php" class="">Tambah Petugas/Admin</a>
      </nav>

      <a class="menu-logout" href="../auth/login.php?logout=true">Keluar</a>
    </aside>

    <!-- ===== KONTEN UTAMA ===== -->
    <main class="main">
      <header class="topbar-a">
        <div>
          <h1>Laporan Kondisi</h1>
          <p>Laporan yang dikirim mahasiswa setelah pengembalian</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <section class="panel">
        
        <!-- HEADER PANEL DENGAN SEARCH BAR SEJAJAR -->
        <div class="panel-head">
          <h2>Laporan Terbaru</h2>
          
          <form method="GET" action="" class="search-bar">
            <!-- Filter Tanggal -->
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" title="Filter tanggal">
            
            <!-- Pencarian Teks -->
            <input type="search" name="search" placeholder="Cari Unit / NIM..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit">Cari</button>
          </form>
        </div>

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
                          // LOGIKA PENENTUAN KONDISI
                          // Asumsi nilai 1-5. Jika ada nilai <= 2 dianggap Rusak, <= 4 Perlu Perawatan, selain itu Baik.
                          $is_rusak = ($row['nilai_ban'] <= 2 || $row['nilai_rem'] <= 2 || $row['nilai_rantai'] <= 2 || $row['nilai_rangka_bodi'] <= 2);
                          $is_perlu_perawatan = ($row['nilai_ban'] <= 4 || $row['nilai_rem'] <= 4 || $row['nilai_rantai'] <= 4 || $row['nilai_rangka_bodi'] <= 4);
                          
                          $chip_class = 'chip-ok';
                          $status_text = 'Baik';
                          
                          if ($is_rusak) {
                              $chip_class = 'chip-bad';
                              $status_text = 'Rusak';
                          } elseif ($is_perlu_perawatan) {
                              $chip_class = 'chip-warn';
                              $status_text = 'Perlu perawatan';
                          }
                      ?>
                      <tr>
                        <td><?= formatTanggalIndo($row['tgl_lapor']) ?></td>
                        <td><strong><?= htmlspecialchars($row['kode_qr']) ?></strong></td>
                        <td><?= htmlspecialchars($row['no_identitas']) ?></td>
                        <td><span class="chip <?= $chip_class ?>"><?= $status_text ?></span></td>
                        <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="5" style="text-align: center; padding: 20px; color: var(--muted);">
                          Tidak ada data laporan kondisi ditemukan.
                      </td>
                  </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <!-- PATH JS TANPA LOADER -->
  <script src="../assets/js/common.js"></script>
  <script src="../assets/js/tabel.js"></script>
  <script src="../assets/js/modal.js"></script>
</body>

</html>