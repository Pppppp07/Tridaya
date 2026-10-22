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
use App\Support\KebijakanBukti;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Satuan kerja mengisi tindak lanjut satu baris. `draf`: isian sementara — berkas tetap di satuan
 * kerja, tanpa jejak Progres dan tanpa status; Setba memantaunya lewat rincian dan pemberitahuan di
 * lonceng (tanpa email). `kirim`: pindah ke Setba dan isiannya jadi arsip, walau dokumen yang diminta
 * belum lengkap.
 */
class SimpanTanggapan
{
    public static function draf(Rekomendasi $r, Sasaran $s, array $isi): void
    {
        KebijakanBukti::pastikanIsian($isi);
        DB::transaction(function () use ($r, $s, $isi) {
            /* Draf milik satu satuan kerja pada satu bentuk tindak lanjut; menimpa draf sebelumnya. */
            DrafTanggapan::updateOrCreate(['sasaran_id' => $s->id], [
                'uraian' => $isi['uraian'] ?: null,
                'tanggal' => $isi['tanggal'] ?: null,
                'bukti' => $isi['bukti'],
                'setoran' => $isi['setoran'],
                'penuhi' => $isi['penuhi'],
                'kali' => ($s->draf?->kali ?? 0) + 1,
                'terakhir' => Jejak::tanggal(),
                'disimpan_oleh' => auth()->id(),
            ]);

            Pemberitahuan::drafDisimpan($r, $s,
                Kemajuan::kalimat(Kemajuan::hitung($r, $s, $isi['penuhi'], $isi['setoran']), 'siap'));
        });
    }

    public static function kirim(Rekomendasi $r, Sasaran $s, array $isi): void
    {
        KebijakanBukti::pastikanIsian($isi);
        DB::transaction(function () use ($r, $s, $isi) {
            $s->kunciSejak();
            $r->loadMissing(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran', 'tindakan']);
            $pendek = $s->satker->namaPendek();
            $hari = Jejak::tanggal();

            /* Keadaan sesudah kiriman ini, dihitung sebelum ditulis. */
            $k = Kemajuan::hitung($r, $s, $isi['penuhi'], $isi['setoran']);
            $rincian = Kemajuan::kalimat($k);
            $sisa = max(0, $k['target'] - $k['masuk']);
            $sisaDok = max(0, $k['dari'] - $k['ada']);

            /* Yang pindah satu baris saja. */
            Jejak::geser($r, PosisiBerkas::SATKER, PosisiBerkas::SETBA_TINJAU, $s->satker_id, $s->tindakan_id);

            /* Batas perbaikan dan alasan penolakan lepas begitu perbaikannya dikirim; arsipnya
               tetap di `pengembalians`. */
            $s->refresh();
            if ($s->batas_perbaikan || $s->kembali_dari) {
                $s->update(['batas_perbaikan' => null, 'alasan_perbaikan' => null,
                    'keterangan_setba' => null, 'kembali_dari' => null, 'dokumen_diminta' => null]);
            }

            Tanggapan::create([
                'rekomendasi_id' => $r->id,
                'sasaran_id' => $s->id,
                'tanggal' => $isi['tanggal'] ?: $hari,
                'uraian' => trim((string) ($isi['uraian'] ?? '')),
                'dicatat_oleh' => auth()->id(),
                'label_pencatat' => $pendek,
            ]);

            /* Setoran menempel pada bentuk tindak lanjut yang menagihnya. */
            foreach ($isi['setoran'] as $st) {
                $berkas = Lampiran::create([
                    'sasaran_id' => $s->id,
                    'tindakan_id' => $s->tindakan_id,
                    'nama_asli' => $st['berkas'] ?: null,
                    'link' => $st['link'] ?: null,
                    'label_jenis' => ($st['jenis'] ?? 'setor') === 'perbaikan' ? 'Berita acara perbaikan' : 'Bukti setor',
                    'label_oleh' => $pendek,
                    'diunggah_oleh' => auth()->id(),
                    'diunggah_pada' => $hari,
                ]);
                Pemulihan::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id' => $s->id,
                    'jenis' => ($st['jenis'] ?? 'setor') === 'perbaikan' ? JenisPemulihan::PERBAIKAN : JenisPemulihan::SETOR,
                    'tanggal' => $st['tanggal'],
                    'nilai' => Kemajuan::angka($st['nilai']),
                    'no_ssbp' => ($st['ssbp'] ?? '') ?: null,
                    'ntpn' => ($st['ntpn'] ?? '') ?: null,
                    'no_nota_kppn' => ($st['notaKppn'] ?? '') ?: null,
                    'no_berita_acara' => ($st['noBa'] ?? '') ?: null,
                    'lampiran_id' => $berkas->id,
                    'dicatat_oleh' => auth()->id(),
                ]);
            }

            /* Berkas menempel pada bentuk tindak lanjut yang memintanya. */
            $idBukti = [];
            $untuk = [];
            foreach ($isi['bukti'] as $b) {
                $l = Lampiran::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id' => $s->id,
                    'tindakan_id' => $s->tindakan_id,
                    'nama_asli' => $b['nama'],
                    'label_jenis' => ($b['jenis'] ?? '') ?: 'Bukti tindak lanjut',
                    'link' => $b['link'] ?: null,
                    'label_oleh' => $pendek,
                    'diunggah_oleh' => auth()->id(),
                    'diunggah_pada' => $hari,
                ]);
                $idBukti[] = $l->id;
                if (! empty($b['untuk'])) {
                    $untuk[(int) $b['untuk']][] = $l->id;
                }
            }

            /* Butir yang dipenuhi hanya milik baris ini. */
            $butir = $r->permintaanUntuk($s->satker_id, $s->tindakan_id)->flatMap->item
                ->whereIn('id', array_map('intval', $isi['penuhi']));
            foreach ($butir as $item) {
                $item->update(['terpenuhi' => true, 'dipenuhi_pada' => $hari]);
                $item->lampiran()->syncWithoutDetaching($untuk[$item->id] ?? $idBukti);
            }

            DrafTanggapan::where('sasaran_id', $s->id)->delete();

            /* "Tuntas" hanya bila nilai dan dokumen yang diminta sudah lengkap. */
            $kabar = match (true) {
                $sisa > 0 => 'Berkas dikirim ke Setba dengan sisa '.Tampil::rupiah($sisa),
                $sisaDok > 0 => "Berkas dikirim ke Setba, {$sisaDok} dokumen menyusul",
                default => 'Kewajiban tuntas, berkas dikirim ke Setba',
            };
            Jejak::riwayat($r, $pendek, $kabar.($rincian ? " — {$rincian}" : ''), $isi['tanggal'] ?: null);

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
