<?php

namespace App\Aksi;

use App\Enums\JenisPemulihan;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\DrafTanggapan;
use App\Models\Lampiran;
use App\Models\Pemulihan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Tanggapan;
use App\Support\Jejak;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Satuan kerja mengisi tindak lanjut satu baris — padanan
 * `aksi.simpanTanggapan` dan penyapu kiriman otomatis prototipe.
 *
 * Dua jalan dari satu isian:
 *
 *   draf   berkasnya tetap di meja satuan kerja; isiannya menimpa draf
 *          sebelumnya. Yang masuk ke rekam jejak permanen hanya kemajuannya.
 *   kirim  berkasnya pindah ke Setba, dan isiannya jadi arsip: tanggapan,
 *          setoran, berkas bukti, dan butir dokumen yang terpenuhi.
 *
 * `$isi` berbentuk isian formulir: uraian, tanggal, bukti[] {nama, jenis,
 * link, untuk}, setoran[] {jenis, tanggal, nilai, ssbp, ntpn, notaKppn,
 * noBa, berkas, link}, penuhi[] (id butir dokumen).
 */
class SimpanTanggapan
{
    public static function draf(Rekomendasi $r, Sasaran $s, array $isi): void
    {
        DB::transaction(function () use ($r, $s, $isi) {
            $r->loadMissing(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran']);
            $lama = $s->draf;
            $pendek = $s->satker->namaPendek();

            $sebelum = Kemajuan::hitung($r, $s, $lama?->penuhi ?? [], $lama?->setoran ?? []);
            $sesudah = Kemajuan::hitung($r, $s, $isi['penuhi'], $isi['setoran']);
            $maju = Kemajuan::kalimat($sesudah);

            /* Draf milik satu satuan kerja pada satu bentuk tindak lanjut. Yang
               lain tidak boleh ikut tertimpa. */
            DrafTanggapan::updateOrCreate(['sasaran_id' => $s->id], [
                'uraian'   => $isi['uraian'] ?: null,
                'tanggal'  => $isi['tanggal'] ?: null,
                'bukti'    => $isi['bukti'],
                'setoran'  => $isi['setoran'],
                'penuhi'   => $isi['penuhi'],
                'kali'     => ($lama?->kali ?? 0) + 1,
                'terakhir' => Jejak::tanggal(),
            ]);

            Jejak::riwayat($r, $pendek, $maju ? "Kemajuan dicatat — {$maju}" : 'Isian disimpan, berkas tetap di satuan kerja');

            /* Diberitahukan hanya kalau kemajuannya benar-benar bergerak. Menyimpan
               ulang tanpa hasil baru bukan pemberitahuan. */
            if (! Kemajuan::sama($sebelum, $sesudah)) {
                Pemberitahuan::tulis($r, "Kemajuan tindak lanjut — {$maju}".self::catatan($isi['uraian']),
                    [PeranPengguna::SETBA], 'r-tindaklanjut', [$s->satker_id], $s->tindakan);
            }
        });
    }

    public static function kirim(Rekomendasi $r, Sasaran $s, array $isi, bool $otomatis = false): void
    {
        DB::transaction(function () use ($r, $s, $isi, $otomatis) {
            $r->loadMissing(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran', 'tindakan']);
            $pendek = $s->satker->namaPendek();
            $hari = Jejak::tanggal();

            /* Keadaan sesudah kiriman ini — yang membaca ingin tahu sampai mana,
               bukan apa yang barusan ditekan. Dihitung sebelum ditulis. */
            $k = Kemajuan::hitung($r, $s, $isi['penuhi'], $isi['setoran']);
            $rincian = Kemajuan::kalimat($k);
            $sisa = max(0, $k['target'] - $k['masuk']);

            /* Yang pindah satu baris saja: satuan kerja ini, pada bentuk tindak
               lanjut ini. */
            Jejak::geser($r, PosisiBerkas::SATKER, PosisiBerkas::SETBA_TINJAU, $s->satker_id, $s->tindakan_id);

            /* Batas perbaikan dan alasan penolakan lepas begitu perbaikannya
               dikirim; arsipnya tetap di `pengembalians`. */
            $s->refresh();
            if ($s->batas_perbaikan || $s->kembali_dari) {
                $s->update(['batas_perbaikan' => null, 'alasan_perbaikan' => null,
                    'keterangan_setba' => null, 'kembali_dari' => null, 'dokumen_diminta' => null]);
            }

            $uraian = trim((string) ($isi['uraian'] ?? ''));
            if (! $otomatis || $uraian !== '') {
                Tanggapan::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id'     => $s->id,
                    'tanggal'        => $isi['tanggal'] ?: $hari,
                    'uraian'         => $uraian,
                    'dicatat_oleh'   => $otomatis ? null : auth()->id(),
                    'label_pencatat' => $pendek,
                ]);
            }

            /* Setoran menempel pada bentuk tindak lanjut yang menagihnya. Kiriman
               otomatis hanya membawa baris yang sah — draf boleh menyimpan yang
               belum lengkap supaya isian orang tidak hilang. */
            foreach ($isi['setoran'] as $st) {
                if ($otomatis && ! Kemajuan::setorSah($st)) {
                    continue;
                }
                $berkas = Lampiran::create([
                    'sasaran_id'    => $s->id,
                    'tindakan_id'   => $s->tindakan_id,
                    'nama_asli'     => $st['berkas'] ?: null,
                    'link'        => $st['link'] ?: null,
                    'label_jenis'   => ($st['jenis'] ?? 'setor') === 'perbaikan' ? 'Berita acara perbaikan' : 'Bukti setor',
                    'label_oleh'    => $pendek,
                    'diunggah_oleh' => $otomatis ? null : auth()->id(),
                    'diunggah_pada' => $hari,
                ]);
                Pemulihan::create([
                    'rekomendasi_id'  => $r->id,
                    'sasaran_id'      => $s->id,
                    'jenis'           => ($st['jenis'] ?? 'setor') === 'perbaikan' ? JenisPemulihan::PERBAIKAN : JenisPemulihan::SETOR,
                    'tanggal'         => $st['tanggal'],
                    'nilai'           => Kemajuan::angka($st['nilai']),
                    'no_ssbp'         => ($st['ssbp'] ?? '') ?: null,
                    'ntpn'            => ($st['ntpn'] ?? '') ?: null,
                    'no_nota_kppn'    => ($st['notaKppn'] ?? '') ?: null,
                    'no_berita_acara' => ($st['noBa'] ?? '') ?: null,
                    'lampiran_id'     => $berkas->id,
                    'dicatat_oleh'    => $otomatis ? null : auth()->id(),
                ]);
            }

            /* Berkas menempel pada bentuk tindak lanjut yang memintanya. */
            $idBukti = [];
            $untuk = [];
            foreach ($isi['bukti'] as $b) {
                $l = Lampiran::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id'     => $s->id,
                    'tindakan_id'    => $s->tindakan_id,
                    'nama_asli'      => $b['nama'],
                    'label_jenis'    => ($b['jenis'] ?? '') ?: 'Bukti tindak lanjut',
                    'link'         => $b['link'] ?: null,
                    'label_oleh'     => $pendek,
                    'diunggah_oleh'  => $otomatis ? null : auth()->id(),
                    'diunggah_pada'  => $hari,
                ]);
                $idBukti[] = $l->id;
                if (! empty($b['untuk'])) {
                    $untuk[(int) $b['untuk']][] = $l->id;
                }
            }

            /* Butir yang dipenuhi: hanya milik baris ini. Berkasnya yang
               diunggah pada baris butir itu; kalau tidak ada yang bertanda,
               seluruh berkas yang ikut terkirim. */
            $butir = $r->permintaanUntuk($s->satker_id, $s->tindakan_id)->flatMap->item
                ->whereIn('id', array_map('intval', $isi['penuhi']));
            foreach ($butir as $item) {
                $item->update(['terpenuhi' => true, 'dipenuhi_pada' => $hari]);
                $item->lampiran()->syncWithoutDetaching($untuk[$item->id] ?? $idBukti);
            }

            DrafTanggapan::where('sasaran_id', $s->id)->delete();

            if ($otomatis) {
                $teks = 'Terkirim otomatis ke Setba — draf belum dikirim lebih dari '.Kemajuan::HARI_KIRIM_OTOMATIS.' hari';
                Jejak::riwayat($r, $pendek, $teks);
                Pemberitahuan::tulis($r, $teks, [PeranPengguna::SETBA, PeranPengguna::SATKER], 'r-tindaklanjut',
                    [$s->satker_id], $s->tindakan, sistem: true, pelaku: $pendek);

                return;
            }

            Jejak::riwayat($r, $pendek,
                ($sisa > 0 ? 'Berkas dikirim ke Setba dengan sisa '.Tampil::rupiah($sisa) : 'Kewajiban tuntas, berkas dikirim ke Setba')
                .($rincian ? " — {$rincian}" : ''),
                $isi['tanggal'] ?: null);

            Pemberitahuan::tulis($r, 'Berkas dikirim ke Setba'.($rincian ? " — {$rincian}" : '').self::catatan($isi['uraian']),
                [PeranPengguna::SETBA], 'r-tindaklanjut', [$s->satker_id], $s->tindakan);
        });
    }

    /** Catatan yang ditulis satuan kerja ikut terbawa ke pemberitahuan. */
    private static function catatan(?string $uraian): string
    {
        $c = trim((string) $uraian);

        return $c !== '' ? ". Catatan: “{$c}”" : '';
    }
}
