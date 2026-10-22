<?php

namespace App\Http\Controllers;

use App\Models\SumberBukti;
use App\Support\Aktivitas;
use App\Support\KebijakanBukti;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SumberBuktiController extends Controller
{
    public function simpan(Request $req, ?SumberBukti $sumber = null)
    {
        abort_unless($req->user()->peranAktif()->kelolaMaster(), 403);
        $data = $req->validate([
            'nama' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:253', Rule::unique('sumber_bukti', 'host')->ignore($sumber?->id)],
            'prefix' => ['nullable', 'string', 'max:200'],
            'aktif' => ['required', 'boolean'],
        ]);
        $data['host'] = strtolower(trim($data['host']));
        if (! KebijakanBukti::hostSah($data['host'])) {
            throw ValidationException::withMessages(['host' => 'Isi satu hostname lengkap tanpa https://, wildcard, alamat IP, atau path.']);
        }
        $data['pola'] = match ($data['host']) {
            'drive.google.com' => 'drive', 'docs.google.com' => 'docs', default => 'repositori',
        };
        $data['prefix'] = $data['pola'] === 'repositori' ? trim($data['prefix'] ?? '') : '/';
        if ($data['pola'] === 'repositori' && (! preg_match('~^/(?:[A-Za-z0-9_-]+/)+$~D', $data['prefix']))) {
            throw ValidationException::withMessages(['prefix' => 'Batasi ke jalur berkas, misalnya /dokumen/ atau /arsip/bukti/. Halaman utama tidak boleh diizinkan.']);
        }
        /* Validasi unique diulang setelah normalisasi huruf/spasi. */
        if (SumberBukti::where('host', $data['host'])->when($sumber, fn ($q) => $q->whereKeyNot($sumber->id))->exists()) {
            throw ValidationException::withMessages(['host' => 'Domain ini sudah terdaftar.']);
        }
        $sebelum = $sumber?->only(['nama', 'host', 'prefix', 'aktif']) ?? [];
        $sumber ??= new SumberBukti;
        $sumber->fill($data)->save();
        KebijakanBukti::lupakan();
        Aktivitas::catat('master.sumber_bukti.simpan', 'Mengatur sumber link bukti '.$data['host'], [
            'subjek' => $sumber, 'rincian' => ['sebelum' => $sebelum, 'sesudah' => $sumber->only(['nama', 'host', 'prefix', 'aktif'])],
        ]);

        return redirect()->route('master', ['tab' => 'sumber'])->with('pesan', 'Aturan sumber bukti tersimpan. Link lama mengikuti aturan ini saat dibuka.');
    }
}
