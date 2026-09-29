<?php

namespace App\Support\Direktori;

use Illuminate\Support\Collection;

/**
 * Kontrak satu sumber data pegawai (27 Sep).
 *
 * Satu pegawai selalu berbentuk:
 *   ['nip' => '18 angka', 'nama' => …, 'jabatan' => …, 'unit' => kode unit,
 *    'unitNama' => nama unit, 'email' => …]
 *
 * Sumber lain (mis. SOAP eHRM, atau basis data kepegawaian langsung) cukup
 * memenuhi kontrak ini lalu didaftarkan di DirektoriIrm::sumber().
 */
interface SumberPegawai
{
    /** Pegawai yang nama atau NIP-nya memuat kata pencari. */
    public function cari(string $kata, int $batas): Collection;

    public function nip(string $nip): ?array;

    /** Pegawai yang tercatat di satu unit kerja. */
    public function diUnit(string $kodeUnit): Collection;

    /** Seluruh isi direktori — hanya bila sumbernya kecil (berkas contoh). */
    public function semua(): Collection;
}
