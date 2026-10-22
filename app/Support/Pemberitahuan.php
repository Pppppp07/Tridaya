<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Tindakan;
use App\Models\User;
use App\Notifications\PemberitahuanEmail;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Menulis dan membaca pemberitahuan. `blok` menunjuk bagian rincian yang berubah. */
class Pemberitahuan
{
    /* Nama bagian rincian = judulnya di halaman. `r-siptl` sengaja tanpa keterangan tujuan. */
    public const BAGIAN = [
        'r-kepala' => 'Kepala rekomendasi',
        /* Tab Progres di baris satuan kerja. */
        'r-perkembangan' => 'Progres tindak lanjut',
        'r-tindaklanjut' => 'Laporan satuan kerja',
        'r-dokumen' => 'Perkembangan tiap satuan kerja',
        'r-pemulihan' => 'Pemulihan nilai',
        'r-riwayat' => 'Progres tindak lanjut',
        'r-ulang' => 'Tindak lanjut ulang atas penolakan BPK',
        'r-tindakan' => 'Tindakan',
        'r-arsip' => 'Arsip rekomendasi',
        'r-jejak' => 'Riwayat aktivitas',
    ];

    public static function tulis(Rekomendasi $r, string $aksi, array $untuk, ?string $blok = null,
        ?array $satkerIds = null, ?Tindakan $tindakan = null): Notifikasi
    {
        $pengguna = auth()->user();

        $pemberitahuan = Notifikasi::create([
            'rekomendasi_id' => $r->id,
            /* Bentuk tindak lanjut disebut hanya bila rekomendasinya punya lebih dari satu. */
            'tindakan_id' => $tindakan && $r->tindakan()->count() > 1 ? $tindakan->id : null,
            'waktu' => now(),
            'label_pelaku' => self::labelPelaku($pengguna),
            'aksi' => $aksi,
            'untuk_peran' => array_values(array_unique(array_map(fn ($p) => $p->value, $untuk))),
            'blok' => $blok,
        ]);

        $satker = $satkerIds ?? $r->semuaBaris()->pluck('satker_id')->unique()->values()->all();
        $pemberitahuan->satker()->attach(array_values(array_unique($satker)));

        /* Pelaku sudah tahu. */
        if ($pengguna) {
            $pemberitahuan->dibaca()->attach($pengguna->id, ['dibaca_pada' => now()]);
        }

        $penerima = collect();
        if (in_array(PeranPengguna::SATKER, $untuk, true)) {
            $penerima = self::penerimaSatker($satker, $pengguna);
        }
        /* Petugas pusat yang menyalakan email di Profil (bawaannya mati). */
        $pusat = array_values(array_filter($untuk, fn ($p) => $p !== PeranPengguna::SATKER));
        if ($pusat) {
            $penerima = $penerima->merge(User::denganPeran($pusat)
                ->where('aktif', true)->whereNotNull('email')
                ->when($pengguna, fn ($q) => $q->whereKeyNot($pengguna->id))
                ->get()->filter(fn (User $u) => $u->setelan('email')
                    && (collect($pusat)->contains(fn ($p) => $p !== PeranPengguna::PIMPINAN && $u->punyaPeran($p))
                        || ($u->punyaPeran(PeranPengguna::PIMPINAN) && array_intersect($u->satkerDipimpin(), $satker))))->values());
        }
        // Satu orang dapat memenuhi beberapa peran penerima, tetapi menerima satu email.
        self::kirim($pemberitahuan, $penerima->unique('id')->values());

        return $pemberitahuan;
    }

    /** Awal kalimat pemberitahuan draf; juga dipakai mencari baris hari ini untuk diperbarui. */
    public const AWAL_DRAF = 'Draf tindak lanjut disimpan';

