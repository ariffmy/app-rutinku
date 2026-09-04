# Penyederhanaan UI

- Font Awesome Free 6.7.2 disimpan dalam `public/assets/vendor/fontawesome`, bersama lesen. Tiada CDN diperlukan semasa aplikasi digunakan.
- Ikon UI menggunakan `ui_icon()` dengan senarai nama yang dibenarkan. Ikon hiasan disembunyikan daripada pembaca skrin; label teks dikekalkan.
- Borang Rutin tidak lagi menerima Penerangan, Jenis atau Susunan. Borang Tugasan tidak lagi menerima Penerangan atau Susunan.
- Lajur lama dikekalkan untuk keserasian dan pemeliharaan data, bukan kawalan pengguna. Tiada migrasi pemadaman data diperlukan.
- Susunan tugasan untuk ibu bapa dan anak ialah masa menaik, diikuti ID untuk masa yang sama. Tugasan tanpa masa berada di akhir; nilai susunan manual lama tidak digunakan.
- Butang Sunting tidak meregang mengikut tinggi kandungan baris.
- Borang Tugasan menggunakan lebar bekas halaman yang sama dengan Rutin.
- Cache aset PWA kini v11. Muat semula aplikasi selepas deploy.

Hari Rutin ialah jadual utama untuk semua tugasan. Kekerapan tugasan hanya mengehadkan hari rutin, bukan mengatasinya. Tugasan sekali dan hari tertentu disahkan terhadap hari rutin semasa menyimpan, termasuk salinan untuk semua anak.
