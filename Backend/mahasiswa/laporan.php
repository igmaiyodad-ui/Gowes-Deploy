<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once '../config/koneksi.php';

if (!isset($_SESSION['id_peminjam'])) {
  header("Location: ../auth/login.php");
  exit;
}

$id_peminjam = $_SESSION['id_peminjam'];
$nama_user = $_SESSION['nama'];
$inisial = strtoupper(substr($nama_user, 0, 1));

// ==========================================
// 1. TENTUKAN ID PEMINJAMAN
// ==========================================
$id_peminjaman = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
  if ($id_peminjaman === 0) {
    $stmt_cari = $pdo->prepare("
            SELECT p.id_peminjaman 
            FROM peminjaman p
            LEFT JOIN laporan_kondisi lk ON p.id_peminjaman = lk.id_peminjaman
            WHERE p.id_peminjam = :id 
              AND p.status_peminjaman = 'Selesai' 
              AND lk.id_laporankondisi IS NULL
            ORDER BY p.waktu_kembali DESC LIMIT 1
        ");
    $stmt_cari->execute([':id' => $id_peminjam]);
    $id_peminjaman = $stmt_cari->fetchColumn();
  }

  if (!$id_peminjaman) {
    echo "<script>alert('Tidak ada peminjaman yang perlu dilaporkan.'); window.location.href='peminjaman.php';</script>";
    exit;
  }

  // Pastikan peminjaman ini milik user, sudah 'Selesai', dan belum pernah dilaporkan
  // (laporan_kondisi.id_peminjaman bersifat UNIQUE)
  $stmt_cek = $pdo->prepare("
        SELECT 1
        FROM peminjaman p
        LEFT JOIN laporan_kondisi lk ON p.id_peminjaman = lk.id_peminjaman
        WHERE p.id_peminjaman = :id
          AND p.id_peminjam = :user
          AND p.status_peminjaman = 'Selesai'
          AND lk.id_laporankondisi IS NULL
    ");
  $stmt_cek->execute([':id' => $id_peminjaman, ':user' => $id_peminjam]);

  if (!$stmt_cek->fetchColumn()) {
    echo "<script>alert('Laporan tidak tersedia atau sudah pernah dikirim.'); window.location.href='peminjaman.php';</script>";
    exit;
  }

} catch (PDOException $e) {
  die("Error: " . $e->getMessage());
}

// ==========================================
// 2. PROSES KIRIM LAPORAN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nilai_ban         = (int) ($_POST['nilai_ban'] ?? 0);
  $nilai_rem         = (int) ($_POST['nilai_rem'] ?? 0);
  $nilai_rantai      = (int) ($_POST['nilai_rantai'] ?? 0);
  $nilai_rangka_bodi = (int) ($_POST['nilai_rangka_bodi'] ?? 0);
  $keterangan        = trim($_POST['keterangan'] ?? '');
  $tgl_lapor         = date('Y-m-d');

  $semua_nilai = [$nilai_ban, $nilai_rem, $nilai_rantai, $nilai_rangka_bodi];

  if (min($semua_nilai) < 1 || max($semua_nilai) > 5) {
    $error = "Semua nilai harus diisi dengan angka 1 sampai 5.";
  } elseif (mb_strlen($keterangan) > 255) {
    $error = "Keterangan maksimal 255 karakter.";
  } else {
    try {
      // Kolom sesuai tabel laporan_kondisi (tanpa id_petugas)
      $sql = "INSERT INTO laporan_kondisi
                  (id_peminjaman, nilai_ban, nilai_rem, nilai_rantai, nilai_rangka_bodi, keterangan, tgl_lapor)
              VALUES (:id_peminjaman, :ban, :rem, :rantai, :rangka, :keterangan, :tgl)";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':id_peminjaman' => $id_peminjaman,
        ':ban'           => $nilai_ban,
        ':rem'           => $nilai_rem,
        ':rantai'        => $nilai_rantai,
        ':rangka'        => $nilai_rangka_bodi,
        ':keterangan'    => $keterangan,
        ':tgl'           => $tgl_lapor
      ]);

      echo "<script>alert('Laporan kondisi berhasil dikirim! Terima kasih.'); window.location.href='peminjaman.php';</script>";
      exit;
    } catch (PDOException $e) {
      $error = "Gagal mengirim laporan: " . $e->getMessage();
    }
  }
}
?>

