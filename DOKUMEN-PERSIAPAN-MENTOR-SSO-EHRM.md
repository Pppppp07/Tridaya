# Kesiapan SSO eHRM, Data Master, dan Keamanan — SIMTLHP

**Sistem Informasi Monitoring Tindak Lanjut Hasil Pemeriksaan (SIMTLHP)**
*Sekretariat Badan Pengembangan Sumber Daya Manusia (BPSDM)*

Diperbarui 29 September 2026 (27 Sep: dibangun; 29 Sep: penyedia SSO dipastikan
SSO PU `sso.pu.go.id`). Dokumen ini menggantikan versi rencana sebelumnya:
yang tertulis di bawah **sudah dibangun dan diuji**, kecuali yang jelas disebut
"perlu dari Pusdatin" atau "perlu diputuskan".

---

## 1. Ringkasan

| Bagian | Keadaan |
|---|---|
| Pintu masuk SSO PU (OpenID Connect, dengan state + PKCE) | **Siap** — `sso.pu.go.id` sudah dipastikan OpenID Connect dan alamatnya sudah terisi; tinggal client id + secret dari Pusdatin |
| Mode simulasi SSO untuk demo tanpa server Pusdatin | **Siap** — tidak pernah menyala di produksi |
| Hak akses menurut NIP (siapa boleh masuk, sebagai apa) | **Siap** — Data master → Pengguna dan Unit kerja |
| Direktori pegawai (cari penanggung jawab/petugas dari eHRM) | **Siap** — adaptor HTTP eHRM; tinggal dipetakan ke API Pusdatin |
| Satu penanggung jawab per unit kerja | **Siap** — dijaga aplikasi dan basis data |
| Log aktivitas + akun DTI (hanya melihat) | **Siap** |
| Keamanan masuk, sesi, cache, cookie, link berkas | **Siap** |
| Optimasi beban server | **Siap** — lihat angka di bagian 8 |

Prinsip utamanya: **SSO hanya membuktikan siapa orangnya; aplikasi ini yang
memutus boleh tidaknya ia masuk dan sebagai apa.** Semua pegawai Kementerian
punya akun eHRM, tapi yang diterima hanya NIP yang didaftarkan Setba.

---

## 2. Alur masuk

```
Pengguna ─► Masuk dengan SSO PU ─► halaman masuk SSO PU (sso.pu.go.id, akun eHRM)
                                        │ membuktikan: "ini pegawai X, NIP Y"
                                        ▼
              /masuk/sso/kembali ─► periksa state + tukar kode (PKCE, lewat server)
                                        │ ambil identitas (userinfo): NIP, nama, email, jabatan
                                        ▼
                         Otorisasi (aplikasi ini), kuncinya NIP
          ┌──────────────────────────┼───────────────────────────────┐
          ▼                          ▼                               ▼
 NIP = akun pusat aktif     NIP = penanggung jawab aktif     NIP tidak terdaftar /
 (Setba, UKI, Inspektorat,  sebuah unit kerja               akunnya dinonaktifkan
  Pimpinan, DTI, Admin)     → masuk sebagai unit itu          → halaman "Tidak memiliki akses"
 → masuk sesuai perannya                                       (tercatat di log)
```

Sesudah masuk: nama, jabatan, dan email akun **disamakan dengan eHRM** (eHRM
sumber kebenarannya — "jangan tertukar atributnya"), dan perubahannya tercatat.

Berkas utama: `app/Http/Controllers/SsoController.php`,
`app/Support/Sso/` (`PenyediaSso`, `SsoOidc`, `SsoSimulasi`, `Otorisasi`,
`Identitas`, `Penyedia`).

---

## 3. Yang perlu diminta ke Pusdatin

