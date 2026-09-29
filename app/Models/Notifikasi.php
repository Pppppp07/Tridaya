<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    protected $fillable = [
        'rekomendasi_id', 'tindakan_id', 'waktu', 'label_pelaku', 'aksi', 'untuk_peran', 'blok',
    ];

    protected $casts = ['waktu' => 'datetime', 'untuk_peran' => 'array'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Bentuk tindak lanjut yang diberitahukan — disebut hanya kalau
       rekomendasinya punya lebih dari satu. */
    public function tindakan()    { return $this->belongsTo(Tindakan::class); }
    /* Satuan kerja yang diberi tahu. Pemberitahuan untuk peran selain satuan kerja tetap
       menyebut satuan kerja mana yang bersangkutan. */
    public function satker()      { return $this->belongsToMany(Satker::class, 'notifikasi_satker'); }
    public function dibaca()      { return $this->belongsToMany(User::class, 'notifikasi_bacas')->withPivot('dibaca_pada'); }
}