<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Laporan kondisi · GOWES</title>
    <link rel="icon" href="../img/logo.png" />
    
    <link rel="stylesheet" href="../assets/css/fonts.css" />
    <link rel="stylesheet" href="../assets/css/tokens.css" />
    <link rel="stylesheet" href="../assets/css/base.css" />
    <link rel="stylesheet" href="../assets/css/components.css" />
    <link rel="stylesheet" href="../assets/css/mahasiswa.css" />
    
    <style>
        /* BACKGROUND HALAMAN */
        body {
            background-color: #f8fafc;
        }

        /* MERAPATKAN TOPBAR (Agar tidak terlalu tinggi) */
        .topbar {
            padding: 12px 24px !important; 
        }

        /* KONTAINER UTAMA (Dikurangi padding atas/bawahnya) */
        .report-container {
            max-width: 850px;
            margin: 0 auto;
            padding: 24px 20px; /* Sebelumnya 40px 20px 60px */
        }

        /* HEADER (Dikurangi margin bottomnya) */
        .report-header {
            margin-bottom: 20px;
        }

        .report-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 6px 0;
        }

        .report-header p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        /* KOTAK RATING (Dikurangi padding dan marginnya) */
        .rating-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 10px 20px; /* Sebelumnya 16px 24px */
            border-radius: 10px;
            margin-bottom: 10px; /* Sebelumnya 12px */
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .rating-label {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            flex: 1;
        }

        .rating-scale-wrapper {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .rating-text {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }

        .scale {
            display: flex;
            gap: 6px;
        }

        /* TOMBOL BULAT RATING (Sedikit dikecilkan) */
        .scale button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .scale button:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .scale button.on {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }

        /* AREA TEKS (Dikurangi tingginya agar tidak terlalu panjang) */
        .textarea-wrapper {
            margin-top: 16px;
            margin-bottom: 16px;
        }

        .textarea-custom {
            width: 100%;
            height: 100px; /* Sebelumnya 140px */
            min-height: 100px;
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            color: #334155;
            resize: vertical;
            outline: none;
            transition: all 0.2s;
        }

        .textarea-custom:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .textarea-custom::placeholder {
            color: #94a3b8;
        }

        /* TOMBOL SUBMIT */
        .btn-submit-laporan {
            width: 100%;
            padding: 12px; /* Sebelumnya 16px */
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit-laporan:hover {
            background: #4338ca;
        }
    </style>
  </head>

  <body class="user">
    <!-- TOPBAR STANDAR -->
    <header class="topbar">
      <a class="brand" href="dashboard.php"><img src="../img/logo.png" alt="GOWES"></a>
      
      <nav class="topnav" aria-label="Menu utama">
        <a class="navitem" href="dashboard.php">
            <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9.6 10 3.5l7 6.1V16a1 1 0 0 1-1 1h-3.4v-4.6H7.4V17H4a1 1 0 0 1-1-1z" /></svg></span>Dashboard
        </a>
        <a class="navitem on" href="peminjaman.php">
            <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="4.8" cy="13" r="3" /><circle cx="15.2" cy="13" r="3" /><path d="M4.8 13 8 7.2h4.2l3 5.8M8 7.2 7 5H5M12.2 7.2 10.6 13" /></svg></span>Peminjaman
        </a>
        <a class="navitem" href="scan.php">
            <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7V4.5A1.5 1.5 0 0 1 4.5 3H7M13 3h2.5A1.5 1.5 0 0 1 17 4.5V7M17 13v2.5a1.5 1.5 0 0 1-1.5 1.5H13M7 17H4.5A1.5 1.5 0 0 1 3 15.5V13M6.5 10h7" /></svg></span>Scan QR
        </a>
        <a class="navitem" href="profil.php">
            <span class="ico"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="6.6" r="3.2" /><path d="M3.8 17c.9-3.1 3.2-4.6 6.2-4.6s5.3 1.5 6.2 4.6" /></svg></span>Profil
        </a>
      </nav>

      <a href="profil.php" class="user-pill-btn logged-in" title="Buka Profil" style="text-decoration: none;">
          <span><?= htmlspecialchars($nama_user) ?></span>
          <div class="avatar-sm"><?= $inisial ?></div>
      </a>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="report-container">
        
        <div class="report-header">
            <h1>Laporan Kondisi Go-bike</h1>
            <p>Beri nilai tiap bagian: 1 = sangat rusak, 5 = sangat baik.</p>
        </div>

        <?php if (isset($error)): ?>
              <div style="background:#ffebee; color:#c62828; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight: 600;">
                  <?= htmlspecialchars($error) ?>
              </div>
        <?php endif; ?>

        <form method="POST" action="" id="formLaporan">
          <input type="hidden" name="id_peminjaman" value="<?= $id_peminjaman ?>">
          
          <input type="hidden" name="nilai_ban" id="input-ban" value="">
          <input type="hidden" name="nilai_rem" id="input-rem" value="">
          <input type="hidden" name="nilai_rantai" id="input-rantai" value="">
          <input type="hidden" name="nilai_rangka_bodi" id="input-rangka" value="">

          <!-- BAN -->
          <div class="rating-row" data-rating="ban">
            <div class="rating-label">Ban</div>
            <div class="rating-scale-wrapper">
                <span class="rating-text">Sangat rusak</span>
                <div class="scale">
                  <button type="button" data-nilai="1">1</button>
                  <button type="button" data-nilai="2">2</button>
                  <button type="button" data-nilai="3">3</button>
                  <button type="button" data-nilai="4">4</button>
                  <button type="button" data-nilai="5">5</button>
                </div>
                <span class="rating-text">Sangat baik</span>
            </div>
          </div>

          <!-- REM -->
          <div class="rating-row" data-rating="rem">
            <div class="rating-label">Rem</div>
            <div class="rating-scale-wrapper">
                <span class="rating-text">Sangat rusak</span>
                <div class="scale">
                  <button type="button" data-nilai="1">1</button>
                  <button type="button" data-nilai="2">2</button>
                  <button type="button" data-nilai="3">3</button>
                  <button type="button" data-nilai="4">4</button>
                  <button type="button" data-nilai="5">5</button>
                </div>
                <span class="rating-text">Sangat baik</span>
            </div>
          </div>

          <!-- RANTAI -->
          <div class="rating-row" data-rating="rantai">
            <div class="rating-label">Rantai</div>
            <div class="rating-scale-wrapper">
                <span class="rating-text">Sangat rusak</span>
                <div class="scale">
                  <button type="button" data-nilai="1">1</button>
                  <button type="button" data-nilai="2">2</button>
                  <button type="button" data-nilai="3">3</button>
                  <button type="button" data-nilai="4">4</button>
                  <button type="button" data-nilai="5">5</button>
                </div>
                <span class="rating-text">Sangat baik</span>
            </div>
          </div>

          <!-- RANGKA DAN BODI -->
          <div class="rating-row" data-rating="rangka">
            <div class="rating-label">Rangka Dan Body</div>
            <div class="rating-scale-wrapper">
                <span class="rating-text">Sangat rusak</span>
                <div class="scale">
                  <button type="button" data-nilai="1">1</button>
                  <button type="button" data-nilai="2">2</button>
                  <button type="button" data-nilai="3">3</button>
                  <button type="button" data-nilai="4">4</button>
                  <button type="button" data-nilai="5">5</button>
                </div>
                <span class="rating-text">Sangat baik</span>
            </div>
          </div>

          <!-- KETERANGAN -->
          <div class="textarea-wrapper">
            <textarea
              class="textarea-custom"
              name="keterangan"
              placeholder="Keterangan kondisi atau kerusakan"
              required
            ></textarea>
          </div>
          
          <button class="btn-submit-laporan" type="submit">
            Kirim Laporan
          </button>
        </form>
    </main>

    <script src="../assets/js/common.js"></script>
    
    <script>
        // SCRIPT INTERAKSI TOMBOL RATING
        document.addEventListener("DOMContentLoaded", function() {
            const rateSections = document.querySelectorAll('.rating-row');
            
            rateSections.forEach(section => {
                const buttons = section.querySelectorAll('.scale button');
                const type = section.getAttribute('data-rating'); 
                const hiddenInput = document.getElementById('input-' + type);

                buttons.forEach(btn => {
                    btn.addEventListener('click', function() {
                        buttons.forEach(b => b.classList.remove('on'));
                        this.classList.add('on');
                        
                        if (hiddenInput) {
                            hiddenInput.value = this.getAttribute('data-nilai');
                        }
                    });
                });
            });
        });

        // SCRIPT VALIDASI FORM
        document.getElementById('formLaporan').addEventListener('submit', function(e) {
            let isValid = true;
            let missingFields = [];
            
            const ratings = [
                { id: 'input-ban', label: 'Ban' },
                { id: 'input-rem', label: 'Rem' },
                { id: 'input-rantai', label: 'Rantai' },
                { id: 'input-rangka', label: 'Rangka dan body' }
            ];

            ratings.forEach(item => {
                const inputVal = document.getElementById(item.id).value;
                if (!inputVal || inputVal === '') {
                    isValid = false;
                    missingFields.push(item.label);
                }
            });

            const textarea = document.querySelector('textarea[name="keterangan"]');
            if (textarea.value.trim() === '') {
                isValid = false;
                missingFields.push('Keterangan');
            }

            if (!isValid) {
                e.preventDefault(); 
                alert('Harap lengkapi bagian berikut sebelum mengirim:\n\n- ' + missingFields.join('\n- '));
                return false;
            }
        });
    </script>
  </body>
</html>