Penyedia SSO-nya **SSO PU** (`https://sso.pu.go.id`, login dengan akun eHRM).
Dicek 29 Sep dari dokumen penemuan publiknya
(`https://sso.pu.go.id/.well-known/openid-configuration`): **OpenID Connect**
standar — authorization code + PKCE S256, `client_secret_post`/`basic`, scope
yang diumumkan hanya `openid` dan `offline_access`, id_token RS256 (JWKS di
`/.well-known/jwks`), dan klaim yang diumumkan hanya klaim standar (`sub`,
`iss`, `aud`, `iat`, `exp`). Alamatnya sudah terisi di `.env.example`
(bagian 4.1). Yang perlu diminta:

1. **Pendaftaran aplikasi (client)** SIMTLHP/Tridaya: **client id** dan
   **client secret**, dengan grant authorization code + PKCE.
2. **Redirect URI yang didaftarkan**: `https://<domain-simtlhp>/masuk/sso/kembali`
   (untuk uji lokal: `http://localhost:8000/masuk/sso/kembali`), dan
   **post-logout redirect URI**: `https://<domain-simtlhp>/masuk`.
3. **Klaim yang membawa NIP** — `sub`, atau klaim khusus (mis. `nip`) di
   userinfo/id_token. Hanya NIP yang wajib; nama, email, jabatan, dan unit
   bila ada (dan scope tambahan yang perlu diminta untuk itu).
4. **API data pegawai eHRM** untuk Data master → Pengguna dan Unit kerja
   (nama, NIP, jabatan, email diambil dari eHRM, tidak diketik). Dari dokumen
   Dwaradaya (SuperApp BPSDM): `POST https://apigw.pu.go.id/user/login` untuk
   token, lalu `GET https://apigw.pu.go.id/v1/ehrm/data-peg?nip={NIP}`
   (email, nama, id_satminkal). Perlu: akun/kredensial API GW, dan apakah ada
   jalur **cari pegawai menurut nama** dan **pegawai per unit**.
5. **Arti `id_satminkal`**, supaya bisa dipasangkan dengan unit kerja di Data
   master (bagian 9 no. 1).
6. Apakah server SIMTLHP nanti bisa menjangkau `sso.pu.go.id` dan
   `apigw.pu.go.id` dari jaringannya.

---

## 4. Cara menyambungkan

### 4.1 SSO (OpenID Connect)

Isi `.env` (semua kuncinya sudah ada di `.env.example`):

```
SIMTLHP_SSO=oidc
SIMTLHP_SSO_NAMA="SSO PU"
SIMTLHP_OIDC_AUTHORIZE=https://sso.pu.go.id/connect/authorize
SIMTLHP_OIDC_TOKEN=https://sso.pu.go.id/connect/token
SIMTLHP_OIDC_USERINFO=https://sso.pu.go.id/connect/userinfo
SIMTLHP_OIDC_LOGOUT=https://sso.pu.go.id/connect/logout
SIMTLHP_OIDC_CLIENT_ID=…            # dari Pusdatin
SIMTLHP_OIDC_CLIENT_SECRET=…        # dari Pusdatin
SIMTLHP_OIDC_SCOPE=openid
SIMTLHP_OIDC_KLAIM_NIP=nip          # nama klaim NIP (mis. sub); boleh bertitik, mis. pegawai.nip
```

Penjaga yang sudah terpasang: `state` acak sekali pakai (link kembali palsu
ditolak), PKCE S256 (kode yang tercegat tidak bisa ditukar orang lain), kode
ditukar lewat jalur belakang server-ke-server dengan batas waktu, `nonce`
dan `aud` id_token diperiksa bila dikirim, perjalanan masuk kedaluwarsa dalam
10 menit, keluar ikut keluar di SSO bila alamat logout diisi.

### 4.2 Akun yang sudah ada

Sebelum SSO dinyalakan, setiap akun perlu NIP:

```bash
php artisan simtlhp:hubungkan-pegawai              # hubungkan akun lama ke eHRM menurut email
php artisan simtlhp:pengguna <NIP> admin         # akun Admin/Setba pertama di server baru
```

Sesudah SSO teruji, padamkan masuk dengan password:
`SIMTLHP_MASUK_PASSWORD=false` — satu-satunya pintu jadi SSO.

