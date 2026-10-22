<?php

namespace App\Aksi;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\ItemPermintaan;
use App\Models\Pengembalian;
use App\Models\PermintaanDokumen;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Jejak;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Setba mengirim ulang berkas satu baris yang ditolak UKI, Inspektorat, atau BPK ke satuan
 * kerjanya. Dokumen yang disetel Setba jadi permintaan milik baris itu.
 */
class KirimUlang
{
    public static function jalankan(Rekomendasi $r, Sasaran $s, ?string $keterangan, array $dokumen): void
    {
        DB::transaction(function () use ($r, $s, $keterangan, $dokumen) {
            $s->kunciSejak();
            $dari = $s->kembali_dari ?: 'pemeriksa';
            $ket = trim((string) $keterangan);
            $dok = PutusPeriksa::bersih($dokumen);
            $nama = $s->satker->namaPendek();

            $teks = "Dikirim ulang ke {$nama} untuk pemberkasan ulang — ditolak {$dari}"
                .($s->alasan_perbaikan ? ": {$s->alasan_perbaikan}" : '')
                .($s->batas_perbaikan ? ' — perbaiki paling lambat '.Tampil::tgl($s->batas_perbaikan) : '')
                .(count($dok) ? ' — '.count($dok).' dokumen diminta: '.implode(', ', $dok) : '')
                .($ket !== '' ? ". Keterangan Setba: {$ket}" : '');

            Jejak::geser($r, PosisiBerkas::SETBA_KEMBALI, PosisiBerkas::SATKER, $s->satker_id, $s->tindakan_id);
            $s->refresh();
            if ($s->kembali_dari) {
                $s->update(['keterangan_setba' => $ket ?: null, 'dokumen_diminta' => $dok ?: null]);
            }

            if ($dok) {
                $pm = PermintaanDokumen::create([
                    'rekomendasi_id' => $r->id,
                    'sasaran_id'     => $s->id,
                    'peran_peminta'  => PeranPengguna::SETBA,
                    'dari'           => $dari,
                    'diminta_oleh'   => auth()->id(),
                    'tanggal'        => Jejak::tanggal(),
                    'alasan'         => $s->alasan_perbaikan,
                ]);
                foreach ($dok as $n) {
                    ItemPermintaan::create(['permintaan_dokumen_id' => $pm->id, 'nama' => $n, 'terpenuhi' => false]);
                }
            }

            Pengembalian::create([
                'rekomendasi_id'   => $r->id,
                'sasaran_id'       => $s->id,
                'tanggal'          => Jejak::tanggal(),
                'oleh_id'          => auth()->id(),
                'label_oleh'       => 'Setba',
                'dari'             => $dari,
                'alasan'           => (string) $s->alasan_perbaikan,
                'batas_waktu'      => $s->batas_perbaikan,
                'keterangan_setba' => $ket ?: null,
                'dokumen'          => $dok ?: null,
            ]);

            Jejak::riwayat($r, 'Setba', $teks);
            Pemberitahuan::tulis($r, $teks, [PeranPengguna::SATKER], 'r-riwayat', [$s->satker_id], $s->tindakan);
        });
    }
}
