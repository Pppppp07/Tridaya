<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Isi bingkai halaman: angka di menu, pemberitahuan yang belum dibaca, dan pemberitahuan yang
 * dimunculkan sebagai pop-up. Dihitung sekali per permintaan, dari daftar yang sudah difilter
 * hak aksesnya — padanan `perluKerja`, `belumDibaca`, dan `popup` di App
 * prototipe.
 */
class Rangka
{
    private const KUNCI_POPUP = 'popup_ditampilkan';

    /**
     * Dihitung sekali per pengguna. Satu permintaan menggambar bingkainya
     * beberapa kali, dan hitungannya mahal — ia memfilter seluruh rekomendasi.
     */
    private static array $isi = [];

    public static function untuk(User $u): array
    {
        return self::$isi[$u->id] ??= self::hitung($u);
    }

    /**
     * Lupakan yang sudah dihitung.
     *
     * Di layanan biasa tidak pernah dipanggil: tiap permintaan proses baru.
     * Uji berbeda — satu proses menjalankan banyak permintaan berturut-turut,
     * dan nilai yang tertinggal membuat halaman menggambar keadaan uji
     * sebelumnya. Tanpa ini, uji draf laporan gagal bergantung urutan.
     */
    public static function lupakan(): void
    {
        self::$isi = [];
    }

