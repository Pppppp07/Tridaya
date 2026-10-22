<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use Illuminate\Database\Eloquent\Model;

/**
 * Bunyi satu surat hasil atas satu rekomendasi. Validasi UKI memutus satu baris; verifikasi
 * Inspektorat memutus rekomendasi seutuhnya, dengan catatan per satuan kerja di `catatan_satker`.
 */
class KeputusanVerifikasi extends Model
{
    protected $fillable = [
        'verifikasi_id', 'rekomendasi_id', 'sasaran_id', 'hasil',
        'tenggat_baru', 'catatan', 'catatan_umum', 'catatan_satker',
        'aksi_id',
    ];

    protected $casts = [
        'hasil'                => HasilTelaah::class,
        'tenggat_baru'         => 'date',
        'catatan_satker'       => 'array',
    ];

    public function verifikasi()  { return $this->belongsTo(Verifikasi::class); }
    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
}