    /**
     * Jenis pemberitahuan untuk filter halaman Pemberitahuan, urut alur kerja. Dikenali dari awal
     * kalimatnya (ditulis kode, jadi tetap); perubahan sistem dari log-nya; sisanya "Lainnya".
     * Kalimat pemberitahuan baru perlu didaftarkan awalannya di sini.
     */
    public const JENIS = [
        'baru' => ['nama' => 'Rekomendasi baru', 'awal' => ['Rekomendasi baru']],
        'draf' => ['nama' => 'Draf satuan kerja', 'awal' => [self::AWAL_DRAF]],
        'kiriman' => ['nama' => 'Kiriman satuan kerja', 'awal' => ['Berkas dikirim ke Setba']],
        'terus' => ['nama' => 'Berkas diteruskan', 'awal' => ['Berkas diteruskan']],
        'putusan' => ['nama' => 'Hasil UKI & Inspektorat', 'awal' => ['Validasi UKI', 'Verifikasi', 'Hasil verifikasi']],
        'ulang' => ['nama' => 'Pemberkasan ulang', 'awal' => ['Dikirim ulang']],
        'siptl' => ['nama' => 'SIPTL', 'awal' => ['Unggahan ke SIPTL', 'Status SIPTL']],
        'sistem' => ['nama' => 'Perubahan sistem', 'awal' => []],
        'lain' => ['nama' => 'Lainnya', 'awal' => []],
    ];

    public static function jenis(Notifikasi $n): string
    {
        if ($n->sistem()) {
            return 'sistem';
        }
        foreach (self::JENIS as $kunci => $j) {
            foreach ($j['awal'] as $awal) {
                if (str_starts_with((string) $n->aksi, $awal)) {
                    return $kunci;
                }
            }
        }

        return 'lain';
    }

    /** Filter jenis pada kueri pemberitahuan; jenis yang tidak dikenal diabaikan. */
    public static function saringJenis($q, ?string $jenis)
    {
        if (! $jenis || ! isset(self::JENIS[$jenis])) {
            return $q;
        }
        /* Sama dengan Notifikasi::sistem(): ada log-nya dan tanpa rekomendasi. */
        if ($jenis === 'sistem') {
            return $q->whereNotNull('log_aktivitas_id')->whereNull('rekomendasi_id');
        }
        $q->where(fn ($w) => $w->whereNull('log_aktivitas_id')->orWhereNotNull('rekomendasi_id'));
        if ($jenis === 'lain') {
            foreach (array_merge(...array_column(self::JENIS, 'awal')) as $awal) {
                $q->where('aksi', 'not like', $awal.'%');
            }

            return $q;
        }

        return $q->where(function ($w) use ($jenis) {
            foreach (self::JENIS[$jenis]['awal'] as $awal) {
                $w->orWhere('aksi', 'like', $awal.'%');
            }
        });
    }

