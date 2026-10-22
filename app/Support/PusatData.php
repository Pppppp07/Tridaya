<?php

namespace App\Support;

use App\Enums\PeranPengguna as P;
use App\Models\LogAktivitas;
use App\Models\Satker;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Aturan hitung Data master untuk Admin DTI (diuji di PusatDataTest). */
final class PusatData
{
    public const HARI_SEPI = 30;       // "Tidak masuk 30 hari terakhir"

    public const HARI_BARU = 7;        // "Masuk 7 hari terakhir"

    public const HARI_PERUBAHAN = 7;   // Ringkasan → "Perubahan 7 hari"

    /** Urutan peran di daftar Pengguna. */
    public const URUT_PERAN = ['setba', 'uki', 'inspektorat', 'pimpinan', 'dti', 'admin', 'satker'];

    public const KEADAAN = ['semua', 'pusat', 'pj', 'nonaktif'];

    public const KEADAAN_UNIT = ['semua', 'aktif', 'nonaktif', 'tanpa-pj'];

    /** Pilihan filter "Terakhir masuk" di daftar Pengguna. */
    public const PILIHAN_MASUK = [
        '' => 'Semua',
        'baru' => 'Masuk '.self::HARI_BARU.' hari terakhir',
        'sepi' => 'Tidak masuk '.self::HARI_SEPI.' hari',
        'belum' => 'Belum pernah masuk',
    ];

    public const AKIBAT_PERAN_KOSONG = [
        'setba' => 'Tidak ada akun aktif yang berwenang mencatat laporan dan meneruskan berkas.',
        'uki' => 'Tidak ada akun aktif yang berwenang menelaah berkas sebagai UKI.',
        'inspektorat' => 'Tidak ada akun aktif yang berwenang memverifikasi berkas sebagai Inspektorat.',
        'admin' => 'Tidak ada akun aktif yang dapat mengelola pengguna, akses, dan data referensi.',
    ];

    /**
     * Peran operasional/administrasi yang tidak boleh kosong pemegang aktifnya. Pimpinan dan DTI
     * hanya melihat.
     */
    public static function peranPenting(): array
    {
        return [P::SETBA, P::UKI, P::INSPEKTORAT, P::ADMIN];
    }

    /* ---- Waktu ---- */

    /** Selisih hari kalender `sampai` − `dari`. */
    public static function selisihHari(CarbonInterface|string $dari, CarbonInterface|string $sampai): int
    {
        $a = Carbon::parse($dari)->startOfDay();
        $b = Carbon::parse($sampai)->startOfDay();

        return (int) $a->diffInDays($b, false);
    }

    /** Apakah waktu masuk terakhir cocok dengan pilihan "Terakhir masuk". */
    public static function cocokMasuk(string $mode, ?CarbonInterface $waktu, CarbonInterface $hariIni): bool
    {
        return match ($mode) {
            'belum' => $waktu === null,
            'baru' => $waktu !== null && self::selisihHari($waktu, $hariIni) <= self::HARI_BARU,
            'sepi' => $waktu === null || self::selisihHari($waktu, $hariIni) > self::HARI_SEPI,
            default => true,
        };
    }

    /* ---- Pengguna ---- */

    /** Akun di daftar Pengguna menurut filternya; urutan asal dipertahankan. */
    public static function saringAkun(Collection $akun, array $f, CarbonInterface $hariIni): Collection
    {
        $kata = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $keadaan = $f['filter'] ?? 'semua';
        $peran = (string) ($f['peran'] ?? '');
        $masuk = (string) ($f['masuk'] ?? '');

        return $akun->filter(function ($a) use ($kata, $keadaan, $peran, $masuk, $hariIni) {
            $satker = $a->peran === P::SATKER;
            if ($keadaan === 'pusat' && ! ($a->aktif && (! $satker || count($a->semuaPeran()) > 1))) {
                return false;
            }
            if ($keadaan === 'pj' && ! ($a->aktif && $satker)) {
                return false;
            }
            if ($keadaan === 'nonaktif' && $a->aktif) {
                return false;
            }
            if ($peran !== '' && ! $a->punyaPeran($peran)) {
                return false;
            }
            if (! self::cocokMasuk($masuk, $a->terakhir_masuk_pada, $hariIni)) {
                return false;
            }

            return $kata === '' || str_contains(mb_strtolower(
                $a->name.' '.$a->nip.' '.$a->email.' '.implode(' ', array_map(fn ($p) => $p->pendek(), $a->semuaPeran())).' '.($a->satker?->nama ?? '').' '.($a->punyaPeran(P::PIMPINAN) ? $a->daftarSatkerDipimpin(true) : '')), $kata);
        })->values();
    }

