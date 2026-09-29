<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\Rekomendasi;
use App\Models\Verifikasi;
use Illuminate\Support\Collection;

/**
 * Riwayat status tindak lanjut — padanan `susunRiwayat` dan `RiwayatBaris`
 * prototipe. Sejak 21 Sep isinya tab "Riwayat" di tiap baris tabel tindak
 * lanjut (riwayat-baris.blade.php), bukan kartu tersendiri.
 *
 * Tujuh catatan yang bentuknya berbeda-beda disamakan jadi satu bentuk baris:
 * perubahan status per baris (dikelompokkan menurut aksi), surat validasi,
 * surat verifikasi, telaah tanpa surat, pengembalian, kiriman satuan kerja, dan
 * penerusan Setba. Satu tindakan, satu baris.
 *
 * Periode verifikasi ditutup oleh penolakan gerbang terakhir: LHP di BPK lewat
 * SIPTL (BS → BT), LHA di verifikasi Inspektorat (→ BM).
 */
class RiwayatTindakLanjut
{
    /** Kolom Sumber kartu riwayat: kode => [kelas, nama]. */
    public const SUMBER = ['UKI' => ['uki', 'UKI'], 'ITJEN' => ['itjen', 'Itjen'], 'SIPTL' => ['siptl', 'SIPTL'],
        'KEMBALI' => ['balik', 'Kembali'], 'SATKER' => ['satker', 'Satker'], 'SETBA' => ['setba', 'Setba']];

