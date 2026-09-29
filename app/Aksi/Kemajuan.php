<?php

namespace App\Aksi;

use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Tampil;

/**
 * Kemajuan satu tindak lanjut milik satu satuan kerja, termasuk isian yang
 * belum dikirim — padanan `kemajuanDraf`, `kalimatKemajuan`, `kewajibanTuntas`,
 * dan `barisSetorSah` prototipe.
 *
 * Selalu per baris (satu satuan kerja, satu bentuk tindak lanjut). Dulu tanpa
 * keduanya, jadi satuan kerja yang memikul dua tindak lanjut membaca dokumen
 * dan uang keduanya dijumlah jadi satu (W15).
 */
class Kemajuan
{
    public const HARI_KIRIM_OTOMATIS = 7;

    /** Baris pemulihan yang boleh terkirim: lengkap dan sah. */
    public static function setorSah(array $x): bool
    {
        $ntpnSah = (bool) preg_match('/^[0-9A-Za-z]{16}$/', (string) ($x['ntpn'] ?? ''));

        return ! empty($x['tanggal']) && self::angka($x['nilai'] ?? 0) > 0
            && trim((string) ($x['berkas'] ?? '')) !== '' && trim((string) ($x['link'] ?? '')) !== ''
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
            'ada'    => $item->where('terpenuhi', true)->count() + count($penuhi),
            'dari'   => $item->count(),
            'masuk'  => $dana ? $dana['masuk'] + $tambah : 0,
            'target' => $dana ? $dana['target'] : 0,
        ];
    }

    /** "2 dari 3 dokumen terpenuhi · pemulihan Rp 20.000.000 dari Rp 30.000.000" */
    public static function kalimat(array $k): string
    {
        $bagian = [];
        if ($k['dari']) {
            $bagian[] = "{$k['ada']} dari {$k['dari']} dokumen terpenuhi";
        }
        if ($k['target']) {
            $bagian[] = 'pemulihan '.($k['masuk'] ? Tampil::rupiah($k['masuk']) : 'Rp 0').' dari '.Tampil::rupiah($k['target']);
        }

        return implode(' · ', $bagian);
    }

    public static function sama(array $a, array $b): bool
    {
        return $a['ada'] === $b['ada'] && $a['masuk'] === $b['masuk'];
    }

    /**
     * Seluruh kewajiban baris ini terpenuhi dengan isian ini: tidak ada dokumen
     * yang belum, dan uangnya pas — tidak kurang, tidak lebih.
     */
    public static function tuntas(Rekomendasi $r, Sasaran $s, array $penuhi = [], array $setoran = []): bool
    {
        $belum = $r->permintaanUntuk($s->satker_id, $s->tindakan_id)->flatMap->item
            ->where('terpenuhi', false)->count();
        if ($belum - count($penuhi) > 0) {
            return false;
        }
        $dana = $r->progresDana($s->satker_id, $s->tindakan_id);
        if (! $dana) {
            return true;
        }
        $bakal = $dana['masuk'] + collect($setoran)->filter(fn ($x) => self::setorSah($x))->sum(fn ($x) => self::angka($x['nilai']));

        return $bakal === $dana['target'];
    }
}
