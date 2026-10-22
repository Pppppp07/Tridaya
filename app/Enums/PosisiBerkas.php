<?php

namespace App\Enums;

/**
 * Di meja siapa berkas satu tindak lanjut satuan kerja berada (`sasarans.posisi`): satker →
 * setba_kembali → setba_tinjau → uki → setba_teruskan → inspektorat → tuntas. Urutan case = urutan
 * rantai.
 */
enum PosisiBerkas: string
{
    case SATKER          = 'satker';
    /* Ditolak UKI, Inspektorat, atau BPK: berkasnya ke Setba dulu, yang lalu mengirim ulang ke
       satuan kerja. */
    case SETBA_KEMBALI   = 'setba_kembali';
    case SETBA_TINJAU    = 'setba_tinjau';
    case UKI             = 'uki';
    case SETBA_TERUSKAN  = 'setba_teruskan';
    case INSPEKTORAT     = 'inspektorat';
    case TUNTAS          = 'tuntas';

    /** Label menyebut posisi, bukan putusan. */
    public function label(): string
    {
        return match ($this) {
            self::SATKER         => 'Menunggu tanggapan satuan kerja',
            self::SETBA_KEMBALI  => 'Dikembalikan — menunggu dikirim ulang Setba',
            self::SETBA_TINJAU   => 'Tanggapan perlu ditinjau Setba',
            self::UKI            => 'Menunggu telaah UKI',
            self::SETBA_TERUSKAN => 'Hasil telaah UKI perlu diteruskan',
            self::INSPEKTORAT    => 'Menunggu verifikasi Inspektorat',
            self::TUNTAS         => 'Sudah selesai diperiksa',
        };
    }

    /** Letaknya di rantai, dari 0. */
    public function urutan(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** Peran pemegang berkas; null pada TUNTAS (urusan SIPTL dibaca terpisah). */
    public function pemegang(): ?PeranPengguna
    {
        return match ($this) {
            self::SATKER         => PeranPengguna::SATKER,
            self::SETBA_KEMBALI,
            self::SETBA_TINJAU,
            self::SETBA_TERUSKAN => PeranPengguna::SETBA,
            self::UKI            => PeranPengguna::UKI,
            self::INSPEKTORAT    => PeranPengguna::INSPEKTORAT,
            self::TUNTAS         => null,
        };
    }

    /** Sebutan pemegang: "Balai", "Setba", "UKI", "Inspektorat", "—". */
    public function pegang(): string
    {
        return match ($this) {
            self::SATKER => 'Balai',
            self::TUNTAS => '—',
            default      => $this->pemegang()->pendek(),
        };
    }

    /** Tahap keberapa pada rel lima tahap. */
    public function tahap(): int
    {
        return match ($this) {
            self::SATKER, self::SETBA_KEMBALI, self::SETBA_TINJAU => 2,
            self::UKI, self::SETBA_TERUSKAN                       => 3,
            self::INSPEKTORAT                                     => 4,
            self::TUNTAS                                          => 5,
        };
    }

    /** Tahap sudah tuntas, tinggal diteruskan. */
    public function transit(): bool
    {
        return in_array($this, [self::SETBA_TINJAU, self::SETBA_TERUSKAN], true);
    }

    /** Lima tahap, sama untuk LHP dan LHA. */
    public const TAHAP = ['Registrasi', 'Tanggapan balai', 'Telaah UKI',
        'Verifikasi Inspektorat', 'Surat & penetapan'];

    /** Yang paling tertinggal dari sederet posisi. Kosong berarti masih di satuan kerja. */
    public static function palingBelakang(iterable $posisi): self
    {
        $terkecil = null;
        foreach ($posisi as $p) {
            if ($terkecil === null || $p->urutan() < $terkecil->urutan()) {
                $terkecil = $p;
            }
        }

        return $terkecil ?? self::SATKER;
    }
}
