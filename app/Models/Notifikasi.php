<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    protected $fillable = [
        'rekomendasi_id', 'tindakan_id', 'log_aktivitas_id', 'waktu', 'label_pelaku', 'aksi', 'untuk_peran', 'blok',
    ];

    protected $casts = ['waktu' => 'datetime', 'untuk_peran' => 'array'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Bentuk tindak lanjut disebut hanya bila rekomendasinya punya lebih dari satu. */
    public function tindakan()    { return $this->belongsTo(Tindakan::class); }
    /* Satuan kerja yang bersangkutan (juga disebut pada pemberitahuan untuk peran lain). */
    public function satker()      { return $this->belongsToMany(Satker::class, 'notifikasi_satker'); }
    public function dibaca()      { return $this->belongsToMany(User::class, 'notifikasi_bacas')->withPivot('dibaca_pada'); }
    /* Perubahan sistem (untuk Admin DTI): entri log yang diberitahukan; rekomendasinya kosong. */
    public function log()         { return $this->belongsTo(LogAktivitas::class, 'log_aktivitas_id'); }

    public function sistem(): bool
    {
        return $this->log_aktivitas_id !== null && $this->rekomendasi_id === null;
    }
}
