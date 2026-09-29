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
use App\Support\Jejak;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Putusan UKI atau Inspektorat atas satu baris — padanan `putusPeriksa`.
 *
 * `otoritas` yang berwenang menurut suratnya; `pengetik` yang benar-benar
 * menekan tombolnya. Untuk Inspektorat keduanya bisa berbeda: meja
 * Inspektorat boleh dikerjakan Setba, yang menyalin isi surat CHV. Riwayat
 * menyebut pengetiknya — kalau tertulis "Inspektorat" padahal Setba yang
 * mengetik, catatannya tidak bisa dipakai sebagai bukti.
 *
 * Belum memadai TIDAK memulangkan berkas ke satuan kerja: ia ke meja
 * pemberkasan ulang Setba, dan Setba yang mengirimkannya ulang.
 */
class PutusPeriksa
{
    /**
     * @param  array{catatan:?string, nomor:?string, tglSurat:?string, perihal:?string, berkas:?string,
     *               link:?string, batasWaktu:?string, dokumenDiminta:list<string>, nomorLhv:?string,
     *               tglLhv:?string, tanda:list<array{satker_id:int, hasil:?string, catatan:?string}>}  $isi
     */
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
                        $x->update(['hasil' => $d['hasil'], 'catatan' => $catatanTanda ?: $x->catatan]);
                    }
                    Jejak::status($x, $uki ? RiwayatStatus::UKI : RiwayatStatus::ITJEN, $lama, $d['hasil'],
                        $pengetik, $catatanJejak, null, $idAksi, null, paksa: true);
                }
            }

            if ($hasil === HasilTelaah::BM) {
                foreach ($baris as $x) {
                    $x->refresh();
                    if (in_array($x->satker_id, $kenaSini, true) && $x->tindakan_id === $s->tindakan_id && $x->pos() === $dari) {
                        $x->update([
                            'kembali_dari'     => $otoritas,
                            'alasan_perbaikan' => $catatan ?: null,
                            'batas_perbaikan'  => ! $uki && filled($isi['batasWaktu'] ?? null) ? $isi['batasWaktu'] : null,
                            'keterangan_setba' => null,
                            'dokumen_diminta'  => $dokumen ?: null,
                        ]);
                    }
                }
            }

            /* ---- surat hasil ----
               Satu CHV memutus SELURUH rekomendasi: memadai hanya kalau seluruh
               satuan kerjanya memadai. Surat validasi UKI milik baris yang
               divalidasi. */
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
                    'nama_asli'      => trim($isi['berkas']),
                    'label_jenis'    => 'Dokumen perbaikan',
                    'link'         => ($isi['link'] ?? '') ?: null,
                    'label_oleh'     => $otoritas,
                    'diunggah_oleh'  => auth()->id(),
                    'diunggah_pada'  => Jejak::tanggal(),
                ])
                : null;

            if ($bersurat) {
                $surat = Verifikasi::firstOrCreate(
                    ['jenis' => $uki ? Verifikasi::LHV : Verifikasi::CHV, 'nomor_surat' => trim($isi['nomor'])],
                    [
                        'tgl_surat'    => $isi['tglSurat'],
                        'perihal'      => ($isi['perihal'] ?? '') ?: null,
                        'pejabat'      => $otoritas,
                        'nomor_lhv'    => ! $uki ? (($isi['nomorLhv'] ?? '') ?: null) : null,
                        'tgl_lhv'      => ! $uki ? (($isi['tglLhv'] ?? '') ?: null) : null,
                        'lampiran_id'  => $berkas?->id,
                        'dicatat_oleh' => auth()->id(),
                    ],
                );
                KeputusanVerifikasi::create([
                    'verifikasi_id'  => $surat->id,
                    'rekomendasi_id' => $r->id,
                    'sasaran_id'     => $uki ? $s->id : null,
                    'hasil'          => $hasilSurat,
                    'tenggat_baru'   => ! $uki && $hasil === HasilTelaah::BM ? (($isi['batasWaktu'] ?? '') ?: null) : null,
                    'catatan'        => $catatanSurat ?: null,
                    /* Putusan diambil dari dalam satu baris: catatannya milik baris
                       itu, bukan catatan umum surat — satuan kerja lain tidak boleh
                       membacanya. */
                    'catatan_umum'   => null,
                    'catatan_satker' => collect($catatan !== '' ? [['satker_id' => $s->satker_id, 'catatan' => $catatan]] : [])
                        ->merge($tanda->filter(fn ($d) => filled($d['catatan'] ?? null))
                            ->map(fn ($d) => ['satker_id' => (int) $d['satker_id'], 'catatan' => trim($d['catatan'])]))
                        ->values()->all(),
                    'aksi_id'        => $idAksi,
                ]);
            }

            /* ---- berkasnya bergerak ---- */
            Jejak::geser($r, $dari, $ke, $s->satker_id, $s->tindakan_id);

            /* LHA berhenti di Inspektorat, jadi putusan suratnya sekaligus status
               akhirnya. LHP masih menunggu BPK — statusnya tidak disentuh. */
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
                'sasaran_id'     => $s->id,
                'tanggal'        => Jejak::tanggal(),
                'oleh_id'        => auth()->id(),
                'label_oleh'     => $otoritas,
                'label_pencatat' => $pengetik,
                'hasil'          => $hasil,
                'catatan'        => $catatan,
                'nomor_surat'    => ($isi['nomor'] ?? '') ?: null,
                'tgl_surat'      => ($isi['tglSurat'] ?? '') ?: null,
                'perihal'        => ($isi['perihal'] ?? '') ?: null,
                'batas_waktu'    => ($isi['batasWaktu'] ?? '') ?: null,
                'dokumen'        => $dokumen ?: null,
                'lampiran_id'    => $berkas?->id,
                'aksi_id'        => $idAksi,
            ]);

            Jejak::riwayat($r, $pengetik, $teks, ($isi['tglSurat'] ?? '') ?: null);

            /* Pemberitahuannya milik satuan kerja yang diputus. Penolakan diberitahukan ke
               satuan kerja cukup status dan tempatnya: alasan dan dokumennya
               sampai saat Setba mengirim ulang. */
            $untuk = $hasil === HasilTelaah::BM ? [PeranPengguna::SETBA] : [PeranPengguna::SETBA, PeranPengguna::SATKER];
            Pemberitahuan::tulis($r, $teks, $untuk, 'r-riwayat', $kenaSini, $s->tindakan);
            if ($hasil === HasilTelaah::BM) {
                Pemberitahuan::tulis($r,
                    ($uki ? 'Validasi UKI' : 'Verifikasi Inspektorat').": {$kata} — berkas di Setba untuk pemberkasan ulang. "
                    .'Alasan dan dokumen yang diminta menyusul saat Setba mengirim ulang.',
                    [PeranPengguna::SATKER], 'r-riwayat', $kenaSini, $s->tindakan);
            }
        });
    }

    /** Daftar isian: dipangkas, tanpa baris kosong, tanpa kembar. */
    public static function bersih(array $daftar): array
    {
        return collect($daftar)->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
    }
}
