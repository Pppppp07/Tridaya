# infaX — Pemantauan Tindak Lanjut Hasil Pemeriksaan

Aplikasi web untuk memantau dan mengelola tindak lanjut temuan LHP (BPK) dan LHA (Inspektorat) di lingkungan Sekretariat Badan.

Nama **infaX** menghubungkan **Temuan, Telaah, Keputusan, dan Pantau**. Huruf X melambangkan eksekusi yang mempertemukan keputusan dengan pemantauan. Lihat [makna dan penggunaan identitas infaX](docs/IDENTITAS-INFAX.md).

Dibangun menggunakan **Laravel 13** &middot; **PHP 8.3+** &middot; **MySQL 8** (SQLite dipakai saat uji otomatis).

CSS dan JavaScript aplikasi menggunakan build Vite. Setelah mengubah sumber aset, jalankan `.\npm-proyek.cmd run build` dan sertakan `public/build` pada deployment. Hasil pengukuran, pengujian, serta batas optimasi saat ini tercatat dalam [Catatan performa](PERFORMANCE-REVIEW.md).

---

## Fitur Utama

- **Yang bergerak adalah penugasan, bukan rekomendasi.** Satu rekomendasi bisa dipikul beberapa satuan kerja dengan beberapa bentuk tindak lanjut; tiap pasangan satuan kerja × tindak lanjut menempuh rantainya sendiri dan punya posisi, tenggat, serta nilainya sendiri.
- **Dua sumbu penilaian yang dipisah**:
  - **Status SIPTL** (BT · BS · SS) &mdash; milik BPK, disalin Setba dari SIPTL, hanya untuk LHP.
  - **Hasil verifikasi** (Memadai / Belum memadai; LHA: Sesuai / Belum sesuai) &mdash; milik Inspektorat, berdiri di atas surat bernomor.
  - **Posisi berkas** &mdash; di meja siapa berkasnya menunggu, berubah karena perpindahan sehari-hari.
- **Multi-peran**: Satuan Kerja, Sekretariat Badan (Setba), Unit Kepatuhan Internal (UKI), Inspektorat, Pimpinan, Administrator.
- **Pimpinan per Satker**: Admin DTI menetapkan Satker yang dipimpin melalui Data Master → User & akses → Ubah peran. Dashboard, laporan, rekomendasi, berkas, dan notifikasi Pimpinan hanya memuat Satker tersebut. Haknya hanya melihat. Jika user memiliki beberapa peran, cakupan mengikuti peran aktif; cakupan Pimpinan tersimpan terpisah dari penugasan sebagai penanggung jawab Satker.
- **Akun Pimpinan lama** perlu ditetapkan Satkernya oleh Admin DTI setelah migrasi. Selama belum ditetapkan, akses data Pimpinan belum tersedia; sistem tidak memberikan akses seluruh Satker sebagai pengganti.
- **Hak lihat satuan kerja dijaga sampai ke kalimatnya**: satuan kerja hanya melihat barisnya sendiri; nama satuan kerja lain di dalam kalimat disamarkan, dan surat pemeriksaan asli tidak pernah sampai kepadanya.
- **Catat laporan baru** tiga langkah, dengan draf yang tersimpan di basis data.
- **Ringkasan** berisi dua blok status, dua tabel rekap, dan peta data yang bisa diatur sendiri (dikelompokkan menurut apa, dihitung apa, digambar dalam bentuk apa).
- **Draf satuan kerja** hanya menyimpan isian sementara: berkas tetap di satuan kerja, tanpa pemberitahuan dan tanpa catatan di Progres, sampai satuan kerja menekan Kirim ke Setba. Kemajuannya hanya terbaca Setba sebagai pantauan (tanpa link berkas), dan Setba diberi tahu di lonceng tanpa email.
- **Filter jenis pemberitahuan**: halaman Pemberitahuan bisa disaring per jenis (Rekomendasi baru, Kiriman satuan kerja, Hasil UKI & Inspektorat, SIPTL, dan lainnya); angkanya mengikuti tab, dan "Tandai yang tampil dibaca" hanya menandai jenis itu.
- **Pembaruan terbaru**: daftar Rekomendasi bawaannya menaruh yang baru diperbarui di atas, memberi titik merah pada pembaruan yang belum dilihat, dan menekan barisnya membuka bagian yang berubah (disorot). Bisa dimatikan di Profil & pengaturan → Pemberitahuan.
- **Kirim bertahap**: dokumen yang diminta boleh dikirim sebagian; sisanya menyusul pada kiriman berikutnya, dan Setba/UKI melihat dokumen mana yang belum terlampir.