    /** @return list<array> butir riwayat, terbaru di atas */
    public static function susun(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): array
    {
        $jenis = $r->jenis();
        $balai = $peran === PeranPengguna::SATKER;
        $barisku = $r->barisTerlihat($peran, $satkerId);
        $terlihat = $barisku->pluck('satker_id')->unique()->all();
        $out = [];
        $tambah = function (array $b) use (&$out) {
            $b['urut'] = count($out);
            $out[] = $b;
        };
        $milik = fn ($s) => $s
            ? ['lingkup' => 'baris', 'satker_id' => $s->satker_id, 'kunci' => $s->tindakan_id.'|'.$s->satker_id]
            : ['lingkup' => 'rek', 'satker_id' => null, 'kunci' => ''];
        $seq = fn ($m) => $m->created_at ? $m->created_at->getTimestamp() : -1;

        $aksiBerjejak = [];
        $aksiSatker = [];
        foreach ($r->semuaBaris() as $x) {
            foreach ($x->riwayatStatus as $j) {
                if ($j->aksi_id) {
                    $aksiBerjejak[$j->aksi_id] = true;
                    $aksiSatker[$j->aksi_id.'|'.$x->satker_id] = true;
                }
            }
        }
        $suratAksi = fn ($id) => $r->keputusan->first(fn ($k) => $k->aksi_id === $id && $k->verifikasi?->jenis === Verifikasi::CHV)
            ?? $r->keputusan->first(fn ($k) => $k->aksi_id === $id);
        $telaahAksi = fn ($id) => $r->telaah->first(fn ($t) => $t->aksi_id === $id);
        $kembaliAksi = fn ($id, $satker) => $r->pengembalian->first(fn ($p) => $p->aksi_id === $id
            && (! $p->sasaran_id || $p->sasaran?->satker_id === $satker));

        /* 1. Perubahan status per baris. */
        foreach ($barisku as $x) {
            $dasar = ['satker_id' => $x->satker_id, 'kunci' => $x->tindakan_id.'|'.$x->satker_id,
                'bentuk' => $x->tindakan?->namaBentuk() ?? '', 'lingkup' => 'baris'];
            $jejak = $x->riwayatStatus->values();
            $sudah = [];
            foreach ($jejak as $j) {
                if (! $j->aksi_id) {
                    $tambah($dasar + [
                        'id' => 'rs-'.$j->id, 'seq' => $seq($j), 'tanggal' => $j->tanggal?->toDateString(),
                        'sumber' => strtoupper($j->sumber), 'dari' => $j->dari, 'ke' => $j->ke, 'oleh' => $j->oleh,
                        'catatan' => (string) $j->catatan, 'tutupPeriode' => self::menutupPeriode($j->sumber, $j->dari, $j->ke, $jenis),
                    ]);
                    continue;
                }
                if (isset($sudah[$j->aksi_id])) {
                    continue;
                }
                $sudah[$j->aksi_id] = true;
                $serangkai = $jejak->where('aksi_id', $j->aksi_id)->values();
                $tutup = $serangkai->contains(fn ($y) => self::menutupPeriode($y->sumber, $y->dari, $y->ke, $jenis));
                $kembali = $kembaliAksi($j->aksi_id, $x->satker_id);

                /* Dikembalikan karena BPK menolak: yang digambar pengembaliannya,
                   bukan "Itjen M → BM". */
                if ($j->jenis_aksi === 'kembaliBpk') {
                    $tambah($dasar + [
                        'id' => 'rs-'.$j->id, 'seq' => $seq($j), 'tanggal' => $j->tanggal?->toDateString(),
                        'sumber' => 'KEMBALI', 'ket' => 'Dikirim ulang', 'oleh' => $kembali?->label_oleh ?: 'Setba',
                        'catatan' => collect([
                            'Ditolak BPK'.($kembali?->alasan ? ": {$kembali->alasan}" : ''),
                            'unggahan SIPTL dilepas, tindak lanjutnya diulang dari satuan kerja',
                            $kembali?->keterangan_setba ? "keterangan Setba: {$kembali->keterangan_setba}" : '',
                            $kembali && $kembali->dokumen ? 'dokumen diminta: '.implode(', ', $kembali->dokumen) : '',
                        ])->filter()->join(' · '),
                        'tutupPeriode' => $tutup,
                    ]);
                    continue;
                }

                /* Putusan UKI atau Itjen, bersama surat atau telaahnya. */
                $putusan = $serangkai->last();
                $surat = $suratAksi($j->aksi_id);
                $telaah = $telaahAksi($j->aksi_id);
                $itjen = strtoupper($putusan->sumber) === 'ITJEN';
                $v = $surat?->verifikasi;
                $berkas = $v?->lampiran ?? $telaah?->lampiran;
                $tambah($dasar + [
                    'id' => 'rs-'.$putusan->id, 'seq' => $seq($putusan),
                    'tanggal' => $v?->tgl_surat?->toDateString() ?? $putusan->tanggal?->toDateString(),
                    'sumber' => strtoupper($putusan->sumber), 'dari' => $putusan->dari, 'ke' => $putusan->ke,
                    'oleh' => $putusan->oleh, 'catatan' => (string) $putusan->catatan,
                    'nomor' => $v?->nomor_surat ?? '', 'nomorLhv' => $v?->nomor_lhv ?? '',
                    'tglLhv' => $v?->tgl_lhv?->toDateString() ?? '',
                    'berkas' => $berkas,
                    'judulBerkas' => $v ? ($itjen ? 'Surat hasil verifikasi ' : 'Surat hasil validasi ').$v->nomor_surat : 'Dokumen perbaikan',
                    'catatanRek' => $itjen && $surat && $putusan->ke === 'M' && $surat->hasil?->value === 'BM'
                        ? 'Rekomendasinya secara keseluruhan masih '.mb_strtolower(\App\Enums\HasilTelaah::BM->nama($jenis)).' — ada satuan kerja lain yang belum selesai.'
                        : '',
                    'tolak' => $putusan->ke === 'BM',
                    'batasWaktu' => $telaah?->batas_waktu?->toDateString() ?? $kembali?->batas_waktu?->toDateString() ?? '',
                    'dokumenDiminta' => $telaah?->dokumen ?? [],
                    'kembali' => $kembali ? ['batasWaktu' => $kembali->batas_waktu?->toDateString() ?? '',
                        'keteranganSetba' => (string) $kembali->keterangan_setba] : null,
                    'tutupPeriode' => $tutup,
                ]);
            }
        }

        /* 2. Surat validasi UKI yang tidak tercakup jejak. */
        foreach ($r->keputusan->filter(fn ($k) => $k->verifikasi?->jenis === Verifikasi::LHV) as $k) {
            if ($k->aksi_id && isset($aksiBerjejak[$k->aksi_id])) {
                continue;
            }
            if ($k->sasaran_id && ! in_array($k->sasaran?->satker_id, $terlihat, true)) {
                continue;
            }
            $v = $k->verifikasi;
            $tambah([
                'id' => 'kv-'.$k->id, 'seq' => $seq($k), 'tanggal' => $v->tgl_surat?->toDateString(),
                'sumber' => 'UKI', 'ke' => $k->hasil?->value, 'oleh' => $v->pejabat ?: 'UKI', 'nomor' => $v->nomor_surat,
                'catatan' => $balai && ! $k->sasaran_id ? '' : (string) $k->catatan,
                'berkas' => $v->lampiran, 'judulBerkas' => 'Surat hasil validasi '.$v->nomor_surat,
            ] + $milik($k->sasaran));
        }

        /* 3. Surat verifikasi Inspektorat yang tidak tercakup jejak — data lama. */
        foreach ($r->keputusan->filter(fn ($k) => $k->verifikasi?->jenis === Verifikasi::CHV) as $k) {
            if ($k->aksi_id && isset($aksiBerjejak[$k->aksi_id])) {
                continue;
            }
            $v = $k->verifikasi;
            $catatan = ! $balai ? (string) $k->catatan
                : collect([$k->catatan_umum])
                    ->merge(collect($k->catatan_satker ?? [])->filter(fn ($c) => in_array($c['satker_id'] ?? null, $terlihat, true))->pluck('catatan'))
                    ->filter()->join(' · ');
            $tambah([
                'id' => 'kv-'.$k->id, 'seq' => $seq($k), 'tanggal' => $v->tgl_surat?->toDateString(),
                'sumber' => 'ITJEN', 'ke' => $k->hasil?->value, 'oleh' => $v->pejabat ?: 'Inspektorat',
                'nomor' => $v->nomor_surat, 'nomorLhv' => $v->nomor_lhv ?? '', 'tglLhv' => $v->tgl_lhv?->toDateString() ?? '',
                'catatan' => $catatan.($k->tenggat_baru ? ' · tenggat baru '.Tampil::tgl($k->tenggat_baru) : ''),
                'berkas' => $v->lampiran, 'judulBerkas' => 'Surat hasil verifikasi '.$v->nomor_surat,
                'lingkup' => 'rek', 'satker_id' => null, 'kunci' => '',
            ]);
        }

        /* 4. Telaah tanpa surat yang tidak tercakup jejak. */
        foreach ($r->telaah->filter(fn ($t) => ! $t->nomor_surat) as $t) {
            if ($t->aksi_id && isset($aksiBerjejak[$t->aksi_id])) {
                continue;
            }
            if ($t->sasaran_id && ! in_array($t->sasaran?->satker_id, $terlihat, true)) {
                continue;
            }
            $tambah([
                'id' => 'th-'.$t->id, 'seq' => $seq($t), 'tanggal' => $t->tanggal?->toDateString(),
                'sumber' => $t->label_oleh === 'UKI' ? 'UKI' : 'ITJEN', 'ke' => $t->hasil?->value,
                'oleh' => $t->label_pencatat ?: $t->label_oleh,
                'catatan' => $balai && ! $t->sasaran_id ? '' : (string) $t->catatan,
                'berkas' => $t->lampiran, 'judulBerkas' => 'Dokumen perbaikan',
            ] + $milik($t->sasaran));
        }

        /* 5. Pengembalian — yang lahir dari putusan sudah tergambar di barisnya. */
        foreach ($r->pengembalian as $p) {
            $sk = $p->sasaran?->satker_id;
            if ($p->aksi_id && isset($aksiSatker[$p->aksi_id.'|'.$sk])) {
                continue;
            }
            if ($p->sasaran_id && ! in_array($sk, $terlihat, true)) {
                continue;
            }
            $tambah([
                'id' => 'kb-'.$p->id, 'seq' => $seq($p), 'tanggal' => $p->tanggal?->toDateString(),
                'sumber' => 'KEMBALI', 'oleh' => $p->label_oleh,
                'catatan' => $balai && ! $p->sasaran_id ? '' : collect([
                    $p->dari ? "Ditolak {$p->dari}".($p->alasan ? ": {$p->alasan}" : '') : $p->alasan,
                    $p->batas_waktu ? 'perbaiki paling lambat '.Tampil::tgl($p->batas_waktu) : '',
                    $p->keterangan_setba ? "keterangan Setba: {$p->keterangan_setba}" : '',
                    $p->dokumen ? 'dokumen diminta: '.implode(', ', $p->dokumen) : '',
                ])->filter()->join(' · '),
                'ket' => $p->dari ? 'Dikirim ulang' : 'Berkas dikembalikan',
                'ikutTutupan' => ! $jenis->melewatiSiptl() && $p->dari === 'Inspektorat',
            ] + $milik($p->sasaran));
        }

        /* 6. Satuan kerja mengirim berkasnya ke Setba. */
        foreach ($r->tanggapan as $t) {
            $x = $barisku->firstWhere('id', $t->sasaran_id);
            if (! $x) {
                continue;
            }
            $tambah([
                'id' => 'tg-'.$t->id, 'seq' => $seq($t), 'tanggal' => $t->tanggal?->toDateString(),
                'sumber' => 'SATKER', 'ket' => 'Dikirim ke Setba', 'oleh' => $t->label_pencatat,
                'catatan' => (string) $t->uraian, 'pindahKe' => 'Setba',
                'lingkup' => 'baris', 'satker_id' => $x->satker_id, 'kunci' => $x->tindakan_id.'|'.$x->satker_id,
            ]);
        }

        /* 7. Setba meneruskan berkasnya, dengan surat pengantarnya. */
        foreach ($r->surat as $q) {
            if (! $q->sasaran_id || ! in_array($q->sasaran?->satker_id, $terlihat, true)) {
                continue;
            }
            $tambah([
                'id' => 'sp-'.$q->id, 'seq' => $seq($q), 'tanggal' => ($q->tanggal ?? $q->tanggal_catat)?->toDateString(),
                'sumber' => 'SETBA', 'ket' => "Diteruskan ke {$q->ke}", 'oleh' => 'Setba', 'nomor' => $q->nomor ?? '',
                'catatan' => (string) $q->perihal, 'pindahKe' => $q->ke,
            ] + $milik($q->sasaran));
        }

        /* Terbaru di atas; seri pada tanggal yang sama diputus urutan pencatatan. */
        usort($out, fn ($a, $b) => strcmp((string) ($b['tanggal'] ?? ''), (string) ($a['tanggal'] ?? ''))
            ?: (($a['seq'] ?? -1) >= 0 && ($b['seq'] ?? -1) >= 0 ? $b['seq'] <=> $a['seq'] : $b['urut'] <=> $a['urut']));

        return $out;
    }

