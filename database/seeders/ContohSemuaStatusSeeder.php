<?php

namespace Database\Seeders;

use App\Aksi\KirimUlang;
use App\Aksi\PutusPeriksa;
use App\Aksi\SimpanTanggapan;
use App\Aksi\Teruskan;
use App\Aksi\UrusanSiptl;
use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Http\Controllers\LaporanBaruController;
use App\Models\KategoriTemuan;
use App\Models\Laporan;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\BentukTindakLanjut;
use App\Support\Rangka;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Data contoh ringkas: satu LHP dan satu LHA yang mewakili setiap keadaan tindak lanjut (posisi
 * berkas, hasil UKI/Inspektorat, urusan SIPTL, pengembalian). Laporan dicatat lewat Catat laporan
 * baru dan tiap langkah lewat aksi yang sama dengan tombolnya, jadi Progres dan pemberitahuan ikut
 * terbentuk. Untuk basis data tanpa laporan: php artisan db:seed --class=ContohSemuaStatusSeeder
 */
class ContohSemuaStatusSeeder extends Seeder
{
    private array $langkah = [];

    private int $urut = 0;

    private int $nomor = 0;

    public function run(): void
    {
        if (Laporan::exists()) {
            $this->command?->error('Basis data masih berisi laporan. Kosongkan dulu: php artisan tlhp:kosongkan');

            return;
        }
        /* Pemberitahuan tetap tercatat; email tidak dikirim. */
        config(['mail.default' => 'array']);

        try {
            foreach (self::laporan() as $lap) {
                $this->catat($lap);
            }
            /* Langkah semua baris diurutkan menurut tanggal, seperti kejadiannya. */
            usort($this->langkah, fn ($a, $b) => [$a['tgl'], $a['urut']] <=> [$b['tgl'], $b['urut']]);
            foreach ($this->langkah as $l) {
                $this->jalankan($l);
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
            Rangka::dataBerubah();
        }

        $this->ringkasan();
    }

    /** Tanggal kejadian dengan jam sebenarnya — jam tidak dikarang. */
    private function hari(string $tgl): void
    {
        $jam = (new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone'))))->format('H:i:s');
        Carbon::setTestNow(Carbon::parse("{$tgl} {$jam}"));
    }

    private function sebagai(PeranPengguna $peran, ?int $satkerId = null): void
    {
        Auth::setUser(User::where('peran', $peran->value)->where('aktif', true)
            ->when($satkerId, fn ($q) => $q->where('satker_id', $satkerId))->orderBy('id')->firstOrFail());
    }

    private function satker(string $pendek): int
    {
        return Satker::where('nama_pendek', $pendek)->firstOrFail()->id;
    }

    private function ref(JenisReferensi $jenis, string $nama): int
    {
        return Referensi::where('jenis', $jenis->value)->where('nama', $nama)->firstOrFail()->id;
    }

    private static function link(string $kunci): string
    {
        return 'https://drive.google.com/file/d/1Contoh'.substr(md5($kunci), 0, 24).'/view';
    }

    private static function ntpn(string $kunci): string
    {
        return strtoupper(substr(md5('ntpn'.$kunci), 0, 16));
    }

    /** Laporan dicatat lewat jalur Catat laporan baru, lalu barisnya dipasangkan dengan skenarionya. */
    private function catat(array $lap): void
    {
        $this->hari($lap['catat']);
        $this->sebagai(PeranPengguna::SETBA);

        $d = ['n' => 1, 'surat' => $lap['surat'], 'berkas' => ['judul' => $lap['berkas'], 'link' => self::link($lap['surat']['nomor'])],
            'temuan' => [], 'aktif' => 't0', 'buka' => null, 'tl' => [], 'surat_buka' => null, 'disimpan' => null, 'versi' => 2];
        foreach ($lap['temuan'] as $i => $t) {
            $rekom = [];
            foreach ($t['rekom'] as $j => $r) {
                $rekom[] = [
                    'id' => "r{$i}{$j}", 'uraian' => $r['uraian'], 'ref_lhp' => '',
                    'sifat' => (string) $this->ref(JenisReferensi::SIFAT_REKOM, $r['sifat'] ?? 'Administratif'),
                    'angsur' => '', 'kunci' => false,
                    'tindakan' => [[
                        'id' => "k{$i}{$j}", 'bentuk' => $r['bentuk'],
                        'satker' => array_map(fn ($b) => ['satker' => $this->satker($b[0]), 'nilai' => $b[1]], $r['baris']),
                        'dokumen' => BentukTindakLanjut::peta()[$r['bentuk']] ?? ['Dokumen pendukung tindak lanjut'],
                        'tgl_renaksi' => $r['renaksi'], 'target' => '', 'catatan' => '',
                    ]],
                ];
            }
            $d['temuan'][] = [
                'id' => "t{$i}", 'nomor' => (string) ($i + 1), 'judul' => $t['judul'],
                'sebab' => $t['sebab'], 'akibat' => $t['akibat'],
                'satker' => array_map(fn ($n) => $this->satker($n), $t['satker']),
                'kategori' => (string) KategoriTemuan::where('nama', $t['kategori'])->firstOrFail()->id,
                'intern' => (string) $this->ref(JenisReferensi::KATEGORI_INTERN, $t['intern']),
                'rekom' => $rekom,
            ];
        }

        $c = app(LaporanBaruController::class);
        (new \ReflectionMethod($c, 'ajukan'))->invoke($c, $d);
        $laporan = Laporan::where('nomor', $lap['surat']['nomor'])->firstOrFail();

        foreach ($laporan->temuan as $i => $tem) {
            foreach ($tem->rekomendasi()->orderBy('nomor_urut')->get() as $j => $rek) {
                foreach ($lap['temuan'][$i]['rekom'][$j]['baris'] as $b) {
                    $s = Sasaran::whereHas('tindakan', fn ($q) => $q->where('rekomendasi_id', $rek->id))
                        ->where('satker_id', $this->satker($b[0]))->firstOrFail();
                    foreach ($b[2] ?? [] as $l) {
                        $this->langkah[] = ['tgl' => $l[0], 'urut' => $this->urut++, 'aksi' => $l[1],
                            'opsi' => $l[2] ?? [], 'sasaran' => $s->id];
                    }
                }
            }
        }
    }

    private function jalankan(array $l): void
    {
        $this->hari($l['tgl']);
        $s = Sasaran::with('satker', 'tindakan.bentuk', 'draf')->findOrFail($l['sasaran']);
        $r = Rekomendasi::with(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran', 'tindakan', 'temuan.laporan'])
            ->findOrFail($s->tindakan->rekomendasi_id);
        $o = $l['opsi'];
        $tgl = $l['tgl'];

        match ($l['aksi']) {
            'kirim', 'draf' => $this->tanggapan($l['aksi'], $r, $s, $o, $tgl),
            'teruskan' => $this->teruskan($r, $s, $tgl),
            'uki', 'insp' => $this->putus($l['aksi'] === 'uki', $r, $s, $o, $tgl),
            'kirim_ulang' => $this->setba(fn () => KirimUlang::jalankan($r, $s, $o['keterangan'] ?? null, $o['dokumen'] ?? [])),
            'unggah' => $this->setba(fn () => UrusanSiptl::unggah($r, $s, $tgl)),
            'bpk' => $this->setba(fn () => UrusanSiptl::status($r, $s, StatusTindakLanjut::from($o['status']), $o['catatan'] ?? null, $tgl)),
            'ulang_bpk' => $this->setba(fn () => UrusanSiptl::ulangBpk($r, $s, $o['alasan'], $o['keterangan'] ?? null,
                $o['dokumen'] ?? [], $o['nilai'] ?? 0)),
        };
    }

    private function setba(callable $aksi): void
    {
        $this->sebagai(PeranPengguna::SETBA);
        $aksi();
    }

    /** Isian disusun seperti TanggapanController: tiap dokumen yang diminta diberi link. */
    private function tanggapan(string $aksi, Rekomendasi $r, Sasaran $s, array $o, string $tgl): void
    {
        $this->sebagai(PeranPengguna::SATKER, $s->satker_id);
        $belum = $r->permintaanUntuk($s->satker_id, $s->tindakan_id)->flatMap->item->where('terpenuhi', false)->values();
        if ($aksi === 'draf') {
            $belum = $belum->take($o['dokumen'] ?? 1);
        }
        $pendek = $s->satker->namaPendek();
        $setor = $s->tindakan->bentuk?->nama === 'Penyetoran ke kas negara';

        $setoran = [];
        foreach ($o['setor'] ?? [] as $k => $nilai) {
            $setoran[] = [
                'jenis' => 'setor', 'nilai' => (string) $nilai, 'tanggal' => $tgl,
                'ssbp' => 'SSBP-'.str_replace('-', '', $tgl).'-'.($k + 1), 'ntpn' => self::ntpn("{$s->id}.{$k}"),
                'notaKppn' => 'NK-'.$s->id.($k + 1).'/KPPN.019/2026', 'noBa' => '',
                'berkas' => 'Bukti setor '.($k + 1).' — '.$pendek, 'link' => self::link("setor.{$s->id}.{$k}"),
            ];
        }
        $isi = [
            'uraian' => $o['uraian'] ?? ($setor
                ? 'Kelebihan pembayaran sudah disetor ke kas negara. Bukti setor dan Nota Konfirmasi KPPN terlampir.'
                : 'Tindak lanjut sudah dilaksanakan sesuai rekomendasi. Dokumen pendukung terlampir.'),
            'tanggal' => $tgl,
            'bukti' => $belum->map(fn ($i) => ['nama' => "{$i->nama} — {$pendek}", 'link' => self::link("bukti.{$i->id}"),
                'jenis' => $i->nama, 'untuk' => $i->id])->all(),
            'setoran' => $setoran,
            'penuhi' => $belum->pluck('id')->all(),
        ];

        if ($aksi === 'draf') {
            SimpanTanggapan::draf($r, $s, $isi);
            Aktivitas::catat('tanggapan.draf', 'Menyimpan draf tindak lanjut', ['subjek' => $r]);
        } else {
            SimpanTanggapan::kirim($r, $s, $isi);
        }
    }

    private function teruskan(Rekomendasi $r, Sasaran $s, string $tgl): void
    {
        $this->sebagai(PeranPengguna::SETBA);
        $keUki = $s->pos() === PosisiBerkas::SETBA_TINJAU;
        Teruskan::jalankan($r, $s, [
            'nomor' => 'PW.02.02-Sb/'.(100 + ++$this->nomor), 'tanggal' => $tgl,
            'perihal' => $keUki ? 'Penyampaian tindak lanjut untuk divalidasi' : 'Penyampaian hasil validasi untuk diverifikasi',
            'link' => null, 'catatan' => null,
        ]);
    }

    /** UKI memvalidasi; Inspektorat memverifikasi (atau Setba mengetik CHV dari surat kertas). */
    private function putus(bool $uki, Rekomendasi $r, Sasaran $s, array $o, string $tgl): void
    {
        $peran = $uki ? PeranPengguna::UKI : (($o['oleh'] ?? '') === 'setba' ? PeranPengguna::SETBA : PeranPengguna::INSPEKTORAT);
        $this->sebagai($peran);
        $hasil = HasilTelaah::from($o['hasil'] ?? 'M');
        $no = ++$this->nomor;
        $memadai = $hasil === HasilTelaah::M;

        PutusPeriksa::jalankan($r, $s, $peran, $hasil, [
            'catatan' => $o['catatan'] ?? '',
            'buktiTolak' => [],
            'nomor' => $uki ? "UKI.{$no}/VAL/BPSDM/2026" : "{$no}/5/2/16/2026/".(200 + $no),
            'tglSurat' => $tgl,
            'perihal' => '', 'berkas' => '', 'link' => '',
            'batasWaktu' => ! $uki && ! $memadai ? ($o['batas'] ?? '') : '',
            'dokumenDiminta' => $memadai ? [] : ($o['dokumen'] ?? []),
            'nomorLhv' => ! $uki && $memadai ? 'PW.03.01-Ij/'.(300 + $no) : '',
            'tglLhv' => ! $uki && $memadai ? $tgl : '',
            'tanda' => [['satker_id' => $s->satker_id, 'hasil' => $hasil->value, 'catatan' => '']],
        ]);
    }

    private function ringkasan(): void
    {
        $baris = Sasaran::with('tindakan.rekomendasi.temuan.laporan', 'satker')->get()
            ->sortBy(fn ($s) => $s->tindakan->rekomendasi->kode)
            ->map(fn ($s) => [
                $s->tindakan->rekomendasi->temuan->laporan->sumber->value,
                $s->tindakan->rekomendasi->kode,
                $s->satker->namaPendek(),
                $s->pos()->label(),
                $s->hasil?->nama($s->tindakan->rekomendasi->jenis()) ?? '—',
                $s->status_bpk?->value ?? '—',
                $s->kembali_dari ?: '',
            ])->values()->all();
        $this->command?->table(['Jenis', 'Kode', 'Satuan kerja', 'Posisi', 'Itjen', 'BPK', 'Kembali dari'], $baris);
    }

    /**
     * Skenario per baris: [satuan kerja, nilai, [[tanggal, aksi, opsi], …]]. Hari ini = 17 Agu 2026.
     */
    private static function laporan(): array
    {
        return [
            [
                'catat' => '2026-03-18',
                'surat' => ['sumber' => 'LHP', 'nomor' => '18/LHP/XVII/03/2026', 'tgl_surat' => '2026-03-09', 'tgl_terima' => '2026-03-16'],
                'berkas' => 'LHP Kepatuhan atas Pengelolaan Belanja dan PNBP BPSDM TA 2025',
                'temuan' => [
                    [
                        'judul' => 'Kelebihan pembayaran honorarium narasumber pada kegiatan pelatihan',
                        'sebab' => 'PPK tidak memedomani standar biaya masukan dalam menetapkan honorarium narasumber.',
                        'akibat' => 'Kelebihan pembayaran honorarium sebesar Rp45.000.000,00.',
                        'kategori' => 'Ketaatan dalam peraturan perundangan', 'intern' => 'Honorarium dan standar biaya',
                        'satker' => ['Balai Wil. I Medan', 'Balai Wil. IV Bandung', 'Pusbangkom Manajemen'],
                        'rekom' => [
                            [
                                /* Selesai: SIPTL Sudah sesuai. */
                                'uraian' => 'Menyetorkan kelebihan pembayaran honorarium narasumber sebesar Rp45.000.000,00 ke kas negara.',
                                'sifat' => 'Informasi kerugian negara', 'bentuk' => 'Penyetoran ke kas negara', 'renaksi' => '2026-05-15',
                                'baris' => [['Balai Wil. I Medan', 45000000, [
                                    ['2026-04-20', 'kirim', ['setor' => [45000000]]], ['2026-04-22', 'teruskan'],
                                    ['2026-05-04', 'uki'], ['2026-05-06', 'teruskan'], ['2026-05-27', 'insp'],
                                    ['2026-06-03', 'unggah'], ['2026-07-15', 'bpk', ['status' => 'SS']],
                                ]]],
                            ],
                            [
                                /* Dua satuan kerja, kemajuan berbeda: satu menunggu BPK, satu di UKI. */
                                'uraian' => 'Memberikan teguran tertulis kepada PPK yang tidak cermat menetapkan honorarium narasumber.',
                                'bentuk' => 'Tindakan administratif atau hukuman disiplin', 'renaksi' => '2026-05-15',
                                'baris' => [
                                    ['Balai Wil. IV Bandung', 0, [
                                        ['2026-04-24', 'kirim'], ['2026-04-27', 'teruskan'], ['2026-05-08', 'uki'],
                                        ['2026-05-11', 'teruskan'], ['2026-06-02', 'insp'], ['2026-06-10', 'unggah'],
                                    ]],
                                    ['Pusbangkom Manajemen', 0, [['2026-07-20', 'kirim'], ['2026-07-23', 'teruskan']]],
                                ],
                            ],
                        ],
                    ],
                    [
                        'judul' => 'Penerimaan negara bukan pajak terlambat disetor ke kas negara',
                        'sebab' => 'Bendahara penerimaan menampung PNBP lebih dari satu hari kerja sebelum disetor.',
                        'akibat' => 'PNBP sebesar Rp30.000.000,00 terlambat masuk kas negara.',
                        'kategori' => 'Ketaatan dalam peraturan perundangan', 'intern' => 'Penerimaan negara bukan pajak',
                        'satker' => ['Politeknik PU', 'Balai Wil. III Jakarta'],
                        'rekom' => [
                            [
                                /* Setor sebagian, ditolak UKI, menunggu Setba mengirim ulang. */
                                'uraian' => 'Menyetorkan PNBP yang terlambat disetor sebesar Rp30.000.000,00 ke kas negara.',
                                'sifat' => 'Informasi kerugian negara', 'bentuk' => 'Penyetoran ke kas negara', 'renaksi' => '2026-05-15',
                                'baris' => [['Politeknik PU', 30000000, [
                                    ['2026-05-05', 'kirim', ['setor' => [20000000],
                                        'uraian' => 'PNBP sudah disetor Rp20.000.000; sisanya menyusul bulan depan.']],
                                    ['2026-05-07', 'teruskan'],
                                    ['2026-05-19', 'uki', ['hasil' => 'BM',
                                        'catatan' => 'Setoran baru Rp20.000.000; sisa Rp10.000.000 belum disetor dan Nota Konfirmasi KPPN belum dilampirkan.',
                                        'dokumen' => ['Bukti setor sisa PNBP', 'Nota Konfirmasi KPPN']]],
                                ]]],
                            ],
                            [
                                /* Menunggu verifikasi Inspektorat. */
                                'uraian' => 'Menyusun dan menetapkan SOP penyetoran PNBP paling lambat satu hari kerja.',
                                'bentuk' => 'Perbaikan sistem pengendalian intern', 'renaksi' => '2026-06-15',
                                'baris' => [['Balai Wil. III Jakarta', 0, [
                                    ['2026-06-02', 'kirim'], ['2026-06-04', 'teruskan'], ['2026-06-16', 'uki'], ['2026-06-18', 'teruskan'],
                                ]]],
                            ],
                        ],
                    ],
                    [
                        'judul' => 'Barang milik negara belum diinventarisasi dan dicatat secara memadai',
                        'sebab' => 'Satuan kerja belum melaksanakan inventarisasi BMN secara berkala.',
                        'akibat' => 'Nilai BMN pada laporan keuangan berisiko tidak akurat.',
                        'kategori' => 'SPI', 'intern' => 'Barang milik negara',
                        'satker' => ['Balai Wil. VI Surabaya', 'Balai Wil. IX Jayapura', 'Sekretariat BPSDM', 'Pusbangkom SDACKPS'],
                        'rekom' => [
                            [
                                /* Sudah dikirim satuan kerja, perlu ditinjau Setba. */
                                'uraian' => 'Melaksanakan inventarisasi dan pencatatan BMN pada aplikasi SAKTI.',
                                'bentuk' => 'Bukti inventarisasi dan penatausahaan BMN', 'renaksi' => '2026-08-21',
                                'baris' => [['Balai Wil. VI Surabaya', 0, [['2026-08-12', 'kirim']]]],
                            ],
                            [
                                /* Belum ditanggapi, rencana aksi sudah lewat. */
                                'uraian' => 'Memproses usulan penghapusan BMN dalam kondisi rusak berat.',
                                'bentuk' => 'Perbaikan dokumen administrasi', 'renaksi' => '2026-06-30',
                                'baris' => [['Balai Wil. IX Jayapura', 0, []]],
                            ],
                            [
                                /* Draf tersimpan, rencana aksi tinggal beberapa hari. */
                                'uraian' => 'Menyusun daftar BMN idle beserta rencana pemanfaatannya.',
                                'bentuk' => 'Perbaikan sistem pengendalian intern', 'renaksi' => '2026-08-21',
                                'baris' => [['Sekretariat BPSDM', 0, [['2026-08-14', 'draf', ['dokumen' => 1,
                                    'uraian' => 'Daftar BMN idle sudah disusun; rencana pemanfaatan masih dibahas.']]]]],
                            ],
                            [
                                /* Memadai di UKI, menunggu Setba meneruskan ke Inspektorat. */
                                'uraian' => 'Melakukan rekonsiliasi BMN dengan KPKNL.',
                                'bentuk' => 'Bukti inventarisasi dan penatausahaan BMN', 'renaksi' => '2026-07-31',
                                'baris' => [['Pusbangkom SDACKPS', 0, [['2026-07-06', 'kirim'], ['2026-07-08', 'teruskan'], ['2026-07-21', 'uki']]]],
                            ],
                        ],
                    ],
                    [
                        'judul' => 'Kelebihan pembayaran pekerjaan pemeliharaan gedung kantor',
                        'sebab' => 'Pengawas tidak cermat memeriksa volume pekerjaan sebelum pembayaran.',
                        'akibat' => 'Kelebihan pembayaran sebesar Rp85.000.000,00 dan dokumen kontrak tidak sesuai realisasi.',
                        'kategori' => 'Ketaatan dalam peraturan perundangan', 'intern' => 'Pengadaan barang dan jasa',
                        'satker' => ['Balai Wil. II Palembang', 'Balai Wil. VII Banjarmasin', 'Balai Wil. V Yogyakarta'],
                        'rekom' => [
                            [
                                /* SIPTL Belum sesuai, menunggu Setba mengirim ulang. */
                                'uraian' => 'Menyetorkan kelebihan pembayaran pekerjaan pemeliharaan sebesar Rp60.000.000,00 ke kas negara.',
                                'sifat' => 'Informasi kerugian negara', 'bentuk' => 'Penyetoran ke kas negara', 'renaksi' => '2026-05-15',
                                'baris' => [['Balai Wil. II Palembang', 60000000, [
                                    ['2026-04-21', 'kirim', ['setor' => [60000000]]], ['2026-04-23', 'teruskan'],
                                    ['2026-05-05', 'uki'], ['2026-05-07', 'teruskan'], ['2026-05-28', 'insp'],
                                    ['2026-06-04', 'unggah'],
                                    ['2026-07-15', 'bpk', ['status' => 'BS', 'catatan' => 'Nota Konfirmasi KPPN belum sesuai dengan nilai setoran.']],
                                ]]],
                            ],
                            [
                                /* Ditolak BPK, sudah dikirim ulang ke satuan kerja (periode kedua). */
                                'uraian' => 'Menagih kelebihan pembayaran sebesar Rp25.000.000,00 kepada penyedia dan menyetorkannya ke kas negara.',
                                'sifat' => 'Informasi kerugian negara', 'bentuk' => 'Penyetoran ke kas negara', 'renaksi' => '2026-05-15',
                                'baris' => [['Balai Wil. VII Banjarmasin', 25000000, [
                                    ['2026-04-22', 'kirim', ['setor' => [20000000, 5000000]]], ['2026-04-24', 'teruskan'],
                                    ['2026-05-06', 'uki'], ['2026-05-08', 'teruskan'], ['2026-05-29', 'insp'],
                                    ['2026-06-05', 'unggah'],
                                    ['2026-07-15', 'bpk', ['status' => 'BS', 'catatan' => 'Setoran Rp5.000.000 atas nama pihak lain tidak dapat diakui.']],
                                    ['2026-07-20', 'ulang_bpk', ['alasan' => 'Setoran Rp5.000.000 atas nama pihak lain tidak diakui BPK; setor ulang atas nama penyedia.',
                                        'dokumen' => ['Bukti setor atas nama penyedia'], 'nilai' => 5000000]],
                                ]]],
                            ],
                            [
                                /* Memadai di Inspektorat (CHV diketik Setba), belum diunggah ke SIPTL. */
                                'uraian' => 'Memperbaiki dokumen kontrak dan berita acara serah terima pekerjaan.',
                                'bentuk' => 'Perbaikan dokumen administrasi', 'renaksi' => '2026-06-30',
                                'baris' => [['Balai Wil. V Yogyakarta', 0, [
                                    ['2026-05-12', 'kirim'], ['2026-05-14', 'teruskan'], ['2026-05-26', 'uki'],
                                    ['2026-05-28', 'teruskan'], ['2026-07-30', 'insp', ['oleh' => 'setba']],
                                ]]],
                            ],
                        ],
                    ],
                    [
                        'judul' => 'Pengadaan jasa konsultansi tidak sesuai ketentuan',
                        'sebab' => 'Pejabat pengadaan tidak melakukan evaluasi kualifikasi penyedia secara memadai.',
                        'akibat' => 'Penyedia wanprestasi belum dikenai sanksi.',
                        'kategori' => 'Ketaatan dalam peraturan perundangan', 'intern' => 'Pengadaan barang dan jasa',
                        'satker' => ['Balai Penilaian Kompetensi', 'Pusat Pengelolaan Talenta'],
                        'rekom' => [
                            [
                                /* Belum memadai di Inspektorat, menunggu Setba mengirim ulang. */
                                'uraian' => 'Mengenakan sanksi daftar hitam kepada penyedia jasa konsultansi yang wanprestasi.',
                                'bentuk' => 'Pengenaan sanksi daftar hitam', 'renaksi' => '2026-06-15',
                                'baris' => [['Balai Penilaian Kompetensi', 0, [
                                    ['2026-05-20', 'kirim'], ['2026-05-22', 'teruskan'], ['2026-06-03', 'uki'], ['2026-06-05', 'teruskan'],
                                    ['2026-08-05', 'insp', ['hasil' => 'BM', 'catatan' => 'Surat penetapan sanksi belum ditandatangani PPK.',
                                        'batas' => '2026-08-28']],
                                ]]],
                            ],
                            [
                                /* Dikirim ulang dari Inspektorat; batas perbaikannya sudah lewat. */
                                'uraian' => 'Memberikan teguran tertulis kepada pejabat pengadaan.',
                                'bentuk' => 'Sanksi administratif atau teguran tertulis', 'renaksi' => '2026-06-15',
                                'baris' => [['Pusat Pengelolaan Talenta', 0, [
                                    ['2026-05-21', 'kirim'], ['2026-05-25', 'teruskan'], ['2026-06-04', 'uki'], ['2026-06-08', 'teruskan'],
                                    ['2026-07-10', 'insp', ['hasil' => 'BM', 'catatan' => 'Surat teguran belum bernomor dan belum ditandatangani.',
                                        'batas' => '2026-08-07']],
                                    ['2026-07-13', 'kirim_ulang', ['keterangan' => 'Lampirkan surat teguran yang sudah bernomor dan ditandatangani.',
                                        'dokumen' => ['Surat teguran bernomor']]],
                                ]]],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'catat' => '2026-04-10',
                'surat' => ['sumber' => 'LHA', 'nomor' => '07/LHA/ITJEN/04/2026', 'tgl_surat' => '2026-04-06', 'tgl_terima' => '2026-04-09'],
                'berkas' => 'LHA Audit Kinerja Penyelenggaraan Pelatihan BPSDM TA 2025',
                'temuan' => [
                    [
                        'judul' => 'Penatausahaan persediaan belum tertib',
                        'sebab' => 'Kartu persediaan tidak dimutakhirkan setiap ada mutasi barang.',
                        'akibat' => 'Saldo persediaan pada laporan berbeda dengan hasil stock opname.',
                        'kategori' => 'SPI', 'intern' => 'Barang milik negara',
                        'satker' => ['Balai Wil. VIII Makassar', 'Pusbangkom BMPIPIW', 'Balai Wil. IV Bandung'],
                        'rekom' => [
                            [
                                /* Selesai: Sesuai. */
                                'uraian' => 'Melakukan stock opname persediaan dan memutakhirkan kartu persediaan.',
                                'bentuk' => 'Perbaikan sistem pengendalian intern', 'renaksi' => '2026-05-22',
                                'baris' => [['Balai Wil. VIII Makassar', 0, [
                                    ['2026-05-04', 'kirim'], ['2026-05-06', 'teruskan'], ['2026-05-15', 'uki'],
                                    ['2026-05-18', 'teruskan'], ['2026-06-08', 'insp'],
                                ]]],
                            ],
                            [
                                /* Belum sesuai di Inspektorat, menunggu Setba mengirim ulang. */
                                'uraian' => 'Menyusun SOP penatausahaan persediaan.',
                                'bentuk' => 'Perbaikan sistem pengendalian intern', 'renaksi' => '2026-05-22',
                                'baris' => [['Pusbangkom BMPIPIW', 0, [
                                    ['2026-05-08', 'kirim'], ['2026-05-11', 'teruskan'], ['2026-05-20', 'uki'], ['2026-05-22', 'teruskan'],
                                    ['2026-08-04', 'insp', ['hasil' => 'BM', 'catatan' => 'SOP belum ditetapkan oleh kepala satuan kerja.',
                                        'batas' => '2026-08-31']],
                                ]]],
                            ],
                            [
                                /* Menunggu verifikasi Inspektorat. */
                                'uraian' => 'Menyampaikan laporan persediaan secara berkala setiap triwulan.',
                                'bentuk' => 'Perbaikan dokumen administrasi', 'renaksi' => '2026-07-31',
                                'baris' => [['Balai Wil. IV Bandung', 0, [
                                    ['2026-07-15', 'kirim'], ['2026-07-17', 'teruskan'], ['2026-07-29', 'uki'], ['2026-07-31', 'teruskan'],
                                ]]],
                            ],
                        ],
                    ],
                    [
                        'judul' => 'Pertanggungjawaban perjalanan dinas tidak lengkap',
                        'sebab' => 'Pelaksana perjalanan dinas tidak menyampaikan bukti pengeluaran secara lengkap.',
                        'akibat' => 'Biaya perjalanan dinas belum dapat diyakini kewajarannya.',
                        'kategori' => 'Ketaatan dalam peraturan perundangan', 'intern' => 'Perjalanan dinas dan penginapan',
                        'satker' => ['Politeknik PU', 'Balai Wil. I Medan'],
                        'rekom' => [
                            [
                                /* Dua satuan kerja: satu di UKI, satu dikirim ulang dari UKI. */
                                'uraian' => 'Melengkapi bukti pertanggungjawaban perjalanan dinas.',
                                'bentuk' => 'Perbaikan dokumen administrasi', 'renaksi' => '2026-08-20',
                                'baris' => [
                                    ['Politeknik PU', 0, [['2026-08-03', 'kirim'], ['2026-08-05', 'teruskan']]],
                                    ['Balai Wil. I Medan', 0, [
                                        ['2026-06-10', 'kirim'], ['2026-06-12', 'teruskan'],
                                        ['2026-06-24', 'uki', ['hasil' => 'BM', 'catatan' => 'Boarding pass dan kuitansi hotel belum dilampirkan.',
                                            'dokumen' => ['Boarding pass', 'Kuitansi hotel']]],
                                        ['2026-06-26', 'kirim_ulang', ['keterangan' => 'Lengkapi boarding pass dan kuitansi hotel seluruh pelaksana.',
                                            'dokumen' => ['Boarding pass', 'Kuitansi hotel']]],
                                    ]],
                                ],
                            ],
                            [
                                /* Baru diterima satuan kerja, belum ditanggapi. */
                                'uraian' => 'Memberikan teguran kepada pelaksana perjalanan dinas yang terlambat menyampaikan pertanggungjawaban.',
                                'bentuk' => 'Surat teguran', 'renaksi' => '2026-08-28',
                                'baris' => [['Balai Wil. I Medan', 0, []]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
