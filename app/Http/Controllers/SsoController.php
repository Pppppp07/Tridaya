<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use App\Support\Sso\GagalSso;
use App\Support\Sso\Identitas;
use App\Support\Sso\Otorisasi;
use App\Support\Sso\Penyedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Masuk lewat SSO eHRM (27 Sep).
 *
 *   /masuk/sso          → ke halaman masuk penyedia SSO
 *   /masuk/sso/kembali  → penyedia mengembalikan orangnya ke sini
 *   /masuk/sso/simulasi → pengganti halaman penyedia, khusus demo
 *
 * Penyedianya dipilih `SIMTLHP_SSO` (App\Support\Sso\Penyedia). Pengendali ini
 * tidak tahu apa-apa tentang OIDC atau SAML — ia menerima Identitas, meminta
 * Otorisasi memutus boleh tidaknya, lalu memasukkan atau menolak.
 */
class SsoController extends Controller
{
    public function arahkan(Request $r)
    {
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

    /** Halaman "penyedia SSO" tiruan: pilih pegawai dari direktori. */
    public function simulasi(Request $r)
    {
        abort_unless(Penyedia::nama() === 'simulasi', 404);

        $q = trim((string) $r->query('q', ''));
        $hasil = mb_strlen($q) >= 2 ? DirektoriIrm::cari($q, 20) : DirektoriIrm::semua()->take(0);
        $akun = User::with('satker')->whereNotNull('nip')->get()->keyBy('nip');

        return view('sso-simulasi', [
            'q' => $q, 'hasil' => $hasil, 'akun' => $akun,
            /* Contoh siap pakai: satu orang untuk tiap keadaan yang perlu
               diperagakan — tiap peran, dan yang ditolak. */
            'contoh' => $this->contoh($akun),
        ]);
    }

    public function simulasiPilih(Request $r)
    {
        abort_unless(Penyedia::nama() === 'simulasi', 404);
        $r->validate(['nip' => ['required', 'string', 'max:30']]);

        return $this->kembali($r);
    }

    /* ================================================================ */

    private function selesaikan(Request $r, Identitas $id)
    {
        [$u, $alasan] = Otorisasi::periksa($id);
        if (! $u) {
            return $this->tolak($alasan, $id);
        }

        $beda = Otorisasi::samakan($u, $id);
        Auth::login($u);
        $r->session()->regenerate();
        $r->session()->put('masuk_lewat_sso', true);
        Akun::tandaiMasuk($u, 'sso');
        if ($beda) {
            Aktivitas::catat('masuk.sso.samakan', 'Nama, jabatan, atau email akun disamakan dengan eHRM', [
                'oleh' => $u, 'subjek' => $u, 'rincian' => $beda,
            ]);
        }

        return redirect()->intended(route('beranda'));
    }

    /**
     * Halaman penolakan — dan satu baris log, supaya DTI tahu ada yang mencoba.
     *
     * Dua keadaan yang berbeda bunyinya (29 Sep, kata Hizkia: "kalau akunnya
     * tidak dipilih atau tidak diberi akses … diberikan keterangan anda tidak
     * memiliki akses untuk sistem ini, hubungi Admin atau pihak berwenang"):
     * - `akses`: SSO berhasil membuktikan orangnya, tapi Data master tidak
     *   memberinya akses (belum didaftarkan atau dinonaktifkan);
     * - bukan `akses`: masuknya gagal di tengah jalan (server SSO tidak bisa
     *   dihubungi, link masuk tidak valid) — orangnya belum tentu tidak berhak.
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

    /** @return list<array{nip:string, ket:string}> */
    private function contoh($akun): array
    {
        $isi = [];
        foreach (DirektoriIrm::semua() as $p) {
            $a = $akun[$p['nip']] ?? null;
            $ket = $a ? ($a->aktif ? $a->peran->pendek().($a->satker ? ' · '.$a->satker->namaPendek() : '') : 'akun nonaktif')
                : 'belum terdaftar';
            $kunci = $a ? ($a->aktif ? $a->peran->value : 'nonaktif') : 'tanpa-akun';
            $isi[$kunci] ??= ['nip' => $p['nip'], 'nama' => $p['nama'], 'ket' => $ket];
        }

        return array_values($isi);
    }
}
