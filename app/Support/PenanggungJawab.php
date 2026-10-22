<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Penanggung jawab = satu-satunya akun yang masuk sebagai unit kerja itu. Akunnya disiapkan dari
 * satu baris IRM (tidak diketik); akses satuan kerja PJ lama dihentikan (peran pusatnya tetap).
 */
class PenanggungJawab
{
    public static function tetapkan(Satker $unit, array $pegawai): ?string
    {
        return DB::transaction(function () use ($unit, $pegawai) {
            /* Kunci unit sebelum akun, sama dengan perubahan izin masuk di Pengguna. */
            $unit = Satker::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $akun = User::where('nip', $pegawai['nip'])->lockForUpdate()->first()
                ?? User::where('email', $pegawai['email'])->lockForUpdate()->first();

            /* Satu orang satu unit kerja: akunnya cuma bisa menunjuk satu. */
            $lain = User::where('nip', $pegawai['nip'])->where('aktif', true)
                ->where('peran', PeranPengguna::SATKER->value)
                ->where('satker_id', '!=', $unit->id)->with('satker')->first();
            if ($lain) {
                return $pegawai['nama'].' sudah memegang '.($lain->satker?->namaPendek() ?? 'unit kerja lain')
                    .'. Pilih penanggung jawab lain untuk unit kerja itu dulu.';
            }

            if ($akun && $akun->nip && $akun->nip !== $pegawai['nip']) {
                return 'Email '.$pegawai['email'].' sudah dipakai akun lain.';
            }

            if (User::where('email', $pegawai['email'])->when($akun, fn ($q) => $q->whereKeyNot($akun->id))->exists()) {
                return 'Email '.$pegawai['email'].' sudah dipakai akun lain.';
            }

            $peranPusat = $akun ? array_values(array_filter(array_map(fn ($p) => $p->value, $akun->semuaPeran()), fn ($p) => $p !== 'satker')) : [];
            $aksesBerubah = ! $akun || ! $akun->aktif || $akun->peran !== PeranPengguna::SATKER || (int) $akun->satker_id !== (int) $unit->id;

            $akun ??= new User(['password' => Akun::passwordAwal()]);
            $akun->fill([
                'name' => $pegawai['nama'],
                'nip' => $pegawai['nip'],
                'jabatan' => $pegawai['jabatan'],
                'email' => $pegawai['email'],
                'peran' => PeranPengguna::SATKER->value,
                'satker_id' => $unit->id,
                'aktif' => true,
            ])->forceFill(['peran_tambahan' => $peranPusat])->save();
            if ($aksesBerubah) {
                Akun::putuskanSesi($akun);
            }

            /* PJ lama langsung diputus dari semua perangkatnya. */
            User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $unit->id)
                ->whereKeyNot($akun->id)->where('aktif', true)->get()
                ->each(function (User $lama) {
                    $pusat = array_values(array_filter(array_map(fn ($p) => $p->value, $lama->semuaPeran()), fn ($p) => $p !== 'satker'));
                    if ($pusat) {
                        $lama->forceFill(['peran' => $pusat[0], 'peran_tambahan' => array_slice($pusat, 1), 'satker_id' => null])->save();
                    } else {
                        $lama->update(['aktif' => false]);
                    }
                    Akun::putuskanSesi($lama);
                });

            return null;
        }, 3);
    }
}
