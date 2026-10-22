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
        'sumber', 'nomor', 'tgl_surat', 'tgl_terima',
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

    /* ---- Satuan kerja yang diperiksa ---- */

    /** Diturunkan dari temuannya, urut kemunculan di surat (bukan abjad). */
    public function satkerDiperiksa(): Collection
    {
        return $this->temuan
            ->flatMap(fn ($t) => $t->satkers)
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Berapa temuan yang mengenai satuan kerja ini. */
    public function jumlahTemuanSatker(int|array $satkerId): int
    {
        return $this->temuan->filter(fn ($t) => $t->satkers->whereIn('id', (array) $satkerId)->isNotEmpty())->count();
    }

    /* ---- Angka ---- */

    /** Angka ringkas satu laporan, dari medan yang sudah dipangkas menurut yang melihat. */
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
            /* Tanpa rekomendasi yang terlihat bukan berarti beres — every() pada daftar kosong
               bernilai benar. */
            'beres'       => $rek->isNotEmpty() && $rek->every(fn ($r) => $r->beres()),
            'hanyaPantau' => $rek->isEmpty(),
            'nilaiTemuan' => (int) $this->temuan->sum(fn ($t) => $t->nilaiTemuan()),
        ];
    }

    /** Sebaran tindak lanjut menurut mejanya; tiap baris satuan kerja dihitung sendiri. */
    public function sebaranTindakLanjut(PeranPengguna $peran, int|array|null $satkerId): array
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

    /** Jarak wajar surat sampai → dicatat; lewat dari ini jaraknya disebut di daftar laporan. */
    public const BATAS_JEDA_CATAT = 7;

    /* Keterangan angka pemulihan dana — satu bunyi untuk kepala rincian dan blok temuan. */
    public const KET_TAGIHAN = 'Total nilai yang harus dipulihkan pada rekomendasi yang ditampilkan, dihitung dari kewajiban tiap penugasan Satker.';
    public const KET_ADMINISTRATIF = 'Selisih nilai temuan dengan tagihan rekomendasi yang ditampilkan. Bagian ini tidak dibebankan sebagai kewajiban pemulihan nilai pada penugasan tersebut.';

    /** Jarak antara surat sampai dan surat dicatat. */
    public function jedaPencatatan(): ?int
    {
        if (! $this->dicatat_pada) {
            return null;
        }

        return (int) $this->tgl_terima->copy()->startOfDay()->diffInDays($this->dicatat_pada->copy()->startOfDay(), false);
    }

    public function statusSimpulan(): ?StatusTindakLanjut
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
