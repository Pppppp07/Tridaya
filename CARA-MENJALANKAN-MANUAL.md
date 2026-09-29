# Menjalankan sendiri, tanpa `jalankan.bat`

Panduan ini untuk memahami apa yang sebenarnya terjadi, bukan sekadar menyalin
perintah. `jalankan.bat` hanya membungkus langkah-langkah di bawah ini.

---

## 1. Apa artinya "menjalankan" aplikasi Laravel

Aplikasi web bukan berkas yang bisa diklik dua kali seperti Word. Yang terjadi
sebenarnya begini:

```
Anda        →  browser  →  http://127.0.0.1:8000  →  program PHP  →  jawaban HTML
(Chrome/Edge)                (alamat komputer sendiri)   (yang Anda jalankan)
```

Jadi harus ada **program yang menunggu** di alamat itu. Program itulah yang
dinyalakan dengan `php artisan serve`. Selama programnya hidup, alamat itu bisa
dibuka. Begitu programnya dimatikan, alamatnya mati juga.

`127.0.0.1` artinya "komputer ini sendiri". Tidak ada yang keluar ke internet —
browser Anda bicara dengan program di komputer Anda sendiri.

Tiga hal harus benar sebelum bisa jalan:

| Syarat | Kenapa |
|---|---|
| PHP bisa dipanggil | `artisan` itu berkas PHP, harus ada yang menjalankannya |
| Terminal ada di folder proyek | `artisan` dicari di folder tempat Anda berdiri |
| Port 8000 belum dipakai | dua program tidak bisa menunggu di pintu yang sama |

---

## 2. Langkah menjalankan

### Langkah 1 — buka terminal tepat di folder proyek

Ini triknya, supaya tidak perlu mengetik `cd` dengan jalur panjang:

1. Buka **File Explorer**, masuk ke folder `simtlhp`
   (yang isinya ada `artisan`, `app`, `public`, dan lain-lain).
2. Klik **bilah alamat** di atas — yang menunjukkan jalur foldernya.
3. Hapus isinya, ketik **`powershell`**, lalu tekan **Enter**.

Jendela biru PowerShell terbuka, dan sudah berdiri tepat di folder itu.
Perhatikan barisnya, harus berakhir dengan `\simtlhp>`.

Kalau ingin memastikan, ketik:

```bash
Get-Location
```

### Langkah 2 — kenalkan PHP ke terminal

Di komputer ini PHP belum terdaftar di PATH, jadi mengetik `php` saja belum
dikenali. Beri tahu dulu letaknya:

```bash
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;" + $env:Path
```

Uji apakah sudah dikenali:

```bash
php -v
```

Kalau muncul `PHP 8.3.30 (cli)`, berarti berhasil.

> **Penting:** perintah ini hanya berlaku untuk jendela terminal itu saja.
> Kalau jendelanya ditutup lalu dibuka lagi, harus diketik ulang.
> Cara membuatnya permanen ada di bagian 5.

### Langkah 3 — nyalakan servernya

```bash
php artisan serve
```

Akan muncul kira-kira begini, lalu **berhenti di situ**:

```
INFO  Server running on [http://127.0.0.1:8000].

Press Ctrl+C to stop the server
```

Ini **bukan macet**. Programnya memang sedang menunggu. Selama tulisan itu ada,
aplikasinya hidup. Jendela ini harus dibiarkan terbuka.

### Langkah 4 — buka di browser

Buka Chrome atau Edge, ketik:

```
http://127.0.0.1:8000
```

Halaman masuk akan muncul. Pakai akun contoh — semuanya memakai password `rahasia123`.

Coba lihat kembali jendela PowerShell tadi: tiap kali Anda mengeklik sesuatu di
browser, muncul baris baru di situ. Itulah catatan setiap permintaan yang masuk.
Jendela ini nanti berguna sekali untuk mencari tahu kalau ada yang salah.

### Langkah 5 — menghentikan

Klik jendela PowerShell-nya, lalu tekan **`Ctrl` + `C`**.

Servernya mati, alamat `127.0.0.1:8000` tidak bisa dibuka lagi. Datanya tetap
aman — tersimpan di `database/database.sqlite`, bukan di memori.

---

## 3. Perintah lain yang perlu diketahui

Semua perintah ini dijalankan di folder `simtlhp`, dan **servernya harus
dimatikan dulu** (Ctrl+C) karena satu terminal hanya bisa mengerjakan satu hal.

### Melihat daftar alamat yang tersedia

```bash
php artisan route:list --except-vendor
```

Ini menampilkan seluruh halaman yang ada di aplikasi beserta controller yang
menanganinya. Berguna untuk memahami susunan aplikasi.

