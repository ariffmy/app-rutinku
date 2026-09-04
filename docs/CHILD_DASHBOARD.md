# Dashboard anak dan gambar ganjaran

- Hari Ini menapis tugasan mengikut jam peranti (telefon atau PC), dari masa mula sehingga sebelum masa tamat, menggunakan tempoh tugasan. Contoh: 17:00 selama 120 minit dipaparkan dari 17:00 sehingga sebelum 19:00, tanpa tambahan masa. Tugasan tanpa masa sentiasa kelihatan. Penapis dikemas kini setiap 15 saat dan apabila aplikasi dibuka semula.
- Jadual hari dan pengesahan penyelesaian kekal di pelayan dalam Asia/Kuala_Lumpur; jam telefon tidak digunakan sebagai kuasa pemberian mata. Tetapkan tarikh, waktu dan zon waktu telefon secara automatik. Tugasan daripada hari lain tidak dibawa ke hari semasa.
- Butang Sudah membuka dialog pengesahan. Selepas pelayan mengesahkan, AJAX mengemas kini mata dan memindahkan kad ke bahagian Sudah selesai di bawah. Kad menggunakan dua lajur pada skrin lebih besar dan satu pada telefon. Batal selesai memulangkan kad ke bahagian Belum selesai.
- Permintaan bersiri, butang dilumpuhkan semasa menyimpan, dan token CSRF diganti selepas setiap respons. Kegagalan rangkaian tidak menandakan tugasan sebagai selesai secara andaian; muat semula untuk menyemak jika respons hilang.
- Profil membenarkan avatar Font Awesome atau gambar JPG/PNG/WebP sehingga 4 MB dan 12 megapiksel. Umur dikira daripada tarikh lahir; nama ibu bapa diambil daripada keluarga anak. Anak tidak boleh mengubah identiti keluarga/tarikh lahir melalui borang gambar.
- Ganjaran mempunyai kategori, nama, mata diperlukan dan muat naik gambar; penerangan lama tidak dipaparkan. Gambar lama dikekalkan jika tiada muat naik baharu.
- Imej dimampatkan semula kepada JPEG maksimum 1024 piksel, dengan nama rawak. Metadata asal tidak diterbitkan. Imej disimpan di writable/uploads/family-ID dan hanya dihantar melalui laluan yang disahkan untuk keluarga tersebut. Gambar tidak dicache dalam PWA. Fail gambar lama dikekalkan apabila diganti (tiada pemadaman automatik).

## Deployment

Jalankan `php spark migrate` untuk lajur kategori ganjaran. Pastikan sambungan PHP GD tersedia dan writable/uploads boleh ditulis. Sandarkan writable/uploads bersama pangkalan data. Cache aset PWA ialah v12; buka semula aplikasi untuk mendapatkan skrip baharu. JavaScript diperlukan untuk penapis waktu telefon dan AJAX.
