<?php

use Illuminate\Support\Facades\Schedule;

/* Pangkas log aktivitas yang lewat masa simpan (SIMTLHP_LOG_SIMPAN_HARI). Butuh `schedule:work`
   atau cron `schedule:run`. */
Schedule::command('model:prune', ['--model' => [\App\Models\LogAktivitas::class]])->dailyAt('01:00');

/* Pekerja antrean untuk server tanpa supervisor: tiap menit mengosongkan antrean lalu berhenti, tidak
   pernah dua sekaligus. Email dikirim berurutan dengan batas per menit (PemberitahuanEmail). */
if (config('simtlhp.beban.antrean_lewat_jadwal') && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --queue=email,default --stop-when-empty --max-time=55 --sleep=3 --memory=128')
        ->everyMinute()->withoutOverlapping(5)->runInBackground();
}
/* Pekerjaan gagal disimpan seminggu untuk diperiksa, lalu dibuang. */
Schedule::command('queue:prune-failed', ['--hours' => 168])->dailyAt('01:30');
