<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengosongkan data contoh. Bawaan: data transaksi saja (master dan akun tetap). --semua: master
 * dan akun ikut — aplikasi tidak bisa dimasuki sampai disemai ulang.
 */
class KosongkanData extends Command
{
    protected $signature = 'tlhp:kosongkan
        {--semua : Data master dan akun ikut dikosongkan}
        {--force : Jalankan tanpa bertanya}';

    protected $description = 'Mengosongkan data contoh dari basis data';

    /** Anak dulu, induk belakangan — tetap benar walau kunci asing tidak bisa dimatikan. */
    private const TRANSAKSI = [
        'item_permintaan_lampiran',
        'item_permintaans',
        'permintaan_dokumens',
        'pemulihans',
        'tolakan_bpks',
        'pengembalians',
        'tindak_lanjuts',
        'draf_tanggapans',
        'telaahs',
        'surats',
        'keputusan_verifikasis',
        'verifikasis',
        'riwayat_statuses',
        'notifikasi_bacas',
        'notifikasi_satker',
        'notifikasis',
        'riwayat_berkas',
        'lampirans',
        'sasarans',
        'tindakans',
        'rekomendasis',
        'temuan_satker',
        'temuans',
        'laporans',
        /* Draf Catat laporan baru ikut dikosongkan. */
        'draf_laporans',
        /* Draf impor data historis juga; file upload-nya tetap di storage. */
        'draf_historis',
    ];

    /** Akun lebih dulu: ia menunjuk satuan kerja. */
    private const MASTER = [
        'users',
        'satkers',
        'kategori_temuans',
        'referensis',
    ];

    /** Tabel kerja Laravel. Bukan data, tapi memuat sisa sesi dan antrean. */
    private const SISTEM = [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        $semua = (bool) $this->option('semua');

        $tabel = array_merge(
            self::TRANSAKSI,
            $semua ? self::MASTER : [],
            self::SISTEM,
        );

        /* Tabel yang tidak ada dilewati, tidak menggagalkan seluruhnya. */
        $tabel = array_values(array_filter($tabel, fn ($t) => Schema::hasTable($t)));

        $jumlah = [];
        $total = 0;
        foreach ($tabel as $t) {
            $n = DB::table($t)->count();
            if ($n > 0) {
                $jumlah[$t] = $n;
                $total += $n;
            }
        }

        $this->newLine();
        $this->line('Basis data: <options=bold>'.DB::getDatabaseName().'</>');

        if ($total === 0) {
            $this->info('Tidak ada yang perlu dikosongkan.');

            return self::SUCCESS;
        }

        foreach ($jumlah as $t => $n) {
            $this->line('  '.str_pad($t, 28).$n);
        }
        $this->line('  <options=bold>'.str_pad('TOTAL', 28).$total.' baris</>');
        $this->newLine();

        if ($semua) {
            $this->warn('--semua: akun ikut dihapus. Sesudah ini aplikasinya');
            $this->warn('tidak bisa dimasuki sampai disemai ulang.');
        } else {
            $this->line('Data master dan akun <options=bold>dipertahankan</>.');
        }

        if (! $this->option('force')
            && ! $this->confirm('Kosongkan? Ini tidak bisa dibatalkan.', false)) {
            $this->line('Dibatalkan. Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        /* truncate, bukan delete: nomor urut kembali ke satu. */
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tabel as $t) {
                DB::table($t)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info("Selesai. {$total} baris dikosongkan dari ".count($tabel).' tabel.');

        if ($semua) {
            $this->line('Semai ulang dengan: <options=bold>php artisan db:seed</>');
        } else {
            $this->line('Akun dan data master masih ada — aplikasinya langsung bisa dipakai.');
        }

        return self::SUCCESS;
    }
}
