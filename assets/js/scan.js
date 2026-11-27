// ==========================================
// LOGIKA UNTUK SCAN QR CODE (KAMERA) + ANIMASI
// ==========================================
let html5QrcodeScanner = null;

async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.getTracks().forEach(track => track.stop());
    } catch (err) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            alert("Akses kamera diblokir.\n\nSilakan klik ikon Gembok (🔒) di browser, lalu ubah 'Kamera' menjadi 'Izinkan'.");
        } else {
            alert("Kamera tidak dapat diakses: " + err.message);
        }
        return; 
    }

    document.getElementById('scanner-placeholder').style.display = 'none';
    document.getElementById('reader').style.display = 'block';
    document.getElementById('hint-text').innerText = "Memuat kamera...";
    document.getElementById('btn-close-camera').style.display = 'flex';

    if (typeof Html5Qrcode === 'undefined') {
        alert("Gagal memuat library kamera. Periksa koneksi internet.");
        stopCamera();
        return;
    }

    html5QrcodeScanner = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    try {
        await html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess);
        document.getElementById('hint-text').innerText = "Posisikan QR Code di dalam kotak.";
    } catch (err) {
        try {
            await html5QrcodeScanner.start({ facingMode: "user" }, config, onScanSuccess);
            document.getElementById('hint-text').innerText = "Posisikan QR Code di dalam kotak.";
        } catch (err2) {
            alert("Kamera tidak dapat dibuka.");
            stopCamera();
        }
    }
}

function onScanSuccess(decodedText, decodedResult) {
    // 1. Ambil hasil scan awal
    let kodeQr = decodedText;

    // 2. Jika QR Code berisi URL utuh, ambil teks kode sepedanya saja (misal: GB-03)
    if (decodedText.includes("qr=")) {
        kodeQr = decodedText.split("qr=")[1].split("&")[0];
    }

    // 3. Matikan kamera terlebih dahulu
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            tampilkanAnimasiSukses(kodeQr);
        }).catch(err => {
            tampilkanAnimasiSukses(kodeQr);
        });
    } else {
        tampilkanAnimasiSukses(kodeQr);
    }
}

function stopCamera() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner.clear();
            resetUI();
        }).catch(err => {
            resetUI();
        });
    } else {
        resetUI();
    }
}

function resetUI() {
    document.getElementById('scanner-placeholder').style.display = 'flex';
    document.getElementById('reader').style.display = 'none';
    document.getElementById('btn-close-camera').style.display = 'none';
    document.getElementById('hint-text').innerText = "Klik kotak di atas untuk membuka kamera.";
}

// ==========================================
// FUNGSI ANIMASI SUKSES SCAN
// ==========================================
function tampilkanAnimasiSukses(kodeQr) {
    // Getaran di HP (Haptic Feedback) - opsional
    if ("vibrate" in navigator) {
        navigator.vibrate(200);
    }

    // 1. Suntikkan CSS untuk animasi ke dalam halaman (hanya sekali)
    if (!document.getElementById('scan-success-css')) {
        const style = document.createElement('style');
        style.id = 'scan-success-css';
        style.innerHTML = `
            .scan-success-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(15, 23, 42, 0.7); /* Warna gelap transparan */
                backdrop-filter: blur(5px);
                display: flex; align-items: center; justify-content: center;
                z-index: 99999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .scan-success-overlay.show {
                opacity: 1;
            }
            .scan-success-box {
                background: #ffffff;
                padding: 30px 40px;
                border-radius: 24px;
                text-align: center;
                transform: scale(0.5); /* Mulai dari kecil */
                transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efek memantul */
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            }
            .scan-success-overlay.show .scan-success-box {
                transform: scale(1);
            }
            .scan-success-icon {
                width: 80px; height: 80px;
                border-radius: 50%;
                background: #dcfce7; /* Hijau pastel */
                color: #16a34a; /* Hijau tua */
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 15px;
            }
            .scan-success-icon svg {
                width: 45px; height: 45px;
                stroke-width: 3;
                stroke-dasharray: 50;
                stroke-dashoffset: 50; /* Sembunyikan centang dulu */
                animation: drawCheck 0.6s 0.3s forwards; /* Animasi menggambar */
            }
            .scan-success-text {
                font-family: 'Albert Sans', sans-serif;
                font-size: 20px;
                font-weight: 700;
                color: #1e293b;
                margin-bottom: 5px;
            }
            .scan-success-sub {
                font-family: 'Albert Sans', sans-serif;
                font-size: 14px;
                color: #64748b;
            }
            @keyframes drawCheck {
                100% { stroke-dashoffset: 0; } /* Gambar centang penuh */
            }
        `;
        document.head.appendChild(style);
    }

    // 2. Buat elemen HTML overlay secara dinamis
    const overlay = document.createElement('div');
    overlay.className = 'scan-success-overlay';
    overlay.innerHTML = `
        <div class="scan-success-box">
            <div class="scan-success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="scan-success-text">QR Code Terdeteksi!</div>
            <div class="scan-success-sub">Mengarahkan ke halaman peminjaman...</div>
        </div>
    `;

    // 3. Tampilkan overlay ke layar
    document.body.appendChild(overlay);

    // Beri sedikit jeda agar browser sempat merender elemen sebelum animasi dimulai
    setTimeout(() => {
        overlay.classList.add('show');
    }, 10);

    // 4. Tunggu 1.2 detik, lalu alihkan halaman ke hasil scan
    setTimeout(() => {
        window.location.href = "hasil_scan.php?qr=" + encodeURIComponent(kodeQr);
    }, 1200);
}