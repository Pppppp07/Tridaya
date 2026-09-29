<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Surat pengantar Setba saat meneruskan berkas satu baris penugasan: ke UKI
 * untuk validasi, ke Inspektorat untuk verifikasi.
 */
class Surat extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'dari', 'ke', 'nomor',
        'tanggal', 'tanggal_catat', 'perihal', 'catatan', 'link',
        'lampiran_id', 'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal'       => 'date',
        'tanggal_catat' => 'date',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function lampiran()    { return $this->belongsTo(Lampiran::class); }
    public function pencatat()    { return $this->belongsTo(User::class, 'dicatat_oleh'); }

    public function ringkas(): string
    {
        return trim($this->dari.' → '.$this->ke);
    }
}
