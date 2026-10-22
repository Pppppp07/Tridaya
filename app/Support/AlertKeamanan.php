<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\AlertKeamanan as Alert;
use App\Models\LogAktivitas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan kecil atas fakta yang memang dicatat aplikasi. Tidak membaca isi
 * request, tidak memperkirakan niat, dan tidak memeriksa semua log lama.
 */
class AlertKeamanan
{
    public const JENDELA_MENIT = 10;

    public const BATAS_BUKTI = 100;

    public static function mulaiPada(): ?Carbon
    {
        if (! Schema::hasTable('pemantauan_keamanan')) {
            return null;
        }
        $mulai = DB::table('pemantauan_keamanan')->where('id', 1)->value('mulai_pada');

        return $mulai ? Carbon::parse($mulai) : null;
    }

    public static function proses(LogAktivitas $log): void
    {
        /* Jalur normal tidak menambah query untuk setiap perubahan berkas. */
        if (! in_array($log->aksi, ['masuk.gagal', 'masuk.dikunci', 'akses.ditolak', 'bukti.link.ditolak', 'master.pengguna.peran', 'master.pengguna.tambah', 'master.pengguna.saklar'], true)
            && ! (str_starts_with($log->aksi, 'master.') && ($log->rincian['konteks']['sumber'] ?? null) === 'perintah_server')) {
            return;
        }
        $mulai = self::mulaiPada();
        if (! $mulai || $log->waktu->lt($mulai)) {
            return;
        }

        DB::transaction(function () use ($log, $mulai) {
            if ($log->aksi === 'bukti.link.ditolak' && $log->user_id) {
                self::berulang($log, $mulai, 'link_bukti_ditolak_berulang', 'user:'.$log->user_id, 5, 'perhatian',
                    'Link bukti ditolak berulang',
                    'Sedikitnya 5 kiriman link dari user yang sama ditolak dalam 10 menit. Periksa sumber link dan alasan penolakan; salah menempelkan link tidak otomatis berarti pelanggaran.',
                    fn (Builder $q) => $q->where('user_id', $log->user_id));
            } elseif ($log->aksi === 'masuk.gagal' && $log->ip) {
                self::berulang($log, $mulai, 'masuk_gagal_berulang', 'ip:'.$log->ip, 5, 'tinggi',
                    'Percobaan masuk gagal berulang',
                    'Sedikitnya 5 percobaan masuk dengan password gagal dari alamat IP yang sama dalam 10 menit. IP dapat dipakai bersama; periksa bukti sebelum menentukan tindak lanjut.',
                    fn (Builder $q) => $q->where('ip', $log->ip));
            } elseif ($log->aksi === 'akses.ditolak' && $log->user_id) {
                self::berulang($log, $mulai, 'akses_ditolak_berulang', 'user:'.$log->user_id, 3, 'perhatian',
                    'Akses yang ditolak berulang',
                    'Sedikitnya 3 permintaan dari akun yang sama ditolak dalam 10 menit. Ini dapat berasal dari tautan lama atau upaya mengakses di luar izin; periksa alamat dan metode permintaannya.',
                    fn (Builder $q) => $q->where('user_id', $log->user_id));
            } elseif ($log->aksi === 'masuk.dikunci') {
                self::berulang($log, $mulai, 'masuk_dikunci', 'ip:'.$log->ip.'|nama:'.$log->nama, 1, 'tinggi',
                    'Percobaan masuk dibatasi sementara',
                    'Aplikasi telah membatasi percobaan masuk setelah beberapa kegagalan. Pembatasan ini adalah fakta teknis, bukan bukti bahwa pemilik akun melakukan pelanggaran.',
                    fn (Builder $q) => $q->where('ip', $log->ip)->where('nama', $log->nama));
            }

            $sebelum = $log->rincian['sebelum']['peran'] ?? null;
            $sesudah = $log->rincian['sesudah']['peran'] ?? null;
            $peranTarget = $log->rincian['peran_target'] ?? $sesudah;
            $aktifSebelum = $log->rincian['sebelum']['aktif'] ?? null;
            $aktifSesudah = $log->rincian['sesudah']['aktif'] ?? null;
            $peransSebelum = $log->rincian['sebelum']['perans'] ?? ($sebelum ? [$sebelum] : []);
            $peransSesudah = $log->rincian['sesudah']['perans'] ?? ($sesudah ? [$sesudah] : []);
            $peransTarget = $log->rincian['perans_target'] ?? ($peransSesudah ?: ($peranTarget ? [$peranTarget] : []));
            $peranPenting = in_array('admin', $peransTarget, true) ? 'admin' : (in_array('setba', $peransTarget, true) ? 'setba' : null);
            if ($log->aksi === 'master.pengguna.peran' && isset($log->rincian['sesudah']['perans']) && $peransSebelum !== $peransSesudah) {
                self::peristiwa($log, 'perubahan_peran', in_array('admin', array_diff($peransSesudah, $peransSebelum), true) ? 'tinggi' : 'perhatian',
                    'Hak akses akun berubah', 'Daftar peran user berubah. Cocokkan peran yang ditambah atau dicabut dengan penugasan yang disetujui.',
                    ['peran_sebelum' => $peransSebelum, 'peran_sesudah' => $peransSesudah]);
            } elseif ($log->aksi === 'master.pengguna.tambah' && isset($log->rincian['sesudah']['perans'])
                && array_intersect(['admin', 'setba'], array_diff($peransSesudah, $peransSebelum))) {
                self::peristiwa($log, 'peran_penting_diberikan', in_array('admin', $peransSesudah, true) ? 'tinggi' : 'perhatian',
                    'Akun dengan peran penting diberikan', 'User diberikan peran penting. Pastikan penerima dan seluruh perannya sesuai penugasan.',
                    ['peran_sesudah' => $peransSesudah]);
            } elseif (in_array($log->aksi, ['master.pengguna.peran', 'master.pengguna.tambah'], true)
                && $sebelum !== null && $sesudah !== null && $sebelum !== $sesudah) {
                self::peristiwa($log, 'perubahan_peran', $sesudah === 'admin' ? 'tinggi' : 'perhatian',
                    'Hak akses akun berubah',
                    'Peran akun berubah dari '.self::peran($sebelum).' menjadi '.self::peran($sesudah).'. Cocokkan perubahan ini dengan penugasan yang disetujui.',
                    ['peran_sebelum' => $sebelum, 'peran_sesudah' => $sesudah]);
            } elseif ($log->aksi === 'master.pengguna.tambah' && $sebelum === null && in_array($sesudah, ['admin', 'setba'], true)) {
                self::peristiwa($log, 'peran_penting_diberikan', $sesudah === 'admin' ? 'tinggi' : 'perhatian',
                    'Akun dengan peran penting diberikan',
                    'Akun diberikan peran '.self::peran($sesudah).'. Penambahan ini dapat merupakan administrasi resmi; pastikan penerima dan penugasannya sesuai.',
                    ['peran_sesudah' => $sesudah]);
            } elseif (in_array($log->aksi, ['master.pengguna.tambah', 'master.pengguna.saklar'], true)
                && $peranPenting !== null
                && is_bool($aktifSebelum) && is_bool($aktifSesudah) && $aktifSebelum !== $aktifSesudah) {
                self::peristiwa($log, 'izin_peran_penting_berubah', $peranPenting === 'admin' && $aktifSesudah ? 'tinggi' : 'perhatian',
                    'Izin masuk akun berperan penting berubah',
                    'Izin masuk akun berperan '.self::peran($peranPenting).' '.($aktifSesudah ? 'diaktifkan kembali' : 'dinonaktifkan').'. Perubahan ini dapat merupakan administrasi resmi; cocokkan dengan persetujuan dan kebutuhan penugasannya.',
                    ['peran_target' => $peranPenting, 'perans_target' => $peransTarget, 'aktif_sebelum' => $aktifSebelum, 'aktif_sesudah' => $aktifSesudah]);
            } elseif (str_starts_with($log->aksi, 'master.') && self::sumber($log) === 'perintah_server') {
                self::peristiwa($log, 'perintah_server', 'perhatian', 'Administrasi melalui perintah server',
                    'Perubahan administrasi berasal dari perintah server. Perintah dapat merupakan pemasangan atau pemeliharaan resmi. Log aplikasi tidak mengidentifikasi operator sistem operasi; cocokkan dengan catatan pemeliharaan.');
            }
        }, 3);
    }

