<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\Tindakan;
use App\Models\User;
use App\Notifications\PemberitahuanEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Menulis dan membaca pemberitahuan — padanan `beritahu` dan `notifSaya`
 * prototipe.
 *
 * `blok` menunjuk bagian mana di halaman rincian yang berubah karena tindakan
 * ini. Tanpa itu pemberitahuan hanya bisa mengantar ke halamannya, dan pembacanya
 * menyusuri sendiri halaman panjang untuk menemukan apa yang diberitahukan.
 */
class Pemberitahuan
{
    /* Nama bagian pada halaman rincian, dipakai sebagai keterangan tujuan pada
       baris pemberitahuan. Namanya sama persis dengan judul di halaman itu — sama
       dengan `NAMA_BAGIAN` prototipe. Pemberitahuan SIPTL (`r-siptl`) sengaja tanpa
       keterangan tujuan, seperti prototipe: menekannya tetap membuka berkasnya
       dan menunjuk bagiannya. */
    public const BAGIAN = [
        'r-kepala'       => 'Kepala rekomendasi',
        /* Pemberitahuan penerusan berkas dulu menunjuk kartu riwayat; sejak 21 Sep
           tab Riwayat di baris satuan kerjanya. */
        'r-perkembangan' => 'Riwayat status tindak lanjut',
        'r-tindaklanjut' => 'Laporan satuan kerja',
        'r-dokumen'      => 'Perkembangan tiap satuan kerja',
        'r-pemulihan'    => 'Pemulihan nilai',
        'r-riwayat'      => 'Riwayat status tindak lanjut',
        'r-ulang'        => 'Tindak lanjut ulang atas penolakan BPK',
        'r-tindakan'     => 'Tindakan',
        'r-arsip'        => 'Arsip rekomendasi',
        'r-jejak'        => 'Riwayat aktivitas',
    ];

    /**
     * @param  list<PeranPengguna>  $untuk      peran yang berwenang atau berkepentingan
     * @param  list<int>|null       $satkerIds  satuan kerja yang diberi tahu; kosong = seluruh satuan kerja rekomendasinya
     * @param  bool                 $sistem     dikirim perintah terjadwal — belum dibaca siapa pun
     */
    public static function tulis(Rekomendasi $r, string $aksi, array $untuk, ?string $blok = null,
        ?array $satkerIds = null, ?Tindakan $tindakan = null, bool $sistem = false,
        ?string $pelaku = null): Notifikasi
    {
        $pengguna = $sistem ? null : auth()->user();

        $pemberitahuan = Notifikasi::create([
            'rekomendasi_id' => $r->id,
            /* Bentuk tindak lanjutnya disebut hanya kalau rekomendasinya punya
               lebih dari satu — kalau cuma satu, menyebutnya menambah bacaan. */
            'tindakan_id'    => $tindakan && $r->tindakan()->count() > 1 ? $tindakan->id : null,
            'waktu'          => now(),
            'label_pelaku'   => $pelaku ?? self::labelPelaku($pengguna),
            'aksi'           => $aksi,
            'untuk_peran'    => array_values(array_unique(array_map(fn ($p) => $p->value, $untuk))),
            'blok'           => $blok,
        ]);

        $satker = $satkerIds ?? $r->semuaBaris()->pluck('satker_id')->unique()->values()->all();
        $pemberitahuan->satker()->attach(array_values(array_unique($satker)));

        /* Yang mengerjakannya sudah tahu — pemberitahuan itu untuk pihak lain. Kiriman
           otomatis tidak punya pelaku, jadi belum terbaca oleh siapa pun
           (W16 prototipe). */
        if ($pengguna) {
            $pemberitahuan->dibaca()->attach($pengguna->id, ['dibaca_pada' => now()]);
        }

        if (in_array(PeranPengguna::SATKER, $untuk, true)) {
            self::kirimEmail($pemberitahuan, $satker, $pengguna);
        }
        /* Petugas pusat yang menyalakan "Kirim juga ke email" di Profil →
           Pemberitahuan (28 Sep). Bawaannya mati — Setba, UKI, dan Inspektorat
           membuka aplikasinya tiap hari, email untuk tiap gerak berkas justru
           menenggelamkan yang penting. */
        $pusat = array_values(array_filter($untuk, fn ($p) => $p !== PeranPengguna::SATKER));
        if ($pusat) {
            self::kirim($pemberitahuan, User::whereIn('peran', array_map(fn ($p) => $p->value, $pusat))
                ->where('aktif', true)->whereNotNull('email')
                ->when($pengguna, fn ($q) => $q->whereKeyNot($pengguna->id))
                ->get()->filter(fn (User $u) => $u->setelan('email'))->values());
        }

        return $pemberitahuan;
    }

