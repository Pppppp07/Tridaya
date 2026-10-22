# Integrasi SSO Dwaradaya — Rekala

Acuan: **Dokumentasi Integrasi SSO Dwaradaya (SuperApp BPSDM).docx** yang diberikan pengelola proyek.
Protokolnya JWT langsung dari SuperApp, terpisah dari adapter OpenID Connect yang juga tersedia di aplikasi.

## Alur penggunaan

1. Admin DTI mendaftarkan user serta menentukan peran dan cakupan Satker di Data Master.
2. User login di Dwaradaya. Rekala tidak menerima atau memeriksa password Dwaradaya.
3. Server SuperApp mengecek akses user melalui `POST /api/sso-check` dengan JWT.
4. User memilih kartu Rekala; browser membuka `GET /sso-login?token=JWT`.
5. Rekala memeriksa tanda tangan, algoritma, waktu token, identitas, serta izin aktif di database lokal.
6. Rekala membuat sesi baru dan mengalihkan ke URL bersih. Halaman awal mengikuti peran/pengaturan user.

Jika browser masih membuka akun Rekala lain, user mengonfirmasi pergantian akun terlebih dahulu.
Token yang tidak valid tidak mengakhiri sesi akun yang masih sah. Sebelum mengganti sesi,
Rekala memeriksa izin akun tujuan kembali. Formulir konfirmasi dilindungi CSRF.

## Kontrak endpoint

| Keperluan | Metode dan alamat relatif | Kredensial | Hasil |
|---|---|---|---|
| Cek apakah user boleh membuka aplikasi | `POST /api/sso-check` | `Authorization: Bearer JWT`; query `?token=JWT` tersedia sebagai fallback | HTTP 200, persis `{"success":true,"exists":true}` |
| Masuk dari kartu SuperApp | `GET /sso-login?token=JWT` | Query JWT; header Bearer juga diterima | Pengalihan ke `/` setelah sesi dibuat |

Jika kedua sumber token ada, nilainya harus sama. JSON body bukan sumber token.
Pengecekan API tidak membuat sesi, mengubah user, atau mengonsumsi token sekali pakai.
Semua kegagalan API berupa satu properti `error`, tanpa wrapper, data user, atau pengalihan HTML:

```json
{"error":"User tidak memiliki akses aktif ke Rekala."}
```

Token hilang/tidak sah, kedaluwarsa, atau user tidak berizin menghasilkan HTTP 401.
Adapter nonaktif: 404; metode salah: 405; batas laju terlampaui: 429; gangguan internal: 500.
Kegagalan login browser dialihkan ke halaman penjelasan tanpa JWT pada URL.

### Identitas dalam JWT

| Klaim | Aturan |
|---|---|
| `user` | NIP dalam **string** 18 digit; spasi dirapikan. Angka JSON tidak diterima karena NIP adalah identitas, bukan bilangan. |
| `email` | Opsional. Untuk akun non-NIP, dapat dipakai bila `user` kosong/tidak dikirim. Jika `user` berisi email, nilainya harus cocok dengan `email` bila keduanya tersedia. |
| `exp` | Wajib integer Unix timestamp yang belum lewat. Diperiksa dengan waktu nyata, terlepas dari tanggal demo. |
| `iat`, `nbf` | Opsional; bila dikirim harus integer dan tidak di masa depan. |
| `iss`, `aud` | Opsional dalam dokumen. Diperiksa terhadap nilai konfigurasi jika disepakati dengan SuperApp. |

NIP yang valid tetapi tidak terdaftar **tidak** dialihkan pencariannya ke email akun lain.
Email dicocokkan tanpa membedakan huruf besar/kecil dan harus menunjuk tepat satu user aktif.
Peran, peran tambahan, status aktif, Satker, serta cakupan Pimpinan selalu berasal dari Data Master.
Klaim seperti `role`, `satker_id`, atau `permissions` dalam token tidak memberi hak akses.
Nama dan jabatan tidak dibuat dari asumsi. Bila sinkronisasi atribut diaktifkan, email terverifikasi
dapat disamakan sepanjang tidak dipakai akun lain.

## Konfigurasi pengelola server

