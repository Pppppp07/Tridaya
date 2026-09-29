<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Pengguna & hak akses — tab "Pengguna" di Data master (27 Sep). Padanan
 * `TabPengguna` prototipe.
 *
 * Siapa boleh masuk, dan sebagai apa. Dengan SSO eHRM, seluruh pegawai
 * Kementerian bisa membuktikan dirinya — daftar inilah yang memutuskan siapa
 * di antara mereka yang boleh masuk ke aplikasi ini (App\Support\Sso\Otorisasi).
 *
 * - Petugas pusat (Setba, UKI, Inspektorat, Pimpinan, DTI, Admin) didaftarkan
 *   dari direktori pegawai menurut NIP: nama, jabatan, dan email-nya ikut dari
 *   sana, tidak diketik — "jangan tertukar atributnya".
 * - Penanggung jawab unit kerja TIDAK diatur di sini, tapi lewat tab Unit
 *   kerja: satu unit satu orang, dan menggantinya menonaktifkan yang lama.
 * - Tidak ada yang dihapus. Akun yang tidak dipakai lagi dinonaktifkan; jejak
 *   perbuatannya tetap tercatat atas namanya.
 *
 * Penjaga:
 * - tidak bisa mengubah akunnya sendiri — orang tidak bisa tanpa sengaja
 *   mengunci dirinya, atau diam-diam menaikkan haknya;
 * - paling tidak satu Setba dan satu Admin tetap aktif;
 * - DTI dan Admin hanya diatur Admin: yang diawasi tidak mengatur pengawasnya
 *   (PeranPengguna::bolehMemberi);
 * - satu NIP satu akun (indeks unik basis data);
 * - akun yang dinonaktifkan atau diubah perannya langsung diputus dari semua
 *   perangkatnya (Akun::putuskanSesi).
 *
 * Setiap perubahan tercatat di log aktivitas, lengkap dengan sebelum → sesudah.
 */
class PenggunaController extends Controller
{
    /** Hasil pencarian direktori untuk jendela "Tambah pengguna". */
    public function cari(Request $r)
    {
        $this->pastikanBoleh($r);

        return view('data-master.hasil-pegawai', self::hasilCari((string) $r->query('q', '')));
    }

    /** @return array<string, mixed> isi untuk `data-master.hasil-pegawai` */
    public static function hasilCari(string $q): array
    {
        $q = trim($q);
        $hasil = DirektoriIrm::cari($q, 12);

        return [
            'q'     => $q,
            'hasil' => $hasil,
            'error' => DirektoriIrm::error(),
            /* NIP → akun yang sudah ada, supaya yang sudah berakun tidak
               ditawarkan dua kali. */
            'akun'  => User::with('satker')->whereIn('nip', $hasil->pluck('nip'))->get()->keyBy('nip'),
        ];
    }

    public function tambah(Request $r)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate([
            'nip'   => ['required', 'string', 'max:30'],
            'peran' => ['required', Rule::in(array_map(fn ($p) => $p->value, $pelaku->peran->bolehMemberi()))],
        ], [
            'peran.required' => 'Pilih perannya.',
            'peran.in'       => 'Anda tidak bisa memberikan peran itu.',
        ]);
        $pegawai = DirektoriIrm::nip($data['nip']);
        if (! $pegawai) {
            return $this->kembali()->with('gagal', DirektoriIrm::error() ?: 'Pegawai dengan NIP itu tidak ada di direktori.');
        }
        $peran = PeranPengguna::from($data['peran']);

        $ada = User::with('satker')->where('nip', $pegawai['nip'])->first();
        if ($ada && $ada->aktif) {
            return $this->kembali($ada)->with('gagal', $pegawai['nama'].' sudah punya akun sebagai '
                .$ada->peran->pendek().($ada->satker && $ada->peran === PeranPengguna::SATKER ? ' '.$ada->satker->namaPendek() : '').'.');
        }
        $emailLain = User::where('email', $pegawai['email'])->when($ada, fn ($q) => $q->whereKeyNot($ada->id))->first();
        if ($emailLain) {
            return $this->kembali($emailLain)->with('gagal', 'Email '.$pegawai['email'].' sudah dipakai akun lain ('.$emailLain->name.').');
        }

        $akun = DB::transaction(function () use ($ada, $pegawai, $peran) {
            $akun = $ada ?? new User(['password' => self::passwordAwal()]);
            $sebelum = $ada ? ['peran' => $ada->peran->value, 'aktif' => false] : [];
            $akun->fill([
                'name' => $pegawai['nama'], 'nip' => $pegawai['nip'], 'jabatan' => $pegawai['jabatan'],
                'email' => $pegawai['email'], 'peran' => $peran->value, 'satker_id' => null, 'aktif' => true,
            ])->save();
            Aktivitas::catat('master.pengguna.tambah', ($ada ? 'Mengaktifkan kembali akun ' : 'Menambah pengguna ')
                .$pegawai['nama'].' sebagai '.$peran->pendek(), [
                    'subjek' => $akun,
                    'rincian' => ['sebelum' => $sebelum, 'sesudah' => ['peran' => $peran->value, 'aktif' => true, 'nip' => $pegawai['nip']]],
                ]);

            return $akun;
        });

