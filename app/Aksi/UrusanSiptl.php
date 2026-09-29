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
 * Urusan SIPTL satu baris, milik Setba — padanan `aksi.unggahSiptl`,
 * `aksi.catatSiptl`, dan `aksi.ulangBpk`.
 *
 * Yang naik ke SIPTL tindak lanjut satuan kerjanya, bukan rekomendasinya: satu
 * baris boleh naik begitu selesai diperiksa, tanpa menunggu satuan kerja lain.
 * Status rekomendasi dirangkum ulang dari barisnya sesudah tiap penulisan.
 */
class UrusanSiptl
{
    private const UNTUK = [PeranPengguna::SATKER, PeranPengguna::UKI, PeranPengguna::INSPEKTORAT, PeranPengguna::PIMPINAN];

    /**
     * Tanggal unggah dikunci sekali tercatat. Penolakannya di pengendali,
     * SEBELUM apa pun ditulis — riwayat dan pemberitahuan yang terlanjur tercatat untuk
     * tanggal yang ditolak justru jadi catatan palsu.
     */
    public static function unggah(Rekomendasi $r, Sasaran $s, string $tanggal): void
    {
        DB::transaction(function () use ($r, $s, $tanggal) {
            $teks = 'Unggahan ke SIPTL dicatat — '.$s->satker->namaPendek()
                .', diunggah '.Tampil::tgl($tanggal).', menunggu penilaian BPK';

            /* Statusnya otomatis Belum Ditindaklanjuti: BPK sudah menerimanya tapi
               belum memutus apa pun. */
            $s->forceFill(['siptl_tanggal' => $tanggal, 'status_bpk' => StatusTindakLanjut::BT])->save();
            Jejak::status($s, RiwayatStatus::SIPTL, '', 'BT', 'Setba', 'Berkasnya diunggah ke SIPTL.', $tanggal);

            if (! $r->siptl_tanggal) {
                $r->update(['siptl_tanggal' => $tanggal]);
            }
            Jejak::rangkumStatus($r);

            Jejak::riwayat($r, 'Setba', $teks, $tanggal);
            Pemberitahuan::tulis($r, $teks, self::UNTUK, 'r-siptl', [$s->satker_id], $s->tindakan);
        });
    }

    /** Status disalin apa adanya dari BPK. */
    public static function status(Rekomendasi $r, Sasaran $s, StatusTindakLanjut $status, ?string $catatan, ?string $tanggal): void
    {
        DB::transaction(function () use ($r, $s, $status, $catatan, $tanggal) {
            $catatan = trim((string) $catatan);
            $teks = 'Status SIPTL dicatat — '.$s->satker->namaPendek().', '.$status->pendek()
                .($catatan !== '' ? " — {$catatan}" : '');

            $lama = $s->status_bpk?->value;
            $s->update(['status_bpk' => $status, 'catatan_bpk' => $catatan ?: null, 'tgl_pantau' => $tanggal ?: Jejak::tanggal()]);
            Jejak::status($s, RiwayatStatus::SIPTL, $lama, $status->value, 'Setba', $catatan);

            Jejak::rangkumStatus($r);
            $r->update([
                'siptl_status'       => $r->statusRek(),
                'siptl_catatan'      => $catatan ?: null,
                'siptl_dicatat_pada' => Jejak::tanggal(),
                'catatan_td'         => $status === StatusTindakLanjut::TD ? ($catatan ?: null) : $r->catatan_td,
            ]);

            Jejak::riwayat($r, 'Setba', $teks, $tanggal ?: null);
            Pemberitahuan::tulis($r, $teks, self::UNTUK, 'r-siptl', [$s->satker_id], $s->tindakan);
        });
    }

    /**
     * BPK menolak: tindak lanjut satu baris dikirim ulang ke satuan kerjanya
     * untuk pemberkasan ulang. Tanda Itjen mundur, unggahan SIPTL dilepas, dan
     * statusnya kembali BT — satu tindakan, satu baris riwayat. Periodenya
     * tertutup di sini: rantainya diulang dari satuan kerja.
     */
    public static function ulangBpk(Rekomendasi $r, Sasaran $s, string $alasan, ?string $keteranganSetba,
        array $dokumen, int $nilaiDitolak = 0): void
    {
        DB::transaction(function () use ($r, $s, $alasan, $keteranganSetba, $dokumen, $nilaiDitolak) {
            $idAksi = Jejak::aksiId();
            $alasan = trim($alasan);
            $ket = trim((string) $keteranganSetba);
            $dok = PutusPeriksa::bersih($dokumen);

            $teks = 'Dikirim ulang ke '.$s->satker->namaPendek().' untuk pemberkasan ulang — ditolak BPK'
                .($alasan !== '' ? ": {$alasan}" : '')
                .(count($dok) ? ' — '.count($dok).' dokumen diminta: '.implode(', ', $dok) : '')
                .($ket !== '' ? ". Keterangan Setba: {$ket}" : '');

            /* Ditandai dulu, baru digeser. Hanya tindak lanjut yang ditolak —
               penyetoran yang sudah SS tidak ikut pulang. */
            if ($s->tuntas()) {
                $lama = $s->hasil?->value;
                $s->update(['hasil' => HasilTelaah::BM, 'catatan' => $alasan ?: $s->catatan]);
                Jejak::status($s, RiwayatStatus::ITJEN, $lama, 'BM', 'Setba', $alasan, null, $idAksi, 'kembaliBpk');

                Jejak::geser($r, PosisiBerkas::TUNTAS, PosisiBerkas::SATKER, $s->satker_id, $s->tindakan_id);
                $s->refresh();
                $s->update([
                    'kembali_dari' => 'BPK', 'alasan_perbaikan' => $alasan ?: null, 'batas_perbaikan' => null,
                    'keterangan_setba' => $ket ?: null, 'dokumen_diminta' => $dok ?: null,
                ]);
            }

            /* Unggahan yang sudah naik adalah tindak lanjut yang baru saja
               ditolak; sesudah diperbaiki, yang harus naik berkas yang baru. */
            $lamaBpk = $s->status_bpk?->value;
            $s->forceFill(['siptl_tanggal' => null, 'status_bpk' => StatusTindakLanjut::BT, 'catatan_bpk' => $alasan ?: null])->save();
            Jejak::status($s, RiwayatStatus::SIPTL, $lamaBpk, 'BT', 'Setba', $alasan, null, $idAksi, 'kembaliBpk');

            $r->unsetRelation('sasaran');
            if (! $r->sasaran()->get()->contains(fn ($x) => $x->siptl_tanggal)) {
                $r->update(['siptl_tanggal' => null]);
            }
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
