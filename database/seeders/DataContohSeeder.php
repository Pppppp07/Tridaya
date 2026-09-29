<?php

namespace Database\Seeders;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\DrafTanggapan;
use App\Models\ItemPermintaan;
use App\Models\KategoriTemuan;
use App\Models\KeputusanVerifikasi;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\Pemulihan;
use App\Models\Pengembalian;
use App\Models\PermintaanDokumen;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Surat;
use App\Models\Tanggapan;
use App\Models\Telaah;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Models\TolakanBpk;
use App\Models\User;
use App\Models\Verifikasi;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh yang sama persis dengan prototipe.
 *
 * Dibaca dari `database/data/data-contoh.json` — salinan
 * `Prototipe/alat/data-contoh.json`, yang juga menjadi `AWAL` dan `PEMBERITAHUAN_AWAL`
 * di prototipe. Data itu tidak diketik: ia dibangun dengan memutar tindakan
 * aplikasi prototipe sendiri, jadi setiap keadaannya memang bisa dicapai.
 * Menyalinnya di sini, bukan mengarang ulang, membuat kedua artefak
 * memperagakan berkas yang sama — dan selisih tampilan di antara keduanya
 * berarti selisih aturan, bukan selisih data.
 *
 * Tanggalnya disusun untuk "hari ini" 17 Agustus 2026. Pasang
 * `SIMTLHP_HARI_INI=2026-08-17` supaya tenggat dan keterlambatannya terbaca
 * sama dengan prototipe.
 */
class DataContohSeeder extends Seeder
{
    /** Kunci posisi prototipe yang namanya berbeda di sini. */
    private const POSISI = [
        'balai' => 'satker', 'setba_review' => 'setba_tinjau', 'setba_uki' => 'setba_teruskan',
    ];

    /** Peran prototipe yang namanya berbeda di sini. */
    private const PERAN = ['balai' => 'satker'];

    private Collection $satkerPanjang;
    private Collection $satkerPendek;
    private array $bentuk;
    private array $kategoriIntern;
    private array $sifat;
    private array $alasanTd;
    private ?int $setba;
    /** @var array<string, Rekomendasi> id prototipe => rekomendasi */
    private array $rek = [];

