<?php

namespace App\Support;

use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\RiwayatBerkas;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Penulis tunggal perubahan berkas: tidak ada jalur mengubah posisi atau penilaian tanpa tercatat.
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
     * Satu baris riwayat rekomendasi sekaligus satu baris log aktivitas. Riwayat memakai tanggal
     * peristiwa (boleh mundur); log memakai waktu sebenarnya. Keduanya di transaksi pemanggil.
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
     * Satu perubahan penilaian; yang tidak berubah tidak dicatat, kecuali `paksa` (suratnya tetap
     * terbit).
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
            /* Akun yang menekan tombol; kosong bila perintah terjadwal. */
            'oleh_id'    => auth()->id(),
            'tanggal'    => self::tanggal($tanggal),
            'catatan'    => $catatan ?: null,
            'aksi_id'    => $aksiId,
            'jenis_aksi' => $jenisAksi ?: null,
        ]);
    }

    /**
     * Pindahkan baris yang sedang di meja `$dari`, hanya milik satuan kerja dan bentuk tindak
     * lanjut yang disebut.
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
     * Status BPK rekomendasi dirangkum ulang dari barisnya; tidak pernah diisi dari formulir. Kolomnya
     * tidak boleh kosong, jadi yang belum ada di SIPTL disimpan BT — tampilan membaca barisnya.
     */
    public static function rangkumStatus(Rekomendasi $r): void
    {
        $r->unsetRelation('sasaran');
        $r->barisSemua = null;
        $r->update(['status' => $r->statusRek() ?? StatusTindakLanjut::BT]);
    }

    /** "Balai Wil. I Medan" — sebutan pelaku satuan kerja. */
    public static function pendek(Sasaran $x): string
    {
        return $x->satker->namaPendek();
    }
}
