<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\DrafLaporan;
use App\Models\ItemPermintaan;
use App\Models\KategoriTemuan;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\PermintaanDokumen;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Rules\LinkBukti;
use App\Support\Aktivitas;
use App\Support\Jejak;
use App\Support\KebijakanBukti;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catat laporan baru: isi laporan (satu halaman bertab), lalu tinjau dan kirim. Semua panel
 * dirender — yang tidak terpilih disembunyikan — supaya satu kiriman memuat seluruh isian. Draf
 * disimpan per pengguna.
 */
class LaporanBaruController extends Controller
{
    /* ---- Bentuk draf ---- */

    private function boleh(): void
    {
        abort_unless(in_array(auth()->user()->peranAktif(), [PeranPengguna::SETBA, PeranPengguna::ADMIN], true),
            403, 'Hanya Setba yang mencatat laporan pemeriksaan.');
    }

    private function id(string $awalan): string
    {
        return $awalan.Str::lower(Str::random(6));
    }

    private function kosong(): array
    {
        /* Pengenal awal tetap (t0/r0/k0), bukan acak: tanpa draf formulir dibangun ulang tiap
           permintaan, dan isian kiriman pertama bisa hilang. */
        $t = $this->temKosong();
        $t['id'] = 't0';
        $t['rekom'][0]['id'] = 'r0';
        $t['rekom'][0]['tindakan'][0]['id'] = 'k0';

        return [
            'n' => 1,
            'surat' => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => ''],
            'berkas' => ['judul' => '', 'link' => ''],
            'temuan' => [$t],
            'aktif' => $t['id'],
            /* Rekomendasi terpilih pada temuan yang terpilih. `null` = bawaan,
               rekomendasi pertama temuan itu. */
            'buka' => null,
            /* Tindak lanjut terpilih per rekomendasi, dan bagian surat terbuka (`null` = terbuka
               selama surat belum lengkap). */
            'tl' => [],
            'surat_buka' => null,
            'disimpan' => null,
            'versi' => 2,
        ];
    }

    private function temKosong(): array
    {
        return [
            'id' => $this->id('t'), 'nomor' => '', 'judul' => '', 'sebab' => '', 'akibat' => '',
            /* Kosong, bukan satuan kerja pertama: pilihan bawaan bisa diam-diam menugaskan
               satuan kerja yang salah. */
            'satker' => [],
            'kategori' => '',
            'intern' => (string) (Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
                ->where('aktif', true)->orderBy('urutan')->orderBy('id')->value('id') ?? ''),
            'rekom' => [$this->rekKosong()],
        ];
    }

    /** Sifat awal: Administratif bila aktif, selain itu sifat aktif pertama. */
    private function sifatAwal(): ?Referensi
    {
        $aktif = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->where('aktif', true)
            ->orderBy('urutan')->orderBy('id')->get();

        return $aktif->firstWhere('nama', 'Administratif') ?? $aktif->first();
    }

    private function rekKosong(): array
    {
        return [
            'id' => $this->id('r'), 'uraian' => '', 'ref_lhp' => '',
            /* Ref LHP mengikuti nomor temuan sampai disunting tangan. */
            'ref_otomatis' => true,
            'sifat' => (string) ($this->sifatAwal()?->id ?? ''),
            'angsur' => '', 'kunci' => false,
            /* Satu tindak lanjut untuk memulai. */
            'tindakan' => [$this->tindakanKosong()],
        ];
    }

    private function tindakanKosong(?string $bentuk = null, string $renaksi = ''): array
    {
        $bentuk ??= (string) ($this->bentukMaster()->firstWhere('aktif', true)?->id ?? '');

        return [
            'id' => $this->id('k'), 'bentuk' => $bentuk, 'satker' => [],
            /* Usulan dari bentuk tindak lanjut; Setba tetap bisa mengubah. */
            'dokumen' => $this->usulanDokumen($bentuk),
            'tgl_renaksi' => $renaksi, 'target' => '', 'catatan' => '',
        ];
    }

    /* ---- Bentuk tindak lanjut (data master) ----
       Isian `bentuk` menyimpan id referensinya. */

    private ?\Illuminate\Support\Collection $daftarBentuk = null;

    /** Seluruh bentuk, termasuk yang nonaktif (draf lama boleh tetap memakainya). */
    public function bentukMaster(): \Illuminate\Support\Collection
    {
        return $this->daftarBentuk ??= Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
            ->orderBy('urutan')->orderBy('id')->get();
    }

    public function namaBentuk(string $id): string
    {
        return $id === '' ? '' : (string) $this->bentukMaster()->firstWhere('id', (int) $id)?->nama;
    }

    /** Dokumen usulan sebuah bentuk; tanpa usulan = satu baris kosong untuk diisi. */
    private function usulanDokumen(string $id): array
    {
        $dok = $id === '' ? [] : (array) ($this->bentukMaster()->firstWhere('id', (int) $id)?->dokumen ?? []);

        return $dok ?: [''];
    }

    /**
     * Id bentuk dari kiriman: id yang terdaftar, atau nama yang persis ada di data master (draf
     * lama menyimpan nama). Selain itu kosong — bentuk tidak lagi bisa diketik bebas.
     */
    private function idBentuk(mixed $nilai): string
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return '';
        }
        $daftar = $this->bentukMaster();
        $x = ctype_digit($nilai) ? $daftar->firstWhere('id', (int) $nilai)
            : $daftar->first(fn ($b) => mb_strtolower($b->nama) === mb_strtolower($nilai));

        return $x ? (string) $x->id : '';
    }

    /** Draf yang dibuat sebelum bentuk jadi data master menyimpan nama — dibaca jadi id. */
    private function bakukanBentuk(array $d): array
    {
        foreach ($d['temuan'] ?? [] as $i => $t) {
            foreach ($t['rekom'] ?? [] as $j => $r) {
                foreach ($r['tindakan'] ?? [] as $k => $tk) {
                    $d['temuan'][$i]['rekom'][$j]['tindakan'][$k]['bentuk'] = $this->idBentuk($tk['bentuk'] ?? '');
                }
            }
        }

        return $d;
    }

    private function draf(): array
    {
        $baris = DrafLaporan::where('user_id', auth()->id())->first();
        if (! $baris) {
            return $this->kosong();
        }
        $d = array_replace($this->kosong(), $baris->isian);
        /* Draf lama (formulir tiga langkah) dipetakan ke dua langkah. */
        if (($baris->isian['versi'] ?? 1) < 2) {
            $d['n'] = ($d['n'] ?? 1) >= 3 ? 2 : 1;
            $d['versi'] = 2;
        }

        return $this->bakukanBentuk($d);
    }

    private function simpanDraf(array $d): void
    {
        $d['disimpan'] = now()->toDateString();

        DrafLaporan::updateOrCreate(['user_id' => auth()->id()], ['isian' => $d]);
    }

    private function hapusDraf(): void
    {
        DrafLaporan::where('user_id', auth()->id())->delete();
    }

    /* ---- Layar ---- */

    public function form()
    {
        $this->boleh();

        return $this->layar();
    }

    /** `$gulir`: bagian yang dituju, dibawa `data-gulir` untuk jawaban kiriman skrip. */
    private function layar(?string $gulir = null)
    {
        $d = $this->draf();
        if (session()->hasOldInput('berkas')) {
            $d = $this->serap(Request::create('/', 'POST', session()->getOldInput()), $d);
        }

        /* Formulir kosong yang ditinggalkan bukan draf — dibuang. */
        if (($d['ditinggal'] ?? false) && ! $this->adaIsi($d)) {
            $this->hapusDraf();
            $d = $this->kosong();
        }

        /* Keterangan "melanjutkan draf" hanya bagi yang kembali ke formulir yang ditinggalkan. */
        $ada = ($d['ditinggal'] ?? false) && $this->adaIsi($d);
        $i = array_search($d['aktif'], array_column($d['temuan'], 'id'), true);

        $ok1 = $this->ok1($d);

        return view('laporan.baru', [
            'd' => $d,
            'iAktif' => $i === false ? 0 : $i,
            'adaDraf' => $ada,
            'suratBuka' => $d['surat_buka'] ?? ! $ok1,
            /* Penanggung jawab ikut dimuat untuk pemilih satuan kerja. */
            'satker' => Satker::with('penanggungJawab')->orderBy('id')->get(),
            'kategori' => KategoriTemuan::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
            'intern' => Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
                ->orderBy('urutan')->orderBy('id')->get(),
            'sifat' => Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
                ->orderBy('urutan')->orderBy('id')->get(),
            'bentuk' => $this->bentukMaster(),
            'ok1' => $ok1,
            'siap' => $ok1 && $this->ok2($d),
            'kurang' => $d['n'] === 1 ? $this->kurangIsi($d) : null,
            'ringkas' => $this->ringkas($d),
            'adaIsi' => $this->adaIsi($d),
            'form' => $this,
            'gulir' => $gulir,
        ]);
    }

    /**
     * Satu pintu untuk semua tombol: isian diserap dulu, supaya yang belum tersimpan tidak hilang.
     */
    public function simpan(Request $req)
    {
        $this->boleh();
        if ($req->input('aksi') !== 'kosongkan') {
            $req->validate(['berkas.link' => ['nullable', 'string', 'max:500', new LinkBukti]]);
        }

        $d = $this->serap($req, $this->draf());
        $aksi = (string) $req->input('aksi', '');

        if ($aksi === 'ajukan') {
            return $this->ajukan($d);
        }
        if ($aksi === 'kosongkan') {
            $this->hapusDraf();
            Aktivitas::catat('laporan.draf.kosongkan', 'Mengosongkan draf laporan baru');

            return $req->ajax() ? $this->layar() : redirect()->route('laporan.baru');
        }

        /* Isian yang kurang ditahan di halaman isian (tombol tidak dimatikan) dan bagiannya
           dibuka. */
        if ($aksi === 'maju' && $d['n'] === 1 && ! ($this->ok1($d) && $this->ok2($d))) {
            $kurang = $this->kurangIsi($d);
            [$d, $sorot, $fokus] = $this->lepasSorot($this->tunjuk($d));
            $this->simpanDraf($d);

            return $this->keFormulir($req, $sorot, $fokus, $kurang['teks'] ?? '');
        }

        /* Meninggalkan formulir kosong tidak menyimpan draf. */
        if (in_array($aksi, ['simpan-draf', 'tinggalkan'], true) && ! $this->adaIsi($d)) {
            $this->hapusDraf();

            return redirect()->route('rekomendasi.index');
        }

        $d = $this->terapkan($d, $aksi);
        [$d, $sorot, $fokus] = $this->lepasSorot($d);
        $d['ditinggal'] = in_array($aksi, ['simpan-draf', 'tinggalkan'], true);
        if ($d['ditinggal']) {
            /* Draf dilanjutkan di halaman isian; temuan terakhir tetap terpilih. */
            $d['n'] = 1;
            $d['surat_buka'] = null;
            $d['buka'] = null;
            $d['tl'] = [];
        }
        $this->simpanDraf($d);

        /* Simpan draft / Kembali ke beranda meninggalkan formulir. Log hanya mencatat draf yang
           ditinggalkan dan laporan yang dicatat. */
        if ($aksi === 'simpan-draf' || $aksi === 'tinggalkan') {
            Aktivitas::catat('laporan.draf', 'Menyimpan draf laporan baru'
                .(trim($d['surat']['nomor']) !== '' ? ' '.trim($d['surat']['nomor']) : ''));

            return redirect()->route('rekomendasi.index');
        }

        return $this->keFormulir($req, $sorot, $fokus);
    }

    /**
     * Kembali ke formulir: kiriman biasa dialihkan ke alamat berjangkar; kiriman skrip langsung
     * dijawab isi formulirnya.
     */
    private function keFormulir(Request $req, ?string $sorot, bool $fokus, ?string $gagal = null)
    {
        if ($req->ajax()) {
            session()->now('fokus', $fokus);
            if ($gagal !== null) {
                session()->now('gagal', $gagal);
            }

            return $this->layar($sorot);
        }

        $jawab = redirect(route('laporan.baru').($sorot ? '#'.$sorot : ''))->with('fokus', $fokus);

        return $gagal !== null ? $jawab->with('gagal', $gagal) : $jawab;
    }

    /** Sasaran gulir (dan fokus) sesaat — tidak ikut tersimpan di draf. */
    private function lepasSorot(array $d): array
    {
        $sorot = $d['_sorot'] ?? null;
        $fokus = (bool) ($d['_fokus'] ?? false);
        unset($d['_sorot'], $d['_fokus']);

        return [$d, $sorot, $fokus];
    }

    /** Membuang draf tanpa membuka formulirnya. */
    public function buang()
    {
        $this->boleh();
        $this->hapusDraf();

        return redirect()->route('rekomendasi.index');
    }

    /* ---- Menyerap isian ---- */

    /**
     * Satu isian dari kiriman. Isian kosong tiba sebagai null (ConvertEmptyStringsToNull), jadi
     * "dikosongkan" dibedakan dari "tidak dikirim" lewat ada-tidaknya kuncinya.
     */
    private function isian(array $kiriman, string|int $k, string $lama): string
    {
        return array_key_exists($k, $kiriman) ? trim((string) $kiriman[$k]) : $lama;
    }

    private function serap(Request $req, array $d): array
    {
        $s = (array) $req->input('surat', []);
        $lama = $d['surat'];
        $d['surat'] = [
            'sumber' => in_array($s['sumber'] ?? '', ['LHP', 'LHA'], true) ? $s['sumber'] : $lama['sumber'],
            'nomor' => $this->isian($s, 'nomor', $lama['nomor']),
            'tgl_surat' => $this->isian($s, 'tgl_surat', $lama['tgl_surat']),
            'tgl_terima' => $this->isian($s, 'tgl_terima', $lama['tgl_terima']),
        ];
        $gantiSumber = $lama['sumber'] !== $d['surat']['sumber'];

        $b = (array) $req->input('berkas', []);
        $d['berkas'] = [
            'judul' => array_key_exists('judul', $b) && ($b['judul'] === null || is_string($b['judul'])) ? trim($b['judul'] ?? '') : $d['berkas']['judul'],
            'link' => array_key_exists('link', $b) && ($b['link'] === null || is_string($b['link'])) ? trim($b['link'] ?? '') : $d['berkas']['link'],
        ];

        /* Seluruh temuan ikut terkirim (yang tak terpilih disembunyikan); yang tidak dikirim
           tetap seperti tersimpan. */
        $isi = (array) $req->input('tem', []);
        if ($isi) {
            $d['temuan'] = array_map(fn ($t) => is_array($isi[$t['id']] ?? null)
                ? $this->serapTemuan($t, $isi[$t['id']]) : $t, $d['temuan']);
        }

        /* Tab yang dipilih lewat skrip dibawa masukan tersembunyi. */
        $ui = (array) $req->input('ui', []);
        if (in_array($ui['aktif'] ?? null, array_column($d['temuan'], 'id'), true)) {
            $d['aktif'] = $ui['aktif'];
        }
        if (array_key_exists('buka', $ui)) {
            $d['buka'] = (string) $ui['buka'] !== '' ? (string) $ui['buka'] : null;
        }
        foreach ((array) ($ui['tl'] ?? []) as $rid => $k) {
            $d['tl'][(string) $rid] = max(0, (int) $k);
        }
        if (isset($ui['surat'])) {
            $d['surat_buka'] = $ui['surat'] === '1';
        }

        /* Sumber berganti → pilihan kategori temuan dikosongkan. */
        if ($gantiSumber) {
            $d['temuan'] = array_map(function ($t) {
                $t['kategori'] = '';

                return $t;
            }, $d['temuan']);
        }

        return $this->isiRenaksi($d, $gantiSumber);
    }

    private function serapTemuan(array $t, array $isi): array
    {
        $nomorLama = $t['nomor'];
        /* Nomor yang dikosongkan ikut mengosongkan Ref LHP otomatisnya. */
        $t['nomor'] = $this->isian($isi, 'nomor', $t['nomor']);
        $t['judul'] = $this->isian($isi, 'judul', $t['judul']);
        $t['sebab'] = $this->isian($isi, 'sebab', $t['sebab']);
        $t['akibat'] = $this->isian($isi, 'akibat', $t['akibat']);
        $t['kategori'] = $this->isian($isi, 'kategori', (string) $t['kategori']);
        $t['intern'] = $this->isian($isi, 'intern', (string) $t['intern']);
        /* Kotak centang kosong tidak terkirim: daftar kosong berarti kosong. */
        $t['satker'] = array_values(array_unique(array_map('intval', (array) ($isi['satker'] ?? []))));

        $t['rekom'] = array_map(function ($r, $j) use ($isi, $t, $nomorLama) {
            $ri = $isi['rekom'][$r['id']] ?? null;
            if (! is_array($ri)) {
                return $r;
            }
            $r['uraian'] = $this->isian($ri, 'uraian', $r['uraian']);
            /* Draf lama tanpa tanda: dianggap otomatis bila kosong atau sama dengan usulannya. */
            $otomatis = $r['ref_otomatis'] ?? ($r['ref_lhp'] === '' || $r['ref_lhp'] === $this->refUsulan($nomorLama, $j));
            $ref = $this->isian($ri, 'ref_lhp', $r['ref_lhp']);
            /* Disunting tangan, termasuk dikosongkan: tidak lagi mengikuti nomor temuan. */
            if ($ref !== $r['ref_lhp'] && $ref !== $this->refUsulan($t['nomor'], $j)) {
                $otomatis = false;
            }
            $r['ref_lhp'] = $ref;
            $r['ref_otomatis'] = $otomatis;
            $r['sifat'] = $this->isian($ri, 'sifat', (string) $r['sifat']);
            $r['angsur'] = preg_replace('/\D/', '', $this->isian($ri, 'angsur', (string) $r['angsur']));
            $r['kunci'] = (bool) ($ri['kunci'] ?? false);
            $r['tindakan'] = array_map(fn ($tk) => is_array($ri['tindakan'][$tk['id']] ?? null)
                ? $this->serapTindakan($tk, $ri['tindakan'][$tk['id']], $t['satker'])
                : $tk, $r['tindakan']);

            return $r;
        }, $t['rekom'], array_keys($t['rekom']));

        return $this->segarkanRef($t);
    }

    /** Ref LHP usulan: nomor temuan + huruf rekomendasi ("1.1" → "1.1.a"); kosong tanpa nomor. */
    public function refUsulan(string $nomor, int $j): string
    {
        $nomor = rtrim(trim($nomor), '.');

        return $nomor === '' ? '' : $nomor.'.'.$this->huruf($j);
    }

    /** Ref LHP yang belum disunting tangan ikut nomor temuan dan urutan rekomendasinya. */
    private function segarkanRef(array $t): array
    {
        $j = 0;
        foreach ($t['rekom'] as $k => $r) {
            if ($r['ref_otomatis'] ?? false) {
                $t['rekom'][$k]['ref_lhp'] = $this->refUsulan($t['nomor'], $j);
            }
            $j++;
        }

        return $t;
    }

    private function serapTindakan(array $tk, array $ki, array $satkerTemuan): array
    {
        $bentukLama = $tk['bentuk'];
        $tk['bentuk'] = $this->idBentuk($this->isian($ki, 'bentuk', (string) $tk['bentuk']));

        /* Usul dokumen ikut bentuk tindak lanjut, kecuali daftarnya sudah disunting tangan. */
        $utuh = $tk['dokumen'] === $this->usulanDokumen($bentukLama);
        $kiriman = array_values(array_map(fn ($x) => trim((string) $x),
            (array) ($ki['dokumen'] ?? $tk['dokumen'])));
        $disunting = $kiriman !== $tk['dokumen'];
        $tk['dokumen'] = $kiriman;
        if ($tk['bentuk'] !== $bentukLama && $utuh && ! $disunting) {
            $tk['dokumen'] = $this->usulanDokumen($tk['bentuk']);
        }

        /* Rencana aksi yang dikosongkan diisi lagi tenggat bawaannya oleh isiRenaksi(). */
        $tk['tgl_renaksi'] = $this->isian($ki, 'tgl_renaksi', (string) $tk['tgl_renaksi']);
        $tk['target'] = $this->isian($ki, 'target', (string) $tk['target']);
        $tk['catatan'] = $this->isian($ki, 'catatan', $tk['catatan']);

        /* Nilai yang sudah diketik dipertahankan untuk satuan kerja yang masih terpilih; satuan
           kerja yang tidak diperiksa dibuang. */
        $lama = collect($tk['satker'])->keyBy('satker');
        $dipilih = array_values(array_intersect(
            array_map('intval', (array) ($ki['satker'] ?? [])), $satkerTemuan));
        $tk['satker'] = array_map(fn ($id) => [
            'satker' => $id,
            'nilai' => preg_replace('/\D/', '',
                $this->isian((array) ($ki['nilai'] ?? []), $id, (string) ($lama[$id]['nilai'] ?? ''))),
        ], $dipilih);

        return $tk;
    }

    /**
     * Isi tanggal rencana aksi; yang disetel tangan dibiarkan, kecuali sumber laporannya berganti.
     */
    private function isiRenaksi(array $d, bool $paksa): array
    {
        if (! $d['surat']['tgl_terima']) {
            return $d;
        }
        $tenggat = Rekomendasi::hitungTenggat(
            Carbon::parse($d['surat']['tgl_terima']),
            SumberLaporan::from($d['surat']['sumber'])
        )->toDateString();

        $d['temuan'] = array_map(fn ($t) => array_merge($t, [
            'rekom' => array_map(fn ($r) => array_merge($r, [
                'tindakan' => array_map(fn ($tk) => array_merge($tk, [
                    'tgl_renaksi' => ($paksa || ! $tk['tgl_renaksi']) ? $tenggat : $tk['tgl_renaksi'],
                ]), $r['tindakan']),
            ]), $t['rekom']),
        ]), $d['temuan']);

        return $d;
    }

    /* ---- Tombol ---- */

    private function terapkan(array $d, string $aksi): array
    {
        $bagian = explode(':', $aksi);
        [$apa, $a, $b, $c, $e] = array_pad($bagian, 5, null);
        $iTem = fn ($id) => array_search($id, array_column($d['temuan'], 'id'), true);

        switch ($apa) {
            case 'maju':
                $d['n'] = min(2, $d['n'] + 1);
                break;
            case 'mundur':
                $d['n'] = max(1, $d['n'] - 1);
                break;
            case 'langkah':
                $d['n'] = max(1, min(2, (int) $a));
                break;

                /* Kepala bagian surat: dibuka-tutup. */
            case 'surat':
                $d['surat_buka'] = ! ($d['surat_buka'] ?? ! $this->ok1($d));
                $d['_sorot'] = 'fb-surat';
                break;

            case 'temuan':
                if ($iTem($a) !== false) {
                    $d['aktif'] = (string) $a;
                    /* Pindah temuan melepas pilihan rekomendasi lama. */
                    $d['buka'] = null;
                    $d['_sorot'] = 'fb-tem-'.$a;
                }
                break;
            case 'rek':
                $d['buka'] = (string) $a;
                break;
            case 'tl':
                $d['tl'][(string) $a] = max(0, (int) $b);
                break;
            case 'tunjukkan':
                $d = $this->tunjuk($d);
                break;
            case 'tambah-temuan':
                $t = $this->temKosong();
                $d['temuan'][] = $t;
                $d['aktif'] = $t['id'];
                $d['buka'] = null;
                $d['_sorot'] = 'fb-tem-'.$t['id'];
                $d = $this->isiRenaksi($d, false);
                break;
            case 'hapus-temuan':
                $i = $iTem($a);
                if (count($d['temuan']) > 1 && $i !== false) {
                    $ganti = $d['temuan'][$i - 1] ?? $d['temuan'][$i + 1] ?? null;
                    unset($d['temuan'][$i]);
                    $d['temuan'] = array_values($d['temuan']);
                    if ($d['aktif'] === $a) {
                        $d['aktif'] = $ganti['id'] ?? $d['temuan'][0]['id'];
                    }
                }
                break;

            case 'tambah-rek':
                $i = $iTem($a);
                if ($i !== false) {
                    $r = $this->rekKosong();
                    $d['temuan'][$i]['rekom'][] = $r;
                    $d['temuan'][$i] = $this->segarkanRef($d['temuan'][$i]);
                    /* Yang baru langsung terpilih. */
                    $d['buka'] = $r['id'];
                    $d['_sorot'] = 'fb-rek-'.$r['id'];
                    $d = $this->isiRenaksi($d, false);
                }
                break;
            case 'hapus-rek':
                $i = $iTem($a);
                if ($i !== false && count($d['temuan'][$i]['rekom']) > 1) {
                    $d['temuan'][$i]['rekom'] = array_values(array_filter(
                        $d['temuan'][$i]['rekom'], fn ($r) => $r['id'] !== $b));
                    /* Huruf rekomendasi bergeser; Ref LHP otomatis ikut. */
                    $d['temuan'][$i] = $this->segarkanRef($d['temuan'][$i]);
                    $d['buka'] = null;
                }
                break;

            case 'tambah-tindakan':
                /* Yang baru langsung terpilih. */
                $d['tl'][(string) $b] = count($this->cariRek($d, $a, $b)['tindakan'] ?? []);
                $d = $this->padaRek($d, $a, $b, fn ($r) => array_merge($r, [
                    /* Tanggal awal ikut tindakan pertama; tetap bisa diubah. */
                    'tindakan' => array_merge($r['tindakan'],
                        [$this->tindakanKosong(null, (string) ($r['tindakan'][0]['tgl_renaksi'] ?? ''))]),
                ]));
                break;
            case 'hapus-tindakan':
                /* Yang terpilih pindah ke tetangga sebelumnya. */
                $d['tl'][(string) $b] = max(0, (int) $c - 1);
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c) {
                    if (count($r['tindakan']) > 1) {
                        unset($r['tindakan'][(int) $c]);
                        $r['tindakan'] = array_values($r['tindakan']);
                    }

                    return $r;
                });
                break;
            case 'tambah-dok':
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c) {
                    $r['tindakan'][(int) $c]['dokumen'][] = '';

                    return $r;
                });
                break;
            case 'hapus-dok':
                $d = $this->padaRek($d, $a, $b, function ($r) use ($c, $e) {
                    unset($r['tindakan'][(int) $c]['dokumen'][(int) $e]);
                    $r['tindakan'][(int) $c]['dokumen'] = array_values($r['tindakan'][(int) $c]['dokumen']);

                    return $r;
                });
                break;

                /* Angsuran 2–24 kali (PP 38/2016); satu kali = pembayaran sekaligus (isian
                   kosong). */
            case 'angsur':
                $d = $this->padaRek($d, $b, $c, function ($r) use ($a) {
                    $kini = (int) $r['angsur'];
                    $baru = $kini === 0 ? ($a === 'naik' ? 2 : 0) : $kini + ($a === 'naik' ? 1 : -1);
                    $r['angsur'] = $baru < 2 ? '' : (string) min($baru, 24);
                    $r['kunci'] = $r['angsur'] ? $r['kunci'] : false;

                    return $r;
                });
                break;
        }

        return $d;
    }

    private function cariRek(array $d, $tid, $rid): ?array
    {
        foreach ($d['temuan'] as $t) {
            if ($t['id'] === $tid) {
                foreach ($t['rekom'] as $r) {
                    if ($r['id'] === $rid) {
                        return $r;
                    }
                }
            }
        }

        return null;
    }

    private function padaRek(array $d, $tid, $rid, callable $fn): array
    {
        $d['temuan'] = array_map(function ($t) use ($tid, $rid, $fn) {
            if ($t['id'] !== $tid) {
                return $t;
            }
            $t['rekom'] = array_map(fn ($r) => $r['id'] === $rid ? $fn($r) : $r, $t['rekom']);

            return $t;
        }, $d['temuan']);

        return $d;
    }

    /* ---- Kelengkapan ---- */

    public function adaIsi(array $d): bool
    {
        $s = $d['surat'] ?? [];
        if (($s['nomor'] ?? '') || ($s['tgl_surat'] ?? '') || ($s['tgl_terima'] ?? '')
            || ($d['berkas']['judul'] ?? '') || ($d['berkas']['link'] ?? '')) {
            return true;
        }

        foreach ($d['temuan'] ?? [] as $t) {
            if (($t['judul'] ?? '') || ($t['sebab'] ?? '') || ($t['akibat'] ?? '') || ($t['nomor'] ?? '')) {
                return true;
            }
            foreach ($t['rekom'] ?? [] as $r) {
                if (trim((string) ($r['uraian'] ?? '')) || $this->barisRek($r)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Punya draf yang berisi (tombol "Lanjutkan draf laporan"). */
    public static function drafBerisi(int $userId): bool
    {
        $baris = DrafLaporan::where('user_id', $userId)->first();

        return $baris !== null && app(self::class)->adaIsi((array) $baris->isian);
    }

    /** Jumlah penugasan sebuah rekomendasi. */
    public function barisRek(array $r): int
    {
        return (int) collect($r['tindakan'] ?? [])->sum(fn ($tk) => count($tk['satker'] ?? []));
    }

    /** Nilai rekomendasi: jumlah bagian tiap satuan kerja, tidak pernah diketik. */
    public function nilaiRek(array $r): int
    {
        return (int) collect($r['tindakan'])->flatMap(fn ($tk) => $tk['satker'])
            ->sum(fn ($x) => (int) $x['nilai']);
    }

    /** Nilai temuan: jumlah tagihan seluruh rekomendasinya. */
    public function nilaiTem(array $t): int
    {
        return (int) collect($t['rekom'])->sum(fn ($r) => $this->nilaiRek($r));
    }

    public function salahTanggal(array $s): string
    {
        if (! $s['tgl_surat'] || ! $s['tgl_terima']) {
            return '';
        }
        $ini = now()->toDateString();
        if ($s['tgl_surat'] > $ini) {
            return 'tanggal surat masih di depan hari ini';
        }
        if ($s['tgl_terima'] > $ini) {
            return 'tanggal diterima masih di depan hari ini';
        }
        if ($s['tgl_terima'] < $s['tgl_surat']) {
            return 'tanggal diterima mendahului tanggal suratnya';
        }

        return '';
    }

    private function ok1(array $d): bool
    {
        $s = $d['surat'];

        return trim($s['nomor']) !== '' && $s['tgl_surat'] && $s['tgl_terima']
            && $this->salahTanggal($s) === '';
    }

    /** Tindak lanjut wajib punya satuan kerja dan bentuk. */
    public function tindakanOk(array $tk): bool
    {
        return trim((string) $tk['bentuk']) !== '' && count($tk['satker']) > 0
            && trim((string) $tk['tgl_renaksi']) !== '';
    }

    public function rekOk(array $r): bool
    {
        return trim($r['uraian']) !== '' && $this->barisRek($r) > 0
            && collect($r['tindakan'])->every(fn ($tk) => $this->tindakanOk($tk));
    }

    public function temOk(array $t): bool
    {
        return trim($t['judul']) !== '' && trim($t['sebab']) !== '' && trim($t['akibat']) !== ''
            && count($t['satker']) > 0 && $t['kategori'] !== ''
            && collect($t['rekom'])->every(fn ($r) => $this->rekOk($r));
    }

    public function ok2(array $d): bool
    {
        return collect($d['temuan'])->every(fn ($t) => $this->temOk($t));
    }

    /** Isian yang kurang beserta tempatnya, untuk bilah bawah dan "Tunjukkan". */
    public function kurangIsi(array $d): ?array
    {
        $s = $d['surat'];
        $k = [];
        if (! trim($s['nomor'])) {
            $k[] = 'nomor surat';
        }
        if (! $s['tgl_surat']) {
            $k[] = 'tanggal surat';
        }
        if (! $s['tgl_terima']) {
            $k[] = 'tanggal diterima';
        }
        /* Tanggal terisi tapi urutannya keliru disebut salahnya, bukan "belum diisi". */
        $salah = $this->salahTanggal($s);
        if ($salah) {
            return ['teks' => 'Surat laporan perlu dibetulkan: '.$salah, 'ke' => ['surat' => true]];
        }
        if ($k) {
            return ['teks' => 'Surat laporan belum lengkap: '.implode(', ', $k), 'ke' => ['surat' => true]];
        }
        /* Link surat asli boleh kosong; yang terisi harus alamat web. */
        if (trim($d['berkas']['link']) !== '' && ! KebijakanBukti::sah($d['berkas']['link'])) {
            return ['teks' => 'Link surat asli perlu diperbarui: '.KebijakanBukti::periksa($d['berkas']['link'])['alasan'], 'ke' => ['surat' => true]];
        }

        foreach (array_values($d['temuan']) as $i => $t) {
            if ($this->temOk($t)) {
                continue;
            }
            $kk = [];
            if (! trim($t['judul'])) {
                $kk[] = 'judul';
            }
            if (! $t['kategori']) {
                $kk[] = 'kategori temuan';
            }
            if (! count($t['satker'])) {
                $kk[] = 'satuan kerja terperiksa';
            }
            if (! trim($t['sebab'])) {
                $kk[] = 'sebab';
            }
            if (! trim($t['akibat'])) {
                $kk[] = 'akibat';
            }
            $temSendiri = $kk !== [];
            $rid = null;
            $tk = null;
            $gulir = 'fb-tem-'.$t['id'];
            foreach (array_values($t['rekom']) as $j => $r) {
                if ($this->rekOk($r)) {
                    continue;
                }
                $h = $this->huruf($j);
                $tl = $r['tindakan'];
                $sebelum = count($kk);
                if (! trim($r['uraian'])) {
                    $kk[] = 'uraian rekomendasi '.$h;
                }
                if (collect($tl)->contains(fn ($x) => trim((string) $x['bentuk']) === '')) {
                    $kk[] = 'bentuk tindak lanjut di rekomendasi '.$h;
                }
                if (! $tl || collect($tl)->contains(fn ($x) => ! count($x['satker']))) {
                    $kk[] = 'satuan kerja di rekomendasi '.$h;
                }
                if (collect($tl)->contains(fn ($x) => trim((string) $x['tgl_renaksi']) === '')) {
                    $kk[] = 'tanggal rencana aksi di rekomendasi '.$h;
                }
                if (count($kk) === $sebelum) {
                    $kk[] = 'rekomendasi '.$h;
                }
                $rid = $r['id'];
                /* Tab tindak lanjut yang kurang ikut dibuka. */
                foreach (array_values($tl) as $kTl => $x) {
                    if (! $this->tindakanOk($x)) {
                        $tk = $kTl;
                        break;
                    }
                }
                if (! $temSendiri) {
                    $gulir = trim($r['uraian']) && $tk !== null ? 'fb-tl-'.$r['id'].'-'.$tk : 'fb-rek-'.$r['id'];
                }
                break;
            }

            return [
                'teks' => 'Temuan '.($i + 1).' belum lengkap: '.implode(', ', $kk),
                'ke' => ['tid' => $t['id'], 'rid' => $rid, 'tk' => $tk, 'gulir' => $gulir],
            ];
        }

        return null;
    }

    /** Membuka bagian yang masih kurang. */
    private function tunjuk(array $d): array
    {
        $k = $this->kurangIsi($d);
        if (! $k) {
            return $d;
        }
        $ke = $k['ke'];
        if (! empty($ke['surat'])) {
            $d['surat_buka'] = true;
            $d['_sorot'] = 'fb-surat';
        } else {
            $d['aktif'] = $ke['tid'];
            $d['buka'] = $ke['rid'];
            if ($ke['rid'] && $ke['tk'] !== null) {
                $d['tl'][$ke['rid']] = $ke['tk'];
            }
            $d['_sorot'] = $ke['gulir'];
        }
        $d['_fokus'] = true;

        return $d;
    }

    public function huruf(int $j): string
    {
        return $j < 26 ? chr(97 + $j) : (string) ($j + 1);
    }

    /** Pembuka baku uraian rekomendasi LHP — dibuang dari judul tab supaya tidak terbaca kembar. */
    public const PEMBUKA_URAIAN = '/^(?:Menteri (?:Pekerjaan Umum(?: dan Perumahan Rakyat)?|PUPR|PU)|Kepala (?:BPSDM|Badan)|Sekretaris Badan)\s+agar\s+(?:memerintahkan\s+(?:Kepala BPSDM|Sekretaris Badan|(?:para )?kepala balai)\s+(?:untuk\s+)?)?/iu';

    /** Inti uraian rekomendasi tanpa pembukanya. */
    public function intiUraian(string $t): string
    {
        $s = preg_replace(self::PEMBUKA_URAIAN, '', $t) ?? $t;

        return $s === '' || $s === $t ? $t : mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /** Angka untuk kotak "Ajukan laporan", dihitung dari tindak lanjut. */
    public function ringkas(array $d): array
    {
        $sat = [];
        $ten = [];
        $pen = 0;
        $rek = 0;
        $nilai = 0;
        foreach ($d['temuan'] as $t) {
            foreach ($t['rekom'] as $r) {
                $rek++;
                $nilai += $this->nilaiRek($r);
                foreach ($r['tindakan'] as $tk) {
                    foreach ($tk['satker'] as $x) {
                        $sat[$x['satker']] = true;
                        $pen++;
                    }
                    if ($tk['tgl_renaksi']) {
                        $ten[$tk['tgl_renaksi']] = true;
                    }
                }
            }
        }

        return ['tem' => count($d['temuan']), 'rek' => $rek, 'sat' => count($sat),
            'pen' => $pen, 'ten' => count($ten), 'nilai' => $nilai];
    }

    /* ---- Mengajukan ---- */

    /** Laporan dipecah jadi penugasan ke banyak satuan kerja; tidak bisa ditarik lewat aplikasi. */
    private function ajukan(array $d)
    {
        if (! $this->ok1($d) || ! $this->ok2($d)) {
            $this->simpanDraf($d);

            return redirect()->route('laporan.baru')->with('gagal', 'Masih ada isian yang belum lengkap.');
        }
        if (Laporan::where('nomor', $d['surat']['nomor'])->exists()) {
            $this->simpanDraf($d);

            return redirect()->route('laporan.baru')
                ->with('gagal', 'Nomor surat ini sudah pernah dicatat. Periksa lagi nomornya.');
        }

        $sumber = SumberLaporan::from($d['surat']['sumber']);
        $tahun = substr($d['surat']['tgl_surat'] ?: now()->toDateString(), 0, 4);
        $noSurat = explode('/', $d['surat']['nomor'])[0] ?? '';

        $laporan = DB::transaction(function () use ($d, $sumber, $tahun, $noSurat) {
            $lap = Laporan::create([
                'sumber' => $sumber->value,
                'nomor' => $d['surat']['nomor'],
                'tgl_surat' => $d['surat']['tgl_surat'],
                'tgl_terima' => $d['surat']['tgl_terima'],
                /* Diisi sistem, bukan diketik. */
                'dicatat_pada' => now()->toDateString(),
                'dicatat_oleh' => auth()->id(),
            ]);

            /* Yang disimpan link surat, bukan berkasnya. */
            if ($d['berkas']['judul']) {
                Lampiran::create([
                    'laporan_id' => $lap->id,
                    'label_jenis' => 'Surat laporan pemeriksaan',
                    'nama_asli' => $d['berkas']['judul'],
                    'link' => $d['berkas']['link'] ?: null,
                    'diunggah_oleh' => auth()->id(),
                    'label_oleh' => 'Setba',
                    'surat_asli' => true,
                    'diunggah_pada' => now(),
                ]);
            }

            $nomorTemuan = Temuan::count();
            $dipakai = [];

            foreach (array_values($d['temuan']) as $i => $t) {
                $nomorTemuan++;
                $tem = Temuan::create([
                    'laporan_id' => $lap->id,
                    'kode' => $this->kodeTemuan($tahun, $nomorTemuan),
                    'nomor_pada_surat' => $t['nomor'] ?: null,
                    'judul' => $t['judul'],
                    'sebab' => $t['sebab'] ?: null,
                    'akibat' => $t['akibat'] ?: null,
                    'kategori_temuan_id' => $t['kategori'] ?: null,
                    'kategori_intern_id' => $t['intern'] ?: null,
                    /* Nol = ikut jumlah rekomendasinya (dibaca `nilaiTemuan()`). */
                    'nilai' => 0,
                ]);
                $tem->satkers()->syncWithoutDetaching($t['satker']);

                foreach (array_values($t['rekom']) as $j => $r) {
                    /* Kode = Ref IDT: tahun LHP . nomor pendek LHP . Ref LHP. */
                    $refLhp = trim($r['ref_lhp'])
                        ?: collect([$t['nomor'] ?: $i + 1, $this->huruf($j)])->filter()->join('.');
                    $kode = $this->kodeUnik(collect([$tahun, $noSurat, $refLhp])->filter()->join('.'), $dipakai);

                    $tgl = collect($r['tindakan'])->pluck('tgl_renaksi')->filter()->sort()->values();
                    $tgt = collect($r['tindakan'])->pluck('target')->filter()->sort()->values();

                    $rek = Rekomendasi::create([
                        'temuan_id' => $tem->id,
                        'kode' => $kode,
                        'ref_lhp' => trim($r['ref_lhp']) ?: null,
                        'nomor_urut' => $j + 1,
                        'uraian' => $r['uraian'],
                        'sifat_id' => $r['sifat'] ?: null,
                        'nilai_pulih' => $this->nilaiRek($r),
                        /* Tenggat = tanggal paling awal tindak lanjutnya; target = paling akhir. */
                        'tenggat_jawab' => $tgl->first(),
                        'target_selesai' => $tgt->last(),
                        'catatan' => collect($r['tindakan'])->pluck('catatan')->filter()->first(),
                        'status' => StatusTindakLanjut::BT->value,
                        'rencana_angsur' => (int) $r['angsur'],
                        'kunci_angsur' => (bool) $r['kunci'] && (int) $r['angsur'] > 0,
                    ]);

                    foreach (array_values($r['tindakan']) as $u => $tk) {
                        $dok = collect($tk['dokumen'])->map(fn ($x) => trim((string) $x))
                            ->filter()->unique()->values()->all();

                        $tindakan = Tindakan::create([
                            'rekomendasi_id' => $rek->id,
                            'bentuk_id' => (int) $this->idBentuk($tk['bentuk']) ?: null,
                            'urutan' => $u + 1,
                            'tgl_renaksi' => $tk['tgl_renaksi'] ?: null,
                            'target_selesai' => $tk['target'] ?: null,
                            'catatan' => trim((string) $tk['catatan']) ?: null,
                            'dokumen' => $dok,
                        ]);

                        foreach ($tk['satker'] as $x) {
                            $baris = Sasaran::create([
                                'tindakan_id' => $tindakan->id,
                                'satker_id' => $x['satker'],
                                'nilai' => (int) $x['nilai'],
                                /* Posisi ditulis sejak awal. */
                                'posisi' => PosisiBerkas::SATKER->value,
                            ]);

                            /* Satu daftar dokumen = kewajiban terpisah untuk tiap satuan kerja
                               yang dituju. */
                            if ($dok) {
                                $minta = PermintaanDokumen::create([
                                    'rekomendasi_id' => $rek->id,
                                    'sasaran_id' => $baris->id,
                                    'peran_peminta' => PeranPengguna::SETBA->value,
                                    'dari' => 'Setba',
                                    'diminta_oleh' => auth()->id(),
                                    'tanggal' => now()->toDateString(),
                                ]);
                                foreach ($dok as $nama) {
                                    ItemPermintaan::create([
                                        'permintaan_dokumen_id' => $minta->id,
                                        'nama' => $nama, 'terpenuhi' => false,
                                    ]);
                                }
                            }
                        }
                    }

                    /* Surat pemeriksaan melekat pada rekomendasi, tidak pernah sampai ke satuan
                       kerja. */
                    if ($d['berkas']['judul']) {
                        Lampiran::create([
                            'rekomendasi_id' => $rek->id,
                            'label_jenis' => 'Surat temuan',
                            'nama_asli' => $d['berkas']['judul'],
                            'link' => $d['berkas']['link'] ?: null,
                            'diunggah_oleh' => auth()->id(),
                            'label_oleh' => 'Setba',
                            'surat_asli' => true,
                            'diunggah_pada' => now(),
                        ]);
                    }

                    $rek->load('tindakan.bentuk', 'tindakan.sasaran', 'sasaran.satker');

                    $nama = $rek->daftarSasaran()->map(fn ($x) => $x->satker?->namaPendek())
                        ->filter()->unique()->join(', ');
                    Jejak::riwayat($rek, 'Setba', 'Rekomendasi dikirim ke '.($nama ?: '—'));

                    /* Satu pemberitahuan per satuan kerja, berisi bebannya sendiri. */
                    foreach ($rek->daftarSasaran()->pluck('satker_id')->unique() as $satkerId) {
                        $milik = $rek->tindakan->filter(
                            fn ($tk) => $tk->sasaran->contains('satker_id', $satkerId));
                        $renaksi = $milik->pluck('tgl_renaksi')->filter()->sort()->first();
                        $bentuk = $milik->map(fn ($tk) => $tk->bentuk?->nama)->filter()->join(', ');

                        Pemberitahuan::tulis($rek, 'Rekomendasi baru untuk Anda — '
                            .($bentuk ?: 'tindak lanjut')
                            .($renaksi ? ' — rencana aksi '.Tampil::tgl($renaksi) : '')
                            .' — berkasnya di meja Anda',
                            [PeranPengguna::SATKER], 'r-kepala', [$satkerId]);
                    }
                }
            }

            $nRek = Rekomendasi::whereHas('temuan', fn ($q) => $q->where('laporan_id', $lap->id))->count();
            Aktivitas::catat('laporan.catat', 'Mencatat '.$lap->sumber->value.' '.$lap->nomor.' — '
                .$lap->temuan()->count().' temuan, '.$nRek.' rekomendasi', ['subjek' => $lap]);

            return $lap;
        });

        $this->hapusDraf();

        /* Diantar ke Daftar laporan dengan kartunya ditandai, supaya jelas kiriman berhasil. */
        return redirect()->route('laporan.index', ['sorot' => 'lap-'.$laporan->id, 'nada' => 'hijau']);
    }

    /** Tahunnya dari suratnya, nomornya urut sepanjang seluruh laporan. */
    private function kodeTemuan(string $tahun, int $nomor): string
    {
        do {
            $kode = 'TMN-'.$tahun.'-'.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);
            $nomor++;
        } while (Temuan::where('kode', $kode)->exists());

        return $kode;
    }

    /** Ref IDT kembar diberi akhiran — penanda ada yang perlu diperiksa. */
    private function kodeUnik(string $dasar, array &$dipakai): string
    {
        $kode = $dasar;
        $ke = 2;
        while (in_array($kode, $dipakai, true) || Rekomendasi::where('kode', $kode)->exists()) {
            $kode = $dasar.'-'.$ke++;
        }
        $dipakai[] = $kode;

        return $kode;
    }
}