---

## Cara Menjalankan

### 1. Cara Cepat (Windows)
Cukup klik dua kali berkas:
- **`jalankan.bat`**: Menjalankan server lokal dan otomatis membuka browser ke `http://localhost:8000`.
- **`atur-ulang.bat`**: Mengembalikan basis data ke contoh awal untuk simulasi ulang.

### 2. Cara Manual

1. Salin berkas lingkungan dan pasang dependensi:
   ```bash
   cp .env.example .env
   composer install
   ```

2. Buat kunci aplikasi:
   ```bash
   php artisan key:generate
   ```

3. Jalankan migrasi dan penyemai:
   ```bash
   php artisan migrate:fresh --seed
   ```

4. Jalankan server lokal:
   ```bash
   php artisan serve
   ```
   Buka di browser: `http://127.0.0.1:8000`

### Demo memakai tanggal tetap

Data contoh disusun untuk **17 Agustus 2026**, sama dengan prototipenya. Tanggalnya dipatok lewat `.env`:

```
SIMTLHP_HARI_INI=2026-08-17
SIMTLHP_DATA_CONTOH=true
```

Kosongkan `SIMTLHP_HARI_INI` untuk pemakaian sungguhan. Untuk mengosongkan data berkas tanpa menghapus data master dan akun: `php artisan tlhp:kosongkan`.

---

## Akun Pengujian (Demo)

Semua akun pengujian menggunakan password bawaan: **`rahasia123`**

| Email | Peran | Hak Akses Utama |
|---|---|---|
| `setba@contoh.test` | Sekretariat Badan | Mencatat laporan baru, meneruskan berkas, mengurus SIPTL, data master |
| `uki@contoh.test` | Unit Kepatuhan Internal | Menelaah kecukupan bukti |
| `inspektorat@contoh.test` | Inspektorat | Verifikasi akhir |
| `pimpinan@contoh.test` | Pimpinan | Hanya melihat Satker yang dipimpin; berandanya Dashboard |
| `admin@contoh.test` | Administrator | Sama seperti Setba |
| `medan@contoh.test` dan 15 lainnya | Satuan kerja | Mengisi tindak lanjut untuk satuan kerjanya sendiri |

Email satuan kerja memakai nama pendeknya: `sekretariat`, `talenta`, `sdackps`, `bmpipiw`, `manajemen`, `politeknik`, `penilaian`, `medan`, `palembang`, `jakarta`, `bandung`, `yogyakarta`, `surabaya`, `banjarmasin`, `makassar`, `jayapura`.

> **Perhatian**: Akun pengujian di atas hanya untuk simulasi lokal. Hapus blok akun contoh di `resources/views/masuk.blade.php` dan ganti seluruh password sebelum dipakai sungguhan.

---

## Menjalankan Pengujian (Testing)

```bash
php artisan test
```

67 uji: layar tiap peran, hak akses satuan kerja, rantai penuh satu berkas (satuan kerja → Setba → UKI → Setba → Inspektorat → SIPTL berikut jalan pulangnya), Catat laporan baru, Pemberitahuan, Data master, Ringkasan, keutuhan data contoh, dan aturan-aturan murni.

---

## Dokumentasi Tambahan

- [Panduan Penggunaan Cepat](BACA-DULU.md)
- [Panduan Menjalankan Manual](CARA-MENJALANKAN-MANUAL.md)
- [Panduan Server (pemasangan, antrean email, batas beban)](PANDUAN-SERVER.md)
- [Catatan Perubahan](../CATATAN-PERUBAHAN.md) &mdash; riwayat perubahan prototipe berikut padanannya di sini
