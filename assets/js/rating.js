document.addEventListener("DOMContentLoaded", function() {
    // Ambil semua section rating
    const rateSections = document.querySelectorAll('.rate');
    
    rateSections.forEach(section => {
        // Ambil semua tombol (1-5) di dalam section ini
        const buttons = section.querySelectorAll('.scale button');
        
        // Ambil jenis rating (ban, rem, rantai, rangka) dari atribut data-rating
        const type = section.getAttribute('data-rating'); 
        
        // Temukan hidden input yang sesuai (contoh: input-ban, input-rem)
        const hiddenInput = document.getElementById('input-' + type);

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                // 1. Hapus class 'on' dari semua tombol di section ini
                buttons.forEach(b => b.classList.remove('on'));
                
                // 2. Tambahkan class 'on' ke tombol yang baru saja diklik
                this.classList.add('on');
                
                // 3. Update nilai di hidden input agar bisa dikirim lewat form
                if (hiddenInput) {
                    hiddenInput.value = this.getAttribute('data-nilai');
                }
            });
        });
    });
});