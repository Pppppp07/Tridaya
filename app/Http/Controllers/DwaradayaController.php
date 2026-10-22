<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Sso\GagalSso;
use App\Support\Sso\Identitas;
use App\Support\Sso\MasukSso;
use App\Support\Sso\Otorisasi;
use App\Support\Sso\Penyedia;
use App\Support\Sso\TokenDwaradaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class DwaradayaController extends Controller
{
    private const TUNDA = 'dwaradaya.konfirmasi';

    /** Stateless: pengecekan kartu SuperApp tidak membuat sesi atau memperbarui user. */
    public function periksa(Request $r, TokenDwaradaya $token): Response
    {
        if (Penyedia::nama() !== 'dwaradaya') {
            return response()->json(['error' => 'SSO Dwaradaya tidak aktif.'], 404);
        }
        try {
            $this->pastikanHttps($r);
            $bukti = $token->dariPermintaan($r);
            [$u, $alasan] = Otorisasi::dwaradaya($bukti['identitas']);
            if (! $u) {
                $this->catatPenolakan($alasan, ['sebab' => 'izin akun'], $bukti['identitas']);

                return response()->json(['error' => 'User tidak memiliki akses aktif ke infaX.'], 401);
            }
        } catch (GagalSso $e) {
            $this->catatPenolakan($e->getMessage(), $e->rincian);

            return response()->json(['error' => $e->getMessage()], 401);
        }

        // Format persis sebagaimana kontrak SuperApp: tidak ada data user atau wrapper.
        return response()->json(['success' => true, 'exists' => true]);
    }

    public function masuk(Request $r, TokenDwaradaya $token): Response
    {
        abort_unless(Penyedia::nama() === 'dwaradaya', 404);
        $r->session()->forget(self::TUNDA);
        try {
            $this->pastikanHttps($r);
            $bukti = $token->dariPermintaan($r);
            [$u, $alasan] = Otorisasi::dwaradaya($bukti['identitas']);
            if (! $u) {
                return $this->tolak($r, $alasan, ['sebab' => 'izin akun'], $bukti['identitas']);
            }
            if ($r->user() && (int) $r->user()->id !== (int) $u->id) {
                // Jangan diam-diam mengganti akun yang masih terbuka melalui sebuah GET.
                // Simpan bukti terverifikasi saja, bukan JWT mentah. Diperiksa ulang saat POST.
                $r->session()->put(self::TUNDA, [
                    'user_id' => $u->id, 'nip' => $bukti['identitas']->nip,
                    'email' => $bukti['identitas']->email,
                    'exp' => $bukti['exp'], 'sidik' => $bukti['sidik'],
                ]);

                return redirect()->route('sso.dwaradaya.konfirmasi');
            }

            return $this->selesaikan($r, $u, $bukti);
        } catch (GagalSso $e) {
            return $this->tolak($r, $e->getMessage(), $e->rincian);
        }
    }

    public function konfirmasi(Request $r): Response
    {
        abort_unless(Penyedia::nama() === 'dwaradaya', 404);
        try {
            $this->pastikanHttps($r);
            [$u] = $this->tertunda($r);
        } catch (GagalSso $e) {
            return $this->tolak($r, $e->getMessage(), $e->rincian);
        }

        return response()->view('sso-konfirmasi', ['akunTujuan' => $u]);
    }

    public function lanjutkan(Request $r): Response
    {
        abort_unless(Penyedia::nama() === 'dwaradaya', 404);
        try {
            $this->pastikanHttps($r);
            if ($r->input('pilihan') === 'batal') {
                $r->session()->forget(self::TUNDA);

                return redirect()->route('beranda');
            }
            [$u, $bukti] = $this->tertunda($r);
            $r->session()->forget(self::TUNDA);

            return $this->selesaikan($r, $u, $bukti);
        } catch (GagalSso $e) {
            return $this->tolak($r, $e->getMessage(), $e->rincian);
        }
    }

    public function hasil(Request $r): Response
    {
        abort_unless(Penyedia::nama() === 'dwaradaya', 404);
        $pesan = $r->session()->get('dwaradaya.gagal');
        if (! is_array($pesan)) {
            return redirect()->route('masuk');
        }

        return response()->view('akses-dibatasi', [
            'alasan' => $pesan['alasan'], 'akses' => $pesan['akses'],
            'id' => null,
            'rangkaMasuk' => 'sso-rangka',
        ], 403);
    }

    private function tertunda(Request $r): array
    {
        $b = $r->session()->get(self::TUNDA);
        if (! is_array($b) || ($b['exp'] ?? 0) <= time()) {
            throw new GagalSso('Permintaan masuk sudah berakhir. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'konfirmasi kedaluwarsa']);
        }
        $id = new Identitas(nip: $b['nip'], email: $b['email']);
        [$u, $alasan] = Otorisasi::dwaradaya($id);
        if (! $u || (int) $u->id !== (int) $b['user_id']) {
            throw new GagalSso('Izin akun berubah. Buka kembali infaX dari Dwaradaya atau hubungi Admin DTI.', ['sebab' => 'izin saat konfirmasi']);
        }

        return [$u, ['identitas' => $id, 'exp' => $b['exp'], 'sidik' => $b['sidik']]];
    }

    private function selesaikan(Request $r, User $u, array $bukti): Response
    {
        if ($bukti['exp'] <= time()) {
            throw new GagalSso('Token Dwaradaya sudah kedaluwarsa. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'token kedaluwarsa']);
        }
        if (config('simtlhp.sso.dwaradaya.sekali_pakai')) {
            // Absolute waktu nyata menghindari perbedaan jam demo aplikasi.
            $habis = (new \DateTimeImmutable)->setTimestamp($bukti['exp']);
            if (! Cache::add('sso:dwaradaya:terpakai:'.$bukti['sidik'], true, $habis)) {
                throw new GagalSso('Link masuk ini sudah digunakan. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'token terpakai']);
            }
        }
        // Hanya setelah identitas dan izin lolos: buang konteks akun/peran/Satker lama.
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        MasukSso::jalankan($r, $u, $bukti['identitas']);

        return redirect()->route('beranda');
    }

    private function pastikanHttps(Request $r): void
    {
        if (app()->environment('production') && ! $r->isSecure()) {
            throw new GagalSso('SSO Dwaradaya memerlukan koneksi HTTPS.', ['sebab' => 'https']);
        }
    }

    private function tolak(Request $r, string $alasan, array $rincian, ?Identitas $id = null): Response
    {
        $r->session()->forget(self::TUNDA);
        $this->catatPenolakan($alasan, $rincian, $id);
        $r->session()->flash('dwaradaya.gagal', ['alasan' => $alasan, 'akses' => $id !== null]);

        // Halaman penjelasan memakai URL bersih agar JWT tidak menjadi referrer/tautan kembali.
        return redirect()->route('sso.dwaradaya.hasil');
    }

    private function catatPenolakan(string $alasan, array $rincian, ?Identitas $id = null): void
    {
        Aktivitas::catat('masuk.sso.ditolak', 'Masuk lewat Dwaradaya ditolak: '.$alasan, [
            'oleh' => null,
            'rincian' => array_filter($rincian + ['penyedia' => 'dwaradaya', 'nip' => $id?->nip]),
        ]);
    }
}
