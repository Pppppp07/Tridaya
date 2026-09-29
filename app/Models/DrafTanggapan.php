<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Isian tindak lanjut yang disimpan satuan kerja tanpa dikirim.
 *
 * Berkasnya tetap di meja satuan kerja. Draf yang belum dikirim lebih dari
 * seminggu dan kewajibannya sudah terpenuhi dikirim sendiri oleh perintah
 * terjadwal `tlhp:kirim-draf` — pemulihan bisa bertahun-tahun, tapi berkas
 * yang sudah lengkap tidak boleh tertahan hanya karena lupa ditekan kirim.
 */
class DrafTanggapan extends Model
{
    protected $fillable = ['sasaran_id', 'uraian', 'tanggal', 'bukti', 'setoran', 'penuhi', 'kali', 'terakhir'];

    protected $casts = [
        'tanggal'  => 'date',
        'terakhir' => 'date',
        'bukti'    => 'array',
        'setoran'  => 'array',
        'penuhi'   => 'array',
    ];

    public function sasaran() { return $this->belongsTo(Sasaran::class); }
}
