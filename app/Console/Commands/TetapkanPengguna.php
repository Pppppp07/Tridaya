<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use Illuminate\Console\Command;

/**
 * Menetapkan satu pengguna pusat dari direktori menurut NIP — untuk Admin DTI pertama di server
 * baru. Password acak; orangnya masuk lewat SSO. php artisan simtlhp:pengguna 199102242018031006
 * admin
 */
class TetapkanPengguna extends Command
{
    protected $signature = 'simtlhp:pengguna {nip : NIP 18 angka} {peran : setba|uki|inspektorat|pimpinan|dti|admin} {--satker= : ID Satker yang dipimpin, dipisah koma, atau "semua"; wajib untuk peran Pimpinan}';

    protected $description = 'Daftarkan (atau aktifkan kembali) satu pengguna pusat dari direktori pegawai menurut NIP';

    public function handle(): int
    {
        $peran = PeranPengguna::tryFrom((string) $this->argument('peran'));
        if (! $peran || ! in_array($peran, PeranPengguna::pusat(), true)) {
            $this->error('Peran harus salah satu: setba, uki, inspektorat, pimpinan, dti, admin. Penanggung jawab unit kerja ditetapkan lewat Data master.');

            return self::FAILURE;
        }
        $p = DirektoriIrm::nip((string) $this->argument('nip'));
        if (! $p) {
            $this->error(DirektoriIrm::error() ?: 'NIP itu tidak ada di direktori pegawai.');

            return self::FAILURE;
        }

        $akun = User::where('nip', $p['nip'])->first();
        $cakupan = $akun ? self::cakupanAkun($akun) : null;
        if ($peran === PeranPengguna::PIMPINAN) {
            $opsi = mb_strtolower(trim((string) $this->option('satker')));
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $opsi)))));
            $ada = Satker::whereKey($ids)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($opsi !== 'semua' && (! $ids || count($ada) !== count($ids))) {
                $this->error('Pimpinan wajib memiliki cakupan Satker. Sertakan --satker=ID (beberapa dipisah koma) dengan Satker yang terdaftar, atau --satker=semua.');

                return self::FAILURE;
            }
            $cakupan = $opsi === 'semua' ? ['ids' => [], 'semua' => true] : ['ids' => $ada, 'semua' => false];
        } elseif (! $akun?->punyaPeran(PeranPengguna::PIMPINAN)) {
            $cakupan = null;
        }
        if ($akun && $akun->peran === PeranPengguna::SATKER && $akun->aktif) {
            $this->error($p['nama'].' sedang menjadi penanggung jawab unit kerja. Ganti penanggung jawabnya dulu lewat Data master.');

            return self::FAILURE;
        }
        if (User::where('email', $p['email'])->when($akun, fn ($q) => $q->whereKeyNot($akun->id))->exists()) {
            $this->error('Email '.$p['email'].' sudah dipakai akun lain.');

            return self::FAILURE;
        }

        $cakupanLama = $akun ? self::cakupanAkun($akun) : null;
        $sebelum = $akun ? ['peran' => $akun->peran->value, 'aktif' => $akun->aktif, 'satker_pimpinan' => self::sebut($cakupanLama)] : [];
        /* Password acak (masuk lewat SSO); demo boleh memakai SIMTLHP_PASSWORD_DEMO. */
        $akun ??= new User(['password' => Akun::passwordAwal()]);
        $akun->fill([
            'name' => $p['nama'], 'nip' => $p['nip'], 'jabatan' => $p['jabatan'], 'email' => $p['email'],
            'peran' => $peran->value, 'satker_id' => null, 'aktif' => true,
        ])->forceFill(['pimpinan_satker_ids' => $cakupan && ! $cakupan['semua'] ? $cakupan['ids'] : null,
            'pimpinan_semua' => (bool) ($cakupan['semua'] ?? false)])->save();
        if ($sebelum && ($sebelum['peran'] !== $peran->value || $cakupanLama !== $cakupan)) {
            Akun::putuskanSesi($akun);
        }
        Aktivitas::catat('master.pengguna.tambah', 'Menetapkan '.$p['nama'].' sebagai '.$peran->pendek().' lewat perintah server', [
            'oleh' => null, 'nama' => 'Perintah server', 'kelompok' => 'pengguna', 'subjek' => $akun,
            'rincian' => ['sebelum' => $sebelum, 'sesudah' => ['peran' => $peran->value, 'aktif' => true, 'nip' => $p['nip'],
                'satker_pimpinan' => self::sebut($cakupan)]],
        ]);

        $this->info($p['nama'].' ('.$p['email'].') kini '.$peran->pendek().'. Masuk lewat SSO dengan NIP '.$p['nip'].'.');

        return self::SUCCESS;
    }

    /** @return array{ids: list<int>, semua: bool}|null */
    private static function cakupanAkun(User $u): ?array
    {
        $ids = $u->pimpinan_semua ? [] : $u->satkerDipimpin();
        sort($ids);

        return $u->pimpinan_semua || $ids ? ['ids' => $ids, 'semua' => (bool) $u->pimpinan_semua] : null;
    }

    private static function sebut(?array $c): ?string
    {
        return ! $c ? null : ($c['semua'] ? 'Semua Satker'
            : Satker::whereKey($c['ids'])->orderBy('id')->get()->map->namaPendek()->join(', '));
    }
}
