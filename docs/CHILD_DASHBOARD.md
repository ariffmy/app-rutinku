# Dashboard anak dan gambar ganjaran

## Hari Ini dan kemajuan

- Hari Ini menapis tugasan mengikut jam peranti (telefon atau PC), dari masa mula sehingga sebelum masa tamat, menggunakan tempoh tugasan. Contoh: 17:00 selama 120 minit dipaparkan dari 17:00 sehingga sebelum 19:00, tanpa tambahan masa. Tugasan tanpa masa sentiasa kelihatan. Penapis dikemas kini setiap 15 saat dan apabila aplikasi dibuka semula.
- Jadual hari dan pengesahan penyelesaian kekal di pelayan dalam Asia/Kuala_Lumpur; jam telefon tidak digunakan sebagai kuasa pemberian mata. Tetapkan tarikh, waktu dan zon waktu telefon secara automatik. Tugasan daripada hari lain tidak dibawa ke hari semasa.
- Butang Sudah membuka dialog pengesahan. Selepas pelayan mengesahkan, AJAX mengemas kini mata dan memindahkan kad ke bahagian Sudah selesai di bawah. Kad menggunakan dua lajur pada skrin lebih besar dan satu pada telefon. Batal selesai memulangkan kad ke bahagian Belum selesai.
- Permintaan bersiri, butang dilumpuhkan semasa menyimpan, dan token CSRF diganti selepas setiap respons. Kegagalan rangkaian tidak menandakan tugasan sebagai selesai secara andaian; muat semula untuk menyemak jika respons hilang.
- Daily Progress memaparkan completion required / jumlah rutin required hari ini, peratus dan progress bar. Pending approval dan rejected tidak dikira; completion automatik dan approved dikira.
- Perfect Day diberikan apabila semua rutin required yang `perfect_day_eligible` selesai pada tarikh tersebut. Rekod `child_id + perfect_date` dan bonus ledger adalah unik. Paparan kejayaan menunjukkan bonus 10 mata.

## Mata, ganjaran dan sasaran

- Baki sentiasa dikira daripada points ledger. Setiap award/deduction menggunakan source type dan source ID; duplicate HTTP request tidak menambah transaksi kedua.
- Reward Goal membenarkan satu sasaran aktif bagi setiap Anak. Menukar sasaran membatalkan sasaran lama dan tidak memotong mata. Potongan hanya berlaku melalui reward redemption sedia ada.
- Kad sasaran memaparkan nama ganjaran, mata semasa/diperlukan, peratus, dan baki mata yang diperlukan.

## Pencapaian dan misi

- Halaman Pencapaian membezakan achievement locked dan unlocked. Milestone semasa: routine pertama, Perfect Day pertama, streak 7 hari, 50 routines, 10 Perfect Days, dan 1,000 earned points.
- Halaman Misi memaparkan misi minggu semasa, progress server, target, progress bar, dan bonus. Misi selesai memaparkan `Misi Selesai`; misi tamat tidak menerima progress atau bonus baharu.
- Hanya completion approved/completed dan Perfect Day sebenar digunakan. Nilai progress daripada browser tidak diterima.

## Profil
- Profil membenarkan avatar Font Awesome atau gambar JPG/PNG/WebP sehingga 4 MB dan 12 megapiksel. Umur dikira daripada tarikh lahir; nama ibu bapa diambil daripada keluarga anak. Anak tidak boleh mengubah identiti keluarga/tarikh lahir melalui borang gambar.
- Ganjaran mempunyai kategori, nama, mata diperlukan dan muat naik gambar; penerangan lama tidak dipaparkan. Gambar lama dikekalkan jika tiada muat naik baharu.
- Imej dimampatkan semula kepada JPEG maksimum 1024 piksel, dengan nama rawak. Metadata asal tidak diterbitkan. Imej disimpan di writable/uploads/family-ID dan hanya dihantar melalui laluan yang disahkan untuk keluarga tersebut. Gambar tidak dicache dalam PWA. Fail gambar lama dikekalkan apabila diganti (tiada pemadaman automatik).

## Deployment

Jalankan `php spark migrate` sehingga migration 26. Pastikan sambungan PHP GD tersedia dan `writable/uploads` boleh ditulis. Sandarkan `writable/uploads` bersama pangkalan data. Buka semula aplikasi selepas deployment aset. JavaScript diperlukan untuk penapis waktu telefon, AJAX, dan kawalan show/hide password.