### 4.3 Kalau penyedia SSO-nya bukan OIDC

Tulis satu kelas yang memenuhi `App\Support\Sso\PenyediaSso` (tiga metode:
`arahkan`, `terima`, `alamatKeluar`), daftarkan di `config/simtlhp.php`
(`sso.kelas`), lalu `SIMTLHP_SSO=<nama>`. Pengendali, aturan hak akses, log,
dan halaman penolakan tidak berubah.

**Dwaradaya (SuperApp BPSDM).** Bila SIMTLHP juga dibuka dari portal
SuperApp, jalurnya lain: SuperApp mengirim JWT bertanda tangan secret bersama
(`user` = NIP, `email`, `exp`) lewat `POST /api/sso-check` dan
`GET /sso-login?token=…` (dokumen "Dokumentasi Integrasi SSO Dwaradaya").
Belum dibangun — menunggu kepastian apakah dipakai di samping SSO PU.

### 4.4 Direktori pegawai eHRM

```
SIMTLHP_DIREKTORI=http
SIMTLHP_EHRM_URL=https://…/api
SIMTLHP_EHRM_TOKEN=…
SIMTLHP_EHRM_JALUR_CARI="/pegawai?q={q}"
SIMTLHP_EHRM_JALUR_NIP="/pegawai/{nip}"
SIMTLHP_EHRM_JALUR_UNIT="/unit/{unit}/pegawai"
SIMTLHP_EHRM_WADAH=data              # letak daftar di jawaban JSON; kosong = jawabannya larik
SIMTLHP_EHRM_MEDAN_NIP=nip           # nama medan jawaban eHRM → atribut aplikasi
…
```

Jawaban disimpan di cache (bawaan 30 menit) dan tiap pertanyaan dibatasi
waktunya (8 detik), jadi eHRM tidak dibebani dan halaman tidak menggantung.
Kalau bentuk API-nya jauh berbeda: tulis kelas yang memenuhi
`App\Support\Direktori\SumberPegawai`.

---

## 5. Data master & hak akses (yang dibangun sesuai arahan)

| Arahan Bang Kamal | Yang dibangun |
|---|---|
| "Master unit kerja" dikelola Setba | Tab **Unit kerja**: tambah, ubah nama (selama belum dipakai berkas), aktif/nonaktif |
| "Penanggung jawab ini ketik dari IRM akunnya … data sama emailnya otomatis … jangan tertukar atributnya" | Penanggung jawab dipilih dari direktori; nama, NIP, jabatan, email diambil satu paket |
| "Cukup satu orang aja yang nginput" | Satu unit satu penanggung jawab; menggantinya menonaktifkan yang lama dan **memutus sesinya saat itu juga** |
| "Notif email lah!" | Pemberitahuan ke penanggung jawab lewat aplikasi dan email (dikirim sesudah halaman terjawab) |
| Kategori temuan & internal "bentuk tabel", "bisa di-on-off" | Tab Kategori temuan, Kategori internal, Sifat rekomendasi — tambah/ubah/nonaktifkan, tidak ada yang dihapus |
| "Ini biru, biru sama merah apa artinya?" | Warna kategori dibuang; merah/hijau hanya untuk keadaan |
| "Gua tuh menghindari banyak scroll" / jendela melayang | Data master bertab; tambah/ubah lewat jendela melayang |
| "setiap aktivitas terekam … DTI … kita tinggal nembak siapa pelakunya" | **Log aktivitas** + akun **DTI** yang hanya melihat |
| DTI: "Log aktivitas, sebatas itu. Terus data master enggak … rekomendasi … hanya nge-view" | DTI: menu Rekomendasi, Daftar laporan, Log aktivitas; semua kiriman ditolak di pintu |
| "Log lu mati, ada apa?" | Tab **Keaktifan akun**: kapan tiap akun terakhir masuk & berbuat; yang diam > 14 hari ditandai |

