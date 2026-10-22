<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jawaban satu satuan kerja atas bagiannya (menggantung pada sasaran). Hanya bertambah. */
class Tanggapan extends Model
{
    protected $table = 'tindak_lanjuts';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'uraian',
        'dicatat_oleh', 'label_pencatat',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function pencatat()    { return $this->belongsTo(User::class, 'dicatat_oleh'); }
}
