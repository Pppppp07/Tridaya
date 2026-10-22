<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use Illuminate\Database\Eloquent\Model;

/**
 * Putusan UKI atau Inspektorat atas satu baris. `label_oleh` = yang memutus, `label_pencatat` =
 * yang mengetik.
 */
class Telaah extends Model
{
    protected $table = 'telaahs';

    protected $fillable = [
        'rekomendasi_id', 'sasaran_id', 'tanggal', 'oleh_id', 'label_oleh', 'label_pencatat',
        'hasil', 'nomor_surat', 'tgl_surat', 'perihal', 'catatan', 'lampiran_id',
        'batas_waktu', 'dokumen', 'aksi_id',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'tgl_surat'   => 'date',
        'batas_waktu' => 'date',
        'dokumen'     => 'array',
        'hasil'       => HasilTelaah::class,
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function sasaran()     { return $this->belongsTo(Sasaran::class); }
    public function oleh()        { return $this->belongsTo(User::class, 'oleh_id'); }
    public function lampiran()    { return $this->belongsTo(Lampiran::class); }

    public function bersurat(): bool
    {
        return filled($this->nomor_surat);
    }
}
