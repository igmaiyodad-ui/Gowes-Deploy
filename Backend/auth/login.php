<?php
// ==========================================
// WAJIB baris 1: buffer output agar header() aman
// ==========================================
ob_start();

// ==========================================
// 1. KONFIGURASI SESSION (HARDENING)
// ==========================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// 'secure' aktif hanya kalau HTTPS (Railway sudah HTTPS)
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// ==========================================
// 2. LOGOUT
// ==========================================
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

// ==========================================
// 3. JIKA SUDAH LOGIN → REDIRECT
// ==========================================
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'superadmin': header("Location: ../superadmin/dashboard.php"); break;
        case 'admin':      header("Location: ../admin/dashboard.php");      break;
        case 'petugas':    header("Location: ../petugas/dashboard.php");    break;
        default:           header("Location: ../mahasiswa/peminjaman.php"); break;
    }
    exit;
}

require_once '../config/koneksi.php';

// ==========================================
// 4. CSRF TOKEN
// ==========================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// ==========================================
// 5. PROSES LOGIN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token. Silakan refresh halaman.");
    }

    $no_identitas = trim($_POST['nim'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (strlen($no_identitas) > 50 || strlen($password) > 100) {
        $error = "Input terlalu panjang!";
    } elseif ($no_identitas === '' || $password === '') {
        $error = "Kolom identitas dan password wajib diisi!";
    } else {
        try {
            // ---------- 1) SUPERADMIN ----------
            $stmt = $pdo->prepare("SELECT id_superadmin, username, password FROM superadmin WHERE username = :id LIMIT 1");
            $stmt->execute([':id' => $no_identitas]);
            $row = $stmt->fetch();
            if ($row !== false && (password_verify($password, $row['password']) || $password === $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['role']          = 'superadmin';
                $_SESSION['id_superadmin'] = $row['id_superadmin'];
                $_SESSION['username']      = $row['username'];
                $_SESSION['nama']          = $row['username'];
                header("Location: ../superadmin/dashboard.php");
                exit;
            }

            // ---------- 2) PEMINJAM (MAHASISWA) ----------
            // PENTING: tabel `peminjam` TIDAK punya kolom `jenis_peminjam`
            $stmt = $pdo->prepare(
                "SELECT id_peminjam, no_identitas, nama, password, no_telp, alamat
                 FROM peminjam WHERE no_identitas = :id LIMIT 1"
            );
            $stmt->execute([':id' => $no_identitas]);
            $row = $stmt->fetch();
            if ($row !== false && (password_verify($password, $row['password']) || $password === $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['role']           = 'peminjam';
                $_SESSION['id_peminjam']    = $row['id_peminjam'];
                $_SESSION['no_identitas']   = $row['no_identitas'];
                $_SESSION['nama']           = $row['nama'];
                $_SESSION['no_telp']        = $row['no_telp'] ?? null;
                $_SESSION['alamat']         = $row['alamat'] ?? null;
                $_SESSION['jenis_peminjam'] = 'Mahasiswa'; // HARDCODE — bukan dari DB
                header("Location: ../mahasiswa/peminjaman.php");
                exit;
            }

            // ---------- 3) PETUGAS ----------
            $stmt = $pdo->prepare("SELECT id_petugas, id_shelter, username, password FROM petugas WHERE username = :id LIMIT 1");
            $stmt->execute([':id' => $no_identitas]);
            $row = $stmt->fetch();
            if ($row !== false && (password_verify($password, $row['password']) || $password === $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['role']        = 'petugas';
                $_SESSION['id_petugas']  = $row['id_petugas'];
                $_SESSION['id_shelter']  = $row['id_shelter'];
                $_SESSION['username']    = $row['username'];
                $_SESSION['nama']        = $row['username'];
                header("Location: ../petugas/dashboard.php");
                exit;
            }

            // ---------- 4) ADMIN ----------
            $stmt = $pdo->prepare("SELECT id_admin, username, password FROM admin WHERE username = :id LIMIT 1");
            $stmt->execute([':id' => $no_identitas]);
            $row = $stmt->fetch();
            if ($row !== false && (password_verify($password, $row['password']) || $password === $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['role']     = 'admin';
                $_SESSION['id_admin'] = $row['id_admin'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['nama']     = $row['username'];
                header("Location: ../admin/dashboard.php");
                exit;
            }

            $error = "Nomor Identitas atau Password salah!";

        } catch (PDOException $e) {
            error_log('LOGIN PDO ERROR: ' . $e->getMessage());
            $error = "Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.";
        }
    }
}

// Buffer bisa dikosongkan (opsional) — tidak wajib
// ob_end_flush();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk · GOWES</title>
  <link rel="icon" href="../img/logo.png">
  <link rel="stylesheet" href="../assets/css/loader.css">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/login-mahasiswa.css">
</head>
<body class="login">

  <div class="login-wrap">

    <aside class="login-brand">
      <div class="brand-content">
        <img src="../img/logo-white.png" alt="GOWES" class="brand-logo">
        <p class="tag">Pinjam Go-bike, Jelajahi Kampus!</p>
      </div>
      <p class="about">
        Nikmati perjalanan di kampus yang lebih praktis dan ramah lingkungan bersama GOWES.
        Pinjam Sepeda dengan mudah tanpa perlu kartu identitas fisik.
        Cukup scan QR Code, pilih durasi peminjaman, dan siap berkeliling kampus.
      </p>
    </aside>

    <section class="login-form">

      <div class="mobile-logo">
        <img src="../img/logo.png" alt="GOWES">
        <p class="mobile-sub">Masuk untuk meminjam Go-bike POLINEMA</p>
      </div>

      <div class="login-form-in">

        <h1 class="desktop-title">LOGIN</h1>
        <p class="desktop-sub">Masuk untuk meminjam Go-bike POLINEMA</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error" style="text-align:center;margin-bottom:15px;">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <input class="input" type="text" name="nim" id="inputNim"
                 placeholder="Nomor Identitas" autocomplete="username"
                 maxlength="50"
                 value="<?= htmlspecialchars($_POST['nim'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 required>

          <div class="password-wrapper">
              <input class="input" type="password" name="password" id="passwordInput"
                     placeholder="Password" autocomplete="current-password"
                     maxlength="100" required>

              <span class="toggle-password" onclick="togglePassword()" title="Lihat/Sembunyikan Password">
                  <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none"
                       viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                       stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                  </svg>
              </span>
          </div>

          <button class="btn btn-primary" type="submit" id="submitBtn">Masuk</button>

          <div class="back-wrapper">
              <a href="../public/dashboard.php" class="back-to-dashboard">
                  Kembali ke Dashboard Utama
              </a>
          </div>
        </form>

      </div>

      <div class="login-footer">
          <p>GOWES siap menjelajahi kampus dengan lebih praktis dan nyaman!</p>
      </div>

    </section>
  </div>

  <script src="../assets/js/login.js"></script>
  <script src="../assets/js/loader.js"></script>
</body>
</html>
