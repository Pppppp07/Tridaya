# Logo infaX

Logo **Simpul Fakta** menghubungkan dua putaran melalui persilangan X. Putaran kiri membawa makna pencatatan dan telaah informasi, sedangkan putaran kanan membawa makna keputusan dan pemantauan pelaksanaannya. Empat lengan pada pertemuan tersebut mewakili **Temuan, Telaah, Keputusan, dan Pantau**. Bentuk menyampaikan hubungan besar antarpekerjaan; tahapan dan kewenangan tetap mengikuti alur aplikasi.

## Bentuk

- Satu jalur tertutup membentuk dua putaran yang bertemu. Keterhubungan melambangkan arsip dan riwayat yang dapat ditelusuri, termasuk saat terjadi pengembalian serta perbaikan.
- X menjadi persilangan nyata jalur, tanpa bidang tambahan atau lubang dekoratif. Aksen mengikuti empat lengan jalur sehingga bentuknya tetap terbaca dalam satu warna.
- Ketebalan jalur seragam, 12 unit pada bidang 96 × 96 unit. Sudut membulat menjaga bentuk teratur dan nyaman pada layar.
- Ruang kosong di dalam kedua putaran menjaga keterbacaan pada ukuran kecil. Jarak dan lengkung adalah keputusan optis, bukan simbol angka tambahan.
- Nama ditulis **infaX** dengan empat huruf kecil dan X kapital. Wordmark menggunakan Manrope 700 yang telah tersedia secara lokal dalam aplikasi. Berkas SVG horizontal menggunakan outline huruf sehingga tidak membutuhkan instalasi font.

## Warna

| Penggunaan | Terang | Gelap |
| --- | --- | --- |
| Jalur dan tulisan utama | Petrol `#155B59` | Petrol terang `#BCE2DC` |
| Persilangan dan huruf X | Tanah liat `#C96F51` | Tanah liat terang `#E39A7D` |
| Latar pendukung | Putih hangat `#F7F6F2` | Mengikuti permukaan tema gelap |

Petrol diberi makna ketelitian serta kesinambungan informasi. Aksen tanah liat menandai eksekusi. Makna ini merupakan narasi identitas infaX, bukan klaim bahwa warna mempunyai arti psikologis universal. Warna status aplikasi tetap mengikuti hasil pemeriksaan; warna logo tidak menggantikan arti status tersebut.

## Aset

Aset web berada pada `public/brand/infax/`:

- `simbol.svg` dan `simbol-gelap.svg`: simbol dua warna tanpa latar.
- `simbol-satu-warna.svg` dan `simbol-putih.svg`: cetak serta penggunaan monokrom.
- `logo-horizontal.svg`, `logo-horizontal-gelap.svg`, dan `logo-satu-warna.svg`: simbol beserta nama dalam outline vektor.
- `favicon.svg`: simbol satu warna dengan latar netral yang menyesuaikan tema tab browser. Favicon ICO menyediakan ukuran 16, 32, serta 48 piksel.
- `apple-touch-icon.png`: ikon 180 piksel untuk perangkat.

`resources/images/email/infax-simbol.png` merupakan PNG transparan 192 piksel yang disematkan pada email. Gambar tidak diambil dari layanan luar. Sumber geometri, rasterisasi aset, dan pemasangan komponen tercatat dalam `.tools/build-infax-logo.py`, `.tools/render-infax-assets.cjs`, serta `.tools/install-infax-logo.py`.

## Penerapan

Komponen `x-logo-infax` mengatur identitas pada login, akses dibatasi, sidebar lebar, sidebar ringkas, dan header ponsel. Label pembaca layar menyebut infaX satu kali; bagian gambar dan tulisan dekoratif tidak dibaca ulang. Alias `x-logo-tridaya` mengarah ke komponen yang sama untuk menjaga kompatibilitas pemanggilan lama.

Pertahankan proporsi simbol, ruang kosong kedua putaran, serta penulisan nama. Gunakan varian satu warna untuk reproduksi kecil dan cetak. Jangan menambah slogan di dalam simbol atau memisahkan X menjadi merek kedua. Lembar visual tersedia pada `docs/brand/infax/identitas-infax.svg` dan PNG pendampingnya.

Semua aset statis. Tidak ada animasi logo, permintaan font baru, pelacakan, maupun proses tambahan pada database. Dekorasi login menggunakan dua SVG sebaris yang memakai bentuk logo; batas gerak dan preferensi pengurangan animasi tetap mengikuti pengaturan yang tersedia.
