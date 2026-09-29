<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris log aktivitas (27 Sep). Hanya bertambah — ditulis lewat
 * App\Support\Aktivitas, tidak pernah diubah. Yang terlalu tua dipangkas
 * `php artisan model:prune` (terjadwal harian, routes/console.php) supaya
 * tabelnya tidak tumbuh tanpa batas di server.
 */
class LogAktivitas extends Model
{
    use MassPrunable;

    protected $table = 'log_aktivitas';

    public $timestamps = false;

    protected $fillable = [
        'waktu', 'user_id', 'nama', 'peran', 'satker_id', 'aksi', 'kelompok', 'ringkasan',
        'subjek_tipe', 'subjek_id', 'rincian', 'ip', 'agen',
    ];

    protected function casts(): array
    {
        return [
            'waktu'   => 'datetime',
            'rincian' => 'array',
        ];
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function satker()
    {
        return $this->belongsTo(Satker::class);
    }

    /** Baris yang sudah melewati masa simpannya. */
    public function prunable(): Builder
    {
        return static::where('waktu', '<', now()->subDays(max(30, (int) config('simtlhp.log.simpan_hari', 1825))));
    }

    /** Sebutan peran pelakunya saat itu — "Setba", "DTI", "Satuan kerja". */
    public function sebutanPeran(): string
    {
        return \App\Enums\PeranPengguna::tryFrom((string) $this->peran)?->pendek() ?? '—';
    }
}