    private static function berulang(LogAktivitas $log, Carbon $mulai, string $aturan, string $identitas, int $batas, string $tingkat, string $judul, string $alasan, callable $saring): void
    {
        $cakupan = hash('sha256', $aturan.'|'.$identitas);
        $dari = $log->waktu->copy()->subMinutes(self::JENDELA_MENIT)->max($mulai);
        /* Tinjauan dan penambahan bukti memakai kunci baris yang sama, jadi pembacaan terkunci
           melihat keputusan terbaru. */
        Alert::where('cakupan', $cakupan)->latest('id')->lockForUpdate()->first();
        $q = LogAktivitas::where('aksi', $log->aksi)->whereBetween('waktu', [$dari, $log->waktu])->where('id', '<=', $log->id);
        $saring($q);
        /* Sesudah ditinjau, bukti lama tidak menghidupkan alert lagi; insiden baru butuh bukti
           baru. */
        $ditinjau = Alert::where('cakupan', $cakupan)->where('status', '!=', 'baru')->latest('id')->lockForUpdate()->first();
        if ($ditinjau) {
            $q->where('id', '>', $ditinjau->bukti['sampai_log_id'] ?? 0);
        }
        $jumlah = (clone $q)->count();
        if ($jumlah < $batas) {
            return;
        }
        $pertama = (clone $q)->min('waktu');
        $ids = (clone $q)->orderByDesc('id')->limit(self::BATAS_BUKTI)->pluck('id')->reverse()->values()->all();
        $nilai = self::snapshot($log, $aturan, $tingkat, $judul, $alasan, [
            'log_ids' => $ids, 'jumlah' => $jumlah, 'batas' => $batas,
            'jendela_menit' => self::JENDELA_MENIT, 'sampai_log_id' => $log->id,
            'bukti_terpotong' => $jumlah > self::BATAS_BUKTI,
        ]);
        $nilai['pertama_pada'] = $pertama;
        $nilai['cakupan'] = $cakupan;
        /* Unique key menutup perlombaan dua request pada ambang yang sama. */
        $aktif = Alert::where('kunci_aktif', $cakupan)->lockForUpdate()->first();
        if ($aktif && $aktif->pertama_pada->lt($dari)) {
            $aktif->update(['kunci_aktif' => null]);
            $aktif = null;
        }
        $aktif ??= Alert::firstOrCreate(['kunci_aktif' => $cakupan], $nilai);
        $aktif = Alert::whereKey($aktif->id)->lockForUpdate()->firstOrFail();
        if ($aktif->status === 'baru' && ($aktif->bukti['sampai_log_id'] ?? 0) <= $log->id) {
            $aktif->update($nilai);
            $aktif->logs()->sync($ids);
        }
    }

