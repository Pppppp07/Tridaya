# Monitoring Tindak Lanjut Hasil Pemeriksaan (SIMTLHP)

Aplikasi web untuk memantau tindak lanjut temuan LHP (BPK) dan LHA (Inspektorat)
di lingkungan Sekretariat Badan.

Laravel 13 · PHP 8.3 · MySQL 8 (SQLite dipakai saat uji otomatis).

Tampilan dan aturannya mengikuti prototipe (`../Prototipe`). Kalau keduanya
berbeda, prototipe yang benar — dan bedanya dicatat di `../CATATAN-PERUBAHAN.md`.

---

## Cara menjalankan

**Klik dua kali `jalankan.bat`.**

Browser terbuka sendiri ke <http://localhost:8000> setelah kira-kira tiga detik.
Jendela hitam yang muncul adalah servernya — biarkan terbuka selama dipakai.
Untuk berhenti: tekan `Ctrl+C` di jendela itu, atau tutup saja jendelanya.

Kalau berkasnya mengeluh PHP tidak ditemukan, berarti Laragon atau XAMPP belum
terpasang di komputer itu. Pasang salah satunya, lalu jalankan lagi.

## Akun contoh

Semuanya memakai password **`rahasia123`**. Halaman masuk (`/masuk`) kini bersih seperti
saat dipasang — tempat SSO. Akun contoh ada di **Login developer**
(`/masuk/pengembang`), lewat kartu "Login developer" di bawah kartu Masuk: daftar
akun yang tinggal diklik — email dan password-nya terisi sendiri — plus formulir email
dan password. Daftarnya dikelompokkan (Petugas pusat, Penanggung jawab unit
kerja) dan bisa dilipat; pilihan itu diingat tiap browser.

| Email | Peran | Yang bisa dilakukan |
|---|---|---|
| `setba@contoh.test` | Sekretariat Badan | melihat semuanya, mencatat laporan baru, meneruskan berkas, mengurus SIPTL, Data master |
| `uki@contoh.test` | Unit Kepatuhan Internal | menelaah kecukupan bukti |
| `inspektorat@contoh.test` | Inspektorat | verifikasi akhir |
| `pimpinan@contoh.test` | Pimpinan | hanya melihat; berandanya Dashboard |
| `dti@contoh.test` | DTI — Data dan Teknologi Informasi | hanya melihat: Log aktivitas dan rekomendasi (27 Sep) |
| `admin@contoh.test` | Administrator | seperti Setba, ditambah Log aktivitas dan akun DTI/Admin |

Sejak 27 Sep tiap akun pusat terhubung ke satu pegawai di direktori contoh
(`database/data/irm-contoh.json`) menurut NIP — nama dan jabatannya ikut dari
sana, supaya masuk lewat SSO nanti mengenali orangnya.

Tiap satuan kerja punya satu akun: akun **penanggung jawabnya**, dengan email
orang itu dari direktori (mis. `martin.simanjuntak@contoh.test` untuk Balai
Wil. I Medan). Daftar lengkapnya ada di Login developer, dan di Data master →
Pengguna.

Login developer **padam sendiri di produksi** (`APP_ENV=production`), atau lewat
`SIMTLHP_AKUN_DEMO=false` — alamatnya tidak ditemukan dan kartunya hilang dari
halaman masuk. Kalau produksi masih membolehkan password sebagai cadangan,
formulirnya pindah ke halaman masuk (tanpa akun contoh). Sebelum dipakai
sungguhan, ganti seluruh password — atau padamkan masuk dengan password sama
sekali begitu SSO berjalan (`SIMTLHP_MASUK_PASSWORD=false`): halaman masuk tinggal
tombol SSO.

## "Hari ini" pada demo

Data contoh disusun untuk **17 Agustus 2026**, sama dengan prototipe. Tanggalnya
dipatok lewat `.env`:

```
SIMTLHP_HARI_INI=2026-08-17
SIMTLHP_DATA_CONTOH=true
```

Tanpa itu, tenggat dan keterlambatan dihitung dari tanggal komputer — dan kedua
artefak yang diperagakan berdampingan akan menyebut keadaan berbeda untuk berkas
yang sama. Kosongkan `SIMTLHP_HARI_INI` untuk pemakaian sungguhan.

