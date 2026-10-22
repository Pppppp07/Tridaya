<?php

namespace App\Models;

use App\Enums\JenisReferensi;
use Illuminate\Database\Eloquent\Model;

class Referensi extends Model
{
    protected $table = 'referensis';
    /* `dokumen`: usulan "Dokumen yang diminta" — hanya untuk bentuk tindak lanjut. */
    protected $fillable = [
        'jenis', 'nama', 'dokumen', 'perlu_nilai', 'urutan', 'aktif',
    ];
    protected $casts = [
        'jenis' => JenisReferensi::class,
        'dokumen' => 'array',
        'perlu_nilai' => 'boolean',
        'aktif' => 'boolean',
    ];

    public function scopeJenis($q, JenisReferensi $j)
    {
        return $q->where('jenis', $j->value)->where('aktif', true)->orderBy('urutan');
    }
}