    public function run(): void
    {
        $data = json_decode(file_get_contents(base_path('database/data/data-contoh.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->satkerPanjang = Satker::all()->keyBy('nama');
        $this->satkerPendek = Satker::all()->keyBy('nama_pendek');
        $this->bentuk = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->pluck('id', 'nama')->all();
        $this->kategoriIntern = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->pluck('id', 'nama')->all();
        $this->sifat = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->pluck('id', 'nama')->all();
        $this->alasanTd = Referensi::where('jenis', JenisReferensi::ALASAN_TD->value)->pluck('id', 'nama')->all();
        $this->setba = User::where('peran', PeranPengguna::SETBA->value)->value('id');

        DB::transaction(function () use ($data) {
            foreach ($data['laporan'] as $lap) {
                $this->laporan($lap);
            }
            foreach ($data['pemberitahuan'] as $k) {
                $this->pemberitahuan($k);
            }

            /* Catatan data contoh tidak punya urutan pencatatan — di prototipe
               idnya bukan buatan `uid()`. Riwayat status memakai `created_at`
               sebagai urutan itu; dikosongkan supaya seri pada tanggal yang
               sama diputus urutan sumbernya, sama seperti prototipe. */
            foreach (['riwayat_statuses', 'tindak_lanjuts', 'surats', 'telaahs',
                'keputusan_verifikasis', 'pengembalians'] as $tabel) {
                DB::table($tabel)->update(['created_at' => null, 'updated_at' => null]);
            }

            $this->logDariRiwayat();
        });
    }

    /**
     * Log aktivitas awal (27 Sep): satu baris per baris riwayat berkas data
     * contoh — padanan `logDariRiwayat` prototipe, jadi kedua artefak membuka
     * Log aktivitas dengan isi yang sama. Pelakunya dicari menurut sebutan di
     * riwayat: "Setba", "UKI", "Inspektorat" → akun pusatnya; nama pendek
     * satuan kerja → penanggung jawabnya sekarang. Waktunya cuma tanggal
     * (riwayat lama tidak menyimpan jam), alamat dan perangkatnya kosong.
     * Sesudah ini, perbuatan baru dicatat App\Support\Aktivitas.
     */
    public function logDariRiwayat(): void
    {
        /* Sekali saja: kalau log berkas sudah ada, riwayatnya sudah tersalin. */
        if (\App\Models\LogAktivitas::where('aksi', 'berkas')->exists()) {
            return;
        }
        $pusat = [
            'Setba'       => User::where('peran', PeranPengguna::SETBA->value)->where('aktif', true)->orderBy('id')->first(),
            'UKI'         => User::where('peran', PeranPengguna::UKI->value)->where('aktif', true)->orderBy('id')->first(),
            'Inspektorat' => User::where('peran', PeranPengguna::INSPEKTORAT->value)->where('aktif', true)->orderBy('id')->first(),
        ];
        $pj = User::where('peran', PeranPengguna::SATKER->value)->where('aktif', true)->with('satker')->get()
            ->filter(fn ($u) => $u->satker)->keyBy(fn ($u) => $u->satker->nama_pendek);

        $baris = [];
        foreach (RiwayatBerkas::orderBy('id')->get() as $rw) {
            $u = $pusat[$rw->label_aktor] ?? $pj[$rw->label_aktor] ?? null;
            $baris[] = [
                'waktu'       => $rw->waktu->copy()->startOfDay(),
                'user_id'     => $u?->id,
                'nama'        => $u?->name ?? $rw->label_aktor,
                'peran'       => $u?->peran->value,
                'satker_id'   => $u?->peran === PeranPengguna::SATKER ? $u->satker_id : null,
                'aksi'        => 'berkas',
                'kelompok'    => 'berkas',
                'ringkasan'   => mb_substr((string) $rw->aksi, 0, 500),
                'subjek_tipe' => 'rekomendasi',
                'subjek_id'   => $rw->rekomendasi_id,
                'rincian'     => json_encode(['atas_nama' => $rw->label_aktor], JSON_UNESCAPED_UNICODE),
                'ip'          => null,
                'agen'        => null,
            ];
        }
        foreach (array_chunk($baris, 200) as $potong) {
            \App\Models\LogAktivitas::insert($potong);
        }
    }

    private function tgl(?string $s): ?string
    {
        return $s ? $s : null;
    }

    private function satker(string $nama): Satker
    {
        return $this->satkerPanjang[$nama] ?? $this->satkerPendek[$nama]
            ?? throw new \RuntimeException("Satuan kerja tidak dikenal: {$nama}");
    }

    private function laporan(array $lap): void
    {
        $laporan = Laporan::create([
            'sumber'       => $lap['jenis'],
            'nomor'        => $lap['nomorDok'],
            'tgl_surat'    => $this->tgl($lap['tglDok']),
            'tgl_terima'   => $lap['tglTerima'],
            'dicatat_pada' => $this->tgl($lap['tglInput']),
            'dicatat_oleh' => $this->setba,
        ]);

        if ($lap['berkasDok'] || $lap['linkDok']) {
            Lampiran::create([
                'laporan_id'    => $laporan->id,
                'nama_asli'     => $lap['berkasDok'] ?: null,
                'link'        => $lap['linkDok'] ?: null,
                'label_jenis'   => 'Surat laporan pemeriksaan',
                'label_oleh'    => 'Setba',
                'surat_asli'    => true,
                'diunggah_oleh' => $this->setba,
                'diunggah_pada' => $lap['tglTerima'],
            ]);
        }

        $sumber = SumberLaporan::from($lap['jenis']);
        foreach ($lap['temuan'] as $tem) {
            $temuan = Temuan::create([
                'laporan_id'         => $laporan->id,
                'kode'               => $tem['kode'],
                'nomor_pada_surat'   => $tem['nomorTemuan'],
                'judul'              => $tem['judul'],
                'sebab'              => $tem['sebab'],
                'akibat'             => $tem['akibat'],
                'kategori_temuan_id' => KategoriTemuan::where('sumber', $sumber->value)
                    ->where('nama', $tem['kategori'])->value('id'),
                'kategori_intern_id' => $this->kategoriIntern[$tem['kategoriIntern']] ?? null,
                'nilai'              => (int) $tem['nilai'],
            ]);
            foreach ($tem['satkerList'] as $nama) {
                $temuan->satkers()->attach($this->satker($nama)->id);
            }
            foreach ($tem['rekom'] as $r) {
                $this->rekomendasi($temuan, $r);
            }
        }
    }

    private function rekomendasi(Temuan $temuan, array $r): void
    {
        $rek = Rekomendasi::create([
            'temuan_id'          => $temuan->id,
            'kode'               => $r['kode'],
            'ref_lhp'            => $r['refLhp'],
            'nomor_urut'         => ord(strtolower($r['no'] ?: 'a')) - 96,
            'uraian'             => $r['uraian'],
            'sifat_id'           => $this->sifat[$r['sifat']] ?? null,
            'nilai_pulih'        => (int) $r['nilaiPulih'],
            'rencana_angsur'     => (int) $r['rencanaAngsur'],
            'kunci_angsur'       => (bool) $r['kunciAngsur'],
            'tenggat_jawab'      => $this->tgl($r['tenggatJawab']),
            'target_selesai'     => $this->tgl($r['targetSelesai']),
            'catatan'            => $r['catatanSetba'] ?: null,
            'status'             => $r['status'] ?: 'BT',
            'alasan_td_id'       => $this->alasanTd[$r['alasanTd']] ?? null,
            'catatan_td'         => $r['catatanTd'] ?: null,
            'siptl_tanggal'      => $r['siptl']['tanggal'] ?? null,
            'siptl_status'       => $r['siptlStatus']['status'] ?? null,
            'siptl_catatan'      => $r['siptlStatus']['catatan'] ?? null,
            'siptl_dicatat_pada' => $r['siptlStatus']['dicatat'] ?? null,
        ]);
        $this->rek[$r['id']] = $rek;

        /* ---- tindakan dan barisnya ---- */
        $tindakan = [];      // id prototipe => Tindakan
        $baris = [];         // "nama satker panjang|id tindakan" => Sasaran
        foreach ($r['tindakan'] as $i => $tk) {
            $t = Tindakan::create([
                'rekomendasi_id' => $rek->id,
                'bentuk_id'      => $this->bentuk[$tk['bentuk']] ?? null,
                'urutan'         => $i + 1,
                'tgl_renaksi'    => $this->tgl($tk['tglRenaksi']),
                'target_selesai' => $this->tgl($tk['targetSelesai']),
                'catatan'        => $tk['catatan'] ?: null,
                'dokumen'        => $tk['dokumen'] ?? [],
            ]);
            $tindakan[$tk['id']] = $t;

            foreach ($tk['sasaran'] as $x) {
                $s = new Sasaran([
                    'tindakan_id'      => $t->id,
                    'satker_id'        => $this->satker($x['satker'])->id,
                    'nilai'            => (int) $x['nilai'],
                    'posisi'           => self::POSISI[$x['posisi']] ?? $x['posisi'],
                    'hasil'            => ($x['hasil'] ?? '') ?: null,
                    'hasil_uki'        => ($x['hasilUki'] ?? '') ?: null,
                    'catatan'          => ($x['catatan'] ?? '') ?: null,
                    'status_bpk'       => ($x['statusBpk'] ?? '') ?: null,
                    'catatan_bpk'      => ($x['catatanBpk'] ?? '') ?: null,
                    'tgl_pantau'       => ($x['tglPantau'] ?? '') ?: null,
                    'kembali_dari'     => ($x['kembaliDari'] ?? '') ?: null,
                    'alasan_perbaikan' => ($x['alasanPerbaikan'] ?? '') ?: null,
                    'batas_perbaikan'  => ($x['batasPerbaikan'] ?? '') ?: null,
                    'keterangan_setba' => ($x['keteranganSetba'] ?? '') ?: null,
                    'dokumen_diminta'  => ($x['dokumenDiminta'] ?? []) ?: null,
                ]);
                /* Tanggal unggah SIPTL tidak bisa diisi massal — sengaja. */
                $s->forceFill(['siptl_tanggal' => $x['siptl']['tanggal'] ?? null])->save();
                $baris[$x['satker'].'|'.$tk['id']] = $s;

                foreach ($x['riwayatStatus'] ?? [] as $j) {
                    RiwayatStatus::create([
                        'sasaran_id' => $s->id,
                        'sumber'     => $j['sumber'],
                        'dari'       => $j['dari'] ?? '',
                        'ke'         => $j['ke'],
                        'oleh'       => $j['oleh'],
                        'tanggal'    => $j['tanggal'],
                        'catatan'    => ($j['catatan'] ?? '') ?: null,
                        'aksi_id'    => $j['aksi'] ?? null,
                        'jenis_aksi' => $j['jenisAksi'] ?? null,
                    ]);
                }
            }
        }

        /* Baris milik satu satuan kerja pada satu tindakan. Nama satuan kerja
           boleh panjang atau pendek — tanggapan menyimpan yang pendek. */
        $sasaran = function (?string $satker, ?string $tkId) use ($baris) {
            if (! $satker) {
                return null;
            }
            $panjang = $this->satker($satker)->nama;
            if ($tkId && isset($baris[$panjang.'|'.$tkId])) {
                return $baris[$panjang.'|'.$tkId];
            }
            foreach ($baris as $kunci => $s) {
                if (str_starts_with($kunci, $panjang.'|')) {
                    return $s;
                }
            }

            return null;
        };
        $satkerDari = function (?string $nama) {
            return $nama && ($this->satkerPanjang->has($nama) || $this->satkerPendek->has($nama));
        };

        /* ---- berkas ---- */
        $dok = [];   // id prototipe => Lampiran
        foreach ($r['dok'] as $d) {
            $s = $satkerDari($d['o']) ? $sasaran($d['o'], $d['tindakan'] ?? null) : null;
            $dok[$d['id']] = Lampiran::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $s?->id,
                'tindakan_id'    => isset($d['tindakan']) ? ($tindakan[$d['tindakan']]->id ?? null) : null,
                'nama_asli'      => $d['n'],
                'label_jenis'    => $d['j'],
                'link'         => ($d['link'] ?? '') ?: null,
                'label_oleh'     => $d['o'],
                'surat_asli'     => (bool) ($d['suratAsli'] ?? false),
                'diunggah_oleh'  => $d['o'] === 'Setba' ? $this->setba : null,
                'diunggah_pada'  => $d['t'],
            ]);
        }

        /* ---- dokumen yang diminta ---- */
        $butir = [];   // id prototipe => ItemPermintaan
        foreach ($r['permintaan'] as $p) {
            $pm = PermintaanDokumen::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $sasaran($p['satker'], $p['tindakan'])?->id,
                'peran_peminta'  => PeranPengguna::SETBA->value,
                'dari'           => ($p['dari'] ?? '') ?: null,
                'diminta_oleh'   => $this->setba,
                'tanggal'        => $p['tanggal'],
                'alasan'         => ($p['alasan'] ?? '') ?: null,
            ]);
            foreach ($p['item'] as $it) {
                $item = ItemPermintaan::create([
                    'permintaan_dokumen_id' => $pm->id,
                    'nama'                  => $it['nama'],
                    'terpenuhi'             => (bool) $it['terpenuhi'],
                ]);
                $butir[$it['id']] = $item;
                foreach ($it['dariDok'] ?? [] as $dId) {
                    if (isset($dok[$dId])) {
                        $item->lampiran()->attach($dok[$dId]->id);
                    }
                }
            }
        }

