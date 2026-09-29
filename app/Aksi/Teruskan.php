<?php

namespace App\Aksi;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Surat;
use App\Support\Jejak;
use App\Support\Pemberitahuan;
use Illuminate\Support\Facades\DB;

/**
 * Setba meneruskan berkas satu baris — padanan `aksi.teruskan`.
 *
 *   setba_tinjau    → uki           surat permohonan validasi
 *   setba_teruskan  → inspektorat   surat permohonan verifikasi
 *
 * Suratnya ikut tercatat: nomor inilah yang ditelusuri orang saat mencari
 * perkara ini di luar aplikasi — "UKI nggak mau kalau nggak ada surat dari
 * Setba".
 */
class Teruskan
{
    /** @param array{nomor:string, tanggal:string, perihal:string, link:?string, catatan:?string} $surat */
    public static function jalankan(Rekomendasi $r, Sasaran $s, array $surat): void
    {
        $dari = $s->pos();
        $ke = $dari === PosisiBerkas::SETBA_TINJAU ? PosisiBerkas::UKI : PosisiBerkas::INSPEKTORAT;
        $nama = $ke === PosisiBerkas::UKI ? 'UKI' : 'Inspektorat';

        DB::transaction(function () use ($r, $s, $surat, $dari, $ke, $nama) {
            $teks = "Berkas diteruskan ke {$nama}".($surat['nomor'] ? " dengan surat {$surat['nomor']}" : '');

            Jejak::geser($r, $dari, $ke, $s->satker_id, $s->tindakan_id);

            Surat::create([
                'rekomendasi_id' => $r->id,
                'sasaran_id'     => $s->id,
                'dari'           => 'Setba',
                'ke'             => $nama,
                'tanggal_catat'  => Jejak::tanggal(),
                'nomor'          => $surat['nomor'],
                'tanggal'        => $surat['tanggal'],
                'perihal'        => $surat['perihal'] ?: null,
                'link'         => ($surat['link'] ?? '') ?: null,
                'catatan'        => ($surat['catatan'] ?? '') ?: null,
                'dicatat_oleh'   => auth()->id(),
            ]);

            Jejak::riwayat($r, 'Setba', $teks, $surat['tanggal']);

            Pemberitahuan::tulis($r, $teks,
                [$ke === PosisiBerkas::UKI ? PeranPengguna::UKI : PeranPengguna::INSPEKTORAT, PeranPengguna::SATKER],
                'r-perkembangan', [$s->satker_id], $s->tindakan);
        });

        /* Surat yang sama sering dipakai untuk beberapa berkas sekaligus;
           panel berikutnya menawarkannya lagi. */
        session(['surat_terakhir.'.$ke->value => $surat]);
    }
}