Dokumen acuan menjelaskan mekanisme dan bentuk permintaan. Nilai operasional berikut perlu
diterima/disepakati bersama tim Dwaradaya, melalui kanal pengelola yang aman:

| Nilai | Penggunaan |
|---|---|
| Shared secret asli | `SUPERAPP_SECRET`; sama di kedua pihak, idealnya khusus aplikasi Rekala. Jangan dikirim melalui chat, disimpan di Git, atau disertakan screenshot. |
| Algoritma JWT | `SIMTLHP_DWARADAYA_ALG`. Implementasi mendukung HS256, HS384, HS512 dengan minimum secret 32, 48, 64 byte. **HS256 adalah pilihan awal, bukan nilai yang dinyatakan dokumen.** Jika algoritma resmi berbeda, adapter perlu disesuaikan terlebih dahulu. |
| URL portal Dwaradaya | `SIMTLHP_DWARADAYA_URL`; alamat HTTPS resmi untuk tombol login/masuk ulang. Klik kartu dari SuperApp tetap dapat diproses tanpa nilai portal ini. |
| Domain Rekala | `APP_URL`; menjadi dasar dua endpoint yang didaftarkan pada kartu aplikasi SuperApp. |

Contoh nama pengaturan, tanpa kredensial asli:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rekala.domain-instansi.go.id
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DRIVER=database
SIMTLHP_HARI_INI=
SIMTLHP_DATA_CONTOH=false
SIMTLHP_AKUN_DEMO=false
SIMTLHP_PASSWORD_DEMO=
SIMTLHP_SSO=dwaradaya
SIMTLHP_SSO_NAMA="Dwaradaya"
SUPERAPP_SECRET=
SIMTLHP_DWARADAYA_ALG=HS256
SIMTLHP_DWARADAYA_URL=
SIMTLHP_DWARADAYA_LOGOUT=
SIMTLHP_DWARADAYA_ISSUER=
SIMTLHP_DWARADAYA_AUDIENCE=
SIMTLHP_DWARADAYA_SEKALI_PAKAI=false
SIMTLHP_DWARADAYA_CEK_PER_MENIT=600
```

Alamat dalam contoh adalah placeholder. Isi secret dan portal dengan nilai resmi sebelum aktivasi.
Setelah mengubah konfigurasi server, jalankan `php artisan config:cache`.
Daftarkan URL lengkap `https://<domain-rekala>/api/sso-check` dan
`https://<domain-rekala>/sso-login` di Dwaradaya sesuai kolom konfigurasi SuperApp.
Pengiriman JWT pada klik kartu dilakukan oleh Dwaradaya, bukan token statis di URL kartu.
Sesudah uji login bersama berhasil, tetapkan `SIMTLHP_MASUK_PASSWORD=false` agar masuk melalui SSO saja.
Jangan mematikan jalur akses yang sedang dipakai sebelum konfigurasi SSO teruji.

Di produksi, kedua endpoint menolak HTTP. Jika TLS diakhiri reverse proxy, isi
`SIMTLHP_PROXY_TEPERCAYA` dengan IP proxy yang dikelola instansi agar HTTPS/IP asli dikenali.
Jangan menggunakan wildcard proxy tanpa pembatasan jaringan yang sesuai.

## Keamanan dan masa berlaku

- Algoritma dipatok di server; token tidak boleh memilih algoritma atau URL kuncinya sendiri.
- Respons SSO memakai `no-store` dan `no-referrer`. URL yang mengandung JWT tidak disimpan
  sebagai alamat kembali di sesi Laravel. JWT mentah tidak dicatat ke audit dan tidak disimpan sebagai atribut user.
- Sesi lama diganti setelah verifikasi berhasil; filter dan peran aktif dari akun lama dibuang.
- Verifikasi JWT dilakukan lokal, tanpa panggilan jaringan per login atau sinkronisasi seluruh direktori pegawai.
- Batas cek API 600 per IP per menit; batas login browser mengikuti `SIMTLHP_MASUK_PER_MENIT_IP`
  (bawaan 120). Sesuaikan dengan jumlah user karena permintaan API dapat berasal dari satu IP SuperApp.