        /* ---- tanggapan satuan kerja ---- */
        foreach ($r['tanggapan'] as $tg) {
            $s = $sasaran($tg['oleh'], $tg['tindakan']);
            Tanggapan::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $s?->id,
                'tanggal'        => $tg['tanggal'],
                'uraian'         => $tg['uraian'],
                'label_pencatat' => $tg['oleh'],
                'dicatat_oleh'   => $s ? User::where('satker_id', $s->satker_id)->value('id') : null,
            ]);
        }

        /* ---- pemulihan ---- */
        foreach ($r['setoran'] as $st) {
            $s = $sasaran($st['satker'], $st['tindakan']);
            $berkas = ($st['berkas'] ?? '') || ($st['link'] ?? '')
                ? Lampiran::create([
                    'sasaran_id'    => $s?->id,
                    'tindakan_id'   => $s?->tindakan_id,
                    'nama_asli'     => ($st['berkas'] ?? '') ?: null,
                    'link'        => ($st['link'] ?? '') ?: null,
                    'label_jenis'   => 'Bukti setor',
                    'label_oleh'    => $s?->satker->namaPendek(),
                    'diunggah_pada' => $st['tanggal'],
                ])
                : null;
            Pemulihan::create([
                'rekomendasi_id'  => $rek->id,
                'sasaran_id'      => $s?->id,
                'jenis'           => $st['jenis'] ?: 'setor',
                'tanggal'         => $st['tanggal'],
                'nilai'           => (int) $st['nilai'],
                'no_ssbp'         => ($st['ssbp'] ?? '') ?: null,
                'ntpn'            => ($st['ntpn'] ?? '') ?: null,
                'no_nota_kppn'    => ($st['notaKppn'] ?? '') ?: null,
                'no_berita_acara' => ($st['noBa'] ?? '') ?: null,
                'lampiran_id'     => $berkas?->id,
            ]);
        }

        /* ---- surat pengantar Setba ---- */
        foreach ($r['surat'] as $sp) {
            Surat::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $sasaran($sp['satker'], $sp['tindakan'])?->id,
                'dari'           => $sp['dari'],
                'ke'             => $sp['ke'],
                'nomor'          => $sp['nomor'],
                'tanggal'        => $sp['tanggal'],
                'tanggal_catat'  => $this->tgl($sp['tanggalCatat']),
                'perihal'        => $sp['perihal'] ?: null,
                'catatan'        => $sp['catatan'] ?: null,
                'link'         => $sp['link'] ?: null,
                'dicatat_oleh'   => $this->setba,
            ]);
        }

        /* ---- putusan UKI dan Inspektorat ---- */
        foreach ($r['telaah'] as $th) {
            Telaah::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $sasaran($th['satker'], $th['tindakan'])?->id,
                'tanggal'        => $th['tanggal'],
                'label_oleh'     => $th['oleh'],
                'label_pencatat' => $th['dicatatOleh'] ?: $th['oleh'],
                'hasil'          => $th['hasil'] ?: null,
                'catatan'        => $th['catatan'] ?? '',
                'nomor_surat'    => $th['nomor'] ?: null,
                'tgl_surat'      => $this->tgl($th['tglSurat']),
                'perihal'        => $th['perihal'] ?: null,
                'batas_waktu'    => $this->tgl($th['batasWaktu']),
                'dokumen'        => $th['dokumen'] ?: null,
                'aksi_id'        => $th['aksi'] ?: null,
            ]);
        }

        foreach ([Verifikasi::LHV => $r['validasi'], Verifikasi::CHV => $r['verifikasi']] as $jenis => $daftar) {
            foreach ($daftar as $v) {
                $surat = Verifikasi::firstOrCreate(
                    ['jenis' => $jenis, 'nomor_surat' => $v['nomor']],
                    [
                        'tgl_surat'    => $v['tglSurat'],
                        'perihal'      => $v['perihal'] ?: null,
                        'pejabat'      => $v['pejabat'] ?: ($jenis === Verifikasi::LHV ? 'UKI' : 'Inspektorat'),
                        'nomor_lhv'    => $v['nomorLhv'] ?: null,
                        'tgl_lhv'      => $this->tgl($v['tglLhv']),
                        'dicatat_oleh' => $this->setba,
                    ],
                );
                KeputusanVerifikasi::create([
                    'verifikasi_id'  => $surat->id,
                    'rekomendasi_id' => $rek->id,
                    'sasaran_id'     => $sasaran($v['satker'], $v['tindakan'])?->id,
                    'hasil'          => $v['hasil'] ?: 'BM',
                    'tenggat_baru'   => $this->tgl($v['tenggatBaru']),
                    'catatan'        => $v['catatan'] ?: null,
                    'catatan_umum'   => $v['catatanUmum'] ?: null,
                    'catatan_satker' => collect($v['catatanSatker'])->map(fn ($c) => [
                        'satker_id' => $this->satker($c['satker'])->id,
                        'catatan'   => $c['catatan'],
                    ])->all(),
                    'aksi_id'        => $v['aksi'] ?: null,
                ]);
            }
        }

        /* ---- pengiriman ulang ---- */
        foreach ($r['pengembalian'] as $kb) {
            Pengembalian::create([
                'rekomendasi_id'   => $rek->id,
                'sasaran_id'       => $sasaran($kb['satker'], $kb['tindakan'])?->id,
                'tanggal'          => $kb['tanggal'],
                'oleh_id'          => $this->setba,
                'label_oleh'       => $kb['oleh'],
                'dari'             => $kb['dari'] ?: null,
                'alasan'           => $kb['alasan'] ?? '',
                'batas_waktu'      => $this->tgl($kb['batasWaktu']),
                'keterangan_setba' => $kb['keteranganSetba'] ?: null,
                'dokumen'          => $kb['dokumen'] ?: null,
                'aksi_id'          => $kb['aksi'] ?: null,
            ]);
        }

        foreach ($r['tolakanBpk'] as $tb) {
            TolakanBpk::create([
                'rekomendasi_id' => $rek->id,
                'sasaran_id'     => $sasaran($tb['satker'], $tb['tindakan'])?->id,
                'nilai'          => (int) $tb['nilai'],
                'tanggal'        => $tb['tanggal'],
                'catatan'        => $tb['catatan'] ?: null,
            ]);
        }

        /* ---- draf yang belum dikirim ----
           Butir yang dipenuhi disimpan sebagai id butir sungguhan; berkasnya
           tetap di dalam draf sampai dikirim. */
        foreach ($r['draf'] as $df) {
            $s = $sasaran($df['satker'], $df['tindakan']);
            if (! $s) {
                continue;
            }
            DrafTanggapan::create([
                'sasaran_id' => $s->id,
                'uraian'     => $df['uraian'] ?: null,
                'tanggal'    => $this->tgl($df['tanggal']),
                'bukti'      => collect($df['bukti'])->map(fn ($b) => [
                    'nama'   => $b['n'],
                    'jenis'  => $b['j'] ?? '',
                    'link' => $b['link'] ?? '',
                    'untuk'  => isset($b['untuk'], $butir[$b['untuk']]) ? $butir[$b['untuk']]->id : null,
                ])->all(),
                'setoran'    => $df['setoran'],
                'penuhi'     => collect($df['penuhi'])->map(fn ($id) => $butir[$id]->id ?? null)->filter()->values()->all(),
                'kali'       => (int) $df['kali'],
                'terakhir'   => $df['terakhir'],
            ]);
        }

        /* ---- riwayat aktivitas ---- */
        foreach ($r['riwayat'] as $rw) {
            RiwayatBerkas::create([
                'rekomendasi_id' => $rek->id,
                'waktu'          => $rw['t'],
                'label_aktor'    => $rw['a'],
                'aksi'           => $rw['k'],
            ]);
        }
    }

    private function pemberitahuan(array $k): void
    {
        $rek = $this->rek[$k['rekId']] ?? null;
        if (! $rek) {
            return;
        }

        $tindakan = $k['bentuk']
            ? $rek->tindakan()->whereHas('bentuk', fn ($q) => $q->where('nama', $k['bentuk']))->first()
            : null;

        $n = Notifikasi::create([
            'rekomendasi_id' => $rek->id,
            'tindakan_id'    => $tindakan?->id,
            'waktu'          => Carbon::createFromFormat('Y-m-d H.i', $k['waktu']),
            'label_pelaku'   => $k['oleh'],
            'aksi'           => $k['aksi'],
            'untuk_peran'    => collect($k['untuk'])->map(fn ($p) => self::PERAN[$p] ?? $p)->values()->all(),
            'blok'           => $k['blok'] ?: null,
        ]);
        $n->satker()->attach(collect($k['satker'])->map(fn ($s) => $this->satker($s)->id)->unique()->all());

        /* Tanda baca prototipe milik peran — "setba", "balai:<satker>" — dan
           di sini milik akun. Seluruh akun di balik kunci itu ikut membacanya. */
        $pembaca = collect($k['dibaca'])->flatMap(function ($kunci) {
            if (str_starts_with($kunci, 'balai:')) {
                return User::where('peran', PeranPengguna::SATKER->value)
                    ->where('satker_id', $this->satker(substr($kunci, 6))->id)->pluck('id');
            }

            return User::where('peran', self::PERAN[$kunci] ?? $kunci)->pluck('id');
        })->unique();
        foreach ($pembaca as $id) {
            $n->dibaca()->attach($id, ['dibaca_pada' => $n->waktu]);
        }
    }
}