    /**
     * Urutan daftar Pengguna. "masuk": naik = terlama dulu (belum pernah masuk paling atas), turun
     * = terbaru dulu.
     */
    public static function urutkanAkun(Collection $akun, string $urut, string $arah): Collection
    {
        if (! in_array($urut, ['nama', 'peran', 'masuk'], true)) {
            return $akun;
        }
        $tanda = $arah === 'turun' ? -1 : 1;
        $nilai = match ($urut) {
            'nama' => fn ($a) => mb_strtolower($a->name),
            'peran' => fn ($a) => (int) array_search($a->peran->value, self::URUT_PERAN, true),
            'masuk' => fn ($a) => $a->terakhir_masuk_pada?->getTimestamp() ?? PHP_INT_MIN,
        };

        return $akun->values()->map(fn ($a, $i) => [$a, $i])
            ->sort(function ($p, $q) use ($nilai, $tanda) {
                $c = $nilai($p[0]) <=> $nilai($q[0]);

                return ($c * $tanda) ?: ($p[1] <=> $q[1]);
            })->map(fn ($p) => $p[0])->values();
    }

    /** @return array{semua:int, aktif:int, pusat:int, pj:int, nonaktif:int} */
    public static function hitungAkun(Collection $akun): array
    {
        return [
            'semua' => $akun->count(),
            'aktif' => $akun->where('aktif', true)->count(),
            'pusat' => $akun->filter(fn ($a) => $a->aktif && ($a->peran !== P::SATKER || count($a->semuaPeran()) > 1))->count(),
            'pj' => $akun->filter(fn ($a) => $a->aktif && $a->peran === P::SATKER)->count(),
            'nonaktif' => $akun->where('aktif', false)->count(),
        ];
    }

    /* ---- Unit kerja ---- */

    /** Unit kerja menurut filternya. `$pakai` = id unit → ['rek' => n, …]. */
    public static function saringUnit(Collection $unit, array $f, array $pakai = []): Collection
    {
        $kata = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $keadaan = $f['filter'] ?? 'semua';
        $hasil = $unit->filter(function ($u) use ($kata, $keadaan) {
            $pj = $u->penanggungJawab;
            if ($keadaan === 'aktif' && ! $u->aktif) {
                return false;
            }
            if ($keadaan === 'nonaktif' && $u->aktif) {
                return false;
            }
            if ($keadaan === 'tanpa-pj' && ! ($u->aktif && ! $pj)) {
                return false;
            }

            return $kata === '' || str_contains(mb_strtolower(
                $u->nama.' '.$u->namaPendek().' '.$u->kode_satker.' '.($pj ? $pj->name.' '.$pj->nip : '')), $kata);
        })->values();

        $urut = $f['urut'] ?? '';
        if ($urut === 'rekomendasi') {
            $tanda = ($f['arah'] ?? 'turun') === 'naik' ? 1 : -1;

            return self::stabil($hasil, fn ($u) => (int) ($pakai[$u->id]['rek'] ?? 0), $tanda);
        }
        if ($urut === 'nama') {
            $tanda = ($f['arah'] ?? 'naik') === 'turun' ? -1 : 1;

            return self::stabil($hasil, fn ($u) => mb_strtolower($u->namaPendek()), $tanda);
        }

        return $hasil;
    }

    /* ---- Yang perlu diurus ---- */

