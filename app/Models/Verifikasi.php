<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Surat hasil bernomor: LHV (UKI), CHV (Inspektorat). Satu nomor boleh memutus beberapa baris dan
 * rekomendasi.
 */
class Verifikasi extends Model
{
    public const LHV = 'LHV';
    public const CHV = 'CHV';

    protected $fillable = [
        'jenis', 'nomor_surat', 'tgl_surat', 'perihal', 'pejabat',
        'nomor_lhv', 'tgl_lhv', 'lampiran_id', 'dicatat_oleh',
    ];

    protected $casts = ['tgl_surat' => 'date', 'tgl_lhv' => 'date'];

    public function scopeChv($q) { return $q->where('jenis', self::CHV); }
    public function scopeLhv($q) { return $q->where('jenis', self::LHV); }

    public function namaJenis(): string
    {
        return $this->jenis === self::LHV
            ? 'Laporan Hasil Validasi'
            : 'Catatan Hasil Verifikasi';
    }

    public function keputusan() { return $this->hasMany(KeputusanVerifikasi::class); }
    public function lampiran()  { return $this->belongsTo(Lampiran::class); }
    public function pencatat()  { return $this->belongsTo(User::class, 'dicatat_oleh'); }
}
