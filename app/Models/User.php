<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nip', 'jabatan', 'peran', 'satker_id', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'peran' => \App\Enums\PeranPengguna::class,
            'aktif' => 'boolean',
            /* Terakhir kali daftar pemberitahuannya dibuka (26 Sep) — pemberitahuan
               yang lebih baru dan belum dibaca itulah yang BARU. */
            'notifikasi_dilihat_pada' => 'datetime',
            /* Terakhir berhasil masuk (27 Sep) — Pengguna dan Keaktifan akun.
               Tanpa cast ini nilainya teks, dan Keaktifan akun error begitu
               ada akun yang pernah masuk. */
            'terakhir_masuk_pada' => 'datetime',
            /* Pengaturan akun sendiri (28 Sep) — lihat App\Support\Setelan. */
            'pengaturan' => 'array',
        ];
    }

    /**
     * Satu pengaturan akun ini: pilihannya sendiri, atau bawaan perannya.
     * Kuncinya: popup, email, animasi, beranda, tema, menu (App\Support\Setelan).
     */
    public function setelan(string $kunci): mixed
    {
        return \App\Support\Setelan::untuk($this)[$kunci] ?? null;
    }

    public function satker()
    {
        return $this->belongsTo(Satker::class);
    }

    /* Peran menentukan wewenang, dan hanya Setba yang boleh mengubah status —
       itu pun hanya dengan menyalin isi surat verifikasi bernomor. */
    public function bolehUbahStatus(): bool
    {
        return $this->peran?->bolehUbahStatus() ?? false;
    }
}
