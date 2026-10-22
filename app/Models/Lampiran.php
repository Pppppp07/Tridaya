<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu berkas (link). Berkas yang sudah tercatat tidak bisa dihapus atau ditarik; kekeliruan
 * diperbaiki lewat penolakan pemeriksanya.
 */
class Lampiran extends Model
{
    protected $fillable = [
        'laporan_id', 'rekomendasi_id', 'sasaran_id', 'tindakan_id',
        'label_jenis', 'link', 'nama_asli',
        'diunggah_oleh', 'label_oleh', 'surat_asli', 'diunggah_pada',
        'ditolak_pada', 'ditolak_oleh', 'alasan_penolakan',
    ];

    protected $casts = [
        'diunggah_pada' => 'datetime',
        'surat_asli' => 'boolean',
        'ditolak_pada' => 'datetime',
    ];

    public function rekomendasi()
    {
        return $this->belongsTo(Rekomendasi::class);
    }

    /* Kosong = berkas milik rekomendasi atau laporan seutuhnya (mis. surat pemeriksaan asli,
       tidak untuk satuan kerja). */
    public function sasaran()
    {
        return $this->belongsTo(Sasaran::class);
    }

    public function tindakan()
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function laporan()
    {
        return $this->belongsTo(Laporan::class);
    }

    public function butir()
    {
        return $this->belongsToMany(ItemPermintaan::class, 'item_permintaan_lampiran');
    }

    /* Bukti berupa link; tidak ada berkas yang disimpan di server. */
    public function berupaLink(): bool
    {
        return filled($this->link);
    }

    public function adaIsinya(): bool
    {
        return filled($this->link);
    }

    /** Sebutan jenisnya: yang diketik atau diminta saat dicatat. */
    public function jenis(): string
    {
        return (string) ($this->label_jenis ?: 'Berkas');
    }

    public function labelTampil(): string
    {
        return (string) ($this->nama_asli ?: $this->link ?: 'berkas tanpa nama');
    }
}
