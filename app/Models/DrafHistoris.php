<?php

namespace App\Models;

use App\Casts\IsianTerkompresi;
use App\Support\Aktivitas;
use Illuminate\Database\Eloquent\Model;

/** Sumber asli, koreksi review, dan hasil impor disimpan terpisah di dalam isian. */
class DrafHistoris extends Model
{
    protected $table = 'draf_historis';

    protected $attributes = ['versi' => 1];

    protected $fillable = ['user_id', 'nama_berkas', 'berkas', 'sha256', 'isian', 'versi', 'diimpor_pada'];

    protected $casts = ['isian' => IsianTerkompresi::class, 'diimpor_pada' => 'datetime', 'versi' => 'integer'];

    public function freshTimestamp()
    {
        return Aktivitas::waktuKini();
    }
}