    /**
     * Jumlah pemberitahuan pengguna ini per jenis: seluruhnya dan yang belum dibaca. Satu kueri
     * berkelompok untuk masing-masing.
     *
     * @return array{semua: array<string, int>, belum: array<string, int>}
     */
    public static function hitungJenis(User $u): array
    {
        $kasus = 'CASE WHEN log_aktivitas_id IS NOT NULL AND rekomendasi_id IS NULL THEN ?';
        $ikat = ['sistem'];
        foreach (self::JENIS as $kunci => $j) {
            foreach ($j['awal'] as $awal) {
                $kasus .= ' WHEN aksi LIKE ? THEN ?';
                array_push($ikat, $awal.'%', $kunci);
            }
        }
        $kasus .= ' ELSE ? END';
        $ikat[] = 'lain';
        $hitung = fn ($q) => $q->selectRaw("{$kasus} as jenis, count(*) as n", $ikat)->groupBy('jenis')
            ->pluck('n', 'jenis')->map(fn ($n) => (int) $n)->all();

        return [
            'semua' => $hitung(self::kueri($u)),
            'belum' => $hitung(self::kueri($u)->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))),
        ];
    }

    /**
     * Draf tindak lanjut disimpan → Setba saja, di lonceng tanpa email. Satu baris per satuan kerja
     * per hari: simpan berikutnya memperbarui kalimatnya dan menjadikannya belum dibaca lagi, supaya
     * draf yang sering disimpan tidak membanjiri daftar.
     */
    public static function drafDisimpan(Rekomendasi $r, Sasaran $s, string $kemajuan): Notifikasi
    {
        $aksi = self::AWAL_DRAF.', belum dikirim'.($kemajuan !== '' ? " — {$kemajuan}" : '');
        $tindakanId = $r->tindakan()->count() > 1 ? $s->tindakan_id : null;
        $pelaku = self::labelPelaku(auth()->user());

        $lama = Notifikasi::where('rekomendasi_id', $r->id)->where('tindakan_id', $tindakanId)
            ->where('aksi', 'like', self::AWAL_DRAF.'%')->where('waktu', '>=', now()->startOfDay())
            ->whereHas('satker', fn ($q) => $q->where('satkers.id', $s->satker_id))
            ->latest('id')->first();
        if ($lama) {
            $lama->update(['aksi' => $aksi, 'waktu' => now(), 'label_pelaku' => $pelaku]);
            $lama->dibaca()->detach();

            return $lama;
        }

        $pemberitahuan = Notifikasi::create([
            'rekomendasi_id' => $r->id, 'tindakan_id' => $tindakanId, 'waktu' => now(),
            'label_pelaku' => $pelaku, 'aksi' => $aksi,
            'untuk_peran' => [PeranPengguna::SETBA->value], 'blok' => 'r-tindaklanjut',
        ]);
        $pemberitahuan->satker()->attach([$s->satker_id]);

        return $pemberitahuan;
    }

    /** Bagian Data Master tiap kelompok aksi log — untuk baris pemberitahuan perubahan sistem. */
    private const BAGIAN_SISTEM = [
        'master.pengguna' => ['User & akses', 'Users'], 'master.unit.pj' => ['User & akses', 'Users'],
        'master.unit' => ['Satuan kerja', 'Building2'], 'master.sumber_bukti' => ['Sumber bukti', 'Paperclip'],
        'master.temuan' => ['Kategori & referensi', 'Tag'], 'master.intern' => ['Kategori & referensi', 'Tag'],
        'master.sifat' => ['Kategori & referensi', 'Tag'], 'master.bentuk' => ['Kategori & referensi', 'Tag'],
        'keamanan.alert' => ['Alert keamanan', 'ShieldCheck'],
    ];

    /**
     * Perubahan Data Master atau keputusan alert keamanan → Admin DTI lain (pelakunya tidak).
     * Unduhan dan kegiatan lain tidak.
     */
    public static function perubahanSistem(LogAktivitas $log, ?User $pelaku): ?Notifikasi
    {
        if (! self::bagianSistemDari($log->aksi)) {
            return null;
        }
        $pemberitahuan = Notifikasi::create([
            'rekomendasi_id' => null,
            'log_aktivitas_id' => $log->id,
            'waktu' => now(),
            'label_pelaku' => $pelaku?->name
                ?? (($log->rincian['konteks']['sumber'] ?? '') === 'perintah_server' ? 'Perintah server' : 'Sistem'),
            'aksi' => $log->ringkasan,
            'untuk_peran' => [PeranPengguna::ADMIN->value],
        ]);
        if ($pelaku) {
            $pemberitahuan->dibaca()->attach($pelaku->id, ['dibaca_pada' => now()]);
        }
        self::kirim($pemberitahuan, User::denganPeran(PeranPengguna::ADMIN)
            ->where('aktif', true)->whereNotNull('email')
            ->when($pelaku, fn ($q) => $q->whereKeyNot($pelaku->id))
            ->get()->filter(fn (User $u) => $u->setelan('email'))->values());

        return $pemberitahuan;
    }

    /** @return array{0: string, 1: string}|null nama bagian dan ikonnya */
    public static function bagianSistemDari(string $aksi): ?array
    {
        foreach (self::BAGIAN_SISTEM as $awal => $bagian) {
            if (str_starts_with($aksi, $awal.'.') || $aksi === $awal) {
                return $bagian;
            }
        }

        return null;
    }

    public static function bagianSistem(Notifikasi $k): string
    {
        return self::bagianSistemDari((string) $k->log?->aksi)[0] ?? 'Data Master';
    }

    /**
     * Pemberitahuan satuan kerja juga ke email penanggung jawabnya, sesudah transaksinya selesai.
     */
    private static function penerimaSatker(array $satkerIds, ?User $pelaku): Collection
    {
        /* Penanggung jawab yang mematikan email cukup menerima di lonceng. */
        return User::where('peran', PeranPengguna::SATKER->value)->where('aktif', true)
            ->whereIn('satker_id', $satkerIds)->whereNotNull('email')
            ->when($pelaku, fn ($q) => $q->whereKeyNot($pelaku->id))
            ->with('satker')->get()->filter(fn (User $u) => $u->setelan('email'))->values();
    }

    /** @param  Collection<int, User>  $penerima */
    private static function kirim(Notifikasi $pemberitahuan, Collection $penerima): void
    {
        if ($penerima->isEmpty()) {
            return;
        }

        /* Dengan antrean: email dititipkan ke tabel jobs, pekerja latar yang mengirim berurutan.
           Tanpa antrean (sync): dikirim sesudah jawaban sampai ke browser. Gagal kirim tercatat di log. */
        $sudah = false;
        $kirim = function () use ($penerima, $pemberitahuan, &$sudah) {
            if ($sudah) {
                return;
            }
            $sudah = true;
            /* Satu per satu: alamat yang ditolak server email tidak menghentikan penerima lain. */
            foreach ($penerima as $u) {
                try {
                    Notification::send($u, new PemberitahuanEmail($pemberitahuan));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        };
        /* Menitip ke antrean cukup cepat untuk langsung; kirim sync menunggu terminate. */
        $antre = config('queue.default') !== 'sync';
        DB::afterCommit(fn () => $antre || (app()->runningInConsole() && ! app()->runningUnitTests())
            ? $kirim() : app()->terminating($kirim));
    }

    public static function labelPelaku(?User $u): string
    {
        if (! $u) {
            return 'Sistem';
        }

        return $u->peranAktif() === PeranPengguna::SATKER
            ? ($u->satker?->namaPendek() ?? 'Satuan kerja')
            : $u->peranAktif()->pendek();
    }

    /** Sekali buka dimuat paling banyak sekian: daftar bertahun tidak membebani server. */
    public const BATAS_HALAMAN = 200;

    public const BATAS_PANEL = 40;

    /**
     * Pemberitahuan yang menyangkut pengguna ini, terbaru dulu. `$belum`: hanya yang belum dibaca;
     * `$hanya`: hanya id ini; `$jenis`: satu jenis (JENIS).
     */
    public static function untuk(User $u, ?int $batas = null, bool $belum = false, ?array $hanya = null, ?string $jenis = null): Collection
    {
        return self::saringJenis(self::kueri($u), $jenis)
            ->when($belum, fn ($q) => $q->whereDoesntHave('dibaca', fn ($d) => $d->where('users.id', $u->id)))
            ->when($hanya !== null, fn ($q) => $q->whereIn('id', $hanya ?: [0]))
            /* Tanda baca cukup milik pengguna ini, bukan semua pembacanya. */
            ->with(['rekomendasi.temuan.laporan', 'satker', 'tindakan.bentuk', 'log',
                'dibaca' => fn ($d) => $d->where('users.id', $u->id)])
            ->orderByDesc('waktu')->orderByDesc('id')
            ->when($batas, fn ($q) => $q->limit($batas))
            ->get();
    }

    /**
     * Daftar untuk panel atau halaman: yang terbaru (tab Semua) ditambah yang belum dibaca, masing-
     * masing dibatasi.
     *
     * @return array{daftar: Collection, semuaId: list<int>, belum: int, semua: int}
     */
    public static function daftarTerbatas(User $u, int $batas, ?string $jenis = null): array
    {
        $terbaru = self::untuk($u, $batas, jenis: $jenis);
        $belum = self::untuk($u, $batas, belum: true, jenis: $jenis);

        return [
            'daftar' => $terbaru->concat($belum)->unique('id')->values(),
            'semuaId' => $terbaru->pluck('id')->all(),
            'belum' => self::belumDibaca($u),
            'semua' => self::kueri($u)->count(),
        ];
    }

    /** Semua yang belum dibaca (satu jenis bila dipilih) ditandai dibaca sekaligus, per 500 baris. */
    public static function tandaiSemua(User $u, ?string $jenis = null): void
    {
        $sekarang = now();
        self::saringJenis(self::kueri($u), $jenis)->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->select('notifikasis.id')->chunkById(500, function ($isi) use ($u, $sekarang) {
                DB::table('notifikasi_bacas')->insertOrIgnore($isi->map(fn ($n) => [
                    'notifikasi_id' => $n->id, 'user_id' => $u->id, 'dibaca_pada' => $sekarang,
                ])->all());
            }, 'notifikasis.id', 'id');
    }

    /**
     * Untuk daftar Rekomendasi: waktu pembaruan terakhir yang menyangkut pengguna ini, dan yang belum
     * dibacanya (terbaru dulu), per rekomendasi. Dua kueri untuk seluruh daftar.
     *
     * @param  list<int>  $ids
     * @return array{terakhir: array<int, CarbonInterface>, belum: array<int, Collection>}
     */
    public static function perRekomendasi(User $u, array $ids): array
    {
        if (! $ids) {
            return ['terakhir' => [], 'belum' => []];
        }
        $terakhir = self::kueri($u)->whereIn('rekomendasi_id', $ids)
            ->selectRaw('rekomendasi_id, max(waktu) as terakhir')->groupBy('rekomendasi_id')
            ->pluck('terakhir', 'rekomendasi_id')->map(fn ($t) => \Illuminate\Support\Carbon::parse($t))->all();
        $belum = self::kueri($u)->whereIn('rekomendasi_id', $ids)
            ->whereDoesntHave('dibaca', fn ($q) => $q->where('users.id', $u->id))
            ->with('satker')->orderByDesc('waktu')->orderByDesc('id')->get()
            ->groupBy('rekomendasi_id')->all();

        return ['terakhir' => $terakhir, 'belum' => $belum];
    }

    /**
     * Alamat rincian yang dituju pemberitahuan: bagian yang berubah, dan barisnya bila tentang satu
     * satuan kerja (satuan kerja hanya dituju barisnya sendiri).
     */
    public static function tujuan(Notifikasi $n, User $u): array
    {
        $satker = $u->peranAktif()->terbatasSatker()
            ? collect((array) $u->satkerAktifId())
            : $n->satker->pluck('id');

        return array_filter([
            'rekomendasi' => $n->rekomendasi_id,
            'sorot' => $n->blok,
            'satker' => $satker->count() === 1 ? $satker->first() : null,
            'tindakan' => $n->tindakan_id,
        ]);
    }

    /** Beberapa pemberitahuan ditandai dibaca sekaligus. */
    public static function tandaiBanyak(Collection $daftar, User $u): void
    {
        if ($daftar->isEmpty()) {
            return;
        }
        $sekarang = now();
        DB::table('notifikasi_bacas')->insertOrIgnore($daftar->map(fn ($n) => [
            'notifikasi_id' => $n->id, 'user_id' => $u->id, 'dibaca_pada' => $sekarang,
        ])->values()->all());
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

    /* ---- Pemberitahuan baru ---- */

    /** Keterangan di balik ikon Info panel dan halaman — sama dengan KET_PEMBERITAHUAN. */
    public const KET = [
        'Angka merah di lonceng menghitung pemberitahuan baru — yang datang sesudah terakhir kali Anda membuka daftar ini. Angkanya hilang begitu daftarnya dibuka.',
        'Titik biru menandai pemberitahuan yang belum dibaca. Klik pemberitahuan untuk menandainya sudah dibaca dan membuka bagian yang dimaksud.',
        'Tombol di ujung tiap baris menandai dibaca atau belum dibaca tanpa membukanya.',
    ];

    /**
     * BARU: belum dibaca dan datang sesudah daftar pemberitahuan terakhir dibuka (angka lonceng).
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
        /* Termasuk yang jamnya di depan jam sekarang (jam peragaan berulang tiap hari). */
        $terbaru = self::kueri($u)->max('waktu');
        $u->forceFill(['notifikasi_dilihat_pada' => $terbaru && now()->lt($terbaru) ? $terbaru : now()])->save();
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
     * Lambang dan warna dari kalimatnya, tanpa kutipan catatan. Urutan penting: "belum memadai"
     * memuat "memadai".
     */
    public static function rupa(Notifikasi $k): array
    {
        if ($k->sistem()) {
            return ['nada' => 'biru', 'ikon' => self::bagianSistemDari((string) $k->log?->aksi)[1] ?? 'SlidersHorizontal'];
        }
        $inti = preg_split('/\.?\s*Catatan:/u', (string) $k->aksi)[0];
        /* Urusan SIPTL dibaca dari judulnya; catatan pemeriksa bisa saja menyebut SIPTL. */
        $siptl = str_contains(explode(' — ', $inti)[0], 'SIPTL');
        $aturan = [
            /* Draf lebih dulu: kalimatnya memuat "belum dikirim". */
            ['/^'.self::AWAL_DRAF.'/u', 'biru', 'Save'],
            ['/belum sesuai|belum memadai|ditolak/iu', 'merah', 'AlertTriangle'],
            ['/dikirim ulang|pemberkasan ulang/iu', 'kuning', 'RotateCcw'],
            ['/sudah sesuai|\bmemadai\b|lunas/iu', 'hijau', 'CheckCircle2'],
            ['/rekomendasi baru/iu', 'biru', 'FileText'],
            ['/dikirim|diteruskan/iu', 'biru', 'Send'],
        ];
        foreach ($aturan as [$uji, $nada, $ikon]) {
            if (preg_match($uji, $inti)) {
                return ['nada' => $nada, 'ikon' => $siptl ? 'Landmark' : $ikon];
            }
        }

        return ['nada' => 'biru', 'ikon' => $siptl ? 'Landmark' : 'Bell'];
    }

    /** "Hari ini", "Kemarin", lalu tanggalnya. */
    public static function labelHari(CarbonInterface $hari): string
    {
        $selisih = (int) $hari->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return match ($selisih) {
            0 => 'Hari ini',
            1 => 'Kemarin',
            default => Tampil::tgl($hari),
        };
    }

    /** Dikelompokkan per hari, terbaru di atas. */
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

    /** Satuan kerja hanya melihat namanya sendiri di pemberitahuan bersama. */
    public static function sebutSatker(Notifikasi $n, User $u): string
    {
        if ($n->sistem()) {
            return self::bagianSistem($n);
        }
        $satker = $u->peranAktif()->terbatasSatker()
            ? $n->satker->whereIn('id', (array) $u->satkerAktifId())
            : $n->satker;

        return $satker->map(fn ($s) => $s->namaPendek())->join(', ') ?: '—';
    }

    /**
     * Satuan kerja hanya menerima pemberitahuan yang menyebut satuan kerjanya; Admin DTI hanya
     * menerima perubahan sistem buatan orang lain.
     */
    private static function kueri(User $u, ?PeranPengguna $peran = null)
    {
        $peran ??= $u->peranAktif();
        $satker = match ($peran) {
            PeranPengguna::SATKER => [(int) $u->satker_id],
            PeranPengguna::PIMPINAN => $u->satkerDipimpin(),
            default => [],
        };

        return Notifikasi::query()
            ->whereJsonContains('untuk_peran', $peran->value)
            ->when($peran->terbatasSatker(),
                fn ($q) => $q->whereHas('satker', fn ($s) => $s->whereIn('satkers.id', $satker ?: [0])))
            ->when($peran === PeranPengguna::ADMIN,
                fn ($q) => $q->whereDoesntHave('log', fn ($l) => $l->where('user_id', $u->id)));
    }

    /** Peran lain milik akun ini yang berhak membuka pemberitahuan ini (link email lintas peran). */
    public static function peranLain(Notifikasi $n, User $u): ?PeranPengguna
    {
        foreach ($u->semuaPeran() as $p) {
            if ($p !== $u->peranAktif() && self::kueri($u, $p)->whereKey($n->id)->exists()) {
                return $p;
            }
        }

        return null;
    }
}
