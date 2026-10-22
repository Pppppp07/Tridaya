<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bentuk tindak lanjut yang dituntut rekomendasi; tiap bentuk bisa dibebankan ke satuan kerja
 * berbeda dengan rencana aksi dan dokumennya sendiri.
 */
class Tindakan extends Model
{
    protected $fillable = [
        'rekomendasi_id', 'bentuk_id', 'bentuk_teks', 'keterangan_paket', 'urutan',
        'tgl_renaksi', 'klasifikasi_renaksi', 'target_selesai', 'catatan', 'dokumen',
    ];

    protected $casts = [
        'tgl_renaksi'    => 'date',
        'target_selesai' => 'date',
        'dokumen'        => 'array',
    ];

    public function rekomendasi() { return $this->belongsTo(Rekomendasi::class); }
    public function bentuk()      { return $this->belongsTo(Referensi::class, 'bentuk_id'); }
    public function sasaran()     { return $this->hasMany(Sasaran::class)->orderBy('id'); }

    /** Rencana aksi bentuk ini; kosong = ikut tanggal rekomendasinya. */
    public function renaksi(): ?\Carbon\CarbonInterface
    {
        return $this->tgl_renaksi ?? $this->rekomendasi?->tenggat_jawab;
    }

    /** Nama bentuknya; data historis di luar daftar memakai teks aslinya; sebutan baku bila kosong. */
    public function namaBentuk(): string
    {
        return $this->bentuk?->nama ?? ($this->bentuk_teks ?: 'Tindak lanjut');
    }

    public function totalNilai(): int
    {
        return (int) $this->sasaran->sum('nilai');
    }
}
