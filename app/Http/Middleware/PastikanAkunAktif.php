<?php

namespace App\Http\Middleware;

use App\Support\Aktivitas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun nonaktif atau yang hak aksesnya berubah keluar di permintaan berikutnya. */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        $izin = $request->session()->get('izin_akun');
        $berubah = $u && $izin && (int) $izin['user'] === (int) $u->id
            && (int) $izin['versi'] !== (int) ($u->versi_akses ?? 1);
        if ($u && (! $u->aktif || $berubah)) {
            /* Sesi lama bisa tertinggal pada penyimpan selain database. */
            try {
                Aktivitas::catat($berubah ? 'akses.izin_berubah' : 'akses.akun_nonaktif', $berubah ? 'Sesi akun diakhiri karena hak akses berubah' : 'Sesi akun diakhiri karena izin masuk sudah dinonaktifkan', [
                    'oleh' => $u,
                    'subjek' => $u,
                    'rincian' => ['hasil' => 'sesi diakhiri', 'alasan' => $berubah ? 'hak akses berubah' : 'izin masuk nonaktif'],
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('masuk')->withErrors([
                'email' => $u->aktif ? 'Hak akses Anda berubah. Silakan masuk ulang.' : 'Izin masuk akun ini sudah dinonaktifkan. Hubungi Admin DTI untuk informasi atau pengaktifan kembali.',
            ]);
        }

        if ($u && (! $izin || (int) $izin['user'] !== (int) $u->id)) {
            $request->session()->put('izin_akun', ['user' => $u->id, 'versi' => $u->versi_akses ?? 1]);
        }

        return $next($request);
    }
}
