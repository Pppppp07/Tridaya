<?php

use Illuminate\Support\Facades\Schedule;

/* Draf tanggapan yang belum dikirim seminggu dan kewajibannya sudah tuntas dikirim
   sendiri ke Setba, tiap malam. Jalankan `php artisan schedule:work` (atau cron
   `schedule:run`) supaya ini berjalan. */
Schedule::command('tlhp:kirim-draf')->dailyAt('00:30');

/* Log aktivitas yang melewati masa simpannya (SIMTLHP_LOG_SIMPAN_HARI,
   bawaan 5 tahun) dipangkas tiap malam, supaya tabelnya tidak tumbuh tanpa
   batas di server (27 Sep). */
Schedule::command('model:prune', ['--model' => [\App\Models\LogAktivitas::class]])->dailyAt('01:00');