    /**
     * Pemberitahuan untuk satuan kerja juga dikirim ke email penanggung jawabnya — satu
     * akun aktif per unit kerja (App\Support\PenanggungJawab). Dikirim sesudah
     * transaksinya selesai: pemberitahuan yang batal tersimpan tidak boleh sudah
     * telanjur sampai di kotak email orang.
     *
     * @param  list<int>  $satkerIds
     */
    private static function kirimEmail(Notifikasi $pemberitahuan, array $satkerIds, ?User $pelaku): void
    {
        /* Penanggung jawab yang mematikan email di Profil → Pemberitahuan
           (28 Sep) cukup menerima pemberitahuannya di lonceng. */
        self::kirim($pemberitahuan, User::where('peran', PeranPengguna::SATKER->value)->where('aktif', true)
            ->whereIn('satker_id', $satkerIds)->whereNotNull('email')
            ->when($pelaku, fn ($q) => $q->whereKeyNot($pelaku->id))
            ->with('satker')->get()->filter(fn (User $u) => $u->setelan('email'))->values());
    }

    /** @param  Collection<int, User>  $penerima */
    private static function kirim(Notifikasi $pemberitahuan, Collection $penerima): void
    {
        if ($penerima->isEmpty()) {
            return;
        }

        /* Dikirim SESUDAH jawaban sampai ke browser (27 Sep), bukan di
           tengah permintaan: server email yang lambat tidak membuat halaman
           menunggu, dan email yang gagal terkirim tidak membuat tindakan yang
           sudah tersimpan tampak gagal — error-nya tercatat di log aplikasi.
           Di server yang memakai antrean (QUEUE_CONNECTION + queue:work),
           PemberitahuanEmail bisa dijadikan ShouldQueue tanpa mengubah baris ini. */
        $sudah = false;
        $kirim = function () use ($penerima, $pemberitahuan, &$sudah) {
            if ($sudah) {
                return;
            }
            $sudah = true;
            try {
                Notification::send($penerima, new PemberitahuanEmail($pemberitahuan));
            } catch (\Throwable $e) {
                report($e);
            }
        };
        /* Permintaan browser: sesudah jawabannya terkirim (terminate).
           Perintah server (penyapu draf terjadwal): langsung. */
        DB::afterCommit(fn () => app()->runningInConsole() && ! app()->runningUnitTests()
            ? $kirim() : app()->terminating($kirim));
    }

    public static function labelPelaku(?User $u): string
    {
        if (! $u) {
            return 'Sistem';
        }

        return $u->peran === PeranPengguna::SATKER
            ? ($u->satker?->namaPendek() ?? 'Satuan kerja')
            : $u->peran->pendek();
    }

    /** Pemberitahuan yang menyangkut pengguna ini, terbaru dulu. */
    public static function untuk(User $u): Collection
    {
        return self::kueri($u)
            ->with('rekomendasi.temuan.laporan', 'satker', 'tindakan.bentuk', 'dibaca')
            ->orderByDesc('waktu')->orderByDesc('id')
            ->get();
    }

    public static function belumDibaca(User $u): int
    {
        return self::kueri($u)
            ->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->count();
    }

    public static function sudahDibaca(Notifikasi $n, User $u): bool
    {
        return $n->dibaca->contains('id', $u->id);
    }

    /* ================= pemberitahuan dirombak (26 Sep) ================= */

    /** Keterangan di balik ikon Info panel dan halaman — sama dengan KET_PEMBERITAHUAN. */
    public const KET = [
        'Angka merah di lonceng menghitung pemberitahuan baru — yang datang sesudah terakhir kali Anda membuka daftar ini. Angkanya hilang begitu daftarnya dibuka.',
        'Titik biru menandai pemberitahuan yang belum dibaca. Klik pemberitahuan untuk menandainya sudah dibaca dan membuka bagian yang dimaksud.',
        'Tombol di ujung tiap baris menandai dibaca atau belum dibaca tanpa membukanya.',
    ];

    /**
     * Pemberitahuan BARU: belum dibaca, dan datang sesudah terakhir kali penggunanya
     * membuka daftar pemberitahuan (panel lonceng atau halamannya). Inilah yang
     * dihitung angka di lonceng — padanan `pemberitahuanBaru` prototipe.
     */
    private static function kueriBaru(User $u)
    {
        return self::kueri($u)
            ->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->when($u->notifikasi_dilihat_pada, fn ($q) => $q->where('waktu', '>', $u->notifikasi_dilihat_pada));
    }

    public static function jumlahBaru(User $u): int
    {
        return self::kueriBaru($u)->count();
    }

    /** @return list<int> */
    public static function idBaru(User $u): array
    {
        return self::kueriBaru($u)->pluck('id')->all();
    }

