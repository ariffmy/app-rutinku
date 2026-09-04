# Rutin Semua anak

Rutin baharu melalui Semua anak menyimpan pengecam kumpulan rawak. Nama, hari rutin dan status aktif disimpan serentak untuk semua salinan kumpulan asal, termasuk anak yang kemudian dinyahaktifkan. Anak baharu tidak dimasukkan secara automatik. Rutin individu dan kumpulan lain tidak terjejas walaupun namanya sama.

Borang kumpulan memaparkan anak yang terlibat dan butang Simpan untuk semua anak. Anak pemilik tidak boleh ditukar secara individu. Untuk nyahaktif kumpulan, matikan Rutin aktif dan simpan; butang padam satu rutin tidak dipaparkan pada borang kumpulan.

Tugasan, penyelesaian dan mata kekal rekod berasingan bagi setiap anak. Suntingan tugasan bukan sebahagian penyelarasan rutin ini.

## Rekod lama

Versi terdahulu tidak merekodkan asal pilihan Semua anak. Migrasi tidak meneka berdasarkan nama atau masa penciptaan. Rekod tersebut dilabel Rekod lama · asal tidak direkodkan dan kekal tidak berpaut. Penggabungan rekod lama memerlukan pengesahan rutin yang benar-benar satu kumpulan.

## Deployment

Sandarkan pangkalan data dan jalankan `php spark migrate`. Migrasi 17 menambah group_token, assignment_scope dan indeks carian tanpa mengubah tugasan atau sejarah mata. Nilai kumpulan tidak diterima daripada borang pengguna. Kemas kini berkumpulan memeriksa sempadan keluarga dan menggunakan transaksi supaya kegagalan tidak menghasilkan suntingan separa.
