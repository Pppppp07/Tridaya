<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\AlertKeamanan;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlertKeamananController extends Controller
{
    public function tinjau(Request $request, AlertKeamanan $alert)
    {
        abort_unless($request->user()?->peranAktif() === PeranPengguna::ADMIN && $request->user()?->aktif, 403);
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['wajar', 'perlu_tindak_lanjut', 'selesai'])],
            'catatan' => ['required', 'string', 'max:2000'],
            'bukti_sampai' => ['required', 'integer', 'min:1'],
        ], ['catatan.required' => 'Tuliskan alasan tinjauan agar keputusan dapat ditelusuri.']);
        $catatan = trim($data['catatan']);
        if ($catatan === '') {
            return back()->withErrors(['catatan' => 'Tuliskan alasan tinjauan agar keputusan dapat ditelusuri.']);
        }

        DB::transaction(function () use ($request, $alert, $data, $catatan) {
            /* Peran dan izin mungkin berubah saat panel sedang dibuka. */
            $admin = User::whereKey($request->user()->id)->lockForUpdate()->first();
            abort_unless($admin?->peranAktif() === PeranPengguna::ADMIN && $admin?->aktif, 403);
            $terkunci = AlertKeamanan::whereKey($alert->id)->lockForUpdate()->firstOrFail();
            if ((int) $data['bukti_sampai'] !== (int) ($terkunci->bukti['sampai_log_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'bukti_sampai' => 'Ada bukti baru sejak panel dibuka. Muat ulang dan periksa bukti terbaru sebelum menyimpan tinjauan.',
                ]);
            }
            $terkunci->tinjauan()->create([
                'user_id' => $admin->id, 'nama' => $admin->name, 'peran' => $admin->peranAktif()->value,
                'keputusan' => $data['keputusan'], 'catatan' => $catatan, 'waktu' => Aktivitas::waktuKini(),
            ]);
            $sebelum = $terkunci->status;
            $terkunci->update(['status' => $data['keputusan'], 'kunci_aktif' => null]);
            Aktivitas::catat('keamanan.alert.tinjau', 'Meninjau alert #'.$terkunci->id.': '.$terkunci->statusLabel(), [
                'oleh' => $admin, 'kelompok' => 'akses', 'subjek' => $terkunci,
                'rincian' => ['alert_id' => $terkunci->id, 'sebelum' => ['status' => $sebelum], 'sesudah' => ['status' => $data['keputusan']]],
            ]);
        }, 3);

        return redirect()->route('log', ['tab' => 'alert', 'alert' => $alert->id])
            ->with('pesan', 'Tinjauan tersimpan. Log bukti asli tetap utuh.');
    }
}
