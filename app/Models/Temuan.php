<?php

namespace App\Models;

use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Illuminate\Database\Eloquent\Model;

class Temuan extends Model
{
    protected $fillable = [
        'laporan_id', 'kode', 'nomor_pada_surat', 'judul',
        'sebab', 'akibat',
        'kategori_temuan_id', 'kategori_intern_id', 'nilai',
    ];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class);
    }

    /* Satuan kerja tempat temuan terjadi (bisa beberapa) — bukan yang menanggung perbaikannya. */
    public function satkers()
    {
        return $this->belongsToMany(Satker::class, 'temuan_satker')->orderBy('temuan_satker.id');
    }

    /** Apakah temuan ini terjadi di satuan kerja tertentu. */
    public function mengenai(int|array|null $satkerId): bool
    {
        return $satkerId !== null && $this->satkers->whereIn('id', (array) $satkerId)->isNotEmpty();
    }

    public function rekomendasi()
    {
        return $this->hasMany(Rekomendasi::class)->orderBy('nomor_urut')->orderBy('id');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriTemuan::class, 'kategori_temuan_id');
    }

    public function kategoriIntern()
    {
        return $this->belongsTo(Referensi::class, 'kategori_intern_id');
    }

    /* Keterangan kedua kategori, satu bunyi untuk rincian laporan dan rincian rekomendasi. */
    public static function ketKategori(SumberLaporan $jenis): array
    {
        return [
            $jenis->melewatiSiptl()
                ? 'Klasifikasi temuan pada Laporan Hasil Pemeriksaan (LHP) BPK.'
                : 'Klasifikasi temuan pada Laporan Hasil Audit (LHA) Inspektorat.',
            'Kategori ini mengikuti laporan pemeriksaan dan dicatat terpisah dari kategori internal BPSDM.',
        ];
    }

    public const KET_KATEGORI_INTERN = [
        'Pengelompokan temuan untuk kebutuhan monitoring dan rekap internal BPSDM.',
        'Kategori ini dapat berbeda dari kategori temuan yang digunakan pemeriksa pada laporan.',
    ];

    /** Nilai temuan: dari surat, atau bila kosong jumlah tagihan rekomendasinya. */
    public function nilaiTemuan(): int
    {
        return (int) $this->nilai ?: (int) $this->rekomendasi->sum('nilai_pulih');
    }

    /* Sama seperti laporan — disimpulkan, tidak disimpan. */
    public function statusSimpulan(): ?StatusTindakLanjut
    {
        return Rekomendasi::simpulkan($this->rekomendasi);
    }
}
