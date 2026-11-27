<?php
session_start();

// ==========================================
// 1. KONEKSI DATABASE & PROTEKSI HALAMAN
// ==========================================
require_once '../config/koneksi.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Super Admin';
$id_superadmin = isset($_SESSION['id_superadmin']) ? $_SESSION['id_superadmin'] : 1;
$pesan = '';

// ==========================================
// 2. PROSES TAMBAH DATA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_petugas') {
            $id_shelter = $_POST['shelter'];
            $username   = trim($_POST['nim']);
            $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO petugas (id_superadmin, id_shelter, username, password) VALUES (:id_super, :id_shelter, :username, :password)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_super' => $id_superadmin,
                ':id_shelter' => $id_shelter,
                ':username' => $username,
                ':password' => $password
            ]);
            $pesan = "<div class='alert-success'>Petugas berhasil ditambahkan!</div>";
            
        } elseif ($action === 'add_admin') {
            $username   = trim($_POST['nim']);
            $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO admin (id_superadmin, username, password) VALUES (:id_super, :username, :password)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_super' => $id_superadmin,
                ':username' => $username,
                ':password' => $password
            ]);
            $pesan = "<div class='alert-success'>Admin berhasil ditambahkan!</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div class='alert-error'>Gagal menambahkan data: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ==========================================
