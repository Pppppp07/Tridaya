<?php

namespace App\Aksi;

use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\KebijakanBukti;
use App\Support\Tampil;

/**
 * Kemajuan satu tindak lanjut milik satu satuan kerja, termasuk isian yang sedang dikirim. Selalu
 * per baris.
 */
class Kemajuan
{
    /** Baris pemulihan yang boleh terkirim: lengkap dan sah. */
    public static function setorSah(array $x): bool
    {
        $ntpnSah = (bool) preg_match('/^[0-9A-Za-z]{16}$/', (string) ($x['ntpn'] ?? ''));

        return ! empty($x['tanggal']) && self::angka($x['nilai'] ?? 0) > 0
            && trim((string) ($x['berkas'] ?? '')) !== '' && KebijakanBukti::sah($x['link'] ?? null)
            && (($x['jenis'] ?? 'setor') === 'perbaikan' ? trim((string) ($x['noBa'] ?? '')) !== '' : $ntpnSah);
    }

    public static function angka($v): int
    {
        return (int) preg_replace('/\D/', '', (string) $v);
    }

    /** @return array{ada:int, dari:int, masuk:int, target:int} */
    public static function hitung(Rekomendasi $r, Sasaran $s, array $penuhi = [], array $setoran = []): array
    {
        $item = $r->permintaanUntuk($s->satker_id, $s->tindakan_id)->flatMap->item;
        $dana = $r->progresDana($s->satker_id, $s->tindakan_id);
        $tambah = collect($setoran)->filter(fn ($x) => self::setorSah($x))->sum(fn ($x) => self::angka($x['nilai']));

        return [
            'ada' => $item->where('terpenuhi', true)->count() + count($penuhi),
            'dari' => $item->count(),
            'masuk' => $dana ? $dana['masuk'] + $tambah : 0,
            'target' => $dana ? $dana['target'] : 0,
        ];
    }

    /** "2 dari 3 dokumen terpenuhi · pemulihan Rp 20.000.000 dari Rp 30.000.000"; draf memakai "siap". */
    public static function kalimat(array $k, string $kata = 'terpenuhi'): string
    {
        $bagian = [];
        if ($k['dari']) {
            $bagian[] = "{$k['ada']} dari {$k['dari']} dokumen {$kata}";
        }
        if ($k['target']) {
            $bagian[] = 'pemulihan '.($k['masuk'] ? Tampil::rupiah($k['masuk']) : 'Rp 0').' dari '.Tampil::rupiah($k['target']);
        }

        return implode(' · ', $bagian);
    }
}
