<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Isian tindak lanjut yang disimpan sementara tanpa dikirim. Kemajuannya terbaca Setba sebagai
 * pantauan; tidak pernah dikirim sendiri.
 */
class DrafTanggapan extends Model
{
    protected $fillable = ['sasaran_id', 'uraian', 'tanggal', 'bukti', 'setoran', 'penuhi', 'kali', 'terakhir', 'disimpan_oleh'];

    protected $casts = [
        'tanggal'  => 'date',
        'terakhir' => 'date',
        'bukti'    => 'array',
        'setoran'  => 'array',
        'penuhi'   => 'array',
    ];

    public function sasaran() { return $this->belongsTo(Sasaran::class); }

    public function penyimpan() { return $this->belongsTo(User::class, 'disimpan_oleh'); }
}
