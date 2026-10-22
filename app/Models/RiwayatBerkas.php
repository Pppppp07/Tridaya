<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hanya bertambah; tidak ada jalur ubah maupun hapus. */
class RiwayatBerkas extends Model
{
    protected $table = 'riwayat_berkas';
    protected $fillable = [
        'rekomendasi_id', 'waktu', 'aktor_id', 'label_aktor', 'aksi',
    ];
    protected $casts = [
        'waktu' => 'datetime',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
}
