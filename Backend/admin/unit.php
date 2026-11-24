<?php
session_start();

// 1. Proteksi Halaman
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// 2. Koneksi Database
require_once '../config/koneksi.php';

// Logika kondisi (harus sama persis dengan laporan.php)
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

// Token CSRF sederhana untuk form tambah unit
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Membuat kode sepeda berikutnya, contoh: GB-06
function kodeBerikutnya(PDO $pdo): string
{
    $max = 0;
    foreach ($pdo->query("SELECT kode_qr FROM sepeda")->fetchAll(PDO::FETCH_COLUMN) as $k) {
        if (preg_match('/^GB-(\d+)$/', $k, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return 'GB-' . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
}

// 3. PROSES TAMBAH UNIT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_unit') {
    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new Exception("Sesi form tidak valid, silakan coba lagi.");
        }

        $id_shelter = (int) ($_POST['id_shelter'] ?? 0);
        $status = $_POST['status'] ?? 'Tersedia';
        if (!in_array($status, ['Tersedia', 'Perawatan'], true)) {
            $status = 'Tersedia';
        }

        $cek = $pdo->prepare("SELECT 1 FROM shelter WHERE id_shelter = :id");
        $cek->execute([':id' => $id_shelter]);
        if (!$cek->fetchColumn()) {
            throw new Exception("Shelter tidak valid.");
        }

        $id_admin = (int) ($_SESSION['id_admin'] ?? 0);
        if ($id_admin === 0) {
            $id_admin = (int) $pdo->query("SELECT id_admin FROM admin ORDER BY id_admin LIMIT 1")->fetchColumn();
        }
        if ($id_admin === 0) {
            throw new Exception("Data admin tidak ditemukan.");
        }

        $kode = kodeBerikutnya($pdo);

        $ins = $pdo->prepare("INSERT INTO sepeda (id_shelter, id_admin, kode_qr, status_ketersediaan)
                              VALUES (:shelter, :admin, :kode, :status)");
        $ins->execute([
            ':shelter' => $id_shelter,
            ':admin' => $id_admin,
            ':kode' => $kode,
            ':status' => $status,
        ]);

        header("Location: unit.php?added=" . urlencode($kode));
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        header("Location: unit.php");
        exit;
    }
}

// 3b. PROSES UBAH UNIT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ubah_unit') {
    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            throw new Exception("Sesi form tidak valid, silakan coba lagi.");
        }

        $id_sepeda = (int) ($_POST['id_sepeda'] ?? 0);
        $id_shelter = (int) ($_POST['id_shelter'] ?? 0);

        $cur = $pdo->prepare("SELECT kode_qr, status_ketersediaan FROM sepeda WHERE id_sepeda = :id");
        $cur->execute([':id' => $id_sepeda]);
        $sepedaLama = $cur->fetch();
        if (!$sepedaLama) {
            throw new Exception("Unit tidak ditemukan.");
        }

        $cek = $pdo->prepare("SELECT 1 FROM shelter WHERE id_shelter = :id");
        $cek->execute([':id' => $id_shelter]);
        if (!$cek->fetchColumn()) {
            throw new Exception("Shelter tidak valid.");
        }

        if ($sepedaLama['status_ketersediaan'] === 'Dipinjam') {
            $status = 'Dipinjam';
        } else {
            $status = $_POST['status'] ?? 'Tersedia';
            if (!in_array($status, ['Tersedia', 'Perawatan'], true)) {
                $status = 'Tersedia';
            }
        }

        $upd = $pdo->prepare("UPDATE sepeda SET id_shelter = :shelter, status_ketersediaan = :status
                              WHERE id_sepeda = :id");
        $upd->execute([':shelter' => $id_shelter, ':status' => $status, ':id' => $id_sepeda]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Unit ' . $sepedaLama['kode_qr'] . ' berhasil diperbarui.'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
    }
    header("Location: unit.php");
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$added = $_GET['added'] ?? '';

// 4. Ambil Data Unit
try {
    $sql = "SELECT s.*, sh.nama_shelter, sh.lokasi,
                   lt.nilai_ban, lt.nilai_rem, lt.nilai_rantai, lt.nilai_rangka_bodi
            FROM sepeda s
            JOIN shelter sh ON s.id_shelter = sh.id_shelter
            LEFT JOIN LATERAL (
                SELECT lk.nilai_ban, lk.nilai_rem, lk.nilai_rantai, lk.nilai_rangka_bodi
                FROM laporan_kondisi lk
                JOIN peminjaman p ON lk.id_peminjaman = p.id_peminjaman
                WHERE p.id_sepeda = s.id_sepeda
                ORDER BY lk.tgl_lapor DESC, lk.id_laporankondisi DESC
                LIMIT 1
            ) lt ON true
            ORDER BY s.kode_qr ASC";
    $units = $pdo->query($sql)->fetchAll();
    $shelters = $pdo->query("SELECT id_shelter, nama_shelter, lokasi FROM shelter ORDER BY id_shelter")->fetchAll();
    $kodeBaru = kodeBerikutnya($pdo);
} catch (PDOException $e) {
    die("Error mengambil data unit: " . $e->getMessage());
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unit Go-bike · GOWES</title>
    <link rel="icon" href="../img/logo.png">

    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="../assets/css/tokens.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/panel.css">
    <link rel="stylesheet" href="../assets/css/loader.css">
    <link rel="stylesheet" href="../assets/css/cetak_qr.css">

    <style>
        /* ========================================= */
        /* CSS OVERRIDE KHUSUS MOBILE (RESPONSIVE)   */
        /* ========================================= */
        @media (max-width: 768px) {

            /* 1. Header halaman */
            .topbar-a {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            /* 2. Header panel: judul di atas, tombol di bawah */
            .panel-head {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 14px !important;
                height: auto !important;
                min-height: 0 !important;
                overflow: visible !important;
                padding-bottom: 16px !important;
            }

            /* 3. Grup tombol: sejajar 50:50 */
            .panel-head>div {
                display: flex !important;
                flex-direction: row !important;
                align-items: stretch !important;
                width: 100% !important;
                height: auto !important;
                gap: 10px !important;
            }

            /* 4. Tombol: teks rata tengah, tinggi mengikuti isi */
            .panel-head .btn-sm {
                flex: 1 1 0 !important;
                min-width: 0 !important;
                height: auto !important;
                min-height: 44px !important;
                margin: 0 !important;
                padding: 10px 12px !important;
                box-sizing: border-box !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
                line-height: 1.2 !important;
                font-size: 13px !important;
                white-space: nowrap !important;
                text-align: center !important;
            }

            /* 5. Tabel bisa digeser ke samping */
            .table-responsive,
            .table-wrap {
                width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                border-radius: 8px;
                margin-top: 4px;
            }

            .tbl {
                min-width: 650px !important;
            }

            .tbl th,
            .tbl td {
                white-space: nowrap;
            }

            /* 6. Padding panel */
            .panel {
                padding: 16px !important;
            }
        }
    </style>
</head>

<body class="admin" data-peran="admin" data-open-qr="<?= $added !== '' ? '1' : '0' ?>">
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
                <a href="unit.php" class="on">Unit Go-bike</a>
                <a href="peminjaman.php">Peminjaman</a>
                <a href="sanksi.php">Sanksi</a>
                <a href="laporan.php">Laporan Kondisi</a>
                <a href="riwayat.php">Riwayat Peminjaman</a>
            </nav>
            <a class="menu-logout" href="javascript:void(0);" onclick="openLogoutModal()">Keluar</a>
        </aside>

        <!-- KONTEN UTAMA -->
        <main class="main">
            <header class="topbar-a" style="margin-bottom: 20px;">
                <div>
                    <h1>Unit Go-bike</h1>
                    <p>Kelola data unit dan status ketersediaan</p>
                </div>
                <span class="pill">Admin - RT POLINEMA</span>
            </header>

            <?php if ($flash || $added !== ''): ?>
                <?php
                $flashType = $flash ? $flash['type'] : 'success';
                $iconOk = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.7 2.7L16 9.5"/></svg>';
                $iconErr = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5h.01"/></svg>';
                ?>
                <div class="flash flash-<?= htmlspecialchars($flashType) ?>" role="status" id="flashMsg"
                    style="margin-bottom: 20px;">
                    <span class="flash-icon"><?= $flashType === 'error' ? $iconErr : $iconOk ?></span>
                    <span class="flash-text">
                        <?php if ($flash): ?>
                            <?= htmlspecialchars($flash['msg']) ?>
                        <?php else: ?>
                            <strong>Unit <?= htmlspecialchars($added) ?> berhasil ditambahkan.</strong>
                            <small>QR code sudah dibuat otomatis dan siap dicetak.</small>
                        <?php endif; ?>
                    </span>
                    <button type="button" class="flash-close" onclick="closeFlash()"
                        aria-label="Tutup pesan">&times;</button>
                </div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-head">
                    <h2 style="margin: 0;">Daftar Unit</h2>
                    <div>
                        <button class="btn-sm" onclick="openQrModal()" style="cursor: pointer; border: none;">🖨️ Lihat
                            QR</button>
                        <button type="button" class="btn-sm solid" onclick="openTambahModal()"
                            style="cursor: pointer; border: none;">+ Tambah unit</button>
                    </div>
                </div>

                <!-- WRAPPER RESPONSIVE UNTUK TABEL -->
                <div class="table-responsive">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>ID Go-bike</th>
                                <th>Shelter</th>
                                <th>Status</th>
                                <th>Kondisi terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($units) > 0): ?>
                                <?php foreach ($units as $row): ?>
                                    <?php
                                    $status_class = ($row['status_ketersediaan'] === 'Dipinjam') ? 'chip-info' : (($row['status_ketersediaan'] === 'Perawatan') ? 'chip-warn' : 'chip-ok');

                                    if ($row['nilai_ban'] !== null) {
                                        [$kondisi_text, $kondisi_class] = labelKondisi(
                                            $row['nilai_ban'],
                                            $row['nilai_rem'],
                                            $row['nilai_rantai'],
                                            $row['nilai_rangka_bodi']
                                        );
                                    } else {
                                        $kondisi_text = 'Baik';
                                        $kondisi_class = 'chip-ok';
                                    }
                                    ?>
                                    <tr class="<?= $row['kode_qr'] === $added ? 'row-new' : '' ?>">
                                        <td><strong><?= htmlspecialchars($row['kode_qr']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['nama_shelter']) ?></td>
                                        <td><span
                                                class="chip <?= $status_class ?>"><?= htmlspecialchars($row['status_ketersediaan']) ?></span>
                                        </td>
                                        <td><span class="chip <?= $kondisi_class ?>"><?= $kondisi_text ?></span></td>
                                        <td>
                                            <button type="button" class="btn-sm" style="cursor: pointer; border: none;"
                                                onclick="openUbahModal(this)" data-id="<?= (int) $row['id_sepeda'] ?>"
                                                data-kode="<?= htmlspecialchars($row['kode_qr']) ?>"
                                                data-shelter="<?= (int) $row['id_shelter'] ?>"
                                                data-status="<?= htmlspecialchars($row['status_ketersediaan']) ?>">Ubah</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 20px;">Belum ada data unit sepeda.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- MODAL POP-UP TAMBAH UNIT -->
    <div class="modal-overlay" id="tambahModal">
        <div class="modal-content" style="max-width: 440px; width: 90%; text-align: left;">
            <h2 style="margin-bottom: 6px;">Tambah Unit Go-bike</h2>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 18px;">QR code akan dibuat otomatis setelah unit
                disimpan.</p>

            <form method="post" action="unit.php" autocomplete="off">
                <input type="hidden" name="aksi" value="tambah_unit">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                <div class="form-group">
                    <label for="kodeBaru">ID Go-bike (otomatis)</label>
                    <input type="text" id="kodeBaru" value="<?= htmlspecialchars($kodeBaru) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="id_shelter">Shelter</label>
                    <select name="id_shelter" id="id_shelter" required>
                        <option value="">-- Pilih shelter --</option>
                        <?php foreach ($shelters as $sh): ?>
                            <option value="<?= (int) $sh['id_shelter'] ?>">
                                <?= htmlspecialchars($sh['nama_shelter']) ?> - <?= htmlspecialchars($sh['lokasi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status awal</label>
                    <select name="status" id="status">
                        <option value="Tersedia">Tersedia</option>
                        <option value="Perawatan">Perawatan</option>
                    </select>
                </div>

                <div class="modal-actions" style="margin-top: 22px;">
                    <button type="button" class="btn-cancel" onclick="closeTambahModal()">Batal</button>
                    <button type="submit" class="btn-confirm" style="background: var(--primary);">Simpan unit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POP-UP UBAH UNIT -->
    <div class="modal-overlay" id="ubahModal">
        <div class="modal-content" style="max-width: 440px; width: 90%; text-align: left;">
            <h2 style="margin-bottom: 6px;">Ubah Unit Go-bike</h2>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 18px;">Perbarui shelter dan status ketersediaan
                unit.</p>

            <form method="post" action="unit.php" autocomplete="off">
                <input type="hidden" name="aksi" value="ubah_unit">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="id_sepeda" id="ubah_id_sepeda">

                <div class="form-group">
                    <label for="ubah_kode">ID Go-bike</label>
                    <input type="text" id="ubah_kode" readonly>
                </div>

                <div class="form-group">
                    <label for="ubah_id_shelter">Shelter</label>
                    <select name="id_shelter" id="ubah_id_shelter" required>
                        <?php foreach ($shelters as $sh): ?>
                            <option value="<?= (int) $sh['id_shelter'] ?>">
                                <?= htmlspecialchars($sh['nama_shelter']) ?> - <?= htmlspecialchars($sh['lokasi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ubah_status">Status</label>
                    <select name="status" id="ubah_status">
                        <option value="Tersedia">Tersedia</option>
                        <option value="Perawatan">Perawatan</option>
                        <option value="Dipinjam" disabled>Dipinjam</option>
                    </select>
                    <small class="form-note" id="ubah_note" style="display: none;">
                        Unit sedang dipinjam, jadi status tidak bisa diubah manual.
                    </small>
                </div>

                <div class="modal-actions" style="margin-top: 22px;">
                    <button type="button" class="btn-cancel" onclick="closeUbahModal()">Batal</button>
                    <button type="submit" class="btn-confirm" style="background: var(--primary);">Simpan
                        perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POP-UP CETAK QR CODE -->
    <div class="modal-overlay" id="qrModal">
        <div class="modal-content modal-content-qr" style="max-width: 800px; width: 90%;">
            <h2 style="text-align: center; margin-bottom: 10px;">QR Code Sepeda</h2>
            <p style="text-align: center; font-size: 14px; color: #64748b; margin-bottom: 20px;">Cetak dan tempelkan
                pada unit yang sesuai.</p>

            <div class="qr-grid">
                <?php foreach ($units as $row):
                    $qrData = $baseUrl . '/user/scan_hasil.php?qr=' . urlencode($row['kode_qr']);
                    ?>
                    <div class="qr-card <?= $row['kode_qr'] === $added ? 'qr-card-new' : '' ?>">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrData) ?>"
                            alt="QR <?= htmlspecialchars($row['kode_qr']) ?>">
                        <div class="qr-title"><?= htmlspecialchars($row['kode_qr']) ?></div>
                        <div class="qr-sub"><?= htmlspecialchars($row['nama_shelter']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="modal-actions" style="margin-top: 25px;">
                <button type="button" class="btn-cancel btn-close-modal" onclick="closeQrModal()">Tutup</button>
                <button type="button" class="btn-confirm btn-print" onclick="printQrCodes()"
                    style="background: var(--primary);">🖨️ Cetak QR</button>
            </div>
        </div>
    </div>

    <!-- MODAL POP-UP LOGOUT -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-content" style="text-align: center;">
            <div style="display: flex; justify-content: center; margin-bottom: 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#ef4444"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 48px; height: 48px;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #1e293b;">Konfirmasi Keluar</h2>
            <p style="font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 24px;">Apakah Anda yakin ingin
                keluar dari akun ini? Anda harus login kembali untuk mengakses halaman admin.</p>
            <div class="modal-actions" style="display: flex; gap: 12px;">
                <button type="button" class="btn-cancel" onclick="closeLogoutModal()"
                    style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #475569; font-weight: 600; cursor: pointer;">Batal</button>
                <a href="../auth/login.php?logout=true" class="btn-confirm"
                    style="flex: 1; padding: 12px; border-radius: 8px; background: #ef4444; color: white; font-weight: 600; text-decoration: none; text-align: center;">Ya,
                    Keluar</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/common.js"></script>
    <script src="../assets/js/logout-modal.js"></script>
    <script src="../assets/js/cetak_qr.js"></script>
</body>

</html>