## Mengembalikan data contoh

Setelah dicoba-coba, datanya berubah. Untuk mengembalikan ke keadaan semula:
**klik dua kali `atur-ulang.bat`** (sama dengan `php artisan migrate:fresh --seed`).

Untuk mengosongkan berkasnya saja — data master dan akun tetap, seperti `?kosong`
di prototipe:

```bash
php artisan tlhp:kosongkan
```

---

## Alur yang bisa dicoba

Coba runtut supaya kelihatan seluruh jalurnya. Yang bergerak adalah **baris
penugasan** — satu satuan kerja pada satu bentuk tindak lanjut — bukan
rekomendasinya.

1. Masuk sebagai **Balai Wil. I Medan** → keranjang *Perlu saya kerjakan*.
   Buka satu berkas, isi uraiannya, lampirkan link bukti untuk tiap dokumen
   yang diminta, tambah baris pemulihan bila ada nilainya.
   **Simpan draf** menyimpan tanpa memindahkan berkas; **Kirim ke Setba** baru
   bisa dipakai kalau dokumennya sudah lengkap.
2. Masuk sebagai **Setba** → berkas tadi muncul di *Perlu saya kerjakan*.
   Teruskan ke UKI dengan surat pengantar (nomor, tanggal, perihal).
3. Masuk sebagai **UKI** → putuskan memadai atau belum.
   - Belum memadai: berkasnya pulang ke **meja pemberkasan ulang Setba**, bukan
     langsung ke satuan kerjanya. Setba yang menyetel dokumen tambahan dan
     mengirimnya ulang.
   - Memadai: berkasnya kembali ke Setba untuk diteruskan ke Inspektorat.
4. Masuk sebagai **Inspektorat** → verifikasi akhir. Memadai berarti tindak
   lanjut itu selesai diperiksa.
5. Kembali sebagai **Setba** → pada halaman rincian, buka tiket tindak lanjutnya
   lalu tekan **Kerjakan** di baris satuan kerja itu: catat tanggal unggahnya ke
   SIPTL, lalu — sesudah BPK memutus — salin hasil pemantauannya (SS atau BS).
   Putusan dan catatan BPK terbaca di kotak **Penilaian BPK lewat SIPTL** di
   rincian barisnya.
   - Tanggal unggah **dikunci** begitu tercatat, dan isiannya cuma muncul saat
     memang sedang tahap itu.
   - Putusan BPK dicatat **sekali** tiap unggahan, lalu terkunci.
   - BPK menyatakan Belum Sesuai? Tab Kerjakan baris itu berganti jadi
     pengiriman ulang ke satuan kerjanya, berikut catatan dan dokumen yang
     diminta. Sesudah diperbaiki, berkasnya diunggah ulang.
   - Seluruh kartu di halaman rincian tertutup sejak awal; tekan kepalanya
     untuk membaca selengkapnya.
6. Jalur **LHA** berhenti di langkah 4 — LHA tidak pernah sampai ke BPK.

Draf satuan kerja yang belum dikirim lebih dari tujuh hari dikirim sendiri oleh
`php artisan tlhp:kirim-draf` (terjadwal tiap hari 00.30), asal kewajibannya
sudah tuntas. Berkas tidak boleh membusuk di satu meja sementara tenggatnya
berjalan.

## Dua sumbu penilaian, satu sumbu posisi

| Sumbu | Isi | Milik siapa |
|---|---|---|
| **Status SIPTL** | BT · BS · SS · TD | BPK, disalin Setba dari SIPTL. Hanya LHP |
| **Hasil verifikasi** | Memadai / Belum memadai (LHA: Sesuai / Belum sesuai) | Inspektorat, lewat suratnya |
| **Posisi berkas** | di meja siapa berkasnya menunggu | perpindahan sehari-hari |

Ketiganya sengaja dipisah. Kenyataan "bagian BPSDM sudah beres tapi SIPTL masih
Belum Sesuai karena unit lain" hanya bisa dicatat kalau sumbunya lebih dari satu.

## Yang tidak disimpan, tapi dihitung

