<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\Sso\Penyedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MasukController extends Controller
{
    /**
     * Halaman masuk sungguhan (SSO). Formulir password hanya tampil bila password dibolehkan dan
     * Login developer tidak ada.
     */
    public function form()
    {
        return $this->tampil(false);
    }

    /** Login developer: password dan akun contoh; hanya di pemasangan demo. */
    public function pengembang()
    {
        abort_unless(self::adaPengembang(), 404);

        return $this->tampil(true);
    }

    public static function adaPengembang(): bool
    {
        return ! app()->isProduction() && config('simtlhp.akun_demo') && config('simtlhp.masuk.password');
    }

    private function tampil(bool $pengembang)
    {
        return view('masuk', [
            'pengembang' => $pengembang,
            'adaPengembang' => self::adaPengembang(),
            /* Akun contoh hanya di Login developer. */
            'akun' => $pengembang ? User::with('satker')->where('aktif', true)->orderBy('id')->get() : collect(),
            'password' => config('simtlhp.masuk.password') && ($pengembang || ! self::adaPengembang()),
            /* Tombol SSO selalu tampil; bila penyedianya belum disambungkan, tombolnya memberi tahu. */
            'sso' => Penyedia::nama() === 'dwaradaya' ? 'Dwaradaya' : (config('simtlhp.sso.nama') ?: 'SSO PU'),
            'dwaradaya' => Penyedia::nama() === 'dwaradaya',
            'ssoBelum' => (bool) request()->session()->get('sso_belum', false),
            'sesiBerakhir' => (bool) request()->session()->pull('sesi_berakhir', false),
        ]);
    }

    /**
     * Masuk dengan email dan password: dibatasi per email + alamat lalu dikunci sementara; pesan
     * sama untuk email dan password salah; tanpa "ingat saya".
     */
    public function masuk(Request $r)
    {
        abort_unless(config('simtlhp.masuk.password'), 404);

        $data = $r->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = Str::lower(trim($data['email']));
        $kunci = 'masuk:'.sha1($email.'|'.$r->ip());
        $batas = max(1, (int) config('simtlhp.masuk.batas_percobaan', 5));

        if (RateLimiter::tooManyAttempts($kunci, $batas)) {
            $detik = RateLimiter::availableIn($kunci);
            Aktivitas::catat('masuk.dikunci', 'Masuk dikunci sementara sesudah '.$batas.' kali salah', [
                'oleh' => null, 'nama' => $email, 'rincian' => ['email' => $email, 'sisa_detik' => $detik],
            ]);

            return back()->withInput($r->only('email'))->withErrors([
                'email' => 'Terlalu banyak percobaan yang salah. Coba lagi dalam '.max(1, $detik).' detik.',
            ]);
        }

        /* Akun nonaktif tidak bisa masuk. */
        if (! Auth::attempt(['email' => $email, 'password' => $data['password'], 'aktif' => true])) {
            RateLimiter::hit($kunci, max(10, (int) config('simtlhp.masuk.jeda_detik', 60)));
            Aktivitas::catat('masuk.gagal', 'Gagal masuk: email atau password salah', [
                'oleh' => null, 'nama' => $email, 'rincian' => ['email' => $email],
            ]);

            return back()->withInput($r->only('email'))->withErrors(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($kunci);
        $r->session()->regenerate();
        Akun::tandaiMasuk($r->user(), 'password');

        return redirect()->intended(route('beranda'));
    }

    public function keluar(Request $r)
    {
        $u = $r->user();
        if ($u) {
            Aktivitas::catat('keluar', 'Keluar dari aplikasi', ['oleh' => $u]);
        }
        $lewatSso = (bool) $r->session()->get('masuk_lewat_sso');
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        /* Masuk lewat SSO, keluar juga di SSO (bila alamat keluarnya ada); tanpa itu sesi SSO
           tetap hidup. */
        if ($lewatSso && ($ke = Penyedia::alamatKeluar())) {
            return redirect()->away($ke);
        }

        return redirect()->route('masuk');
    }
}
