<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Support\TindakLanjutRingkas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Laporan extends Model
{
    /** Hasil `angka()` yang sudah dihitung sekali untuk satu permintaan. Bukan atribut Eloquent. */
    public ?array $angkaTersimpan = null;

    protected $fillable = [
        /* `jenis_periksa` (LK / PDTT) adalah sumbu TERSENDIRI, bukan pengganti
           LHP/LHA. Dari 312 baris lembar pemantauan: 297 Laporan Keuangan,
           15 Pemeriksaan Dengan Tujuan Tertentu — keduanya sama-sama LHP. */
        'sumber', 'jenis_periksa', 'nomor', 'tgl_surat', 'tgl_terima',
        'dicatat_pada', 'dicatat_oleh',
    ];

    protected $casts = [
        'sumber' => SumberLaporan::class,
        'tgl_surat' => 'date',
        'tgl_terima' => 'date',
        'dicatat_pada' => 'date',
    ];

    public function temuan()      { return $this->hasMany(Temuan::class)->orderBy('id'); }
    public function lampiran()    { return $this->hasMany(Lampiran::class); }
    public function dicatatOleh() { return $this->belongsTo(User::class, 'dicatat_oleh'); }

    /** Seluruh rekomendasi di bawah laporan ini, lewat temuannya. */
    public function rekomendasi()
    {
        return $this->hasManyThrough(Rekomendasi::class, Temuan::class);
    }

    /** Surat pemeriksaan aslinya — tidak pernah diberikan ke satuan kerja. */
    public function suratAsli(): ?Lampiran
    {
        return $this->lampiran->first(fn ($l) => $l->rekomendasi_id === null);
    }

    /* ================================================================
       SATUAN KERJA YANG DIPERIKSA
       ================================================================ */

    /**
     * Diturunkan dari temuannya, urut menurut kemunculan pertamanya di surat —
     * bukan abjad, supaya terbaca berurutan seperti dokumen aslinya.
     */
    public function satkerDiperiksa(): Collection
    {
        return $this->temuan
            ->flatMap(fn ($t) => $t->satkers)
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Berapa temuan yang mengenai satuan kerja ini. */
    public function jumlahTemuanSatker(int $satkerId): int
    {
        return $this->temuan->filter(fn ($t) => $t->satkers->contains('id', $satkerId))->count();
    }

    /* ================================================================
       ANGKA — padanan `angkaLaporan` prototipe
       ================================================================ */

    /**
     * Angka ringkas satu laporan. Dibaca dari medan yang sudah dipangkas
     * menurut yang melihat: pada tampilan satuan kerja, `nilai_pulih` sudah
     * dikecilkan jadi bagiannya dan barisnya tinggal miliknya.
     */
    public function angka(): array
    {
        $rek = $this->temuan->flatMap->rekomendasi->values();

        return [
            'rek'         => $rek,
            'temuan'      => $this->temuan->count(),
            'jml'         => $rek->count(),
            'target'      => (int) $rek->sum('nilai_pulih'),
            'masuk'       => (int) $rek->sum(fn ($r) => $r->totalSetor()),
            'telat'       => $rek->filter(fn ($r) => $r->telat())->count(),
            'perlu'       => $rek->filter(fn ($r) => $r->perluPerhatian())->count(),
            'tuntas'      => $rek->filter(fn ($r) => $r->status === StatusTindakLanjut::SS)->count(),
            /* Laporan tanpa rekomendasi yang terlihat bukan berarti sudah beres —
               tanpa penjaga ini, every() pada daftar kosong membuatnya dianggap
               selesai dan berkasnya menghilang dari daftar yang masih berjalan. */
            'beres'       => $rek->isNotEmpty() && $rek->every(fn ($r) => $r->beres()),
            'hanyaPantau' => $rek->isEmpty(),
            'nilaiTemuan' => (int) $this->temuan->sum(fn ($t) => $t->nilaiTemuan()),
        ];
    }

    /**
     * Sebaran tindak lanjut laporan ini menurut mejanya. Tiap baris satuan kerja
     * dihitung sendiri — satu rekomendasi bisa punya beberapa tindak lanjut di
     * meja berbeda. Satuan kerja menghitung bagiannya sendiri.
     *
     * @return list<array{nama:string, n:int}>
     */
    public function sebaranTindakLanjut(PeranPengguna $peran, ?int $satkerId): array
    {
        $peta = [];
        foreach ($this->temuan->flatMap->rekomendasi as $r) {
            foreach (TindakLanjutRingkas::daftar($r, $peran, $satkerId) as $x) {
                $peta[$x['meja']] = ($peta[$x['meja']] ?? 0) + 1;
            }
        }

        return collect(TindakLanjutRingkas::URUT_MEJA)
            ->filter(fn ($m) => isset($peta[$m]))
            ->map(fn ($m) => ['nama' => $m, 'n' => $peta[$m]])
            ->values()->all();
    }

    /**
     * Jarak wajar antara surat sampai dan surat dicatat. Lewat dari ini,
     * jaraknya disebut di daftar laporan — bukan sebagai kesalahan, tapi
     * supaya terbaca.
     */
    public const BATAS_JEDA_CATAT = 7;

    /* Keterangan angka pemulihan dana — satu bunyi untuk Pemulihan dana di
       kepala rincian laporan dan angka di tiap blok temuan. Padanan
       KET_TAGIHAN dan KET_ADMINISTRATIF di prototipe. */
    public const KET_TAGIHAN = 'Bagian yang memang harus disetor ke kas negara sesuai bunyi rekomendasinya.';
    public const KET_ADMINISTRATIF = 'Bagian yang tidak perlu disetor — cukup dilengkapi dokumennya atau diperbaiki prosedurnya. Bisa berubah jadi tagihan kalau buktinya tidak pernah ada.';

    /** Jarak antara surat sampai dan surat dicatat. */
    public function jedaPencatatan(): ?int
    {
        if (! $this->dicatat_pada) {
            return null;
        }

        return (int) $this->tgl_terima->copy()->startOfDay()->diffInDays($this->dicatat_pada->copy()->startOfDay(), false);
    }

    public function statusSimpulan(): StatusTindakLanjut
    {
        return Rekomendasi::simpulkan($this->temuan->flatMap->rekomendasi);
    }

    public function tenggatJawab(): \Carbon\CarbonInterface
    {
        return Rekomendasi::hitungTenggat($this->tgl_terima, $this->sumber);
    }

    /** Tahun surat pemeriksaannya — sama dengan tahun pada Ref IDT. */
    public function tahun(): string
    {
        return optional($this->tgl_surat ?? $this->tgl_terima)->format('Y') ?? '—';
    }
}
