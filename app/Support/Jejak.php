<?php

namespace App\Support;

use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Penulis tunggal perubahan berkas — padanan `geserBerkas`, `jejakBaris`,
 * `ubahBarisSiptl`, dan `ubahRek` prototipe.
 *
 * Satu tempat, supaya tidak ada jalur mengubah posisi atau penilaian yang lolos
 * tanpa tercatat. Pengendali dan perintah terjadwal memanggil kelas ini, tidak
 * menulis kolomnya sendiri.
 */
class Jejak
{
    /** Tanda satu tindakan, dibawa seluruh catatannya — riwayat menggambarnya satu baris. */
    public static function aksiId(): string
    {
        return 'ak-'.Str::lower((string) Str::ulid());
    }

    /** Tanggal peristiwa: yang dibawa langkahnya (tanggal surat, tanggal setor), atau hari ini. */
    public static function tanggal($t = null): Carbon
    {
        return ($t ? Carbon::parse($t) : now())->copy()->startOfDay();
    }

    /**
     * Satu baris riwayat aktivitas rekomendasi — dan, sejak 27 Sep, satu baris
     * log aktivitas dengan kalimat yang sama. Riwayat memakai tanggal
     * peristiwanya (tanggal surat boleh mundur); log memakai waktu kejadian
     * sebenarnya, beserta akun, alamat, dan perangkat yang menekan tombolnya.
     * Keduanya di dalam transaksi pemanggilnya: gagal satu, batal semua.
     */
    public static function riwayat(Rekomendasi $r, string $oleh, string $teks, $tanggal = null): RiwayatBerkas
    {
        $baris = RiwayatBerkas::create([
            'rekomendasi_id' => $r->id,
            'waktu'          => self::tanggal($tanggal),
            'aktor_id'       => auth()->id(),
            'label_aktor'    => $oleh,
            'aksi'           => $teks,
        ]);
        Aktivitas::catat('berkas', $teks, ['subjek' => $r, 'rincian' => array_filter([
            'atas_nama' => $oleh,
            'tanggal_peristiwa' => $tanggal ? self::tanggal($tanggal)->toDateString() : null,
        ])]);

        return $baris;
    }

    /**
     * Satu perubahan penilaian pada satu baris. Yang tidak berubah tidak
     * dicatat — kecuali `paksa`: putusan selalu tercatat walau hasilnya sama,
     * karena suratnya tetap terbit.
     */
    public static function status(Sasaran $x, string $sumber, ?string $dari, ?string $ke, string $oleh,
        ?string $catatan, $tanggal = null, ?string $aksiId = null, ?string $jenisAksi = null, bool $paksa = false): void
    {
        if ((string) $dari === (string) $ke && ! $paksa) {
            return;
        }

        RiwayatStatus::create([
            'sasaran_id' => $x->id,
            'sumber'     => $sumber,
            'dari'       => (string) $dari,
            'ke'         => (string) $ke,
            'oleh'       => $oleh,
            'tanggal'    => self::tanggal($tanggal),
            'catatan'    => $catatan ?: null,
            'aksi_id'    => $aksiId,
            'jenis_aksi' => $jenisAksi ?: null,
        ]);
    }

    /**
     * Memindah baris yang sedang di meja `$dari` — hanya milik satuan kerja dan
     * bentuk tindak lanjut yang disebut. Rekomendasi tidak berpindah ke mana
     * pun; yang bergerak barisnya.
     *
     * @return Collection<int, Sasaran> baris yang dipindah
     */
    public static function geser(Rekomendasi $r, PosisiBerkas $dari, PosisiBerkas $ke,
        ?int $satkerId = null, ?int $tindakanId = null): Collection
    {
        $pindah = $r->sasaran()->get()
            ->filter(fn ($x) => $x->pos() === $dari
                && (! $satkerId || $x->satker_id === $satkerId)
                && (! $tindakanId || $x->tindakan_id === $tindakanId))
            ->values();

        foreach ($pindah as $x) {
            $x->update(['posisi' => $ke]);
        }
        $r->unsetRelation('sasaran');

        return $pindah;
    }

    /**
     * Status BPK rekomendasi dirangkum ulang dari barisnya sesudah tiap
     * penulisan SIPTL — `rekomendasis.status` tidak pernah diisi dari formulir.
     */
    public static function rangkumStatus(Rekomendasi $r): void
    {
        $r->unsetRelation('sasaran');
        $r->barisSemua = null;
        $r->update(['status' => $r->statusRek()]);
    }

    /** "Balai Wil. I Medan" — sebutan pelaku satuan kerja. */
    public static function pendek(Sasaran $x): string
    {
        return $x->satker->namaPendek();
    }
}
