<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MasukController extends Controller
{
    /**
     * Halaman masuk yang sungguhan — rupanya sama dengan saat dipasang: bersih,
     * tanpa akun contoh. Di sinilah SSO dipasang (28 Sep, permintaan Hizkia).
     *
     * Formulir email dan password tampil di sini hanya bila masuk dengan kata
     * password dibolehkan DAN Login developer tidak ada — yaitu pemasangan
     * sungguhan yang masih memakai password sebagai pintu cadangan. Selama
     * pengembangan formulir itu tinggal di Login developer, jadi halaman ini
     * tampil persis seperti nanti bila SSO satu-satunya pintu.
     */
    public function form()
    {
        return $this->tampil(false);
    }

    /**
     * Login developer: tampilan masuk selama pengembangan — SSO simulasi,
     * email dan password, dan daftar akun contoh. Hanya ada di pemasangan
     * demo (config simtlhp.akun_demo, padam sendiri di produksi) yang
     * masih membolehkan password; selain itu tidak ditemukan.
     */
    public function pengembang()
    {
        abort_unless(self::adaPengembang(), 404);

        return $this->tampil(true);
    }

    public static function adaPengembang(): bool
    {
        return config('simtlhp.akun_demo') && config('simtlhp.masuk.password');
    }

    private function tampil(bool $pengembang)
    {
        return view('masuk', [
            'pengembang'    => $pengembang,
            'adaPengembang' => self::adaPengembang(),
            /* Daftar akun contoh supaya demo tidak tersendat mengetik kata
               password — hanya di Login developer. */
            'akun'          => $pengembang ? User::with('satker')->where('aktif', true)->orderBy('id')->get() : collect(),
            'password'         => config('simtlhp.masuk.password') && ($pengembang || ! self::adaPengembang()),
            'sso'           => \App\Support\Sso\Penyedia::aktif() ? config('simtlhp.sso.nama') : null,
        ]);
    }

    /**
     * Masuk dengan email dan password.
     *
     * Dijaga tiga hal (27 Sep):
     * - percobaan yang salah dibatasi per email + alamat — lima kali semenit,
     *   lalu dikunci sementara. Menebak password jadi tidak mungkin dalam
     *   waktu yang wajar;
     * - pesannya sama untuk email yang tidak ada dan password yang salah,
     *   jadi formulir ini tidak bisa dipakai menebak email siapa yang terdaftar;
     * - tidak ada lagi "ingat saya" yang dipaksakan. Dulu setiap orang yang
     *   masuk dibekali cookie lima tahun — di komputer bersama, orang berikutnya
     *   langsung masuk sebagai dia. Sekarang sesinya habis sendiri bila lama
     *   tidak dipakai (SESSION_LIFETIME).
     *
     * Setiap percobaan — berhasil, salah, dikunci — tercatat di log aktivitas.
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

        /* Akun yang dinonaktifkan — penanggung jawab yang sudah diganti,
           petugas yang pindah — tidak bisa masuk lagi. */
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

        /* Masuk lewat SSO, keluar juga di SSO — kalau Pusdatin menyediakan
           alamat keluarnya. Tanpa itu sesi SSO-nya tetap hidup, dan menekan
           "Masuk dengan SSO" langsung masuk lagi tanpa ditanya. */
        if ($lewatSso && ($ke = \App\Support\Sso\Penyedia::alamatKeluar())) {
            return redirect()->away($ke);
        }

        return redirect()->route('masuk');
    }
}
