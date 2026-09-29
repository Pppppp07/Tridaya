# Identitas Tridaya di aplikasi PHP

Logo **T Terhubung**, berdasarkan [pedoman Figma v1.1](https://www.figma.com/design/eTBJydg6U8PNYro6GHU4Ty/Tridaya?node-id=15-7).
Sumber simbol adalah SVG asli yang dipakai untuk menyusun desain Figma; bentuk dan celahnya dipertahankan.

## Aset dan pemakaian

- `public/brand/tridaya/simbol.svg`: logo utama untuk latar terang, ukuran minimal 24 px.
- `public/brand/tridaya/simbol-gelap.svg`: adaptasi putih, biru muda, dan emas untuk latar biru malam `#102C54`.
- `public/brand/tridaya/simbol-mikro.svg`: versi 16–23 px dengan celah 2 px pada ukuran 16 px.
- `public/brand/tridaya/favicon.svg`: bentuk mikro dengan warna yang mengikuti tema tab browser.
- `public/favicon.ico`: fallback 16, 32, dan 48 px di atas bidang putih supaya terbaca pada tema tab terang maupun gelap; ukuran 16 px memakai bentuk mikro, ukuran lebih besar memakai bentuk utama.
- `public/brand/tridaya/apple-touch-icon.png`: simbol utama di bidang putih untuk ikon perangkat, 180 px.

Gunakan `<x-logo-tridaya />` untuk simbol dan nama, tanpa keterangan di bawahnya. `:simbol-saja="true"` menampilkan simbol saja, sedangkan `:gelap="true"` memilih adaptasi latar gelap. Semua varian tetap bernama Tridaya untuk pembaca layar.

Tema aplikasi otomatis memilih simbol latar gelap dan nama berwarna terang saat akun memakai tema Gelap. Pilihan Terang/Gelap disimpan di `users.pengaturan.tema`; Terang adalah default. Aturan warna aplikasi berada di `public/css/simtlhp-tema.css`, terpisah dari salinan CSS prototipe.

Tulisan **tridaya** memakai Manrope ExtraBold (800), huruf kecil dengan jarak huruf −4%, sesuai desain. Font dimuat lokal dari `public/fonts/manrope-variable.ttf`, hanya untuk logo. Sumber: [Google Fonts — Manrope](https://github.com/google/fonts/tree/main/ofl/manrope), lisensi SIL Open Font License disertakan di `public/fonts/manrope-OFL.txt`.

Warna utama `#163D78`, biru aksen `#2864C6`, dan emas `#E9B64B`. Ini adalah palet merek Tridaya; maknanya merupakan interpretasi desain, bukan standar resmi instansi. Ruang aman di sekitar logo dan antara simbol/nama minimal setengah lebar batang T. Pertahankan rasio simbol; jangan menambahkan lingkaran, bayangan, atau gradien pada logo.

Identitas dipakai bersama pada halaman masuk, SSO simulasi, akses dibatasi, sidebar (lebar/ringkas), header ponsel, judul halaman, dan ikon browser. Nama peran **Setba**, konfigurasi `SIMTLHP_*`, cookie, serta alur kerja tetap memiliki arti operasionalnya masing-masing.
