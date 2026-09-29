<?php

namespace App\Support\Direktori;

use Illuminate\Support\Collection;

/**
 * Direktori pegawai dari berkas JSON — padanan `IRM_CONTOH` prototipe.
 * Dibangkitkan `Prototipe/alat/buat-irm-contoh.py`; seluruh orangnya rekaan.
 */
class DirektoriBerkas implements SumberPegawai
{
    private ?Collection $isi = null;

    public function __construct(private string $berkas) {}

    public function semua(): Collection
    {
        if ($this->isi === null) {
            $data = is_file($this->berkas) ? json_decode((string) file_get_contents($this->berkas), true) : null;
            $this->isi = collect(is_array($data) ? $data : []);
        }

        return $this->isi;
    }

    public function cari(string $kata, int $batas): Collection
    {
        $q = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $kata)));
        $nip = (string) preg_replace('/\s/u', '', $q);

        return $this->semua()
            ->filter(fn ($p) => str_contains(mb_strtolower($p['nama']), $q)
                || (ctype_digit($nip) && str_contains($p['nip'], $nip)))
            ->take($batas)->values();
    }

    public function nip(string $nip): ?array
    {
        return $this->semua()->firstWhere('nip', $nip);
    }

    public function diUnit(string $kodeUnit): Collection
    {
        return $this->semua()->where('unit', $kodeUnit)->values();
    }
}
