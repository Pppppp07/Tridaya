<?php

namespace App\Aksi;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\ItemPermintaan;
use App\Models\Pengembalian;
use App\Models\PermintaanDokumen;
use App\Models\Rekomendasi;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use App\Models\TolakanBpk;
use App\Support\Jejak;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;

/**
 * Urusan SIPTL satu baris, milik Setba. Yang naik ke SIPTL tindak lanjut satuan kerja, bukan
 * rekomendasinya; status rekomendasi dirangkum ulang sesudahnya.
 */
class UrusanSiptl
{
    private const UNTUK = [PeranPengguna::SATKER, PeranPengguna::UKI, PeranPengguna::INSPEKTORAT, PeranPengguna::PIMPINAN];

    /**
     * Tanggal unggah dikunci sekali tercatat; penolakannya di pengendali, sebelum apa pun ditulis.
     */
    public static function unggah(Rekomendasi $r, Sasaran $s, string $tanggal): void
    {
        DB::transaction(function () use ($r, $s, $tanggal) {
            $s->kunciSejak();
            $teks = 'Unggahan ke SIPTL dicatat — '.$s->satker->namaPendek()
                .', diunggah '.Tampil::tgl($tanggal).', menunggu penilaian BPK';

            /* Status awal Belum Ditindaklanjuti: BPK menerima tapi belum memutus. */
            $s->forceFill(['siptl_tanggal' => $tanggal, 'status_bpk' => StatusTindakLanjut::BT, 'siptl_historis' => false, 'nilai_diakui_bpk' => null])->save();
            Jejak::status($s, RiwayatStatus::SIPTL, '', 'BT', 'Setba', 'Berkasnya diunggah ke SIPTL.', $tanggal);

            Jejak::rangkumStatus($r);

            Jejak::riwayat($r, 'Setba', $teks, $tanggal);
            Pemberitahuan::tulis($r, $teks, self::UNTUK, 'r-siptl', [$s->satker_id], $s->tindakan);
        });
    }

    /** Status disalin apa adanya dari BPK. */
    public static function status(Rekomendasi $r, Sasaran $s, StatusTindakLanjut $status, ?string $catatan, ?string $tanggal): void
    {
        DB::transaction(function () use ($r, $s, $status, $catatan, $tanggal) {
            $s->kunciSejak();
            $catatan = trim((string) $catatan);
            $teks = 'Status SIPTL dicatat — '.$s->satker->namaPendek().', '.$status->pendek()
                .($catatan !== '' ? " — {$catatan}" : '');

            $lama = $s->status_bpk?->value;
            $s->forceFill(['status_bpk' => $status, 'nilai_diakui_bpk' => null, 'catatan_bpk' => $catatan ?: null, 'tgl_pantau' => $tanggal ?: Jejak::tanggal()])->save();
            Jejak::status($s, RiwayatStatus::SIPTL, $lama, $status->value, 'Setba', $catatan);

            Jejak::rangkumStatus($r);

            Jejak::riwayat($r, 'Setba', $teks, $tanggal ?: null);
            Pemberitahuan::tulis($r, $teks, self::UNTUK, 'r-siptl', [$s->satker_id], $s->tindakan);
        });
    }

    /**
     * BPK menolak: baris dikirim ulang ke satuan kerja. Tanda Itjen mundur, unggahan dilepas,
     * status kembali BT; periodenya tertutup.
     */
    public static function ulangBpk(Rekomendasi $r, Sasaran $s, string $alasan, ?string $keteranganSetba,
        array $dokumen, int $nilaiDitolak = 0): void
    {
        DB::transaction(function () use ($r, $s, $alasan, $keteranganSetba, $dokumen, $nilaiDitolak) {
            $s->kunciSejak();
            $idAksi = Jejak::aksiId();
            $alasan = trim($alasan);
            $ket = trim((string) $keteranganSetba);
            $dok = PutusPeriksa::bersih($dokumen);

            $teks = 'Dikirim ulang ke '.$s->satker->namaPendek().' untuk pemberkasan ulang — ditolak BPK'
                .($alasan !== '' ? ": {$alasan}" : '')
                .(count($dok) ? ' — '.count($dok).' dokumen diminta: '.implode(', ', $dok) : '')
                .($ket !== '' ? ". Keterangan Setba: {$ket}" : '');

            /* Ditandai dulu, baru digeser. Penyetoran yang sudah SS tidak ikut pulang. */
            if ($s->tuntas()) {
                $lama = $s->hasil?->value;
                $s->forceFill(['hasil' => HasilTelaah::BM, 'nilai_diakui_itjen' => null, 'catatan' => $alasan ?: $s->catatan])->save();
                Jejak::status($s, RiwayatStatus::ITJEN, $lama, 'BM', 'Setba', $alasan, null, $idAksi, 'kembaliBpk');

                Jejak::geser($r, PosisiBerkas::TUNTAS, PosisiBerkas::SATKER, $s->satker_id, $s->tindakan_id);
                $s->refresh();
                $s->update([
                    'kembali_dari' => 'BPK', 'alasan_perbaikan' => $alasan ?: null, 'batas_perbaikan' => null,
                    'keterangan_setba' => $ket ?: null, 'dokumen_diminta' => $dok ?: null,
                ]);
            }

            /* Sesudah diperbaiki, yang harus naik berkas yang baru. */
            $lamaBpk = $s->status_bpk?->value;
            $s->forceFill(['siptl_tanggal' => null, 'siptl_historis' => false, 'nilai_diakui_bpk' => null, 'status_bpk' => StatusTindakLanjut::BT, 'catatan_bpk' => $alasan ?: null])->save();
            Jejak::status($s, RiwayatStatus::SIPTL, $lamaBpk, 'BT', 'Setba', $alasan, null, $idAksi, 'kembaliBpk');

            $r->unsetRelation('sasaran');
            Jejak::rangkumStatus($r);

            if ($dok) {
                $pm = PermintaanDokumen::create([
                    'rekomendasi_id' => $r->id, 'sasaran_id' => $s->id, 'peran_peminta' => PeranPengguna::SETBA,
                    'dari' => 'BPK', 'diminta_oleh' => auth()->id(), 'tanggal' => Jejak::tanggal(), 'alasan' => $alasan ?: null,
                ]);
                foreach ($dok as $n) {
                    ItemPermintaan::create(['permintaan_dokumen_id' => $pm->id, 'nama' => $n, 'terpenuhi' => false]);
                }
            }

            if ($nilaiDitolak > 0) {
                TolakanBpk::create([
                    'rekomendasi_id' => $r->id, 'sasaran_id' => $s->id, 'nilai' => $nilaiDitolak,
                    'tanggal' => Jejak::tanggal(), 'catatan' => $alasan ?: null,
                ]);
            }

            Pengembalian::create([
                'rekomendasi_id' => $r->id, 'sasaran_id' => $s->id, 'tanggal' => Jejak::tanggal(),
                'oleh_id' => auth()->id(), 'label_oleh' => 'Setba', 'dari' => 'BPK', 'alasan' => $alasan,
                'keterangan_setba' => $ket ?: null, 'dokumen' => $dok ?: null, 'aksi_id' => $idAksi,
            ]);

            Jejak::riwayat($r, 'Setba', $teks);
            Pemberitahuan::tulis($r, $teks, self::UNTUK, 'r-riwayat', [$s->satker_id], $s->tindakan);
        });
    }
}