### Mengembalikan data contoh ke keadaan semula

```bash
php artisan migrate:fresh --seed
```

Artinya: hapus semua tabel, buat ulang dari awal, lalu isi lagi dengan data
contoh. Dipakai setelah dicoba-coba sampai datanya berantakan.

Kalau ditanya konfirmasi, jawab `yes`.

### Membuat tabel tanpa menghapus isinya

```bash
php artisan migrate
```

Ini hanya menjalankan migrasi yang belum pernah jalan. Dipakai kalau nanti ada
tabel baru ditambahkan, dan data lama ingin dipertahankan.

### Melihat migrasi mana yang sudah jalan

```bash
php artisan migrate:status
```

### Membersihkan cache tampilan

```bash
php artisan view:clear
```

Dipakai kalau berkas Blade sudah diubah tapi halamannya masih menampilkan yang
lama.

### Mencoba kode secara langsung

```bash
php artisan tinker
```

Terbuka semacam kalkulator PHP yang sudah mengenal seluruh model aplikasi ini.
Contoh yang bisa dicoba di dalamnya:

```
App\Models\Rekomendasi::count()
App\Models\Rekomendasi::find(1)->sisaPemulihan()
App\Models\Laporan::find(1)->statusSimpulan()
```

Keluar dengan mengetik `exit`.

Ini cara paling cepat membuktikan bahwa status laporan memang **dihitung**, bukan
disimpan — nilainya keluar dari perhitungan, bukan dari kolom tabel.

### Memakai port lain

Kalau port 8000 sudah dipakai program lain:

```bash
php artisan serve --port=8001
```

Alamatnya berubah jadi `http://127.0.0.1:8001`.

---

## 4. Kalau muncul pesan error

### `php : The term 'php' is not recognized...`

Langkah 2 belum dijalankan, atau jendela terminalnya sudah ditutup dan dibuka
lagi. Ulangi langkah 2.

### `Could not open input file: artisan`

Terminalnya tidak berdiri di folder `simtlhp`. Cek dengan `Get-Location`.
Perbaiki dengan:

```bash
Set-Location "C:\jalur\ke\folder\simtlhp"
```

### `Failed to listen on 127.0.0.1:8000`

Port 8000 masih dipakai — biasanya karena server sebelumnya belum benar-benar
mati. Cek siapa yang memakainya:

```bash
Get-NetTCPConnection -LocalPort 8000 -State Listen
```

Kolom `OwningProcess` adalah nomor programnya. Matikan dengan:

```bash
Stop-Process -Id NOMOR -Force
```

Atau lebih gampang: jalankan saja di port lain dengan `--port=8001`.

### Halaman putih atau tulisan error merah di browser

Baca pesannya, biasanya sudah menyebut berkas dan nomor barisnya. Kalau kurang
jelas, lihat juga jendela PowerShell — catatannya lebih lengkap di situ.

### `SQLSTATE[HY000] [14] unable to open database file`

Berkas `database/database.sqlite` hilang. Buat ulang:

```bash
php artisan migrate:fresh --seed
```

Kalau berkasnya benar-benar tidak ada, buat dulu berkas kosong bernama
`database.sqlite` di dalam folder `database`, baru jalankan perintah di atas.

---

## 5. Supaya tidak perlu mengetik PATH tiap kali

Kalau sudah terbiasa dan ingin `php` langsung dikenali di jendela mana pun:

1. Tekan tombol **Windows**, ketik **`environment variables`**, pilih
   *Edit the system environment variables*.
2. Klik tombol **Environment Variables...**
3. Di kotak atas (*User variables*), pilih baris **`Path`**, klik **Edit...**
4. Klik **New**, tempelkan:
   ```
   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64
   ```
5. **OK** tiga kali.
6. **Tutup semua jendela PowerShell**, buka yang baru — perubahan PATH hanya
   terbaca oleh jendela yang dibuka setelahnya.

Setelah itu langkah 2 tidak diperlukan lagi. Cukup buka terminal di folder
proyek, lalu langsung `php artisan serve`.

---

## 6. Ringkasan — yang perlu diingat

```bash
php artisan serve
```

Itu saja perintah utamanya. Sisanya adalah menyiapkan keadaan supaya perintah itu
bisa jalan: berdiri di folder yang benar, dan PHP sudah dikenali.

Urutan lengkap dari nol:

```bash
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;" + $env:Path
```

```bash
php artisan serve
```

Lalu buka `http://127.0.0.1:8000`, dan tekan `Ctrl+C` kalau sudah selesai.
