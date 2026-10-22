<?php

return [

    /* Tanggal demo (data contoh disusun untuk 17 Agustus 2026). Kosongkan untuk pemakaian
       sungguhan; yang dipatok hanya tanggal, jamnya tetap jam komputer. Akibatnya hari demo
       berulang: pemberitahuan dari hari nyata berikutnya bisa terbaca lebih lama dan tidak dihitung
       "baru" di lonceng. Untuk uji lintas hari, kosongkan. */
    'hari_ini' => env('SIMTLHP_HARI_INI'),

    /* Semai data contoh bersama data master. */
    'data_contoh' => (bool) env('SIMTLHP_DATA_CONTOH', true),

    /* Direktori pegawai (App\Support\DirektoriIrm): `berkas` = daftar contoh rekaan, `http` =
       layanan eHRM Pusdatin (hasil di-cache sebentar). */
    'direktori' => [
        'driver' => env('SIMTLHP_DIREKTORI', 'berkas'),
        'berkas' => env('SIMTLHP_IRM_BERKAS', database_path('data/irm-contoh.json')),
        'http' => [
            'url' => env('SIMTLHP_EHRM_URL'),
            'token' => env('SIMTLHP_EHRM_TOKEN'),
            'batas_detik' => (int) env('SIMTLHP_EHRM_BATAS_DETIK', 8),
            'simpan_menit' => (int) env('SIMTLHP_EHRM_SIMPAN_MENIT', 30),
            /* Jalur tiap pertanyaan. {q}, {nip}, {unit} diganti isinya. */
            'jalur' => [
                'cari' => env('SIMTLHP_EHRM_JALUR_CARI', '/pegawai?q={q}'),
                'nip' => env('SIMTLHP_EHRM_JALUR_NIP', '/pegawai/{nip}'),
                'unit' => env('SIMTLHP_EHRM_JALUR_UNIT', '/unit/{unit}/pegawai'),
            ],
            /* Nama medan di jawaban eHRM → nama medan di aplikasi ini. Boleh
               bertitik untuk medan bersarang, mis. "unit.kode". */
            'medan' => [
                'nip' => env('SIMTLHP_EHRM_MEDAN_NIP', 'nip'),
                'nama' => env('SIMTLHP_EHRM_MEDAN_NAMA', 'nama'),
                'jabatan' => env('SIMTLHP_EHRM_MEDAN_JABATAN', 'jabatan'),
                'unit' => env('SIMTLHP_EHRM_MEDAN_UNIT', 'kode_unit'),
                'unitNama' => env('SIMTLHP_EHRM_MEDAN_UNIT_NAMA', 'nama_unit'),
                'email' => env('SIMTLHP_EHRM_MEDAN_EMAIL', 'email'),
            ],
            /* Daftar pegawai di jawaban pencarian; kosong = jawabannya sendiri larik. */
            'wadah' => env('SIMTLHP_EHRM_WADAH', 'data'),
        ],
    ],

    /* Nama lama medan di atas; tetap dibaca supaya .env lama tidak rusak. */
    'irm_berkas' => env('SIMTLHP_IRM_BERKAS', database_path('data/irm-contoh.json')),

    /* Password akun penanggung jawab dari Data master, KHUSUS demo. Kosong = acak (masuk lewat
       SSO). Jangan diisi di pemasangan sungguhan. */
    'password_demo' => env('SIMTLHP_PASSWORD_DEMO'),

    /* Akun contoh di halaman Masuk: menyala di luar produksi, padam di produksi. */
    'akun_demo' => (bool) env('SIMTLHP_AKUN_DEMO', env('APP_ENV', 'production') !== 'production'),

    /* Masuk dengan password. Padamkan (SIMTLHP_MASUK_PASSWORD=false) begitu SSO berjalan di
       produksi. Salah `batas_percobaan` kali dalam `jeda_detik` detik per email + alamat →
       dikunci sementara. */
    'masuk' => [
        'password' => (bool) env('SIMTLHP_MASUK_PASSWORD', true),
        'batas_percobaan' => (int) env('SIMTLHP_MASUK_BATAS', 5),
        'jeda_detik' => (int) env('SIMTLHP_MASUK_JEDA', 60),
        /* Kiriman masuk dan SSO per alamat IP per menit. Satu kantor sering keluar lewat satu IP:
           terlalu kecil membuat peserta sosialisasi tertolak saat masuk bersamaan. */
        'per_menit_ip' => (int) env('SIMTLHP_MASUK_PER_MENIT_IP', 120),
    ],

    /* Keluar otomatis sesudah sekian menit tanpa aktivitas (maks. SESSION_LIFETIME). */
    'sesi' => [
        'batas_diam' => (int) env('SIMTLHP_BATAS_DIAM', 10),
    ],

    /* SSO: `dwaradaya` = JWT dari kartu SuperApp, `oidc` = OpenID Connect + PKCE,
       `mati` = belum diaktifkan. Hak akses tetap dari Data Master. */
    'sso' => [
        'driver' => env('SIMTLHP_SSO', 'mati'),
        'nama' => env('SIMTLHP_SSO_NAMA', 'SSO PU'),
        /* Nama, jabatan, dan email disamakan dengan eHRM tiap masuk. */
        'sinkron_atribut' => (bool) env('SIMTLHP_SSO_SINKRON', true),
        'dwaradaya' => [
            'secret' => env('SUPERAPP_SECRET'),
            // HS256 adalah pilihan awal; cocokkan dengan konfigurasi resmi SuperApp.
            'algoritma' => env('SIMTLHP_DWARADAYA_ALG', 'HS256'),
            'portal' => env('SIMTLHP_DWARADAYA_URL'),
            'logout' => env('SIMTLHP_DWARADAYA_LOGOUT'),
            'issuer' => env('SIMTLHP_DWARADAYA_ISSUER'),
            'audience' => env('SIMTLHP_DWARADAYA_AUDIENCE'),
            // Aktifkan setelah SuperApp memastikan JWT baru untuk setiap klik aplikasi.
            'sekali_pakai' => (bool) env('SIMTLHP_DWARADAYA_SEKALI_PAKAI', false),
            'cek_per_menit' => (int) env('SIMTLHP_DWARADAYA_CEK_PER_MENIT', 600),
        ],
        'oidc' => [
            /* Wajib cocok persis dengan issuer dan jwks_uri metadata penyedia. */
            'issuer' => env('SIMTLHP_OIDC_ISSUER'),
            'jwks' => env('SIMTLHP_OIDC_JWKS'),
            'authorize' => env('SIMTLHP_OIDC_AUTHORIZE'),
            'token' => env('SIMTLHP_OIDC_TOKEN'),
            'userinfo' => env('SIMTLHP_OIDC_USERINFO'),
            'logout' => env('SIMTLHP_OIDC_LOGOUT'),
            'client_id' => env('SIMTLHP_OIDC_CLIENT_ID'),
            'client_secret' => env('SIMTLHP_OIDC_CLIENT_SECRET'),
            /* SSO PU hanya mengenal `openid` (dan `offline_access`); scope lain bisa ditolak. */
            'scope' => env('SIMTLHP_OIDC_SCOPE', 'openid'),
            /* Kosong = {APP_URL}/masuk/sso/kembali (didaftarkan ke Pusdatin sebagai redirect
               URI). */
            'redirect' => env('SIMTLHP_OIDC_REDIRECT'),
            'batas_detik' => (int) env('SIMTLHP_OIDC_BATAS_DETIK', 10),
            /* Nama klaim di userinfo → atribut pegawai. Boleh bertitik. */
            'klaim' => [
                'nip' => env('SIMTLHP_OIDC_KLAIM_NIP', 'nip'),
                'nama' => env('SIMTLHP_OIDC_KLAIM_NAMA', 'name'),
                'email' => env('SIMTLHP_OIDC_KLAIM_EMAIL', 'email'),
                'jabatan' => env('SIMTLHP_OIDC_KLAIM_JABATAN', 'jabatan'),
                'unit' => env('SIMTLHP_OIDC_KLAIM_UNIT', 'unit_kerja'),
            ],
        ],
    ],

    /* Log aktivitas dipangkas sesudah sekian hari (bawaan 5 tahun). */
    'log' => [
        'simpan_hari' => (int) env('SIMTLHP_LOG_SIMPAN_HARI', 1825),
        'per_halaman' => 50,
    ],

    /* Penjaga beban untuk server dengan sumber daya terbatas: batas per user dan laju email.
       Lihat PANDUAN-SERVER.md. */
    'beban' => [
        // Permintaan yang menulis data (kirim, teruskan, simpan, unggah) per user per menit.
        'tulis_per_menit' => (int) env('SIMTLHP_TULIS_PER_MENIT', 120),
        // Permintaan baca per user per menit (meredam muat ulang beruntun atau skrip liar).
        'baca_per_menit' => (int) env('SIMTLHP_BACA_PER_MENIT', 300),
        // Unduhan (ekspor log, daftar, user) per user per menit: tiap unduhan membaca banyak baris.
        'unduh_per_menit' => (int) env('SIMTLHP_UNDUH_PER_MENIT', 10),
        // Email pemberitahuan yang dikirim pekerja antrean per menit.
        'email_per_menit' => (int) env('SIMTLHP_EMAIL_PER_MENIT', 20),
        // Pekerja antrean dijalankan penjadwal (cron `schedule:run`) bila server tanpa supervisor.
        'antrean_lewat_jadwal' => (bool) env('SIMTLHP_ANTREAN_LEWAT_JADWAL', true),
    ],

    'keamanan' => [
        /* Content-Security-Policy di setiap halaman. Padamkan hanya untuk
           mencari masalah. */
        'csp' => (bool) env('SIMTLHP_CSP', true),
        /* Alamat reverse proxy / penyeimbang beban, dipisah koma, atau "*".
           Lihat App\Http\Middleware\PercayaiProxy. */
        'proxy_tepercaya' => env('SIMTLHP_PROXY_TEPERCAYA'),
    ],

];