Tambahan untuk hak akses (tab **Pengguna**): petugas pusat didaftarkan dari
direktori menurut NIP; peran bisa diubah; akun dinonaktifkan/diaktifkan lagi.
Penjaganya:

- tidak bisa mengubah akun sendiri;
- paling tidak satu Setba dan satu Admin tetap aktif;
- akun **DTI dan Admin hanya diatur Admin** — yang diawasi tidak mengatur pengawasnya;
- satu NIP satu akun (indeks unik basis data);
- petugas pusat tidak bisa sekaligus jadi penanggung jawab unit kerja;
- setiap perubahan tercatat dengan **sebelum → sesudah** (tab Riwayat perubahan).

| Peran | Rekomendasi | Kerjakan berkas | Dashboard | Data master | Log aktivitas |
|---|---|---|---|---|---|
| Setba | semua | ya | ya | ya | — |
| Satuan kerja (penanggung jawab) | miliknya saja | miliknya | — | — | — |
| UKI, Inspektorat | semua | di mejanya | — | — | — |
| Pimpinan | semua (lihat) | — | ya | — | — |
| DTI | semua (lihat) | — | — | — | ya (lihat) |
| Admin | semua | seperti Setba | ya | ya + akun DTI/Admin | ya |

---

## 6. Log aktivitas

- Tabel `log_aktivitas`, **hanya bertambah** — tidak ada jalur ubah atau hapus
  di aplikasi. Isinya: waktu, akun, nama/peran/unit **saat itu**, kalimat
  aktivitas, objek (rekomendasi/laporan/akun), sebelum → sesudah, alamat IP,
  perangkat. Password dan token tidak pernah dicatat.
- Tiga jalan masuk supaya tidak ada yang lolos: setiap gerak berkas
  (`Jejak::riwayat`), setiap perubahan data master/hak akses/masuk/keluar
  (dicatat langsung, lengkap sebelum → sesudah), dan jaring terakhir di
  middleware untuk kiriman lain serta **setiap penolakan akses (403)**.
- Percobaan masuk yang gagal, dikunci, dan SSO yang ditolak ikut tercatat.
- Bisa difilter (waktu, kelompok, peran, kata), dibuka rinciannya, dan
  **diunduh CSV**.
- Dipangkas otomatis sesudah 5 tahun (`SIMTLHP_LOG_SIMPAN_HARI`), lewat jadwal
  `model:prune` tiap malam.

---

## 7. Keamanan

| Bagian | Yang dipasang |
|---|---|
| Masuk | Batas 5 kali salah per email+alamat dalam 60 detik lalu dikunci sementara, plus 30 kiriman/menit per alamat; pesan yang sama untuk email tak dikenal dan password salah; sesi diperbarui sesudah masuk |
| Sesi & cookie | Tidak ada lagi cookie "ingat saya" 5 tahun yang dipaksakan; sesi habis sendiri bila lama tidak dipakai; cookie HttpOnly + SameSite=Lax, `SESSION_SECURE_COOKIE=true` di HTTPS; akun yang dinonaktifkan/diubah perannya diputus dari semua perangkat saat itu juga |
| Cache | Halaman berlogin dikirim `Cache-Control: no-store` — tombol Kembali sesudah keluar tidak menampilkan isi berkas di komputer bersama |
| Kepala keamanan | Content-Security-Policy dengan nonce (skrip sebaris asing tidak jalan), X-Frame-Options DENY (anti clickjacking), nosniff, Referrer-Policy, Permissions-Policy, COOP, HSTS bila HTTPS |
| Link berkas | Hanya `http://`/`https://` yang diterima dan dijadikan link — `javascript:` ditolak di server dan di tampilan |
| Akses | Akun hanya-melihat (DTI, Pimpinan) ditolak di pintu untuk semua kiriman, bukan cuma tombolnya disembunyikan |
| Akun contoh | Halaman masuk (`/masuk`) bersih, tempat SSO dipasang. Akun contoh, email, dan password selama pengembangan ada di Login developer (`/masuk/pengembang`), yang padam sendiri di produksi (tidak ditemukan) |
| Akun sendiri (28 Sep) | Profil & pengaturan: data pegawai hanya dibaca (pemiliknya eHRM); ganti password — paling sedikit 10 karakter berisi huruf dan angka, dibatasi 6 kiriman/menit, perangkat lain langsung dikeluarkan dan id sesi diganti; daftar perangkat yang sedang masuk + "Keluarkan" (id sesi tidak pernah tampil di halaman — yang dikirim HMAC-nya dengan APP_KEY); riwayat masuk termasuk percobaan gagal dengan email-nya; semua perubahan tercatat di log (kelompok Pengaturan akun) |
| Proksi | `SIMTLHP_PROXY_TEPERCAYA` supaya IP asli pengguna yang tercatat di belakang reverse proxy |

