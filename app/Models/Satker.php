<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Satker extends Model
{
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'nama_pendek', 'provinsi', 'jenis', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    /** Baris penugasan yang jadi tanggungannya. Inilah pintu satu-satunya
        untuk menjawab "apa pekerjaan satuan kerja ini". */
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
     * Penanggung jawabnya: satu-satunya akun satuan kerja yang masih aktif.
     * Menetapkan yang baru menonaktifkan yang lama (App\Support\PenanggungJawab),
     * jadi paling banyak ada satu.
     */
    public function penanggungJawab()
    {
        return $this->hasOne(User::class)
            ->where('peran', PeranPengguna::SATKER->value)->where('aktif', true);
    }

    /**
     * Sudah tertulis di berkas mana pun — sebagai tempat temuan atau sebagai
     * yang ditugasi. Namanya lalu terkunci: berkas lama menyebut nama itu.
     */
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

    /**
     * Nama pendek untuk layar. Nama panjangnya dipakai di surat; singkatannya
     * mengikuti yang memang sudah dipakai di lembar pemantauan mereka.
     */
    public function namaPendek(): string
    {
        return $this->nama_pendek ?: $this->nama;
    }
}
