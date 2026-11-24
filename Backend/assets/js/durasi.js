document.addEventListener("DOMContentLoaded", function() {
    const buttons = document.querySelectorAll('.dur');
    const batasText = document.getElementById('batas');
    const inputDurasi = document.getElementById('input-durasi');
    
    // Ambil waktu mulai dari atribut data-waktu di HTML (format: HH:MM)
    const waktuMulaiEl = document.getElementById('waktu-mulai');
    let waktuMulaiText = "08:00"; // Fallback jika elemen tidak ada
    
    if (waktuMulaiEl) {
        waktuMulaiText = waktuMulaiEl.getAttribute('data-waktu');
    }

    // Fungsi untuk menghitung dan menampilkan batas waktu
    function updateBatasWaktu(durasi) {
        // Pisahkan jam dan menit (contoh: "15:43" menjadi 15 dan 43)
        let timeParts = waktuMulaiText.split(':');
        let jamMulai = parseInt(timeParts[0]);
        let menitMulai = parseInt(timeParts[1]);

        // Hitung total menit
        let totalMenit = (jamMulai * 60) + menitMulai + (durasi * 60);
        
        // Konversi kembali ke jam dan menit
        let jamBatas = Math.floor(totalMenit / 60) % 24;
        let menitBatas = totalMenit % 60;
        
        // Format ke HH.MM
        let formatJam = String(jamBatas).padStart(2, '0');
        let formatMenit = String(menitBatas).padStart(2, '0');
        
        // Tampilkan di layar
        batasText.textContent = `${formatJam}.${formatMenit} WIB`;

        // Cek jika melewati jam 15:30 WIB (Beri warna merah)
        if (jamBatas > 15 || (jamBatas === 15 && menitBatas > 30)) {
            batasText.style.color = 'red';
            batasText.style.fontWeight = 'bold';
        } else {
            batasText.style.color = ''; // Kembalikan ke warna asli
            batasText.style.fontWeight = '';
        }
    }

    // Inisialisasi awal saat halaman dimuat (default 1 jam)
    updateBatasWaktu(1);

    // Event listener saat tombol durasi (1 jam, 2 jam, 3 jam) diklik
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Hapus class 'active' dari semua tombol
            buttons.forEach(b => b.classList.remove('active'));
            
            // Tambahkan class 'active' ke tombol yang baru diklik
            this.classList.add('active');

            // Ambil nilai durasi dan update input form
            const durasi = parseInt(this.getAttribute('data-durasi'));
            inputDurasi.value = durasi;

            // Hitung ulang batas waktu
            updateBatasWaktu(durasi);
        });
    });
});