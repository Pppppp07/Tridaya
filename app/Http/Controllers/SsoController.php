<?php

namespace App\Http\Controllers;

use App\Support\Aktivitas;
use App\Support\Sso\GagalSso;
use App\Support\Sso\Identitas;
use App\Support\Sso\MasukSso;
use App\Support\Sso\Otorisasi;
use App\Support\Sso\Penyedia;
use Illuminate\Http\Request;

/**
 * Masuk lewat SSO: /masuk/sso (ke penyedia), /masuk/sso/kembali (dari penyedia). Pengendali ini
 * hanya menerima Identitas lalu meminta Otorisasi memutus.
 */
class SsoController extends Controller
{
    public function arahkan(Request $r)
    {
        /* Tombol SSO selalu ada; selama penyedianya belum disambungkan, kembali dengan pesan. */
        if (! Penyedia::aktif()) {
            return redirect()->route('masuk')->with('sso_belum', true);
        }
        try {
            return Penyedia::driver()->arahkan($r);
        } catch (GagalSso $e) {
            return $this->tolak($e->getMessage(), null, $e->rincian, akses: false);
        }
    }

    public function kembali(Request $r)
    {
        try {
            $id = Penyedia::driver()->terima($r);
        } catch (GagalSso $e) {
            return $this->tolak($e->getMessage(), null, $e->rincian, akses: false);
        }

        return $this->selesaikan($r, $id);
    }

    /* ================================================================ */

    private function selesaikan(Request $r, Identitas $id)
    {
        [$u, $alasan] = Otorisasi::periksa($id);
        if (! $u) {
            return $this->tolak($alasan, $id);
        }

        MasukSso::jalankan($r, $u, $id);

        return redirect()->intended(route('beranda'));
    }

    /**
     * Halaman penolakan, tercatat di log. `akses`: SSO berhasil tapi Data master tidak memberi
     * akses; selain itu masuk gagal di tengah jalan (orangnya belum tentu tidak berhak).
     */
    private function tolak(string $alasan, ?Identitas $id, array $rincian = [], bool $akses = true)
    {
        Aktivitas::catat('masuk.sso.ditolak', 'Masuk lewat SSO ditolak: '.$alasan, [
            'oleh' => null,
            'nama' => $id ? trim($id->nama.' · NIP '.$id->nip, ' ·') : null,
            'rincian' => array_filter($rincian + ['nip' => $id?->nip, 'unit' => $id?->unit ?: null]),
        ]);

        return response()->view('akses-dibatasi', ['alasan' => $alasan, 'id' => $id, 'akses' => $akses], 403);
    }
}
