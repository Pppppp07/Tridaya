<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu berkas: link ke tempat berkasnya disimpan, atau unggahan.
 *
 * Berkas yang sudah tercatat tidak bisa dihapus, ditarik, atau diminta kembali
 * (Hizkia, 14 Sep). Kalau keliru, ia diperbaiki lewat penolakan pemeriksanya:
 * berkasnya pulang untuk pemberkasan ulang, dan berkas yang benar dikirim
 * bersama perbaikannya. Arsip yang lama tetap utuh.
 */
class Lampiran extends Model
{
    protected $fillable = [
        'laporan_id', 'rekomendasi_id', 'sasaran_id', 'tindakan_id',
        'jenis_dokumen_id', 'label_jenis',
        'link', 'nama_asli', 'nama_simpan', 'mime', 'ukuran',
        'diunggah_oleh', 'label_oleh', 'surat_asli', 'diunggah_pada',
    ];

    protected $casts = [
        'diunggah_pada' => 'datetime',
        'surat_asli'    => 'boolean',
    ];

    public function rekomendasi()  { return $this->belongsTo(Rekomendasi::class); }
    /* Sasaran pemilik berkas. Kosong berarti berkas milik rekomendasi atau
       laporan seutuhnya — mis. surat pemeriksaan aslinya, yang justru tidak
       boleh dibuka satuan kerja. */
    public function sasaran()      { return $this->belongsTo(Sasaran::class); }
    public function tindakan()     { return $this->belongsTo(Tindakan::class); }
    public function laporan()      { return $this->belongsTo(Laporan::class); }
    public function jenisDokumen() { return $this->belongsTo(Referensi::class, 'jenis_dokumen_id'); }
    public function butir()        { return $this->belongsToMany(ItemPermintaan::class, 'item_permintaan_lampiran'); }

    public function berupaLink(): bool
    {
        return filled($this->link) && blank($this->nama_simpan);
    }

    public function adaIsinya(): bool
    {
        return filled($this->link) || filled($this->nama_simpan);
    }

    /** Sebutan jenisnya: yang diketik saat diminta, lalu jenis baku. */
    public function jenis(): string
    {
        return (string) ($this->label_jenis ?: $this->jenisDokumen?->nama ?: 'Berkas');
    }

    public function labelTampil(): string
    {
        return (string) ($this->nama_asli ?: $this->link ?: 'berkas tanpa nama');
    }
}
