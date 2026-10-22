<?php

namespace App\Aksi;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\KeputusanVerifikasi;
use App\Models\Lampiran;
use App\Models\Rekomendasi;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use App\Models\Telaah;
use App\Models\Verifikasi;
use App\Support\Aktivitas;
use App\Support\Jejak;
use App\Support\KebijakanBukti;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Putusan UKI atau Inspektorat atas satu baris. `otoritas` = yang berwenang menurut surat,
 * `pengetik` = yang menekan tombol (meja Inspektorat boleh dikerjakan Setba). Belum memadai ke meja
 * pemberkasan ulang Setba, tidak langsung ke satuan kerja.
 */
class PutusPeriksa
{
    public static function jalankan(Rekomendasi $r, Sasaran $s, PeranPengguna $peran, HasilTelaah $hasil, array $isi): void
    {
        $uki = $peran === PeranPengguna::UKI;
        $otoritas = $uki ? 'UKI' : 'Inspektorat';
        $pengetik = $uki ? 'UKI' : ($peran === PeranPengguna::SETBA ? 'Setba' : 'Inspektorat');
        $dari = $uki ? PosisiBerkas::UKI : PosisiBerkas::INSPEKTORAT;
        $ke = $hasil === HasilTelaah::BM ? PosisiBerkas::SETBA_KEMBALI
            : ($uki ? PosisiBerkas::SETBA_TERUSKAN : PosisiBerkas::TUNTAS);
        $jenis = $r->jenis();
        $kata = mb_strtolower($hasil->nama($jenis));
        $catatan = trim((string) ($isi['catatan'] ?? ''));
        $dokumen = self::bersih($isi['dokumenDiminta'] ?? []);
        $bersurat = filled($isi['nomor'] ?? null) && filled($isi['tglSurat'] ?? null);
        $idAksi = Jejak::aksiId();

        $teks = ($uki ? "Validasi UKI selesai — {$kata}"
                : ($peran === PeranPengguna::SETBA ? "Hasil verifikasi Inspektorat dicatat — {$kata}" : "Verifikasi selesai — {$kata}"))
            .(filled($isi['nomor'] ?? null) ? ", surat {$isi['nomor']}" : '')
            .($catatan !== '' ? " — {$catatan}" : '')
            .(filled($isi['batasWaktu'] ?? null) ? ' — perbaiki paling lambat '.Tampil::tgl($isi['batasWaktu']) : '')
            .(count($dokumen) ? ' — '.count($dokumen).' dokumen diminta' : '')
            .($hasil === HasilTelaah::BM ? ' — berkas ke Setba untuk dikirim ulang ke satuan kerja'
                : ($uki ? ' — berkas ke Setba untuk diteruskan ke Inspektorat' : ' — tindak lanjut ini selesai diverifikasi'));

        $tanda = collect($isi['tanda'] ?? [])->filter(fn ($d) => ! empty($d['satker_id']))->values();
        $kenaSini = $tanda->pluck('satker_id')->push($s->satker_id)->unique()->all();

        DB::transaction(function () use ($r, $s, $uki, $otoritas, $pengetik, $dari, $ke, $hasil, $isi, $catatan,
            $dokumen, $bersurat, $idAksi, $teks, $tanda, $kenaSini, $jenis, $kata) {
            $s->kunciSejak();

            /* ---- tanda per satuan kerja, lalu penolakannya ---- */
            $baris = $r->sasaran()->with('satker')->get();
            foreach ($tanda as $d) {
                if (empty($d['hasil'])) {
                    continue;
                }
                foreach ($baris->where('satker_id', (int) $d['satker_id']) as $x) {
                    if ($x->pos() !== $dari || $x->tindakan_id !== $s->tindakan_id) {
                        continue;
                    }
                    $catatanTanda = trim((string) ($d['catatan'] ?? ''));
                    $catatanJejak = $catatan !== '' && $catatan !== $catatanTanda
                        ? implode(' · ', array_filter([$catatan, $catatanTanda])) : $catatanTanda;
                    $lama = $uki ? $x->hasil_uki?->value : $x->hasil?->value;
                    if ($uki) {
                        $x->update(['hasil_uki' => $d['hasil']]);
                    } else {
                        $x->forceFill(['hasil' => $d['hasil'], 'nilai_diakui_itjen' => null, 'catatan' => $catatanTanda ?: $x->catatan])->save();
                    }
                    Jejak::status($x, $uki ? RiwayatStatus::UKI : RiwayatStatus::ITJEN, $lama, $d['hasil'],
                        $pengetik, $catatanJejak, null, $idAksi, null, paksa: true);
                }
            }

            if ($hasil === HasilTelaah::BM) {
                $idDitolak = array_map('intval', $isi['buktiTolak'] ?? []);
                /* Penolakan melekat pada arsip; pengiriman ulang membuat lampiran baru. */
                foreach (Lampiran::where('sasaran_id', $s->id)->whereIn('id', $isi['buktiTolak'] ?? [])
                    ->whereNull('ditolak_pada')->with('butir.lampiran')->get() as $b) {
                    $b->update(['ditolak_pada' => Aktivitas::waktuKini(), 'ditolak_oleh' => auth()->id(),
                        'alasan_penolakan' => $catatan]);
                    foreach ($b->butir as $item) {
                        $adaPengganti = $item->lampiran->contains(fn ($l) => ! in_array($l->id, $idDitolak, true) && ! $l->ditolak_pada && KebijakanBukti::sah($l->link));
                        if (! $adaPengganti) {
                            $item->update(['terpenuhi' => false, 'dipenuhi_pada' => null]);
                        }
                    }
                    Aktivitas::catat('bukti.ditolak', 'Bukti perlu diganti: '.$b->labelTampil(), [
                        'subjek' => $r, 'rincian' => ['lampiran_id' => $b->id, 'sasaran_id' => $s->id,
                            'alasan' => $catatan, 'sebelum' => ['aktif' => true], 'sesudah' => ['aktif' => false]],
                    ]);
                }
                foreach ($baris as $x) {
                    $x->refresh();
                    if (in_array($x->satker_id, $kenaSini, true) && $x->tindakan_id === $s->tindakan_id && $x->pos() === $dari) {
                        $x->update([
                            'kembali_dari' => $otoritas,
                            'alasan_perbaikan' => $catatan ?: null,
                            'batas_perbaikan' => ! $uki && filled($isi['batasWaktu'] ?? null) ? $isi['batasWaktu'] : null,
                            'keterangan_setba' => null,
                            'dokumen_diminta' => $dokumen ?: null,
                        ]);
                    }
                }
            }

            /* ---- Surat hasil ----
               Satu CHV memutus seluruh rekomendasi: memadai hanya bila seluruh satuan kerjanya
               memadai. Surat validasi UKI milik baris yang divalidasi. */
            $r->unsetRelation('sasaran');
            $semuaMemadai = $r->sasaran()->get()->every(fn ($x) => $x->hasil === HasilTelaah::M);
            $hasilSurat = $uki ? $hasil : ($hasil === HasilTelaah::M && $semuaMemadai ? HasilTelaah::M : HasilTelaah::BM);
            $catatanSurat = collect([$catatan])
                ->merge($tanda->filter(fn ($d) => filled($d['catatan'] ?? null))
                    ->map(fn ($d) => $baris->firstWhere('satker_id', (int) $d['satker_id'])?->satker->namaPendek().': '.trim($d['catatan'])))
                ->filter()->join(' · ');

            $berkas = filled($isi['berkas'] ?? null)
                ? Lampiran::create([
                    'rekomendasi_id' => $r->id,
                    'nama_asli' => trim($isi['berkas']),
                    'label_jenis' => 'Dokumen perbaikan',
                    'link' => ($isi['link'] ?? '') ?: null,
                    'label_oleh' => $otoritas,
                    'diunggah_oleh' => auth()->id(),
                    'diunggah_pada' => Jejak::tanggal(),
                ])
                : null;

            if ($bersurat) {
                $surat = Verifikasi::firstOrCreate(
                    ['jenis' => $uki ? Verifikasi::LHV : Verifikasi::CHV, 'nomor_surat' => trim($isi['nomor'])],
                    [
                        'tgl_surat' => $isi['tglSurat'],
                        'perihal' => ($isi['perihal'] ?? '') ?: null,
                        'pejabat' => $otoritas,
                        'nomor_lhv' => ! $uki ? (($isi['nomorLhv'] ?? '') ?: null) : null,
                        'tgl_lhv' => ! $uki ? (($isi['tglLhv'] ?? '') ?: null) : null,
                        'lampiran_id' => $berkas?->id,
                        'dicatat_oleh' => auth()->id(),
                    ],
                );
                KeputusanVerifikasi::create([
                    'verifikasi_id' => $surat->id,
                    'rekomendasi_id' => $r->id,
                    'sasaran_id' => $uki ? $s->id : null,
                    'hasil' => $hasilSurat,
                    'tenggat_baru' => ! $uki && $hasil === HasilTelaah::BM ? (($isi['batasWaktu'] ?? '') ?: null) : null,
                    'catatan' => $catatanSurat ?: null,
                    /* Catatan putusan milik baris itu; satuan kerja lain tidak boleh membacanya. */
                    'catatan_umum' => null,
                    'catatan_satker' => collect($catatan !== '' ? [['satker_id' => $s->satker_id, 'catatan' => $catatan]] : [])
                        ->merge($tanda->filter(fn ($d) => filled($d['catatan'] ?? null))
                            ->map(fn ($d) => ['satker_id' => (int) $d['satker_id'], 'catatan' => trim($d['catatan'])]))
                        ->values()->all(),
                    'aksi_id' => $idAksi,
                ]);
            }

            /* ---- berkasnya bergerak ---- */
            Jejak::geser($r, $dari, $ke, $s->satker_id, $s->tindakan_id);

            /* LHA berhenti di Inspektorat, jadi putusan suratnya sekaligus status akhir. Status
               LHP menunggu BPK. */
            $naik = ! $uki && $hasil === HasilTelaah::M && $r->semuaTuntas();
            if (! $uki && ! $jenis->melewatiSiptl()) {
                if ($hasilSurat === HasilTelaah::BM) {
                    $r->update(['status' => StatusTindakLanjut::BS]);
                } elseif ($naik) {
                    $r->update(['status' => StatusTindakLanjut::SS]);
                }
            }

            Telaah::create([
                'rekomendasi_id' => $r->id,
                'sasaran_id' => $s->id,
                'tanggal' => Jejak::tanggal(),
                'oleh_id' => auth()->id(),
                'label_oleh' => $otoritas,
                'label_pencatat' => $pengetik,
                'hasil' => $hasil,
                'catatan' => $catatan,
                'nomor_surat' => ($isi['nomor'] ?? '') ?: null,
                'tgl_surat' => ($isi['tglSurat'] ?? '') ?: null,
                'perihal' => ($isi['perihal'] ?? '') ?: null,
                'batas_waktu' => ($isi['batasWaktu'] ?? '') ?: null,
                'dokumen' => $dokumen ?: null,
                'lampiran_id' => $berkas?->id,
                'aksi_id' => $idAksi,
            ]);

            Jejak::riwayat($r, $pengetik, $teks, ($isi['tglSurat'] ?? '') ?: null);

            /* Pemberitahuan penolakan ke satuan kerja cukup status dan tempatnya; alasan dan
               dokumen sampai saat Setba mengirim ulang. Pimpinan diberi tahu keputusannya, sama
               dengan satuan kerjanya. */
            $untuk = $hasil === HasilTelaah::BM ? [PeranPengguna::SETBA]
                : [PeranPengguna::SETBA, PeranPengguna::SATKER, PeranPengguna::PIMPINAN];
            Pemberitahuan::tulis($r, $teks, $untuk, 'r-riwayat', $kenaSini, $s->tindakan);
            if ($hasil === HasilTelaah::BM) {
                Pemberitahuan::tulis($r,
                    ($uki ? 'Validasi UKI' : 'Verifikasi Inspektorat').": {$kata} — berkas di Setba untuk pemberkasan ulang. "
                    .'Alasan dan dokumen yang diminta menyusul saat Setba mengirim ulang.',
                    [PeranPengguna::SATKER, PeranPengguna::PIMPINAN], 'r-riwayat', $kenaSini, $s->tindakan);
            }
        });
    }

    /** Daftar isian: dipangkas, tanpa baris kosong, tanpa kembar. */
    public static function bersih(array $daftar): array
    {
        return collect($daftar)->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
    }
}