    /**
     * Kolom Catatan terakhir di tabel tindak lanjut — kolom Catatan pada baris
     * "Terbaru" kartu riwayat pasangan ini. Padanan `catatanTerakhir`
     * prototipe. Kata Hizkia (21 Sep): "catatannya berdasarkan catatan riwayat
     * tindak lanjut saja".
     *
     * Sumbernya yang disebut, bukan pengetiknya: putusan Inspektorat diketik
     * Setba, dan "Setba" di bawah catatan Inspektorat terbaca seolah catatan
     * Setba.
     *
     * @param  list<array>  $kartu  hasil kartu()
     * @return array{teks:string, ket:string}|null  null = belum ada peristiwa
     */
    public static function catatanTerakhir(array $kartu, string $kunci): ?array
    {
        $b = collect($kartu)->firstWhere('kunci', $kunci)['baris'][0] ?? null;
        if (! $b) {
            return null;
        }

        return [
            'teks' => (string) ($b['catatan'] ?? ''),
            'ket' => collect([
                self::SUMBER[$b['sumber']][1] ?? $b['sumber'],
                ! empty($b['tanggal']) ? Tampil::tgl($b['tanggal']) : '',
                ($b['lingkup'] ?? '') === 'rek' ? 'seluruh rekomendasi' : '',
            ])->filter()->join(' · '),
        ];
    }

