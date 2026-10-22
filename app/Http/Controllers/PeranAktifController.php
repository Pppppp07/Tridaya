<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Support\Aktivitas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeranAktifController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['peran' => ['required', Rule::in(array_map(fn ($p) => $p->value, $user->semuaPeran()))]]);
        $baru = PeranPengguna::from($data['peran']);
        $lama = $user->peranAktif();
        if ($lama !== $baru) {
            Aktivitas::catat('akun.peran_aktif', 'Beralih peran aktif dari '.$lama->pendek().' ke '.$baru->pendek(), [
                'rincian' => ['sebelum' => ['peran_aktif' => $lama->value], 'sesudah' => ['peran_aktif' => $baru->value]],
            ]);
            $request->session()->put('akses_aktif', ['user' => $user->id, 'peran' => $baru->value]);
            // Formulir dari tab lama tidak boleh bekerja diam-diam dengan peran baru.
            $request->session()->regenerateToken();
        }

        /* Hanya kembali ke pemberitahuan yang membawanya ke sini; alamat lain diabaikan. */
        $kembali = (string) $request->input('kembali');
        $tujuan = preg_match('#^/pemberitahuan/\d+\z#', $kembali) ? url($kembali) : route('beranda');

        return redirect($tujuan)->with('pesan', 'Anda bekerja sebagai '.$baru->pendek().'.');
    }
}
