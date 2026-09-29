<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penanggung jawab tiap unit kerja — padanan `pj` pada data master prototipe.
 *
 * Satu unit kerja satu penanggung jawab, dan penanggung jawab itulah akun yang
 * masuk sebagai unit kerja tersebut. Kata Bang Kamal: "Cukup satu orang aja
 * yang nginput … Nanti pada saat ada inputan login, yang bisa lihat tuh hanya
 * dia doang." Jadi menetapkan penanggung jawab berarti:
 *
 * - akun pegawai itu disiapkan dari satu baris IRM — nama, NIP, jabatan, dan
 *   email diambil sekaligus, tidak pernah diketik;
 * - akun satuan kerja lain di unit yang sama dinonaktifkan. Mereka tidak bisa
 *   masuk lagi, tapi jejak perbuatannya tetap tercatat atas namanya.
 */
class PenanggungJawab
{
    /**
     * @param  array{nip:string, nama:string, jabatan:string, email:string}  $pegawai  satu baris IRM
     * @return string|null  alasan penolakan, atau null bila berhasil
     */
    public static function tetapkan(Satker $unit, array $pegawai): ?string
    {
        /* Satu orang satu unit kerja: akunnya cuma bisa menunjuk satu. */
        $lain = User::where('nip', $pegawai['nip'])->where('aktif', true)
            ->where('peran', PeranPengguna::SATKER->value)
            ->where('satker_id', '!=', $unit->id)->with('satker')->first();
        if ($lain) {
            return $pegawai['nama'].' sudah memegang '.($lain->satker?->namaPendek() ?? 'unit kerja lain')
                .'. Pilih penanggung jawab lain untuk unit kerja itu dulu.';
        }

        $akun = User::where('nip', $pegawai['nip'])->first()
            ?? User::where('email', $pegawai['email'])->first();

        /* Akun yang sudah ada dengan peran lain — misalnya pegawai Setba — tidak
           diubah jadi akun satuan kerja diam-diam. Akun pusat yang sudah
           dinonaktifkan pun tidak: jejaknya tetap atas peran lamanya. */
        if ($akun && $akun->peran !== PeranPengguna::SATKER) {
            return $pegawai['nama'].' sudah punya akun sebagai '.$akun->peran->pendek()
                .($akun->aktif ? '.' : ' (nonaktif). Aktifkan atau ubah lewat tab Pengguna.');
        }
        if ($akun && $akun->nip && $akun->nip !== $pegawai['nip']) {
            return 'Email '.$pegawai['email'].' sudah dipakai akun lain.';
        }

        DB::transaction(function () use ($unit, $pegawai, $akun) {
            $akun ??= new User(['password' => self::passwordAwal()]);
            $akun->fill([
                'name'      => $pegawai['nama'],
                'nip'       => $pegawai['nip'],
                'jabatan'   => $pegawai['jabatan'],
                'email'     => $pegawai['email'],
                'peran'     => PeranPengguna::SATKER->value,
                'satker_id' => $unit->id,
                'aktif'     => true,
            ])->save();

            /* Penanggung jawab lama langsung diputus dari semua perangkatnya
               (27 Sep) — bukan menunggu sesinya habis. */
            User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $unit->id)
                ->whereKeyNot($akun->id)->where('aktif', true)->get()
                ->each(function (User $lama) {
                    $lama->update(['aktif' => false]);
                    Akun::putuskanSesi($lama);
                });
        });

        return null;
    }

    /**
     * Akun yang dibuat dari Data master belum punya password yang diketahui
     * siapa pun. Pada pemasangan sungguhan orangnya masuk lewat IRM; pada
     * demo password-nya boleh dipatok lewat `SIMTLHP_PASSWORD_DEMO`.
     */
    private static function passwordAwal(): string
    {
        return (string) (config('simtlhp.password_demo') ?: Str::random(40));
    }
}