    public static function menutupPeriode(string $sumber, ?string $dari, ?string $ke, SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl()
            ? strtoupper($sumber) === 'SIPTL' && $dari === 'BS' && $ke === 'BT'
            : strtoupper($sumber) === 'ITJEN' && $ke === 'BM';
    }

    /**
     * Isi tab Riwayat untuk tiap lingkup — pasangan bentuk tindak lanjut dan
     * satuan kerja, sama dengan satu baris tabel tindak lanjut. Tiap lingkup
     * punya periodenya sendiri, jadi dihitung terpisah.
     *
     * @return list<array>
     */
    public static function kartu(Rekomendasi $r, PeranPengguna $peran, ?int $satkerId): array
    {
        $jenis = $r->jenis();
        $semua = self::susun($r, $peran, $satkerId);
        $lingkupan = $r->barisTerlihat($peran, $satkerId)->map(fn ($x) => [
            'kunci' => $x->tindakan_id.'|'.$x->satker_id, 'satker' => $x->satker, 'bentuk' => $x->tindakan?->namaBentuk() ?? '',
            'posisi' => $x->pos(), 'x' => $x,
        ])->values();
        $banyak = $lingkupan->count() > 1;

        return $lingkupan->map(function ($aktif) use ($semua, $banyak, $jenis) {
            $baris = $banyak
                ? array_values(array_filter($semua, fn ($b) => ($b['lingkup'] ?? '') === 'rek'
                    || (! empty($b['kunci']) ? $b['kunci'] === $aktif['kunci'] : ($b['satker_id'] ?? null) === $aktif['satker']->id)))
                : $semua;

            $tutupan = array_values(array_filter($baris, fn ($b) => ! empty($b['tutupPeriode'])));
            $lebihDulu = fn ($t, $b) => ($t['tanggal'] ?? '') < ($b['tanggal'] ?? '')
                || (($t['tanggal'] ?? '') === ($b['tanggal'] ?? '') && $t['seq'] >= 0 && $b['seq'] >= 0 && $t['seq'] < $b['seq']);
            $nomorPeriode = fn ($b) => max(1, 1 + count(array_filter($tutupan, fn ($t) => $t['id'] !== $b['id'] && $lebihDulu($t, $b)))
                - (! empty($b['ikutTutupan']) ? 1 : 0));
            $periodeKini = count($tutupan) + 1;

            $lewatSiptl = $jenis->melewatiSiptl();
            $tujuan = function ($b) use ($lewatSiptl) {
                if (! empty($b['pindahKe'])) {
                    return $b['pindahKe'];
                }
                $s = $b['sumber'];
                if ($s === 'UKI') {
                    return 'Setba';
                }
                if ($s === 'ITJEN') {
                    return ($b['ke'] ?? '') === 'M' && ! $lewatSiptl ? 'Selesai' : 'Setba';
                }
                if ($s === 'KEMBALI') {
                    return 'Satker';
                }
                if ($s === 'SIPTL') {
                    if (empty($b['dari']) && ! empty($b['ke'])) {
                        return 'BPK';
                    }
                    if (in_array($b['ke'] ?? '', ['SS', 'TD'], true)) {
                        return 'Selesai';
                    }
                    if (($b['ke'] ?? '') === 'BS') {
                        return 'Setba';
                    }
                }

                return null;
            };
            $jalurPeriode = function ($k) use ($baris, $nomorPeriode, $tujuan) {
                $j = [['tempat' => 'Satker', 'tanggal' => '']];
                $isi = array_values(array_filter($baris, fn ($b) => $nomorPeriode($b) === $k));
                usort($isi, fn ($a, $c) => strcmp((string) $a['tanggal'], (string) $c['tanggal'])
                    ?: ($a['seq'] >= 0 && $c['seq'] >= 0 ? $a['seq'] <=> $c['seq'] : $a['urut'] <=> $c['urut']));
                foreach ($isi as $b) {
                    $t = $tujuan($b);
                    if ($t && $t !== end($j)['tempat']) {
                        $j[] = ['tempat' => $t, 'tanggal' => $b['tanggal']];
                    }
                }

                return $j;
            };

            /* "Posisi saat ini" dulu dihitung di sini untuk kartu riwayat. Tab
               Riwayat tidak menyebutnya lagi: alur tahap di atas tab sudah
               menandainya (21 Sep). */

            $babak = [];
            for ($k = $periodeKini; $k >= 1; $k--) {
                $isi = array_values(array_filter($baris, fn ($b) => $nomorPeriode($b) === $k));
                $tutupBaris = collect($isi)->first(fn ($b) => ! empty($b['tutupPeriode']));
                $babak[] = ['k' => $k, 'isi' => $isi, 'tutup' => $tutupBaris['tanggal'] ?? null,
                    'jalur' => $k !== $periodeKini && $isi ? $jalurPeriode($k) : []];
            }

            return [
                'kunci' => $aktif['kunci'], 'satker' => $aktif['satker'], 'bentuk' => $aktif['bentuk'],
                'baris' => $baris, 'periodeKini' => $periodeKini,
                'jalur' => $jalurPeriode($periodeKini), 'babak' => $babak,
                'adaSurat' => collect($baris)->contains(fn ($b) => ! empty($b['nomor']) || ! empty($b['berkas'])
                    || ! empty($b['nomorLhv']) || ! empty($b['tglLhv'])),
            ];
        })->all();
    }
}
