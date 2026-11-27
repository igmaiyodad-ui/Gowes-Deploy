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

        if (!$data || (int) $data['id_shelter'] !== $id_shelter_petugas) {
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
        .qr-tag { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; }
        .time-normal { color: #4f46e5; font-weight: 600; }
        .time-late { color: #dc2626; font-weight: 600; font-size: 12px; }
        .time-waiting { color: #64748b; font-style: italic; font-size: 12px; }
        .action-group { display: flex; flex-direction: row; gap: 6px; justify-content: flex-end; }
        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; white-space: nowrap; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { filter: brightness(1.08); }
        .btn-outline { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn-outline:hover { background: var(--tint); }
        .btn-disabled { background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: not-allowed; pointer-events: none; }

        /* ========================================= */
        /* RESPONSIVE MOBILE                         */
        /* ========================================= */
        @media (max-width: 768px) {
            .main { padding: 16px !important; }
            .topbar-a { flex-direction: column; align-items: flex-start; gap: 10px; }
            .topbar-a h1 { font-size: 22px; line-height: 1.25; }
            .panel { padding: 16px; margin-top: 16px; }
            .panel-head-tools { flex-direction: column; align-items: stretch; gap: 12px; }
            .panel-head-tools .input-wrapper { width: 100%; height: 42px; }
            .panel-head-tools .inp-cari { flex: 1; min-width: 0; font-size: 14px; }
            .panel-head-tools .btn-cari { height: 100%; padding: 0 18px; }
            .tbl { min-width: 760px; }
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
                <label class="hamburger" for="nav-toggle" aria-label="Buka menu">
                    <span></span><span></span><span></span>
                </label>
            </div>
            <nav class="menu" aria-label="Menu Petugas / Satpam">
                <a href="dashboard.php">Dashboard</a>
                <a href="unit.php">Unit Go-bike</a>
                <a href="peminjaman.php" class="on">Peminjaman</a>
                <a href="laporan.php">Laporan Kondisi</a>
                <a href="riwayat.php">Riwayat Peminjaman</a>
            </nav>
            <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
        </aside>

        <main class="main">
            <header class="topbar-a">
                <div>
                    <h1>Peminjaman</h1>
                    <p>Verifikasi bukti digital dan konfirmasi pengembalian</p>
                </div>
                <span class="pill"><?= $is_admin ? 'Admin' : 'Petugas - ' . htmlspecialchars($nama_shelter_petugas) ?></span>
            </header>

            <!-- PANEL CARI -->
            <section class="panel">
                <div class="panel-head-tools" style="margin-bottom: 0;">
                    <h2>Verifikasi Bukti</h2>
                    <form method="GET" action="" class="input-wrapper">
                        <input type="search" name="cari" class="inp-cari" placeholder="Cari No. Identitas / ID Go-bike" value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-cari">Cari</button>
                    </form>
                </div>
            </section>

            <!-- PANEL TABEL -->
            <section class="panel">
                <div class="panel-head"><h2>Peminjaman Aktif</h2></div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Nomor Identitas</th>
                                <th>Unit</th>
                                <th>Mulai</th>
                                <th>Batas</th>
                                <th>Sisa waktu</th>
                                <th>Status</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
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
                                        $chip_class = 'chip-info';
                                        $chip_text = 'Menunggu Pinjam';
                                        $sisa_waktu_text = '<span class="time-waiting">Belum dimulai</span>';
                                    } elseif ($is_menunggu_kembali) {
                                        $chip_class = 'chip-info';
                                        $chip_text = 'Menunggu Kembali';
                                        $sisa_waktu_text = '<span class="time-waiting">Menunggu ACC</span>';
                                    } elseif ($selisih < 0) {
                                        $chip_class = 'chip-bad';
                                        $chip_text = 'Terlambat';
                                        $diff = abs($selisih);
                                        $sisa_waktu_text = '<span class="time-late">Lewat ' . sprintf("%02d:%02d:%02d", floor($diff / 3600), floor(($diff % 3600) / 60), $diff % 60) . '</span>';
                                    } elseif ($selisih > 0 && $selisih < 1800) {
                                        $chip_class = 'chip-warn';
                                        $chip_text = 'Mendekati batas';
                                        $sisa_waktu_text = '<span class="countdown time-normal" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                                    } else {
                                        $chip_class = 'chip-ok';
                                        $chip_text = 'Dipinjam';
                                        $sisa_waktu_text = '<span class="countdown time-normal" data-target="' . date('Y-m-d\TH:i:s', $batas_time) . '">...</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($row['no_identitas']) ?></td>
                                        <td><span class="qr-tag"><?= htmlspecialchars($row['kode_qr']) ?></span></td>
                                        <td><?= $jam_mulai ?></td>
                                        <td><?= $jam_batas ?></td>
                                        <td><?= $sisa_waktu_text ?></td>
                                        <td><span class="chip <?= $chip_class ?>"><?= $chip_text ?></span></td>
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
                                <tr><td colspan="7" style="text-align: center; padding: 36px; color: var(--muted);">Tidak ada data peminjaman aktif.</td></tr>
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
            <div class="modal-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <h2>Konfirmasi Keluar</h2>
            <p>Apakah Anda yakin ingin keluar dari akun ini? Anda harus login kembali untuk mengakses halaman petugas.</p>
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