    private static function hitung(User $u): array
    {
        $lingkup = Lingkup::dari();
        $terlihat = Terlihat::untuk($u);

        /* Angka menu Rekomendasi = keranjang "Perlu saya kerjakan" — rumus yang
           sama, supaya angka yang menuntun orang masuk tidak berbeda dari isi
           keranjangnya.

           Hitungannya memfilter SELURUH rekomendasi dan dipakai di setiap
           halaman, jadi hasilnya disimpan di cache per akun dan lingkup
           (27 Sep). Cache itu basi sendiri begitu ada data yang berubah:
           setiap perubahan tercatat lewat Aktivitas::catat(), yang mengganti
           versi data. Batas lima menit hanya jaring pengaman. */
        $perluKerja = (int) Cache::remember(
            'rangka:perlu:'.$u->id.':'.$lingkup.':'.self::versiData(), now()->addMinutes(5),
            fn () => $terlihat->rekomendasi()
                ->with(['sasaran', 'temuan.laporan'])
                ->get()
                ->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))
                ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
                ->filter(fn ($r) => $r->diMeja($u->peran, $u->satker_id))
                ->count());

        $belum = Pemberitahuan::belumDibaca($u);
        /* Angka di lonceng = pemberitahuan BARU (26 Sep): yang belum dibaca DAN belum
           pernah tampil di daftarnya. Hilang begitu panelnya dibuka. */
        $baru = Pemberitahuan::jumlahBaru($u);

        /* Data master memberi tanda kalau ada unit kerja aktif tanpa penanggung
           jawab — rumus yang sama dengan ringkasan di layarnya. Tanpa
           penanggung jawab, pemberitahuan dan email-nya tidak sampai ke siapa pun. */
        $tanpaPj = $u->peran->kelolaMaster()
            ? Satker::where('aktif', true)->whereDoesntHave('penanggungJawab')->count()
            : 0;

        return [
            'perluKerja' => $perluKerja,
            'tanpaPj' => $tanpaPj,
            'belumDibaca' => $belum,
            'baru' => $baru,
            /* Halaman yang diminta skrip untuk ditukar sebagian di tempat
               (dasbor, Catat laporan baru — 29 Sep) tidak memakai kotaknya:
               tanpa ini pemberitahuannya tercatat sudah dimunculkan padahal
               tidak pernah tampil. Kotaknya menyusul lewat `ringkas`. */
            'popup' => request()->ajax() ? null : self::popupPemberitahuan($u, $baru),
            /* Hanya draf yang berisi (28 Sep): keadaan kerja formulir yang
               masih kosong bukan draf, tombolnya tetap "Catat laporan baru". */
            'drafLaporan' => $u->peran === PeranPengguna::SETBA
                && \App\Http\Controllers\LaporanBaruController::drafBerisi($u->id),
        ];
    }

    /**
     * Kotak popup — padanan efek `idPemberitahuanBaru` di App prototipe (26 Sep).
     * Hanya pemberitahuan BARU, masing-masing sekali per sesi. Yang ditampilkan pemberitahuan
     * baru yang terbaru; lebih dari satu cukup jumlahnya. Yang sedang membuka
     * halaman Pemberitahuan tidak diberi pop-up — pemberitahuannya dicatat sudah
     * dimunculkan sebagai pop-up, karena memang sudah di depan matanya.
     */
    public static function popupPemberitahuan(User $u, int $baru): ?array
    {
        /* Yang mematikan pop-up (Profil → Pemberitahuan, 28 Sep) cukup diberi
           angka di lonceng. */
        if (! $baru || ! $u->setelan('popup')) {
            return null;
        }

        $sudah = session(self::KUNCI_POPUP, []);
        $idBaru = Pemberitahuan::idBaru($u);
        $belumPopup = array_values(array_diff($idBaru, $sudah));
        if (! $belumPopup) {
            return null;
        }
        session([self::KUNCI_POPUP => array_values(array_unique(array_merge($sudah, $belumPopup)))]);
        if (request()->routeIs('pemberitahuan')) {
            return null;
        }

        $semuaBaru = Pemberitahuan::untuk($u)->whereIn('id', $idBaru)->values();

        return ['pemberitahuan' => $semuaBaru->first(), 'jumlah' => $semuaBaru->count()];
    }

    /** Versi data sekarang — berganti tiap kali ada data yang berubah (Aktivitas::catat). */
    public static function versiData(): string
    {
        return (string) Cache::get(self::KUNCI_VERSI, '0');
    }

    /** Tandai data berubah: semua hitungan bingkai yang tersimpan jadi basi. */
    public static function dataBerubah(): void
    {
        Cache::forever(self::KUNCI_VERSI, Str::random(16));
    }

    private const KUNCI_VERSI = 'simtlhp:versi-data';

    /** Angka di menu paling banyak tiga tanda; lebih dari itu cukup "99+". */
    public static function angka(int $n): string
    {
        return $n > 99 ? '99+' : (string) $n;
    }

    /** Sebutan akun di kanan batang atas dan di kaki menu, seperti prototipe. */
    public static function pengguna(User $u): array
    {
        return match ($u->peran) {
            PeranPengguna::SETBA       => ['rupa' => 'SE', 'nama' => 'Setba', 'ket' => 'Sekretariat Badan', 'peran' => 'Setba — Sekretariat Badan'],
            /* Satuan kerja masuk sebagai penanggung jawabnya: orangnya yang
               disebut, unit kerjanya di bawah — sama dengan prototipe. */
            PeranPengguna::SATKER      => ['rupa' => self::inisial($u->name), 'nama' => $u->name, 'ket' => $u->satker?->namaPendek() ?? 'Satuan kerja', 'peran' => 'Satuan kerja'],
            PeranPengguna::UKI         => ['rupa' => 'UK', 'nama' => 'UKI', 'ket' => 'Unit Kepatuhan Internal', 'peran' => 'UKI — Unit Kepatuhan Internal'],
            PeranPengguna::INSPEKTORAT => ['rupa' => 'IN', 'nama' => 'Inspektorat', 'ket' => 'Pengguna', 'peran' => 'Inspektorat'],
            PeranPengguna::PIMPINAN    => ['rupa' => 'PI', 'nama' => 'Pimpinan', 'ket' => 'Pengguna', 'peran' => 'Pimpinan'],
            PeranPengguna::DTI         => ['rupa' => 'DT', 'nama' => 'DTI', 'ket' => 'Data dan Teknologi Informasi', 'peran' => 'DTI — Data dan Teknologi Informasi'],
            PeranPengguna::ADMIN       => ['rupa' => 'AD', 'nama' => 'Admin', 'ket' => 'Administrator', 'peran' => 'Administrator'],
        };
    }

    /** Dua huruf depan nama orang, untuk lingkaran di batang atas. */
    public static function inisial(?string $nama): string
    {
        $kata = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L} ]/u', ' ', (string) $nama)), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn ($k) => mb_substr($k, 0, 1), array_slice($kata, 0, 2)))) ?: '?';
    }
}
