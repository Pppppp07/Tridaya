<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan tinjauan hanya ditambahkan; keputusan lama tetap dapat ditelusuri. */
class TinjauanAlert extends Model
{
    protected $table = 'tinjauan_alert';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function alert()
    {
        return $this->belongsTo(AlertKeamanan::class, 'alert_keamanan_id');
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
