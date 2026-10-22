<?php

namespace Tests;

use App\Models\SumberBukti;
use App\Models\User;
use App\Support\KebijakanBukti;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Support\Carbon;

/** Data contoh yang diperagakan, berikut tanggalnya; angka di uji boleh disebut apa adanya. */
trait PakaiDataContoh
{
    /** Tanggal demo; `AppServiceProvider` sengaja tidak memasangnya saat uji. */
    protected function siapkanDataContoh(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));
        /* Domain rekaan hanya di basis data pengujian, tidak di aturan bawaan aplikasi. */
        foreach (['contoh.test', 'drive.pu.go.id', 'intranet.pu.go.id'] as $host) {
            SumberBukti::firstOrCreate(['host' => $host],
                ['nama' => 'Penyimpanan uji '.$host, 'pola' => 'repositori', 'prefix' => '/', 'aktif' => true]);
        }
        KebijakanBukti::lupakan();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Akun menurut email, atau untuk satuan kerja menurut nama singkat unitnya ('medan',
     * 'bandung').
     */
    protected function akun(string $email): User
    {
        if (! str_contains($email, '@')) {
            $kode = collect(DataMasterSeeder::satker())->firstWhere(3, $email)[0] ?? '-';

            return User::where('peran', 'satker')->where('aktif', true)
                ->whereHas('satker', fn ($q) => $q->where('kode', $kode))->firstOrFail();
        }

        return User::where('email', $email)->firstOrFail();
    }

    protected function masuk(string $email): static
    {
        return $this->actingAs($this->akun($email));
    }
}