---

## 8. Beban server dan kelancaran di perangkat pengguna

Diukur di data contoh (SQLite, 49 rekomendasi), sebelum → sesudah:

| Halaman | Kueri | Waktu |
|---|---|---|
| Data master | 125 → 23 | 157 → 81 ms |
| Daftar laporan | 92 → 21 | 215 → 127 ms |
| Rekomendasi (Setba) | 22 → 18 | 301 → 227 ms |
| Dashboard | 25 → 21 | 372 → 252 ms |

Yang dikerjakan: kueri "N+1" dihapus (induk temuan/laporan dihubungkan, baris
penugasan dimuat sekaligus); angka menu tidak dihitung ulang di setiap
halaman — disimpan di cache dan basi sendiri begitu ada data yang berubah;
email dikirim sesudah halaman terjawab (server email yang lambat tidak
menahan pengguna); aset CSS/JS/fonta disimpan browser setahun dan dikompres
(`public/.htaccess`); pembaruan lonceng hanya berjalan saat tab terlihat.

Saat pemasangan produksi (sekali):

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize          # cache config, rute, tampilan
```

Plus: OPcache menyala, `APP_DEBUG=false`, cron `* * * * * php artisan schedule:run`
(penyapu draf 00.30, pemangkasan log 01.00), HTTPS.

---

## 9. Perlu diputuskan / dikonfirmasi

1. **Kode unit eHRM ↔ unit kerja SIMTLHP.** Saat ini unit kerja dicocokkan
   dengan kode di tabel `satkers`. Bila kode unit di eHRM berbeda, perlu
   pemetaan (kolom tambahan atau tabel padanan).
2. **Verifikasi tanda tangan id_token (JWKS).** Sekarang identitas dibaca dari
   userinfo lewat TLS dengan access token — sah menurut OpenID Connect Core
   3.1.3.7. SSO PU menandatangani id_token dengan RS256 dan membuka JWKS di
   `https://sso.pu.go.id/.well-known/jwks`; bila diwajibkan, pemeriksaannya
   ditambahkan di `SsoOidc::klaimIdToken()`.
3. **Siapa yang boleh memberi peran UKI/Inspektorat/Pimpinan** — sekarang Setba
   dan Admin. DTI dan Admin hanya Admin.
4. **Masa simpan log** — bawaan 5 tahun.
5. **Nama resmi akun DTI** — sekarang "DTI — Data dan Teknologi Informasi".
6. **Lama sesi** — bawaan 120 menit tanpa aktivitas (`SESSION_LIFETIME`).

---

## 10. Bukti uji

Uji otomatis Laravel mencakup: masuk & pembatas percobaan, tanpa cookie
ingat-saya, kepala keamanan dan nonce, link berbahaya, log aktivitas dan
pemangkasannya, DTI hanya melihat, pengguna & peran beserta semua penjaganya,
penggantian penanggung jawab, SSO simulasi, SSO OIDC (state, PKCE, penyamaan
atribut, state palsu, identitas tanpa NIP), direktori eHRM lewat HTTP (dipalsukan),
dan perintah server. Jumlah dan hasil terakhirnya tercatat di
`../CATATAN-PERUBAHAN.md`.
