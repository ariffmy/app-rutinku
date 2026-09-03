# Rutin dan Tugasan

Menu menggunakan **Rutin**; setiap rutin mengandungi **Tugasan**. Borang tugasan mesra telefon menyediakan idea nama, masa mula, pratonton masa tamat, tempoh 1–1440 minit, mata 0–10000 dan pilihan anak.

Paparan jam menggunakan format Bahasa Melayu: `08:00` → `8 pagi`, `14:30` → `2:30 petang`, `20:00` → `8 malam`. Jam 00 menggunakan tengah malam; 01–11 pagi; 12–13 tengah hari; 14–18 petang; 19–23 malam. Borang menyediakan pilihan jam berlabel Melayu dan minit 00–59, termasuk tanpa JavaScript. Simpanan pangkalan data kekal dalam format 24 jam. Masa pada rekod peranti dan ganjaran dipaparkan bersama tarikh `dd/mm/yyyy`.

## Jadual

- Tugasan lama menggunakan `inherit` (Ikut rutin), tanpa perubahan hari kelayakan.
- Sekali: hanya pada tarikh yang dipilih.
- Setiap hari: bermula pada tarikh mula.
- Mingguan: hari terpilih selepas atau pada tarikh mula.
- Bulanan: nombor hari yang sama; bulan tanpa tarikh tersebut dilangkau.
- Jadual eksplisit mengatasi hari rutin, tetapi rutin dan anak mesti aktif.
- Semakan paparan anak dan penyelesaian menggunakan peraturan yang sama, dalam zon waktu aplikasi.
- Tempoh ialah maklumat perancangan, bukan pemasa atau notifikasi automatik.

## Semua anak

Pilihan ini tersedia semasa menambah tugasan. Pelayan memilih semua anak aktif dalam keluarga ibu bapa. Rutin anak dengan nama sama digunakan; jika tiada, maklumat rutin serta hari rutinnya disalin. Tugasan lain tidak disalin. Jika nama rutin tidak unik atau rutin sasaran tidak aktif, seluruh operasi dibatalkan tanpa simpanan separa.

Setiap anak mendapat rekod tugasan sendiri, dengan penyelesaian, mata dan suntingan berasingan. Ia bukan pautan penyegerakan kekal. Menambah anak kemudian tidak menyalin tugasan lama secara automatik.

## Pemasangan

Sandarkan pangkalan data production sebelum deploy. Selepas deploy kod, jalankan `php spark migrate` pada pelayan untuk menambah empat lajur jadual kepada `routine_tasks`. Migrasi tidak memadam rekod lama. Cache aset PWA dinaikkan kepada v7; muat semula aplikasi selepas kemas kini.

Pangkalan data tempatan telah dimigrasikan. Ujian automatik menggunakan SQLite sementara, bukan data aplikasi tempatan atau production.
