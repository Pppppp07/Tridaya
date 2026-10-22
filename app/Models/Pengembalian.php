<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Arsip satu pengiriman ulang (pengirim selalu Setba; `dari` = penolaknya). Keadaan yang berlaku
 * ada di `sasarans`; yang di sini tidak pernah dikosongkan.
 */
class Pengembalian extends Model
{
    protected $table = 'pengembalians';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'oleh_id', 'label_oleh', 'dari', 'alasan',
        'batas_waktu', 'keterangan_setba', 'dokumen', 'aksi_id',
    ];

    protected $casts = ['tanggal' => 'date', 'batas_waktu' => 'date', 'dokumen' => 'array'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function oleh()        { return $this->belongsTo(User::class, 'oleh_id'); }
}
