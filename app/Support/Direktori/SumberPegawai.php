<?php

namespace App\Support\Direktori;

use Illuminate\Support\Collection;

/** Kontrak sumber data pegawai: ['nip', 'nama', 'jabatan', 'unit', 'unitNama', 'email']. */
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
