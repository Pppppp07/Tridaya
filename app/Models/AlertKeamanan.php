<?php

namespace App\Models;

use App\Support\Aktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Indikasi untuk ditinjau manusia, bukan putusan tentang niat atau pelanggaran. */
class AlertKeamanan extends Model
{
    protected $table = 'alert_keamanan';

    protected $guarded = ['id'];

    public function freshTimestamp()
    {
        return Aktivitas::waktuKini();
    }

    protected function casts(): array
    {
        return ['pertama_pada' => 'datetime', 'terakhir_pada' => 'datetime', 'bukti' => 'array'];
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tinjauan()
    {
        return $this->hasMany(TinjauanAlert::class)->orderByDesc('id');
    }

    public function logs()
    {
        return $this->belongsToMany(LogAktivitas::class, 'alert_keamanan_log', 'alert_keamanan_id', 'log_aktivitas_id');
    }

    /** Log asli dapat dipangkas menurut retensi; snapshot fakta tetap tersedia. */
    public function logBukti(): Collection
    {
        return $this->logs()->orderByDesc('waktu')->orderByDesc('log_aktivitas.id')->get();
    }

    public function tingkatLabel(): string
    {
        return $this->tingkat === 'tinggi' ? 'Prioritas tinggi' : 'Perhatian';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'wajar' => 'Dinilai wajar',
            'perlu_tindak_lanjut' => 'Perlu tindak lanjut',
            'selesai' => 'Selesai ditinjau',
            default => 'Belum ditinjau',
        };
    }

    public function aturanLabel(): string
    {
        return match ($this->aturan) {
            'masuk_gagal_berulang' => 'Gagal masuk berulang',
            'akses_ditolak_berulang' => 'Akses ditolak berulang',
            'masuk_dikunci' => 'Pembatasan percobaan masuk',
            'perubahan_peran' => 'Perubahan hak akses',
            'peran_penting_diberikan' => 'Pemberian peran penting',
            'izin_peran_penting_berubah' => 'Izin masuk peran penting berubah',
            'perintah_server' => 'Administrasi melalui perintah server',
            default => $this->aturan,
        };
    }

    public function sumberLabel(): string
    {
        return $this->sumber === 'perintah_server' ? 'Perintah server' : ($this->sumber === 'web' ? 'Aplikasi web' : 'Sumber tidak tercatat');
    }
}