// 3. AMBIL DATA UNTUK DITAMPILKAN
// ==========================================
try {
    $shelters = $pdo->query("SELECT id_shelter, nama_shelter, lokasi FROM shelter ORDER BY nama_shelter ASC")->fetchAll();
    
    $sql_petugas = "SELECT p.username, s.nama_shelter 
                    FROM petugas p 
                    JOIN shelter s ON p.id_shelter = s.id_shelter 
                    ORDER BY s.nama_shelter ASC";
    $petugas_list = $pdo->query($sql_petugas)->fetchAll();

    $admin_list = $pdo->query("SELECT username FROM admin ORDER BY username ASC")->fetchAll();

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Petugas/Admin · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/panel.css">
  
  <style>
      .alert-success { background: #ecfdf5; color: #047857; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #a7f3d0; font-size: 14px; font-weight: 500; }
      .alert-error { background: #fef2f2; color: #b91c1c; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #fecaca; font-size: 14px; font-weight: 500; }

      .panel {
          border-radius: 16px;
          box-shadow: 0 4px 24px rgba(0, 0, 0, 0.03);
          border: 1px solid #f1f5f9;
          background: #ffffff;
          margin-bottom: 24px;
      }
      .panel-head {
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: wrap;
          gap: 12px;
          padding: 24px;
          border-bottom: 1px solid #f8fafc;
      }
      .panel-head h2 {
          font-size: 18px;
          color: #1e293b;
          font-weight: 600;
          margin: 0;
      }

      .toolbar {
          display: flex;
          align-items: center;
          gap: 12px;
          flex-wrap: wrap;
      }
      .btn-aesthetic {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          height: 40px;
          padding: 0 20px;
          border-radius: 10px;
          font-size: 13px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.2s ease;
          border: none;
          background: #4f46e5;
          color: #ffffff;
      }
      .btn-aesthetic:hover {
          background: #4338ca;
          transform: translateY(-1px);
          box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
      }
      .btn-aesthetic svg {
          width: 16px;
          height: 16px;
      }
      
      .search-form {
          display: flex;
          align-items: center;
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 10px;
          padding: 0 16px;
          height: 40px;
          width: 250px;
          max-width: 100%;
          transition: border 0.2s;
      }
      .search-form:focus-within {
          border-color: #4f46e5;
          background: #fff;
          box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
      }
      .search-form svg {
          color: #94a3b8;
          width: 18px;
          height: 18px;
      }
      .search-form input {
          border: none;
          background: transparent;
          outline: none;
          width: 100%;
          margin-left: 10px;
          font-size: 13px;
          color: #334155;
      }
      .search-form input::placeholder {
          color: #94a3b8;
      }

      .table-wrap {
          padding: 0 24px 24px 24px;
      }
      .tbl th {
          color: #64748b;
          font-size: 13px;
          font-weight: 500;
          padding: 16px 12px;
          border-bottom: 1px solid #e2e8f0;
          text-align: left;
      }
      .tbl td {
          padding: 18px 12px;
          font-size: 14px;
          color: #334155;
          border-bottom: 1px solid #f1f5f9;
          vertical-align: middle;
      }
      .tbl tr:last-child td {
          border-bottom: none;
      }

      .badge-aktif {
          display: inline-flex;
          align-items: center;
          padding: 6px 14px;
          background: #dcfce7;
          color: #15803d;
          border-radius: 20px;
          font-size: 12px;
          font-weight: 600;
          letter-spacing: 0.3px;
      }

      .custom-overlay {
          display: none;
          position: fixed;
          top: 0; left: 0; width: 100%; height: 100%;
          background: rgba(15, 23, 42, 0.6);
          z-index: 9999;
          align-items: center;
          justify-content: center;
          backdrop-filter: blur(4px);
      }
      .custom-modal {
          background: #fff;
          width: 90%;
          max-width: 440px;
          padding: 28px;
          border-radius: 16px;
          box-shadow: 0 20px 40px rgba(0,0,0,0.2);
          animation: modalSlideIn 0.3s ease;
      }
      @keyframes modalSlideIn {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
      }
      .custom-modal h2 {
          margin-top: 0;
          margin-bottom: 6px;
          font-size: 20px;
          color: #1e293b;
      }
      .custom-modal p {
          font-size: 14px;
          color: #64748b;
          margin-bottom: 20px;
      }
      .form-group {
          margin-bottom: 16px;
          text-align: left;
      }
      .form-group label {
          display: block;
          font-size: 13px;
          font-weight: 500;
          color: #475569;
          margin-bottom: 6px;
      }
      .form-group input, .form-group select {
          width: 100%;
          height: 42px;
          padding: 0 14px;
          border: 1px solid #cbd5e1;
          border-radius: 10px;
          font-size: 14px;
          outline: none;
          transition: all 0.2s;
      }
      .form-group input:focus, .form-group select:focus {
          border-color: #4f46e5;
          box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
      }
      .modal-actions {
          display: flex;
          justify-content: flex-end;
          gap: 12px;
          margin-top: 24px;
      }
      .btn-cancel {
          background: #f1f5f9;
          color: #475569;
          border: none;
          padding: 10px 20px;
          border-radius: 10px;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.2s;
      }
      .btn-cancel:hover { background: #e2e8f0; }
      .btn-submit {
          background: #4f46e5;
          color: #fff;
          border: none;
          padding: 10px 20px;
          border-radius: 10px;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.2s;
      }
      .btn-submit:hover { background: #4338ca; }
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
        <a href="laporan.php">Laporan Kondisi</a>
        <a href="riwayat.php">Riwayat Peminjaman</a>
        <a href="tambah-petugas-admin.php" class="on">Tambah Petugas/Admin</a>
      </nav>

      <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
    </aside>

    <!-- ===== KONTEN UTAMA ===== -->
    <main class="main">
      <header class="topbar-a">
        <div>
          <h1>Tambah Petugas/Admin</h1>
          <p>Data Petugas/Admin Go-bike</p>
        </div>
        <span class="pill">SuperAdmin</span>
      </header>

      <?= $pesan ?>

      <!-- ===== TABEL PETUGAS ===== -->
      <section class="panel">
        <div class="panel-head">
          <h2>Data Petugas Go-bike</h2>
          <div class="toolbar">
            <button type="button" class="btn-aesthetic" onclick="openCustomModal('modal-petugas')">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
                Add
            </button>
            <form class="search-form" onsubmit="return false;">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="9" r="5.4"/><path d="m13.2 13.2 3.6 3.6"/></svg>
                <input type="search" placeholder="Search">
            </form>
          </div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Shelter</th>
                <th>Nomor Identitas</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($petugas_list) > 0): ?>
                  <?php foreach($petugas_list as $ptg): ?>
                      <tr>
                        <td><?= htmlspecialchars($ptg['nama_shelter']) ?></td>
                        <td><?= htmlspecialchars($ptg['username']) ?></td>
                        <td><span class="badge-aktif">Aktif</span></td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="3" style="text-align:center; color: #94a3b8;">Belum ada data petugas.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== TABEL ADMIN ===== -->
      <section class="panel">
        <div class="panel-head">
          <h2>Data Admin Go-bike</h2>
          <div class="toolbar">
            <button type="button" class="btn-aesthetic" onclick="openCustomModal('modal-admin')">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
                Add
            </button>
            <form class="search-form" onsubmit="return false;">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="9" r="5.4"/><path d="m13.2 13.2 3.6 3.6"/></svg>
                <input type="search" placeholder="Search">
            </form>
          </div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th>Shelter</th>
                <th>Nomor Identitas</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($admin_list) > 0): ?>
                  <?php foreach($admin_list as $adm): ?>
                      <tr>
                        <td style="color: #94a3b8;">-</td>
                        <td><?= htmlspecialchars($adm['username']) ?></td>
                        <td><span class="badge-aktif">Aktif</span></td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="3" style="text-align:center; color: #94a3b8;">Belum ada data admin.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- POPUP MODAL TAMBAH PETUGAS -->
  <div class="custom-overlay" id="modal-petugas">
      <div class="custom-modal">
          <h2>Tambah Petugas Go-bike</h2>
          <p>Isi data petugas baru di bawah ini.</p>
          <form method="POST" action="">
            <input type="hidden" name="action" value="add_petugas">
            
            <div class="form-group">
              <label>Letak Shelter</label>
              <select name="shelter" required>
                <option value="" disabled selected>Pilih shelter...</option>
                <?php foreach($shelters as $s): ?>
                    <option value="<?= $s['id_shelter'] ?>"><?= htmlspecialchars($s['nama_shelter']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Nomor Identitas (Username)</label>
              <input type="text" name="nim" placeholder="Masukkan NIM / Username" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" placeholder="Masukkan Password" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCustomModal('modal-petugas')">Batal</button>
                <button type="submit" class="btn-submit">Simpan Petugas</button>
            </div>
          </form>
      </div>
  </div>

  <!-- POPUP MODAL TAMBAH ADMIN -->
  <div class="custom-overlay" id="modal-admin">
      <div class="custom-modal">
          <h2>Tambah Admin Go-bike</h2>
          <p>Isi data admin baru di bawah ini.</p>
          <form method="POST" action="">
            <input type="hidden" name="action" value="add_admin">
            
            <div class="form-group">
              <label>Nomor Identitas (Username)</label>
              <input type="text" name="nim" placeholder="Masukkan NIM / Username" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" placeholder="Masukkan Password" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCustomModal('modal-admin')">Batal</button>
                <button type="submit" class="btn-submit">Simpan Admin</button>
            </div>
          </form>
      </div>
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
          <p>Apakah Anda yakin ingin keluar dari akun ini?</p>
          <div class="modal-actions">
              <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
              <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
          </div>
      </div>
  </div>

  <script>
      function openCustomModal(id) {
          document.getElementById(id).style.display = 'flex';
      }
      function closeCustomModal(id) {
          document.getElementById(id).style.display = 'none';
      }
  </script>
  
  <script src="../assets/js/tabel.js"></script>
  <script src="../assets/js/logout-modal.js"></script>
</body>

</html>