<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SumberBukti extends Model
{
    protected $table = 'sumber_bukti';

    protected $fillable = ['nama', 'host', 'pola', 'prefix', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];
}