Status laporan, status temuan, nilai temuan, nilai terpulihkan, sisa, dan progres
tidak disimpan sebagai kolom. Semuanya disimpulkan dari barisnya setiap kali
halaman dibuka. Kalau disimpan, cepat atau lambat angkanya akan berbeda dengan
kenyataan.

---

## Profil & pengaturan akun

Tombol nama di kanan atas membuka menu akun: **Profil saya**, **Keamanan**,
**Pemberitahuan**, **Tampilan**, **Aktivitas saya**, Panduan singkat, dan
Keluar. Halamannya `/akun` (sejak 28 Sep):

- *Profil* — data pegawai dari eHRM (hanya dibaca), peran dan hak aksesnya;
- *Keamanan* — ganti password, perangkat yang sedang masuk (bisa
  dikeluarkan), riwayat masuk termasuk percobaan yang gagal;
- *Pemberitahuan* — pop-up pemberitahuan, dan pemberitahuan lewat email (bawaan menyala untuk
  penanggung jawab unit kerja, mati untuk petugas pusat);
- *Tampilan* — lebar menu samping, "Kurangi gerak", halaman pertama sesudah
  masuk;
- *Aktivitas saya* — log aktivitas milik akun itu sendiri.

Pengaturannya disimpan di `users.pengaturan` (JSON, `App\Support\Setelan`).
Daftar perangkat membaca tabel sesi, jadi hanya ada bila
`SESSION_DRIVER=database`.

## Susunan berkas

```
app/Enums/        aturan domain: posisi, status, hasil, sumber laporan, peran
app/Models/       Rekomendasi.php memuat sebagian besar hitungannya
app/Aksi/         satu berkas satu perbuatan: kirim, teruskan, putus, SIPTL
app/Support/      Terlihat (hak lihat), Jejak (pencatat), Aktivitas (log), Pemberitahuan, Tampil
app/Support/Sso/   masuk lewat SSO: kontrak penyedia, OIDC, simulasi, Otorisasi (NIP → akun)
app/Support/Direktori/  direktori pegawai: berkas contoh atau layanan eHRM
app/Http/         pengendali tiap layar
database/         migrasi, penyemai, dan data contoh (database/data/data-contoh.json)
resources/views/  tampilan Blade
public/css/       simtlhp.css disalin dari prototipe; simtlhp-tambahan.css milik Laravel
public/js/        satu berkas, penambah kenyamanan — bukan penopang
```

Gaya di `public/css/simtlhp.css` diambil dari prototipe lewat
`Prototipe/alat/salin-gaya.py`, awalan `.simt` dibuang. Jangan menyuntingnya
tangan: yang perlu diubah sendiri ditulis di `simtlhp-tambahan.css`.

## Menjalankan uji

```bash
php artisan test
```

Seluruh uji (lihat CATATAN-PERUBAHAN untuk jumlah terakhir): layar tiap peran,
hak akses satuan kerja, rantai penuh satu berkas, Catat laporan baru,
Pemberitahuan, Data master, Pengguna & hak akses, Log aktivitas dan DTI,
keamanan masuk (pembatas percobaan, sesi, CSP, link), Profil & pengaturan
akun, SSO (simulasi dan OIDC), direktori eHRM, Dashboard, data contoh, dan
aturan-aturan murni.

## Keamanan, SSO, dan pemasangan sungguhan

Ringkasan kesiapan untuk mentor — apa yang sudah dibangun, cara
menyambungkan SSO dan direktori eHRM, dan daftar yang perlu diminta ke
Pusdatin — ada di **`DOKUMEN-PERSIAPAN-MENTOR-SSO-EHRM.md`**. Semua pengaturan
baru ada di `.env.example` (bagian "MASUK, SSO eHRM, …") dan
`config/simtlhp.php`.

Demo SSO tanpa server Pusdatin: `SIMTLHP_SSO=simulasi` di `.env`, lalu
tombol **Masuk dengan SSO** di halaman masuk.

## Pindah ke MySQL

Ubah `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simtlhp
DB_USERNAME=root
DB_PASSWORD=
```

Buat basis datanya lebih dulu, lalu `php artisan migrate --seed`.
Seluruh migrasi memakai tipe yang aman di MySQL 8 — nilai rupiah disimpan sebagai
`bigInteger` dalam satuan rupiah penuh, bukan `float`.