- Dokumen tidak menjelaskan nonce/state, jti, atau apakah JWT baru diterbitkan per klik.
  Mode bawaan menerima token yang masih valid sesuai kontrak tersebut. Ini bukan protokol OIDC
  dengan pengikatan state browser. Token merupakan kredensial bearer: siapa pun yang memperolehnya
  dapat memakainya selama berlaku, kecuali mode sekali pakai sudah aktif dan token telah dikonsumsi.

Sepakati masa berlaku singkat dengan tim SuperApp (misalnya 1–5 menit), secret khusus aplikasi,
dan `iss`/`aud` bila tersedia. Jangan mengasumsikan klaim tersebut ada sebelum dikonfirmasi.
Jika setiap klik aplikasi menghasilkan JWT baru, aktifkan `SIMTLHP_DWARADAYA_SEKALI_PAKAI=true`.
Rekala lalu mencatat hash token secara atomik di cache sampai kedaluwarsa; pemeriksaan API tetap
bisa diulang. Gunakan cache bersama yang persisten di semua server aplikasi, bukan `array`;
pengosongan cache menghapus catatan token yang telah dipakai.

### Access log infrastruktur

Perlindungan aplikasi tidak menghapus query dari access log web server/proxy/WAF atau
riwayat browser. Atur pengelola infrastruktur agar **query `token` dan header Authorization
tidak direkam** untuk `/sso-login` dan `/api/sso-check`, termasuk APM dan laporan error.
Pada Nginx, gunakan format khusus berbasis `$uri` tanpa `$request_uri`, `$args`, atau `$request`
untuk endpoint tersebut; pada Apache, gunakan jalur `%U` tanpa `%q` atau `%r` pada aturan log yang sesuai.
Token juga tidak boleh dimasukkan ke analytics, screenshot, atau laporan pengujian.

## Direktori eHRM dan hak akses

Login Dwaradaya memverifikasi identitas user yang sudah diizinkan Admin DTI.
Tahap auto-registration eHRM dalam dokumen bersifat opsional dan tidak diaktifkan karena
akses Rekala diberikan oleh Admin DTI. User valid di SuperApp belum tentu memiliki hak Rekala.

Integrasi direktori pegawai tetap terpisah dari login. Endpoint API gateway eHRM yang disebut
dokumen memerlukan kredensial dan pengaturan sendiri jika kelak dipakai untuk pencarian/sinkronisasi
pegawai di Data Master. Kredensial gateway tersebut tidak diperlukan untuk dua endpoint SSO di atas.

## Logout dan sesi

`SIMTLHP_DWARADAYA_LOGOUT` kosong berarti keluar dari **Rekala** saja. Sesi Dwaradaya mungkin
masih aktif, sehingga user dapat masuk lagi dengan memilih kartu Rekala. Isi URL logout global
hanya setelah tim Dwaradaya menyediakan dan menyetujui endpoint resminya.
Idle timeout, penonaktifan akun, pencabutan akses, serta pilihan peran tetap dijaga sistem Rekala.

## Verifikasi bersama tim Dwaradaya

1. Daftarkan dua URL HTTPS Rekala dan samakan secret/algoritma di kedua pihak.
2. Siapkan user aktif dengan NIP yang sama di Dwaradaya dan Data Master, tanpa memakai data palsu pada produksi.
3. Login Dwaradaya, pastikan cek API mendapatkan JSON tepat, lalu klik kartu Rekala.
4. Periksa identitas, halaman awal, peran tambahan, dan cakupan Satker user.
5. Uji user tanpa izin dan user nonaktif; keduanya harus ditolak tanpa akun baru.
6. Uji token kedaluwarsa dan tanda tangan salah; sesi baru tidak terbentuk.
7. Uji browser dengan akun Rekala berbeda, masuk ulang setelah idle timeout, dan logout.
8. Periksa access log/proxy/APM untuk memastikan token tidak tersimpan.

Uji otomatis ada di `tests/Feature/DwaradayaSsoTest.php`, dijalankan dengan database SQLite
terpisah dalam memori. Uji ini memvalidasi perilaku aplikasi dengan JWT pengujian;
hasilnya tidak menggantikan pengujian koneksi langsung dengan server Dwaradaya.
