<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DataMasterSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh yang sama dengan yang diperagakan, berikut tanggalnya.
 *
 * Angka di uji ini boleh disebut apa adanya — 49 rekomendasi, 26 laporan, 7
 * pemberitahuan — karena data contohnya tetap. Kalau datanya berubah, ujinya yang
 * memberi tahu lebih dulu, bukan orang yang membaca layar.
 */
trait PakaiDataContoh
{
    /** Tanggal demo; `AppServiceProvider` sengaja tidak memasangnya saat uji. */
    protected function siapkanDataContoh(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Akun menurut email-nya — atau, untuk satuan kerja, menurut nama singkat
     * unitnya ('medan', 'bandung'). Sejak 18 Sep akun satuan kerja milik
     * penanggung jawabnya, dan email-nya email orang itu dari IRM.
     */
    protected function akun(string $email): User
    {
        if (! str_contains($email, '@')) {
            $kode = collect(DataMasterSeeder::satker())->firstWhere(4, $email)[0] ?? '-';

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
