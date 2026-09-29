<?php

return [

    /*
     * "Hari ini" untuk demo.
     *
     * Data contoh disusun untuk 17 Agustus 2026, sama dengan `HARI_INI` di
     * prototipe. Tanpa ini tenggat, keterlambatan, dan pemberitahuan "baru" dihitung
     * dari tanggal komputer — dan kedua artefak yang diperagakan berdampingan
     * menyebut keadaan berbeda untuk berkas yang sama.
     *
     * Kosongkan untuk pemakaian sungguhan. Jamnya tetap jam komputer; yang
     * dipatok hanya tanggalnya.
     */
    'hari_ini' => env('SIMTLHP_HARI_INI'),

    /* Semai data contoh bersama data master. `false` = padanan `?kosong`. */
    'data_contoh' => (bool) env('SIMTLHP_DATA_CONTOH', true),

    /* Draf tanggapan yang belum dikirim sekian hari dikirim sendiri bila
       kewajibannya sudah terpenuhi. */
    'hari_kirim_otomatis_draf' => 7,

    /*
     * Direktori pegawai IRM/eHRM — tempat Data master mencari penanggung jawab
     * unit kerja dan petugas pusat. Lihat App\Support\DirektoriIrm.
     *
     * - `berkas` (bawaan): daftar pegawai contoh yang sama dengan prototipe
     *   (`Prototipe/alat/buat-irm-contoh.py`). Seluruh orangnya rekaan.
     * - `http`: layanan pegawai eHRM di server Pusdatin. Alamat, token, dan
     *   nama medannya diisi di bawah; hasil pencarian disimpan sebentar di
     *   cache supaya eHRM tidak ditanya berulang-ulang.
     */
    'direktori' => [
        'driver' => env('SIMTLHP_DIREKTORI', 'berkas'),
        'berkas' => env('SIMTLHP_IRM_BERKAS', database_path('data/irm-contoh.json')),
        'http' => [
            'url'         => env('SIMTLHP_EHRM_URL'),
            'token'       => env('SIMTLHP_EHRM_TOKEN'),
            'batas_detik' => (int) env('SIMTLHP_EHRM_BATAS_DETIK', 8),
            'simpan_menit' => (int) env('SIMTLHP_EHRM_SIMPAN_MENIT', 30),
            /* Jalur tiap pertanyaan. {q}, {nip}, {unit} diganti isinya. */
            'jalur' => [
                'cari' => env('SIMTLHP_EHRM_JALUR_CARI', '/pegawai?q={q}'),
                'nip'  => env('SIMTLHP_EHRM_JALUR_NIP', '/pegawai/{nip}'),
                'unit' => env('SIMTLHP_EHRM_JALUR_UNIT', '/unit/{unit}/pegawai'),
            ],
            /* Nama medan di jawaban eHRM → nama medan di aplikasi ini. Boleh
               bertitik untuk medan bersarang, mis. "unit.kode". */
            'medan' => [
                'nip'      => env('SIMTLHP_EHRM_MEDAN_NIP', 'nip'),
                'nama'     => env('SIMTLHP_EHRM_MEDAN_NAMA', 'nama'),
                'jabatan'  => env('SIMTLHP_EHRM_MEDAN_JABATAN', 'jabatan'),
                'unit'     => env('SIMTLHP_EHRM_MEDAN_UNIT', 'kode_unit'),
                'unitNama' => env('SIMTLHP_EHRM_MEDAN_UNIT_NAMA', 'nama_unit'),
                'email'    => env('SIMTLHP_EHRM_MEDAN_EMAIL', 'email'),
            ],
            /* Daftar pegawai di jawaban pencarian; kosong = jawabannya sendiri larik. */
            'wadah' => env('SIMTLHP_EHRM_WADAH', 'data'),
        ],
    ],

    /* Nama lama medan di atas; tetap dibaca supaya .env lama tidak rusak. */
    'irm_berkas' => env('SIMTLHP_IRM_BERKAS', database_path('data/irm-contoh.json')),

    /*
     * Password akun penanggung jawab yang dibuat dari Data master, KHUSUS
     * demo. Kosong (bawaan) = password acak: akun itu baru bisa dipakai
     * begitu masuk lewat SSO. Jangan diisi pada pemasangan sungguhan.
     */
    'password_demo' => env('SIMTLHP_PASSWORD_DEMO'),

    /*
     * Daftar akun contoh di halaman Masuk (klik untuk masuk). Bawaannya menyala
     * di luar produksi dan PADAM di produksi.
     */
    'akun_demo' => (bool) env('SIMTLHP_AKUN_DEMO', env('APP_ENV', 'production') !== 'production'),

    /*
     * Cara masuk.
     *
     * `password`: formulir email + password. Begitu SSO berjalan di produksi,
     * padamkan (SIMTLHP_MASUK_PASSWORD=false) supaya satu-satunya pintu adalah
     * SSO. `batas_percobaan` kali salah dalam `jeda_detik` detik untuk satu
     * email dari satu alamat, lalu dikunci sementara.
     */
    'masuk' => [
        'password'           => (bool) env('SIMTLHP_MASUK_PASSWORD', true),
        'batas_percobaan' => (int) env('SIMTLHP_MASUK_BATAS', 5),
        'jeda_detik'      => (int) env('SIMTLHP_MASUK_JEDA', 60),
    ],

    /*
     * SSO — masuk lewat akun eHRM Kementerian (Pusdatin).
     *
     * `driver`:
     * - `mati`     : tidak ada tombol SSO (bawaan).
     * - `simulasi` : demo tanpa server SSO — memilih pegawai dari
     *                direktori, lalu aturan hak aksesnya dijalankan sungguhan.
     *                Tidak pernah menyala di produksi.
     * - `oidc`     : OpenID Connect / OAuth 2.0 (authorization code + PKCE).
     *                Isi alamat dan kredensial dari Pusdatin di bawah.
     *
     * SSO PU (https://sso.pu.go.id, login eHRM) memang OpenID Connect — dicek
     * 29 Sep dari https://sso.pu.go.id/.well-known/openid-configuration:
     * authorize/token/userinfo/logout di /connect/…, PKCE S256, client_secret
     * lewat isian (client_secret_post), scope yang dikenal hanya `openid` dan
     * `offline_access`, id_token RS256 (JWKS di /.well-known/jwks). Alamatnya
     * sudah terisi di .env.example; yang masih ditunggu dari Pusdatin: client
     * id + secret, pendaftaran redirect URI, dan klaim yang membawa NIP.
     *
     * SSO hanya MEMBUKTIKAN siapa orangnya. Boleh tidaknya ia masuk, dan
     * sebagai apa, tetap ditentukan aplikasi ini: NIP-nya harus terdaftar di
     * Data master sebagai akun aktif (App\Support\Sso\Otorisasi).
     */
    'sso' => [
        'driver' => env('SIMTLHP_SSO', 'mati'),
        'nama'   => env('SIMTLHP_SSO_NAMA', 'SSO PU'),
        /* Nama, jabatan, dan email akun disamakan dengan eHRM setiap kali
           orangnya masuk — sumber kebenarannya eHRM, bukan ketikan. */
        'sinkron_atribut' => (bool) env('SIMTLHP_SSO_SINKRON', true),
        'oidc' => [
            'authorize'     => env('SIMTLHP_OIDC_AUTHORIZE'),
            'token'         => env('SIMTLHP_OIDC_TOKEN'),
            'userinfo'      => env('SIMTLHP_OIDC_USERINFO'),
            'logout'        => env('SIMTLHP_OIDC_LOGOUT'),
            'client_id'     => env('SIMTLHP_OIDC_CLIENT_ID'),
            'client_secret' => env('SIMTLHP_OIDC_CLIENT_SECRET'),
            /* SSO PU hanya mengumumkan `openid` (dan `offline_access`); scope
               lain, mis. `profile email`, bisa ditolak sebagai invalid_scope. */
            'scope'         => env('SIMTLHP_OIDC_SCOPE', 'openid'),
            /* Kosong = {APP_URL}/masuk/sso/kembali. Alamat ini yang
               didaftarkan ke Pusdatin sebagai redirect URI. */
            'redirect'      => env('SIMTLHP_OIDC_REDIRECT'),
            'batas_detik'   => (int) env('SIMTLHP_OIDC_BATAS_DETIK', 10),
            /* Nama klaim di userinfo → atribut pegawai. Boleh bertitik. */
            'klaim' => [
                'nip'     => env('SIMTLHP_OIDC_KLAIM_NIP', 'nip'),
                'nama'    => env('SIMTLHP_OIDC_KLAIM_NAMA', 'name'),
                'email'   => env('SIMTLHP_OIDC_KLAIM_EMAIL', 'email'),
                'jabatan' => env('SIMTLHP_OIDC_KLAIM_JABATAN', 'jabatan'),
                'unit'    => env('SIMTLHP_OIDC_KLAIM_UNIT', 'unit_kerja'),
            ],
        ],
    ],

    /* Log aktivitas dipangkas sesudah sekian hari (bawaan 5 tahun). */
    'log' => [
        'simpan_hari' => (int) env('SIMTLHP_LOG_SIMPAN_HARI', 1825),
        'per_halaman' => 50,
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