        return $this->kembali($akun)->with('pesan', $pegawai['nama'].' kini bisa masuk sebagai '.$peran->pendek().'.');
    }

    /** Mengubah peran akun pusat. */
    public function simpan(Request $r, User $pengguna)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate(['peran' => ['required', Rule::in(array_map(fn ($p) => $p->value, PeranPengguna::cases()))]]);
        $baru = PeranPengguna::from($data['peran']);
        $lama = $pengguna->peran;

        if ($alasan = $this->alasanTolak($pelaku, $pengguna) ?? (! in_array($baru, $pelaku->peran->bolehMemberi(), true)
            ? 'Anda tidak bisa memberikan peran '.$baru->pendek().'.' : null)) {
            return $this->kembali($pengguna)->with('gagal', $alasan);
        }
        if ($baru === $lama) {
            return $this->kembali($pengguna);
        }
        if ($alasan = $this->alasanTerakhir($pengguna, 'peran')) {
            return $this->kembali($pengguna)->with('gagal', $alasan);
        }

        DB::transaction(function () use ($pengguna, $lama, $baru) {
            $pengguna->update(['peran' => $baru->value]);
            Akun::putuskanSesi($pengguna);
            Aktivitas::catat('master.pengguna.peran', 'Mengubah peran '.$pengguna->name.' dari '.$lama->pendek().' menjadi '.$baru->pendek(), [
                'subjek' => $pengguna,
                'rincian' => ['sebelum' => ['peran' => $lama->value], 'sesudah' => ['peran' => $baru->value]],
            ]);
        });

        return $this->kembali($pengguna)->with('pesan', 'Peran '.$pengguna->name.' kini '.$baru->pendek().'.');
    }

    /** Menonaktifkan atau mengaktifkan kembali akun pusat. */
    public function saklar(Request $r, User $pengguna)
    {
        $pelaku = $this->pastikanBoleh($r);
        if ($alasan = $this->alasanTolak($pelaku, $pengguna)) {
            return $this->kembali($pengguna)->with('gagal', $alasan);
        }
        if ($pengguna->aktif && ($alasan = $this->alasanTerakhir($pengguna, 'aktif'))) {
            return $this->kembali($pengguna)->with('gagal', $alasan);
        }
        if (! $pengguna->aktif && $pengguna->nip
            && User::where('nip', $pengguna->nip)->whereKeyNot($pengguna->id)->where('aktif', true)->exists()) {
            return $this->kembali($pengguna)->with('gagal', 'NIP ini sudah dipakai akun aktif lain.');
        }

        $aktif = ! $pengguna->aktif;
        DB::transaction(function () use ($pengguna, $aktif) {
            $pengguna->update(['aktif' => $aktif]);
            if (! $aktif) {
                Akun::putuskanSesi($pengguna);
            }
            Aktivitas::catat('master.pengguna.saklar', ($aktif ? 'Mengaktifkan kembali akun ' : 'Menonaktifkan akun ')
                .$pengguna->name.' ('.$pengguna->peran->pendek().')', [
                    'subjek' => $pengguna,
                    'rincian' => ['sebelum' => ['aktif' => ! $aktif], 'sesudah' => ['aktif' => $aktif]],
                ]);
        });

        return $this->kembali($pengguna)->with('pesan', $pengguna->name.($aktif ? ' bisa masuk lagi.' : ' tidak bisa masuk lagi.'));
    }

    /* ================================================================
       PENJAGA
       ================================================================ */

    private function pastikanBoleh(Request $r): User
    {
        $u = $r->user();
        abort_unless($u->peran->kelolaMaster(), 403, 'Pengguna hanya diatur Setba dan Admin.');

        return $u;
    }

    /** Penolakan yang berlaku untuk semua perubahan akun orang lain. */
    private function alasanTolak(User $pelaku, User $target): ?string
    {
        return match (true) {
            $pelaku->is($target) => 'Akun Anda sendiri tidak bisa diubah dari sini. Minta Setba atau Admin lain.',
            $target->peran === PeranPengguna::SATKER => 'Akun satuan kerja milik penanggung jawab unit kerjanya. Ganti lewat tab Unit kerja.',
            ! in_array($target->peran, $pelaku->peran->bolehMemberi(), true) => 'Akun '.$target->peran->pendek().' hanya bisa diatur Admin.',
            default => null,
        };
    }

    /** Setba dan Admin terakhir yang aktif tidak boleh hilang. */
    private function alasanTerakhir(User $target, string $apa): ?string
    {
        foreach ([PeranPengguna::SETBA, PeranPengguna::ADMIN] as $p) {
            if ($target->peran === $p && $target->aktif
                && ! User::where('peran', $p->value)->where('aktif', true)->whereKeyNot($target->id)->exists()) {
                return 'Paling tidak satu akun '.$p->pendek().' harus tetap aktif'
                    .($apa === 'peran' ? ', jadi peran akun ini belum bisa diubah.' : '.');
            }
        }

        return null;
    }

    /**
     * Akun baru tidak punya password yang diketahui siapa pun: orangnya
     * masuk lewat SSO. Pada demo password-nya boleh dipatok
     * (`SIMTLHP_PASSWORD_DEMO`), sama dengan penanggung jawab unit kerja.
     */
    private static function passwordAwal(): string
    {
        return (string) (config('simtlhp.password_demo') ?: Str::random(40));
    }

    private function kembali(?User $u = null)
    {
        return redirect()->route('master', array_filter(['tab' => 'pengguna', 'kartu' => $u ? 'akun-'.$u->id : null]));
    }
}
