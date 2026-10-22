<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Support\Setelan;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nip', 'jabatan', 'peran', 'satker_id', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'peran' => PeranPengguna::class,
            'aktif' => 'boolean',
            /* Terakhir daftar pemberitahuan dibuka; yang lebih baru dan belum dibaca = BARU. */
            'notifikasi_dilihat_pada' => 'datetime',
            /* Cast tanggal: tanpa ini nilainya teks dan Keaktifan akun error. */
            'terakhir_masuk_pada' => 'datetime',
            /* Pengaturan akun sendiri (App\Support\Setelan). */
            'pengaturan' => 'array',
            'peran_tambahan' => 'array',
            'versi_akses' => 'integer',
            'pimpinan_satker_ids' => 'array',
            'pimpinan_semua' => 'boolean',
        ];
    }

    /** Satu pengaturan akun: pilihannya sendiri atau bawaan peran. */
    public function setelan(string $kunci): mixed
    {
        return Setelan::untuk($this)[$kunci] ?? null;
    }

    public function satker()
    {
        return $this->belongsTo(Satker::class);
    }

    /**
     * Satker yang dipimpin (id). "Semua Satker" dibaca dari data master saat ini, jadi Satker baru
     * ikut. Kosong = belum ditetapkan, bukan semua.
     *
     * @return list<int>
     */
    public function satkerDipimpin(): array
    {
        if ($this->pimpinan_semua) {
            return Satker::orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) ($this->pimpinan_satker_ids ?? [])))));
    }

    /** @return \Illuminate\Support\Collection<int, Satker> */
    public function satkerDipimpinModel(): \Illuminate\Support\Collection
    {
        $id = $this->satkerDipimpin();

        return $id ? Satker::whereKey($id)->orderBy('id')->get() : collect();
    }

    /** Isian formulir cakupan pimpinan. @return array{ids: list<int>, semua: bool} */
    public function cakupanPimpinan(): array
    {
        return ['ids' => $this->pimpinan_semua ? [] : $this->satkerDipimpin(), 'semua' => (bool) $this->pimpinan_semua];
    }

    /** "Semua Satker" atau nama Satker-nya berderet (untuk spanduk, rincian akun, pencarian). */
    public function daftarSatkerDipimpin(bool $panjang = false): string
    {
        if ($this->pimpinan_semua) {
            return 'Semua Satker';
        }

        return $this->satkerDipimpinModel()->map(fn ($s) => $panjang ? $s->nama : $s->namaPendek())->join(', ');
    }

    /** "Semua Satker", nama Satker-nya, atau "3 Satker". */
    public function sebutSatkerDipimpin(): string
    {
        if ($this->pimpinan_semua) {
            return 'Semua Satker';
        }
        $d = $this->satkerDipimpinModel();

        return match ($d->count()) {
            0 => 'Satker belum ditentukan',
            1 => $d->first()->namaPendek(),
            default => $d->count().' Satker',
        };
    }

    /**
     * Cakupan mengikuti satu peran aktif, bukan gabungan semua izin user. Satu Satker = angka (sama
     * seperti akun satuan kerja); pimpinan beberapa Satker = daftar.
     *
     * @return int|list<int>|null
     */
    public function satkerAktifId(): int|array|null
    {
        if ($this->peranAktif() === PeranPengguna::PIMPINAN) {
            $id = $this->satkerDipimpin();

            return count($id) === 1 ? $id[0] : $id;
        }

        return $this->peranAktif() === PeranPengguna::SATKER ? $this->satker_id : null;
    }

    /** Satker dalam cakupan peran aktif. @return \Illuminate\Support\Collection<int, Satker> */
    public function satkerAktifModel(): \Illuminate\Support\Collection
    {
        return match ($this->peranAktif()) {
            PeranPengguna::SATKER => collect([$this->satker])->filter()->values(),
            PeranPengguna::PIMPINAN => $this->satkerDipimpinModel(),
            default => collect(),
        };
    }

    /** Semua peran yang ditetapkan admin, bukan gabungan hak saat bekerja. */
    public function semuaPeran(): array
    {
        $peran = [$this->peran];
        foreach ($this->peran_tambahan ?? [] as $nilai) {
            $p = is_string($nilai) ? PeranPengguna::tryFrom($nilai) : null;
            // Satker hanya lahir dari penetapan PJ dengan cakupan unit yang jelas.
            if ($p && $p !== PeranPengguna::SATKER && ! in_array($p, $peran, true)) {
                $peran[] = $p;
            }
        }

        return $peran;
    }

    public function punyaPeran(PeranPengguna|string $peran): bool
    {
        $p = is_string($peran) ? PeranPengguna::tryFrom($peran) : $peran;

        return $p && in_array($p, $this->semuaPeran(), true);
    }

    /** Pilihan per sesi. Membaca peran aktif tidak pernah menulis users.peran. */
    public function peranAktif(): PeranPengguna
    {
        $request = app()->bound('request') ? request() : null;
        if ($request?->hasSession() && (int) $request->session()->get('akses_aktif.user') === (int) $this->id) {
            $p = PeranPengguna::tryFrom((string) $request->session()->get('akses_aktif.peran', ''));
            if ($p && $this->punyaPeran($p)) {
                return $p;
            }
        }

        return $this->peran;
    }

    /** Anggota peran termasuk peran tambahan, dengan OR yang tetap terkurung. */
    public function scopeDenganPeran(Builder $query, PeranPengguna|string|array $peran): Builder
    {
        $nilai = array_map(fn ($p) => $p instanceof PeranPengguna ? $p->value : $p, is_array($peran) ? $peran : [$peran]);

        return $query->where(function (Builder $q) use ($nilai) {
            $q->whereIn('peran', $nilai);
            foreach ($nilai as $p) {
                if ($p !== PeranPengguna::SATKER->value) {
                    $q->orWhereJsonContains('peran_tambahan', $p);
                }
            }
        });
    }

    public function bolehUbahStatus(): bool
    {
        return $this->peranAktif()->bolehUbahStatus();
    }
}
