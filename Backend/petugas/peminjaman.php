<?php
session_start();

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'petugas')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';

$is_admin = ($_SESSION['role'] === 'admin');
$id_shelter_petugas = isset($_SESSION['id_shelter']) ? (int) $_SESSION['id_shelter'] : 0;

if (!$is_admin && $id_shelter_petugas === 0) {
    echo "<script>alert('Akun petugas Anda belum ditugaskan ke shelter manapun.'); window.location='../auth/login.php?logout=true';</script>";
    exit;
}

// ==========================================
// KONFIRMASI PINJAM
// ==========================================
if (isset($_GET['konfirmasi_pinjam'])) {
    $id_peminjaman = $_GET['konfirmasi_pinjam'];
    try {
        $cek_pinjam = $pdo->prepare("SELECT s.id_shelter FROM peminjaman p JOIN sepeda s ON p.id_sepeda = s.id_sepeda WHERE p.id_peminjaman = :id");
        $cek_pinjam->execute(['id' => $id_peminjaman]);
        $shelter_sepeda = (int) $cek_pinjam->fetchColumn();

        if ($shelter_sepeda !== $id_shelter_petugas) {
            echo "<script>alert('Akses Ditolak.'); window.location='peminjaman.php';</script>";
            exit;
        }

        $stmt = $pdo->prepare("UPDATE peminjaman
                               SET status_peminjaman = 'Dipinjam',
                                   waktu_mulai = CURRENT_TIMESTAMP,
                                   batas_kembali = CURRENT_TIMESTAMP + (durasi_pinjam * interval '1 hour')
                               WHERE id_peminjaman = :id AND status_peminjaman = 'Menunggu'");
        $stmt->execute(['id' => $id_peminjaman]);
        echo "<script>alert('Peminjaman berhasil dikonfirmasi!'); window.location='peminjaman.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal: " . $e->getMessage() . "');</script>";
    }
}

// ==========================================
// KONFIRMASI KEMBALI (DENGAN AUTO-SANKSI)
// ==========================================
if (isset($_GET['konfirmasi_kembali'])) {
    $id_peminjaman = $_GET['konfirmasi_kembali'];
    try {
        $stmt_cek = $pdo->prepare("SELECT p.id_peminjam, p.batas_kembali, s.id_shelter 
                                   FROM peminjaman p 
                                   JOIN sepeda s ON p.id_sepeda = s.id_sepeda 
                                   WHERE p.id_peminjaman = :id");
        $stmt_cek->execute(['id' => $id_peminjaman]);
        $data = $stmt_cek->fetch();

        if (!$data || $data['id_shelter'] !== $id_shelter_petugas) {
            echo "<script>alert('Akses Ditolak.'); window.location='peminjaman.php';</script>";
            exit;
        }

        $waktu_sekarang = time();
        $batas_kembali = strtotime($data['batas_kembali']);
        $is_late = ($waktu_sekarang > $batas_kembali);
        $status_ket = $is_late ? 'Terlambat' : 'Tepat Waktu';

        $stmt_update = $pdo->prepare("UPDATE peminjaman 
                                      SET status_peminjaman = 'Selesai', 
                                          waktu_kembali = CURRENT_TIMESTAMP,
                                          status_keterlambatan = :status
                                      WHERE id_peminjaman = :id");
        $stmt_update->execute(['status' => $status_ket, 'id' => $id_peminjaman]);

        if ($is_late) {
            $stmt_hitung = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE id_peminjam = :id_peminjam AND status_keterlambatan = 'Terlambat'");
            $stmt_hitung->execute(['id_peminjam' => $data['id_peminjam']]);
            $total_terlambat = $stmt_hitung->fetchColumn();

            $hari_suspen = 3;
            if ($total_terlambat == 2) {
                $hari_suspen = 7;
            } elseif ($total_terlambat >= 3) {
                $hari_suspen = 36500;
            }

            $tgl_mulai = date('Y-m-d');
            $tgl_selesai = date('Y-m-d', strtotime("+$hari_suspen days"));

            $stmt_sanksi = $pdo->prepare("INSERT INTO sanksi (id_peminjam, tgl_mulai, tgl_selesai) VALUES (:id_peminjam, :mulai, :selesai)");
            $stmt_sanksi->execute([
                'id_peminjam' => $data['id_peminjam'],
                'mulai' => $tgl_mulai,
                'selesai' => $tgl_selesai
            ]);

            echo "<script>alert('Pengembalian dikonfirmasi. Mahasiswa TERLAMBAT dan dikenakan sanksi!'); window.location='peminjaman.php';</script>";
        } else {
            echo "<script>alert('Pengembalian berhasil dikonfirmasi (Tepat Waktu).'); window.location='peminjaman.php';</script>";
        }

    } catch (PDOException $e) {
        echo "<script>alert('Gagal: " . $e->getMessage() . "');</script>";
    }
}

// ==========================================
// AMBIL DATA
// ==========================================
$search = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$where_clauses = ["p.waktu_kembali IS NULL"];
$params = [];

if (!$is_admin) {
    $where_clauses[] = "s.id_shelter = :id_shelter";
    $params['id_shelter'] = $id_shelter_petugas;
}
if (!empty($search)) {
    $where_clauses[] = "(pm.no_identitas ILIKE :search OR s.kode_qr ILIKE :search)";
    $params['search'] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT p.*, pm.no_identitas, s.kode_qr, sh.nama_shelter 
        FROM peminjaman p
        JOIN peminjam pm ON p.id_peminjam = pm.id_peminjam
        JOIN sepeda s ON p.id_sepeda = s.id_sepeda
        JOIN shelter sh ON s.id_shelter = sh.id_shelter
        WHERE $where_sql ORDER BY p.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peminjaman = $stmt->fetchAll();

$nama_shelter_petugas = "";
if (!$is_admin) {
    $stmt_sh = $pdo->prepare("SELECT nama_shelter FROM shelter WHERE id_shelter = :id");
    $stmt_sh->execute(['id' => $id_shelter_petugas]);
    $nama_shelter_petugas = $stmt_sh->fetchColumn();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>Peminjaman · GOWES</title>
    <link rel="icon" href="../img/logo.png">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="../assets/css/tokens.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/panel.css">
    <style>
        body { background-color: #f8fafc; }
        .main { padding: 24px 32px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .page-header h1 { font-size: 22px; font-weight: 700; color: #1e293b; margin: 0 0 6px 0; }
        .page-header p { color: #64748b; font-size: 13px; margin: 0; }
        .role-badge { background: #e0e7ff; color: #4338ca; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .search-card { display: flex; align-items: center; justify-content: space-between; background: #ffffff; padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .search-card h2 { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0; }
        .search-form { display: flex; align-items: center; gap: 10px; width: 100%; max-width: 550px; }
        .search-input-wrapper { position: relative; flex-grow: 1; }
        .search-input-wrapper input { width: 100%; height: 38px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; }
        .btn-search { background: #4f46e5; color: white; border: none; height: 38px; padding: 0 20px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .table-card { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; }
        .table-card-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
        .table-card-header h3 { font-size: 15px; font-weight: 700; margin: 0; }
        .tbl-modern { width: 100%; table-layout: fixed; border-collapse: collapse; text-align: left; }
        .tbl-modern th { background: #ffffff; padding: 12px 16px; font-size: 12px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; }
        .tbl-modern td { padding: 12px 16px; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9; }
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef08a; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #e0e7ff; color: #4338ca; }
        .time-normal { color: #4f46e5; font-weight: 600; }
        .time-late { color: #dc2626; font-weight: 600; font-size: 12px; }
        .time-waiting { color: #64748b; font-style: italic; font-size: 12px; }
        .action-group { display: flex; flex-direction: row; gap: 6px; justify-content: flex-end; }
        .btn-action { padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-outline { background: transparent; color: #4f46e5; border: 1px solid #4f46e5; }
        .btn-disabled { background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: not-allowed; pointer-events: none; }

        /* ========================================= */
        /* CSS MODAL LOGOUT (SESUAI SCREENSHOT)      */
        /* ========================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); 
            display: none; align-items: center; justify-content: center; z-index: 9999;
            backdrop-filter: blur(3px); 
        }
        .modal-overlay.active { display: flex; }
        
        .modal-content {
            background: #fff; padding: 32px 24px; border-radius: 20px; width: 90%; max-width: 420px;
            text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-icon-wrapper {
            width: 64px; height: 64px; background: #fee2e2; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .modal-icon-wrapper svg { width: 32px; height: 32px; color: #ef4444; }

        .modal-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 12px 0; }
        .modal-desc { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0 0 24px 0; padding: 0 10px; }

        .modal-actions { display: flex; gap: 12px; }
        
        .btn-cancel {
            flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1;
            background: #ffffff; color: #475569; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: 0.2s;
        }
        .btn-cancel:hover { background: #f8fafc; }

        .btn-confirm {
            flex: 1; padding: 12px; border-radius: 10px; border: none;
            background: #ef4444; color: white; font-size: 14px; font-weight: 600;
            text-decoration: none; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s;
        }
        .btn-confirm:hover { background: #dc2626; }
    </style>
</head>
<body class="admin" data-peran="petugas">
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="sidebar-header"><div><a class="logo" href="dashboard.php"><img src="../img/logo-white.png" alt="GOWES"></a><div class="role">Petugas / Satpam</div></div></div>
            <nav class="menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="unit.php">Unit Go-bike</a>
                <a href="peminjaman.php" class="on">Peminjaman</a>
                <a href="laporan.php">Laporan Kondisi</a>
                <a href="riwayat.php">Riwayat Peminjaman</a>
            </nav>
            <!-- TOMBOL LOGOUT MEMANGGIL MODAL -->
            <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
        </aside>

        <main class="main">
            <header class="page-header">
                <div><h1>Peminjaman</h1><p>Verifikasi bukti digital dan konfirmasi pengembalian</p></div>
                <span class="role-badge"><?= $is_admin ? 'Admin' : 'Petugas - ' . htmlspecialchars($nama_shelter_petugas) ?></span>
            </header>

            <div class="search-card">
                <h2>Verifikasi Bukti</h2>
                <form method="GET" action="" class="search-form">
                    <div class="search-input-wrapper">
                        <input type="search" name="cari" placeholder="Cari Nomor Identitas atau ID Go-bike" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-search">Cari</button>
                </form>
            </div>

            <div class="table-card">
                <div class="table-card-header"><h3>Peminjaman Aktif</h3></div>
                <div class="table-responsive">
                    <table class="tbl-modern">
                        <thead>
                            <tr><th>Nomor Identitas</th><th>Unit</th><th>Mulai</th><th>Batas</th><th>Sisa waktu</th><th>Status</th><th style="text-align: right;">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($peminjaman) > 0): ?>
                                    <?php foreach ($peminjaman as $row): ?>
                                            <?php
                                            $status_db = $row['status_peminjaman'] ?? 'Menunggu';
                                            $is_menunggu_pinjam = ($status_db === 'Menunggu');
                                            $is_menunggu_kembali = ($status_db === 'Menunggu Kembali');
                                            $jam_mulai = date('H.i', strtotime($row['waktu_mulai']));
                                            $jam_batas = date('H.i', strtotime($row['batas_kembali']));

                                            $now = time();
                                            $batas_time = strtotime($row['batas_kembali']);
                                            $selisih = $batas_time - $now;

                                            if ($is_menunggu_pinjam) {
                                                $chip_class = 'badge-info';
                                                $chip_text = 'Menunggu Pinjam';
                                                $sisa_waktu_text = '<span class="time-waiting">Belum dimulai</span>';
                                            } elseif ($is_menunggu_kembali) {
                                                $chip_class = 'badge-info';
                                                $chip_text = 'Menunggu Kembali';
                                                $sisa_waktu_text = '<span class="time-waiting">Menunggu ACC</span>';
                                            } elseif ($selisih < 0) {
                                                $chip_class = 'badge-danger';
                                                $chip_text = 'Terlambat';
                                                $diff = abs($selisih);
                                                $sisa_waktu_text = '<span class="time-late">Lewat ' . sprintf("%02d:%02d:%02d", floor($diff / 3600), floor(($diff % 3600) / 60), $diff % 60) . '</span>';
                                            } elseif ($selisih > 0 && $selisih < 1800) {
                                                $chip_class = 'badge-warning';
                                                $chip_text = 'Mendekati batas';
                                                $sisa_waktu_text = '<span class="countdown time-normal" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                                            } else {
                                                $chip_class = 'badge-active';
                                                $chip_text = 'Dipinjam';
                                                $sisa_waktu_text = '<span class="countdown time-normal" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                                            }
                                            ?>
                                            <tr>
                                                <td style="font-weight: 600;"><?= htmlspecialchars($row['no_identitas']) ?></td>
                                                <td><span style="background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;"><?= htmlspecialchars($row['kode_qr']) ?></span></td>
                                                <td><?= $jam_mulai ?></td>
                                                <td><?= $jam_batas ?></td>
                                                <td><?= $sisa_waktu_text ?></td>
                                                <td><span class="badge <?= $chip_class ?>"><?= $chip_text ?></span></td>
                                                <td>
                                                    <div class="action-group">
                                                        <?php if ($is_menunggu_pinjam && !$is_admin): ?>
                                                                <a class="btn-action btn-primary" href="?konfirmasi_pinjam=<?= $row['id_peminjaman'] ?>" onclick="return confirm('Serahkan sepeda?')">Konfirmasi Pinjam</a>
                                                        <?php else: ?>
                                                                <span class="btn-action btn-disabled">Konfirmasi Pinjam</span>
                                                        <?php endif; ?>

                                                        <?php if ($is_menunggu_kembali && !$is_admin): ?>
                                                                <a class="btn-action btn-outline" href="?konfirmasi_kembali=<?= $row['id_peminjaman'] ?>" onclick="return confirm('ACC pengembalian sepeda ini?')">Konfirmasi Kembali</a>
                                                        <?php else: ?>
                                                                <span class="btn-action btn-disabled">Konfirmasi Kembali</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">Tidak ada data peminjaman aktif.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================================== -->
    <!-- MODAL LOGOUT (SESUAI SCREENSHOT)               -->
    <!-- ============================================== -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-content">
            <div class="modal-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <h2 class="modal-title">Konfirmasi Keluar</h2>
            <p class="modal-desc">Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses halaman petugas.</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                <a href="../auth/login.php?logout=true" class="btn-confirm">Ya, Keluar</a>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/countdown.js"></script>
    <script>
        // Script untuk membuka dan menutup modal logout
        function openLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }
        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
    </script>
</body>
</html>