<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Http\Controllers\LaporanBaruController;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Isi bingkai halaman: angka menu, pemberitahuan belum dibaca, pop-up. Dihitung sekali per
 * permintaan dari daftar yang sudah difilter hak aksesnya.
 */
class Rangka
{
    private const KUNCI_POPUP = 'popup_ditampilkan';

    /** Dihitung sekali per pengguna; hitungannya mahal. */
    private static array $isi = [];

    public static function untuk(User $u): array
    {
        return self::$isi[$u->id.':'.$u->peranAktif()->value.':'.implode(',', (array) $u->satkerAktifId())] ??= self::hitung($u);
    }

    /** Lupakan hasil hitungan. Hanya untuk uji: satu proses menjalankan banyak permintaan. */
    public static function lupakan(): void
    {
        self::$isi = [];
    }

    public static function perluKerja(User $u): int
    {
        $lingkup = Lingkup::dari();
        $terlihat = Terlihat::untuk($u);

        /* Angka menu Rekomendasi = isi keranjang "Perlu saya kerjakan". Disimpan di cache per
           akun dan lingkup; basi sendiri tiap ada data berubah (lewat Aktivitas::catat). */
        return (int) Cache::remember(
            'rangka:perlu:'.$u->id.':'.$u->peranAktif()->value.':'.implode(',', (array) $u->satkerAktifId()).':'.$lingkup.':'.self::versiData(), now()->addMinutes(5),
            fn () => $terlihat->rekomendasi()
                ->with(['sasaran', 'temuan.laporan'])
                ->get()
                ->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))
                ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
                ->filter(fn ($r) => $r->diMeja($u->peranAktif(), $u->satkerAktifId()))
                ->count());
    }

    private static function hitung(User $u): array
    {
        $perluKerja = self::perluKerja($u);

        $belum = Pemberitahuan::belumDibaca($u);
        /* Angka lonceng = pemberitahuan BARU; hilang begitu panelnya dibuka. */
        $baru = Pemberitahuan::jumlahBaru($u);

        /* Tanda di Data master bila ada unit aktif tanpa penanggung jawab (pemberitahuannya
           tidak sampai ke siapa pun). */
        $tanpaPj = $u->peranAktif()->kelolaMaster()
            ? Satker::where('aktif', true)->whereDoesntHave('penanggungJawab')->count()
            : 0;

        return [
            'perluKerja' => $perluKerja,
            'tanpaPj' => $tanpaPj,
            'belumDibaca' => $belum,
            'baru' => $baru,
            /* Halaman yang ditukar sebagian lewat skrip tidak memakai kotak pop-up; kotaknya
               menyusul lewat `ringkas`. */
            'popup' => request()->ajax() ? null : self::popupPemberitahuan($u, $baru),
            /* Hanya draf yang berisi. */
            'drafLaporan' => $u->peranAktif() === PeranPengguna::SETBA
                && LaporanBaruController::drafBerisi($u->id),
        ];
    }

    /**
     * Kotak pop-up: hanya pemberitahuan BARU, masing-masing sekali per sesi. Yang sedang di halaman
     * Pemberitahuan tidak diberi pop-up.
     */
    public static function popupPemberitahuan(User $u, int $baru): ?array
    {
        /* Yang mematikan pop-up cukup diberi angka di lonceng. */
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

        return ['pemberitahuan' => Pemberitahuan::untuk($u, 1, hanya: $idBaru)->first(), 'jumlah' => count($idBaru)];
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

    /** Sebutan akun di batang atas dan kaki menu. */
    public static function pengguna(User $u): array
    {
        return match ($u->peranAktif()) {
            PeranPengguna::SETBA => ['rupa' => 'SE', 'nama' => 'Setba', 'ket' => 'Sekretariat Badan', 'peran' => 'Setba — Sekretariat Badan'],
            /* Satuan kerja: orangnya yang disebut, unit kerjanya di bawah. */
            PeranPengguna::SATKER => ['rupa' => self::inisial($u->name), 'nama' => $u->name, 'ket' => $u->satker?->namaPendek() ?? 'Satuan kerja', 'peran' => 'Satuan kerja'],
            PeranPengguna::UKI => ['rupa' => 'UK', 'nama' => 'UKI', 'ket' => 'Unit Kepatuhan Internal', 'peran' => 'UKI — Unit Kepatuhan Internal'],
            PeranPengguna::INSPEKTORAT => ['rupa' => 'IN', 'nama' => 'Inspektorat', 'ket' => 'User', 'peran' => 'Inspektorat'],
            PeranPengguna::PIMPINAN => ['rupa' => self::inisial($u->name), 'nama' => $u->name, 'ket' => $u->sebutSatkerDipimpin(), 'peran' => 'Pimpinan'],
            PeranPengguna::DTI => ['rupa' => 'DT', 'nama' => 'DTI', 'ket' => 'Data dan Teknologi Informasi', 'peran' => 'DTI — Data dan Teknologi Informasi'],
            PeranPengguna::ADMIN => ['rupa' => 'AD', 'nama' => 'Admin DTI', 'ket' => 'Administrator', 'peran' => 'Admin DTI — Administrator'],
        };
    }

    /** Dua huruf depan nama orang, untuk lingkaran di batang atas. */
    public static function inisial(?string $nama): string
    {
        $kata = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L} ]/u', ' ', (string) $nama)), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn ($k) => mb_substr($k, 0, 1), array_slice($kata, 0, 2)))) ?: '?';
    }
}