    /**
     * Pemeriksaan administrasi di Ringkasan: id, tingkat ("awas"/"saran"), judul, ket, n, tuju.
     * Yang awas lebih dulu.
     */
    public static function periksaKesehatan(Collection $unit, Collection $akun, CarbonInterface $hariIni, ?array $nganggur = []): array
    {
        $hasil = [];

        $tanpaPj = $unit->filter(fn ($u) => $u->aktif && ! $u->penanggungJawab)->count();
        if ($tanpaPj) {
            $hasil[] = ['id' => 'unit-tanpa-pj', 'tingkat' => 'awas', 'n' => $tanpaPj,
                'judul' => "$tanpaPj satuan kerja aktif belum punya penanggung jawab",
                'ket' => 'Belum ada akun penanggung jawab aktif untuk satuan kerja tersebut. Tetapkan penanggung jawab agar akses dan pemberitahuannya terhubung.',
                'tuju' => ['tab' => 'unit', 'saring' => ['filter' => 'tanpa-pj']]];
        }

        $aktifPer = fn (P $p) => $akun->filter(fn ($a) => $a->aktif && $a->punyaPeran($p))->count();
        foreach (self::peranPenting() as $p) {
            if ($aktifPer($p) === 0) {
                $hasil[] = ['id' => 'peran-kosong-'.$p->value, 'tingkat' => 'awas', 'n' => null,
                    'judul' => 'Belum ada akun '.$p->pendek().' yang aktif',
                    'ket' => self::AKIBAT_PERAN_KOSONG[$p->value],
                    'tuju' => ['tab' => 'pengguna', 'saring' => ['peran' => $p->value]]];
            }
        }
        $pimpinanTanpaUnit = $akun->filter(fn ($a) => $a->aktif && $a->punyaPeran(P::PIMPINAN) && ! $a->pimpinan_semua
            && ! collect($a->satkerDipimpin())->contains(fn ($id) => $unit->contains('id', $id)))->count();
        if ($pimpinanTanpaUnit) {
            $hasil[] = ['id' => 'pimpinan-tanpa-unit', 'tingkat' => 'awas', 'n' => $pimpinanTanpaUnit,
                'judul' => "$pimpinanTanpaUnit user Pimpinan belum memiliki cakupan Satker",
                'ket' => 'Tentukan Satker yang dipimpin melalui Kelola akses. Data Pimpinan dibatasi sampai cakupannya ditetapkan.',
                'tuju' => ['tab' => 'pengguna', 'saring' => ['peran' => 'pimpinan']]];
        }
        if ($nganggur === null) {
            return $hasil;
        }

        $tunggal = array_values(array_filter(self::peranPenting(), fn (P $p) => $aktifPer($p) === 1));
        if ($tunggal) {
            $hasil[] = ['id' => 'peran-tunggal', 'tingkat' => 'saran', 'n' => null,
                'judul' => 'Hanya satu akun aktif untuk '.self::sambungKata(array_map(fn (P $p) => $p->pendek(), $tunggal)),
                'ket' => 'Peran tersebut sudah memiliki pemegang aktif. Tinjau kebutuhan petugas tambahan sesuai penetapan organisasi.',
                'tuju' => ['tab' => 'pengguna', 'saring' => ['filter' => 'pusat']]];
        }

        $pjBelum = $akun->filter(fn ($a) => $a->aktif && $a->peran === P::SATKER
            && self::cocokMasuk('belum', $a->terakhir_masuk_pada, $hariIni))->count();
        if ($pjBelum) {
            $hasil[] = ['id' => 'pj-belum-masuk', 'tingkat' => 'saran', 'n' => $pjBelum,
                'judul' => "$pjBelum penanggung jawab satuan kerja belum pernah masuk",
                'ket' => 'Akun belum memiliki riwayat masuk yang tercatat. Status tindak lanjut satuan kerja tetap dilihat pada rekomendasinya.',
                'tuju' => ['tab' => 'pengguna', 'saring' => ['filter' => 'pj', 'masuk' => 'belum']]];
        }

        $pusatSepi = $akun->filter(fn ($a) => $a->aktif && $a->peran !== P::SATKER
            && self::cocokMasuk('sepi', $a->terakhir_masuk_pada, $hariIni))->count();
        if ($pusatSepi) {
            $hasil[] = ['id' => 'pusat-sepi', 'tingkat' => 'saran', 'n' => $pusatSepi,
                'judul' => "$pusatSepi akun petugas pusat tidak masuk ".self::HARI_SEPI.' hari terakhir',
                'ket' => 'Tinjau penugasan dan kebutuhan aksesnya sebelum memutuskan perubahan akun.',
                'tuju' => ['tab' => 'pengguna', 'saring' => ['filter' => 'pusat', 'masuk' => 'sepi']]];
        }

        $ada = array_values(array_filter($nganggur, fn ($x) => $x['n'] > 0));
        if ($ada) {
            $total = array_sum(array_column($ada, 'n'));
            $hasil[] = ['id' => 'kategori-nganggur', 'tingkat' => 'saran', 'n' => $total,
                'judul' => "$total kategori dan label aktif belum dipakai",
                'ket' => implode(', ', array_map(fn ($x) => $x['n'].' '.$x['nama'], $ada))
                    .'. Tinjau kebutuhan kategori sebelum menonaktifkan atau menggabungkannya.',
                'tuju' => ['tab' => $ada[0]['tab'], 'saring' => $ada[0]['saring'] ?? []]];
        }

        return self::stabil(collect($hasil), fn ($x) => $x['tingkat'] === 'awas' ? 0 : 1, 1)->all();
    }

