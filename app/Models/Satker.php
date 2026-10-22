<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Satker extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'kode_satker', 'nama', 'nama_pendek', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    /**
     * Baris penugasan yang jadi tanggungannya — pintu satu-satunya untuk pekerjaan satuan kerja
     * ini.
     */
    public function sasaran()
    {
        return $this->hasMany(Sasaran::class);
    }

    /** Temuan yang terjadi di tempatnya — belum tentu jadi pekerjaannya. */
    public function temuan()
    {
        return $this->belongsToMany(Temuan::class, 'temuan_satker');
    }

    public function pengguna()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Penanggung jawab: satu-satunya akun satuan kerja yang aktif (PenanggungJawab menonaktifkan
     * yang lama).
     */
    public function penanggungJawab()
    {
        return $this->hasOne(User::class)
            ->where('peran', PeranPengguna::SATKER->value)->where('aktif', true);
    }

    /** Sudah tertulis di berkas mana pun; namanya lalu terkunci. */
    public function dipakai(): bool
    {
        return $this->sasaran()->exists() || $this->temuan()->exists();
    }

    /** Kode unit kerja baru, dari nama pendeknya. Tidak pernah dipakai dua kali. */
    public static function kodeBaru(string $pendek): string
    {
        $dasar = Str::limit(Str::upper(Str::slug($pendek)), 16, '') ?: 'UNIT';
        $kode = $dasar;
        for ($i = 2; static::where('kode', $kode)->exists(); $i++) {
            $kode = $dasar.'-'.$i;
        }

        return $kode;
    }

    /** Nama pendek untuk layar; nama panjang untuk surat. */
    public function namaPendek(): string
    {
        return $this->nama_pendek ?: $this->nama;
    }
}