    private static function peristiwa(LogAktivitas $log, string $aturan, string $tingkat, string $judul, string $alasan, array $tambahan = []): void
    {
        $kunci = hash('sha256', $aturan.'|log:'.$log->id);
        $alert = Alert::firstOrCreate(['kunci_peristiwa' => $kunci], self::snapshot($log, $aturan, $tingkat, $judul, $alasan, [
            'log_ids' => [$log->id], 'jumlah' => 1, 'sampai_log_id' => $log->id, 'aksi' => $log->aksi,
        ] + $tambahan) + ['cakupan' => $kunci]);
        $alert->logs()->sync([$log->id]);
    }

    private static function snapshot(LogAktivitas $log, string $aturan, string $tingkat, string $judul, string $alasan, array $bukti): array
    {
        return [
            'aturan' => $aturan, 'tingkat' => $tingkat, 'judul' => $judul, 'alasan' => $alasan, 'status' => 'baru',
            'pertama_pada' => $log->waktu, 'terakhir_pada' => $log->waktu,
            'user_id' => $log->user_id, 'nama' => $log->nama, 'peran' => $log->peran,
            'ip' => $log->ip, 'sumber' => self::sumber($log), 'bukti' => $bukti,
        ];
    }

    private static function sumber(LogAktivitas $log): string
    {
        return $log->rincian['konteks']['sumber'] ?? 'tidak_tercatat';
    }

    private static function peran(string $peran): string
    {
        return PeranPengguna::tryFrom($peran)?->pendek() ?? $peran;
    }
}