    /**
     * Perubahan data master dan hak akses dalam HARI_PERUBAHAN hari terakhir: jumlah dan jumlah
     * orangnya.
     */
    public static function hitungPerubahan(CarbonInterface $hariIni): array
    {
        $dari = Carbon::parse($hariIni)->startOfDay()->subDays(self::HARI_PERUBAHAN - 1);
        $sampai = Carbon::parse($hariIni)->startOfDay()->addDay();
        $baru = LogAktivitas::whereIn('kelompok', ['master', 'pengguna'])
            ->where('waktu', '>=', $dari)->where('waktu', '<', $sampai)->get(['nama']);

        return ['n' => $baru->count(), 'orang' => $baru->pluck('nama')->filter()->unique()->count()];
    }

    /** "Setba", "Setba dan UKI", "Setba, UKI, dan Admin DTI". */
    public static function sambungKata(array $daftar): string
    {
        $daftar = array_values($daftar);

        return count($daftar) <= 2 ? implode(' dan ', $daftar)
            : implode(', ', array_slice($daftar, 0, -1)).', dan '.end($daftar);
    }

    /* ---- Masukan yang ditempel ---- */

    /** Nama dari kotak "Tambah banyak": satu per baris, tanpa kembar dan kosong. */
    public static function uraiBaris(?string $teks): array
    {
        $lihat = [];
        $hasil = [];
        foreach (preg_split('/\r?\n/', (string) $teks) as $x) {
            $x = trim((string) preg_replace('/\s+/u', ' ', $x));
            $k = mb_strtolower($x);
            if ($x === '' || isset($lihat[$k])) {
                continue;
            }
            $lihat[$k] = true;
            $hasil[] = $x;
        }

        return $hasil;
    }

    /**
     * NIP dari kotak "Tambah beberapa pengguna": per baris, koma, atau titik koma; boleh
     * berkelompok. Yang kembar dihitung sekali.
     */
    public static function uraiNip(?string $teks): array
    {
        $lihat = [];
        $hasil = [];
        foreach (preg_split('/[\r\n,;\t]+/', (string) $teks) as $x) {
            $x = (string) preg_replace('/\D/', '', $x);
            if ($x === '' || isset($lihat[$x])) {
                continue;
            }
            $lihat[$x] = true;
            $hasil[] = $x;
        }

        return $hasil;
    }

    /* ---- Urutan ---- */

    /** Geser satu butir di antara sesamanya; null bila sudah di ujung. */
    public static function geser(array $daftar, int $id, string $arah): ?array
    {
        $daftar = array_values($daftar);
        $di = array_search($id, $daftar, true);
        if ($di === false) {
            return null;
        }
        $tujuan = $di + ($arah === 'atas' ? -1 : 1);
        if ($tujuan < 0 || $tujuan >= count($daftar)) {
            return null;
        }
        [$daftar[$di], $daftar[$tujuan]] = [$daftar[$tujuan], $daftar[$di]];

        return $daftar;
    }

    /* ---- CSV ---- */

    /** CSV untuk Excel Indonesia: pemisah titik koma, diawali BOM. */
    public static function unduhCsv(string $berkas, array $kepala, iterable $baris): StreamedResponse
    {
        return response()->streamDownload(function () use ($kepala, $baris) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, $kepala, ';');
            foreach ($baris as $b) {
                fputcsv($o, $b, ';');
            }
            fclose($o);
        }, $berkas.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * NIP dan angka panjang ditulis ="…" supaya Excel membacanya sebagai teks (tidak jadi
     * 1,97E+17).
     */
    public static function teksExcel(string $v): string
    {
        return preg_match('/^\d{12,}$/', $v) ? '="'.$v.'"' : $v;
    }

    /* ---- Alamat kembali ---- */

    /**
     * Alamat daftar Data master (`kembali`), supaya filter tetap sesudah mengubah sesuatu. Hanya
     * alamat di dalam Data master yang dipercaya.
     */
    public static function alamatKembali(?string $k): ?string
    {
        $k = (string) $k;

        return $k !== '' && preg_match('#^/data-master(\?[^\s\#]*)?\z#', $k) ? $k : null;
    }

    /* ---- Pembantu ---- */

    /** Urutan stabil: seri dipecahkan oleh urutan asalnya. */
    private static function stabil(Collection $daftar, callable $nilai, int $tanda): Collection
    {
        return $daftar->values()->map(fn ($x, $i) => [$x, $i])
            ->sort(fn ($p, $q) => (($nilai($p[0]) <=> $nilai($q[0])) * $tanda) ?: ($p[1] <=> $q[1]))
            ->map(fn ($p) => $p[0])->values();
    }
}
