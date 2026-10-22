# Upload, review, dan impor data historis

SETBA atau Admin membuka **Daftar laporan → Data historis**, lalu mengupload workbook pemantauan SETBA berformat `.xlsx`. Maksimal 2 MB dan 500 baris data per file. Sistem mengenali header `No LHP`, `Reff IDT`, dan `Rekomendasi Temuan Besar` pada 30 baris awal sheet. Baris yang hanya berisi formula bantu tidak menjadi data laporan. Formula tidak dijalankan; sistem membaca nilai cache Excel.

Upload menghasilkan draft pribadi. Pada halaman review, user dapat membuka tiap baris, membandingkan dengan semua kolom sumber, mengoreksi isian, memilih Satker dan referensi dari Data Master, atau mengeluarkan baris dari impor. Sumber Excel asli tetap tersimpan di penyimpanan privat; koreksi disimpan terpisah. Tanggal laporan yang sama dapat diisi bersama. Perubahan tanggal bersama membatalkan tanda “sudah diperiksa” pada baris yang berubah.

Tanggal surat, tanggal diterima, nomor temuan, dan tanggal acuan snapshot harus dilengkapi berdasarkan sumber yang benar. Sistem tidak mengarang tanggal atau nama pemeriksa. Tanggal acuan menyatakan kapan posisi/status/nilai pada snapshot diketahui berlaku; bukan tanggal keputusan UKI, Inspektorat, atau BPK. Tanggal unggah SIPTL boleh belum diketahui: statusnya ditandai sebagai snapshot historis dan tetap ikut rekap SIPTL. Riwayat menampilkan awal historis, tanpa merekonstruksi tahapan yang belum tersedia.

Satu baris diperlakukan sebagai penugasan Satker pada satu bentuk tindak lanjut. Beberapa baris dengan Ref IDT yang sama menjadi satu rekomendasi bila identitas dan uraiannya konsisten. Nilai tiap baris harus merupakan bagian Satker/bentuk tersebut, bukan total rekomendasi yang diulang. Temuan dikelompokkan berdasarkan nomor temuan pada laporan. Nilai yang diakui sebagian didukung; penilaian baru pada alur biasa menggantikan nilai snapshot yang bersangkutan.

Konfirmasi hanya tersedia bila seluruh baris pilihan lengkap dan sudah diperiksa. Server memeriksa ulang Data Master, duplikat, konsistensi kelompok, dan versi draft. Satu konfirmasi menggunakan satu transaksi: kegagalan membatalkan seluruh penulisan. Konfirmasi berulang pada draft yang sudah diimpor tidak menambah data lagi. Ref IDT yang sudah ada di database harus dikeluarkan dari impor; fitur ini tidak menimpa data operasional yang sudah ada. Rekomendasi baru dapat ditambahkan pada laporan yang sudah ada bila sumber/tanggal laporan dan identitas temuannya sesuai.

Setelah berhasil, data masuk ke tabel utama laporan, temuan, rekomendasi, tindakan, dan sasaran. Dashboard dan hak akses Satker/Pimpinan memakai data yang sama. Draft, sumber asli, koreksi, dan ID hasil impor tetap tersimpan sebagai asal data. Log mencatat upload, review, dan konfirmasi pada waktu pencatatan yang sebenarnya.

Nomor CHV/LHV, informasi paket, pejabat, dan kolom lain tetap dipertahankan di sumber. Surat keputusan, bukti pembayaran, dan riwayat transaksi tidak dibuat otomatis dari informasi yang belum lengkap. Nilai TPTD positif ditahan untuk pemetaan terpisah karena alur saat ini tidak mendukung status tersebut. Workbook dengan struktur berbeda perlu disesuaikan terlebih dahulu; pemetaan header bebas belum tersedia.

Pengujian fitur berada di `tests/Feature/ReviewHistorisTest.php` dan memakai database SQLite sementara. Migrasi `2026_10_10_000100_tambah_review_historis.php` menambah struktur tanpa menghapus laporan yang sudah ada.