    /** Daftarnya dibuka: semua yang ada kini sudah pernah tampil. */
    public static function catatDilihat(User $u): void
    {
        $u->forceFill(['notifikasi_dilihat_pada' => now()])->save();
    }

    /** Tanda dibaca satu pemberitahuan — dipasang atau dilepas. */
    public static function tandai(Notifikasi $n, User $u, bool $baca): void
    {
        $ada = $n->dibaca()->where('users.id', $u->id)->exists();
        if ($baca && ! $ada) {
            $n->dibaca()->attach($u->id, ['dibaca_pada' => now()]);
        } elseif (! $baca && $ada) {
            $n->dibaca()->detach($u->id);
        }
    }

    /**
     * Lambang dan warna satu pemberitahuan, dibaca dari kalimatnya — tanpa catatan yang
     * dikutip di belakangnya, supaya kata di dalam kutipan tidak ikut
     * menentukan. Urutannya penting: "belum memadai" memuat kata "memadai".
     * Padanan `RUPA_PEMBERITAHUAN`/`rupaPemberitahuan` prototipe — ubah keduanya bersamaan.
     *
     * @return array{nada:string, ikon:string}
     */
    public static function rupa(Notifikasi $k): array
    {
        $inti = preg_split('/\.?\s*Catatan:/u', (string) $k->aksi)[0];
        $siptl = str_contains($inti, 'SIPTL');
        $aturan = [
            ['/belum sesuai|belum memadai|tidak dapat ditindaklanjuti|ditolak/iu', 'merah', 'AlertTriangle'],
            ['/terkirim otomatis/iu', 'kuning', 'Clock'],
            ['/dikirim ulang|pemberkasan ulang/iu', 'kuning', 'RotateCcw'],
            ['/sudah sesuai|\bmemadai\b|lunas/iu', 'hijau', 'CheckCircle2'],
            ['/rekomendasi baru/iu', 'biru', 'FileText'],
            ['/kemajuan/iu', 'biru', 'TrendingUp'],
            ['/dikirim|diteruskan/iu', 'biru', 'Send'],
        ];
        foreach ($aturan as [$uji, $nada, $ikon]) {
            if (preg_match($uji, $inti)) {
                return ['nada' => $nada, 'ikon' => $siptl ? 'Landmark' : $ikon];
            }
        }

        return ['nada' => 'biru', 'ikon' => $siptl ? 'Landmark' : 'Bell'];
    }

    /** "Hari ini", "Kemarin", lalu tanggalnya — padanan `labelHari`. */
    public static function labelHari(\Carbon\CarbonInterface $hari): string
    {
        $selisih = (int) $hari->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return match ($selisih) {
            0 => 'Hari ini',
            1 => 'Kemarin',
            default => Tampil::tgl($hari),
        };
    }

    /**
     * Pemberitahuan dikelompokkan per hari, yang terbaru di atas.
     *
     * @return list<array{hari:string, label:string, isi:Collection}>
     */
    public static function kelompokHari(Collection $daftar): array
    {
        return $daftar->sortByDesc(fn ($k) => $k->waktu->format('Y-m-d H:i:s').sprintf('%010d', $k->id))
            ->groupBy(fn ($k) => $k->waktu->toDateString())
            ->map(fn ($isi, $hari) => ['hari' => $hari, 'label' => self::labelHari($isi->first()->waktu), 'isi' => $isi->values()])
            ->values()->all();
    }

    /** Pemberitahuan ini memang ditujukan kepada pengguna ini. */
    public static function boleh(Notifikasi $n, User $u): bool
    {
        return self::kueri($u)->whereKey($n->id)->exists();
    }

    /**
     * Satuan kerja yang disebut pada baris pemberitahuan. Satuan kerja hanya melihat
     * namanya sendiri — pemberitahuan bersama tidak boleh membocorkan siapa lagi yang
     * kebagian.
     */
    public static function sebutSatker(Notifikasi $n, User $u): string
    {
        $satker = $u->peran === PeranPengguna::SATKER
            ? $n->satker->where('id', $u->satker_id)
            : $n->satker;

        return $satker->map(fn ($s) => $s->namaPendek())->join(', ') ?: '—';
    }

    /**
     * Satuan kerja hanya menerima pemberitahuan yang menyebut satuan kerjanya. Admin
     * membaca apa yang dibaca Setba.
     */
    private static function kueri(User $u)
    {
        $peran = $u->peran === PeranPengguna::ADMIN ? PeranPengguna::SETBA : $u->peran;

        return Notifikasi::query()
            ->whereJsonContains('untuk_peran', $peran->value)
            ->when($u->peran === PeranPengguna::SATKER,
                fn ($q) => $q->whereHas('satker', fn ($s) => $s->where('satkers.id', $u->satker_id)));
    }
}
