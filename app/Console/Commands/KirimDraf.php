<?php

namespace App\Console\Commands;

use App\Aksi\Kemajuan;
use App\Aksi\SimpanTanggapan;
use App\Enums\PosisiBerkas;
use App\Models\DrafTanggapan;
use Illuminate\Console\Command;

/**
 * Penyapu kiriman otomatis.
 *
 * Draf tanggapan yang diam lebih dari seminggu dikirim apa adanya ke Setba —
 * berkasnya tidak boleh membusuk di satu meja sementara tenggat 60 hari terus
 * berjalan. Syaratnya sama persis dengan tombol kirim: hanya yang kewajibannya
 * sudah tuntas. Yang belum lengkap dibiarkan.
 *
 * Di prototipe ini dijalankan sekali saat aplikasinya dibuka; di sini
 * pekerjaan terjadwal harian (routes/console.php), dan sekali sesudah data
 * contoh disemai. Pemberitahuannya dikirim tanpa pelaku, jadi belum terbaca oleh siapa
 * pun — termasuk Setba, yang justru harus meninjau berkasnya (W16).
 */
class KirimDraf extends Command
{
    protected $signature = 'tlhp:kirim-draf';

    protected $description = 'Mengirim ke Setba draf tanggapan yang lama belum dikirim dan kewajibannya sudah tuntas';

    public function handle(): int
    {
        $batas = now()->startOfDay()->subDays(Kemajuan::HARI_KIRIM_OTOMATIS);
        $n = 0;

        $draf = DrafTanggapan::with('sasaran.satker', 'sasaran.tindakan.rekomendasi')
            ->whereDate('terakhir', '<=', $batas)
            ->orderBy('id')
            ->get();

        foreach ($draf as $d) {
            $s = $d->sasaran;
            if (! $s || $s->pos() !== PosisiBerkas::SATKER) {
                continue;
            }
            $r = $s->tindakan->rekomendasi;
            $r->load(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran', 'tindakan']);
            if (! Kemajuan::tuntas($r, $s, $d->penuhi ?? [], $d->setoran ?? [])) {
                continue;
            }

            SimpanTanggapan::kirim($r, $s, [
                'uraian'  => $d->uraian,
                'tanggal' => $d->tanggal?->toDateString(),
                'bukti'   => $d->bukti ?? [],
                'setoran' => $d->setoran ?? [],
                'penuhi'  => $d->penuhi ?? [],
            ], otomatis: true);
            $n++;
        }

        $this->info("{$n} draf terkirim otomatis.");

        return self::SUCCESS;
    }
}
