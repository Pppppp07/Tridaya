<?php

namespace App\Support\Direktori;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Direktori pegawai dari layanan eHRM (27 Sep) — `SIMTLHP_DIREKTORI=http`.
 *
 * Alamat, token, jalur tiap pertanyaan, dan nama medan jawabannya diatur di
 * config simtlhp.direktori.http, jadi bentuk API Pusdatin yang sebenarnya
 * cukup dipetakan di .env. Kalau bentuknya terlalu jauh berbeda, tulis kelas
 * baru yang memenuhi SumberPegawai.
 *
 * Supaya server eHRM tidak dibebani:
 * - jawaban disimpan di cache (bawaan 30 menit) — mengetik nama yang sama
 *   dua kali tidak bertanya dua kali;
 * - setiap pertanyaan dibatasi waktunya (bawaan 8 detik); eHRM yang lambat
 *   tidak menahan halaman Data master selamanya;
 * - pencarian baru dikirim sesudah dua huruf (DirektoriIrm::cari).
 *
 * Kegagalan menghubungi eHRM dilempar sebagai GagalDirektori; DirektoriIrm
 * menangkapnya dan halaman menampilkan pesannya.
 */
class DirektoriHttp implements SumberPegawai
{
    public function __construct(private array $c) {}

    public function semua(): Collection
    {
        return collect();
    }

    public function cari(string $kata, int $batas): Collection
    {
        return $this->daftar('cari', ['q' => $kata])->take($batas)->values();
    }

    public function nip(string $nip): ?array
    {
        $jwb = $this->tanya('nip', ['nip' => $nip]);
        $wadah = (string) ($this->c['wadah'] ?? '');
        $isi = $wadah !== '' && is_array(data_get($jwb, $wadah)) ? data_get($jwb, $wadah) : $jwb;
        /* Sebagian layanan menjawab satu pegawai sebagai larik berisi satu. */
        if (is_array($isi) && array_is_list($isi)) {
            $isi = $isi[0] ?? null;
        }
        $p = is_array($isi) ? $this->petakan($isi) : null;

        return $p && $p['nip'] === $nip ? $p : null;
    }

    public function diUnit(string $kodeUnit): Collection
    {
        return $this->daftar('unit', ['unit' => $kodeUnit]);
    }

    /* ================================================================ */

    private function daftar(string $jalur, array $isi): Collection
    {
        $jwb = $this->tanya($jalur, $isi);
        $wadah = (string) ($this->c['wadah'] ?? '');
        $baris = $wadah !== '' ? data_get($jwb, $wadah, []) : $jwb;

        return collect(is_array($baris) && array_is_list($baris) ? $baris : [])
            ->map(fn ($x) => is_array($x) ? $this->petakan($x) : null)
            ->filter(fn ($p) => $p && $p['nip'] !== '')->values();
    }

    private function tanya(string $jalur, array $isi): array
    {
        $url = rtrim((string) ($this->c['url'] ?? ''), '/');
        if ($url === '') {
            throw new GagalDirektori('Alamat layanan eHRM belum diisi (SIMTLHP_EHRM_URL).');
        }
        $pola = (string) ($this->c['jalur'][$jalur] ?? '');
        $alamat = $url.strtr($pola, array_combine(
            array_map(fn ($k) => '{'.$k.'}', array_keys($isi)),
            array_map(fn ($v) => rawurlencode((string) $v), array_values($isi)),
        ));
        $menit = max(0, (int) ($this->c['simpan_menit'] ?? 30));

        $ambil = function () use ($alamat) {
            try {
                $jwb = Http::acceptJson()
                    ->when($this->c['token'] ?? null, fn ($h, $t) => $h->withToken((string) $t))
                    ->timeout(max(2, (int) ($this->c['batas_detik'] ?? 8)))
                    ->get($alamat);
            } catch (ConnectionException $e) {
                throw new GagalDirektori('Layanan eHRM tidak bisa dihubungi. Coba lagi sebentar lagi.');
            }
            if ($jwb->status() === 404) {
                return [];
            }
            if (! $jwb->successful()) {
                throw new GagalDirektori('Layanan eHRM mengembalikan error ('.$jwb->status().').');
            }

            return (array) $jwb->json();
        };

        return $menit > 0
            ? Cache::remember('ehrm:'.sha1($alamat), now()->addMinutes($menit), $ambil)
            : $ambil();
    }

    /** Satu baris jawaban eHRM → bentuk pegawai aplikasi ini. */
    private function petakan(array $x): array
    {
        $m = (array) ($this->c['medan'] ?? []);
        $v = fn (string $k) => trim((string) data_get($x, (string) ($m[$k] ?? $k), ''));

        return [
            'nip'      => (string) preg_replace('/\D/', '', $v('nip')),
            'nama'     => $v('nama'),
            'jabatan'  => $v('jabatan'),
            'unit'     => $v('unit'),
            'unitNama' => $v('unitNama'),
            'email'    => mb_strtolower($v('email')),
            'pj'       => false,
        ];
    }
}
