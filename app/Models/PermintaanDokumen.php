<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumen yang diminta Setba dari satu satuan kerja pada satu bentuk tindak lanjut; `dari` =
 * penolakan yang melahirkannya.
 */
class PermintaanDokumen extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'peran_peminta', 'dari', 'diminta_oleh', 'tanggal', 'catatan', 'alasan',
    ];

    protected $casts = ['peran_peminta' => PeranPengguna::class, 'tanggal' => 'date'];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    /* Permintaan selalu untuk satu satuan kerja. */
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function item()        { return $this->hasMany(ItemPermintaan::class)->orderBy('id'); }
}
