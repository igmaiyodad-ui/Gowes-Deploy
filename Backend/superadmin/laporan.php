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

    if (!empty($search)) {
        $where_clauses[] = "(sp.kode_qr ILIKE :search OR p.no_identitas ILIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($filter_date)) {
        $where_clauses[] = "lk.tgl_lapor = :filter_date";
        $params[':filter_date'] = $filter_date;
    }

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
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">

  <style>
    .date-btn-wrap {
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
        flex-shrink: 0;
        transition: all 0.2s;
    }
    .date-btn-wrap:hover,
    .date-btn-wrap.has-date {
        background: var(--primary);
        color: #fff;
    }
    .date-btn-wrap input[type="date"] {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .date-btn-wrap input[type="date"]::-webkit-calendar-picker-indicator {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    @media (max-width: 768px) {
        .panel-head-tools {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        .toolbar-group .input-wrapper {
            flex: 1;
            display: flex;
        }
        .toolbar-group .inp-cari {
            width: 100%;
            min-width: 0;
        }
    }
  </style>
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
        <a href="dashboard.php">Dashboard</a>
        <a href="unit.php">Unit Go-bike</a>
        <a href="peminjaman.php">Peminjaman</a>
        <a href="sanksi.php">Sanksi</a>
        <a href="laporan.php" class="on">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
        <a href="tambah-petugas-admin.php">Tambah Petugas/Admin</a>
      </nav>

      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
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
        <div class="panel-head-tools">
          <h2>Laporan Terbaru</h2>
          
          <form method="GET" action="" class="toolbar-group">
            <label class="date-btn-wrap <?= !empty($filter_date) ? 'has-date' : '' ?>" title="<?= !empty($filter_date) ? 'Tanggal: ' . htmlspecialchars($filter_date) : 'Filter tanggal' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <input type="date" name="date" id="dateFilterLaporan" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" onclick="try{this.showPicker()}catch(e){}">
            </label>

            <div class="input-wrapper">
                <input type="search" name="search" class="inp-cari" placeholder="Cari Unit / NIM..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-cari">Cari</button>
            </div>
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
