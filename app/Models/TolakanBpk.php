<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Setoran yang tidak diakui BPK, milik satu satuan kerja; dikurangkan di totalSetor(). */
class TolakanBpk extends Model
{
    protected $table = 'tolakan_bpks';

    protected $fillable = ['rekomendasi_id', 'sasaran_id', 'nilai', 'tanggal', 'catatan'];

    protected $casts = ['tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
}
