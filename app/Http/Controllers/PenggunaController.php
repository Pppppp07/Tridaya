<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use App\Support\PusatData;
use App\Support\Sesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pengguna & hak akses: daftar ini yang memutuskan siapa boleh masuk (lihat Sso\Otorisasi). */
class PenggunaController extends Controller
{
    /** Maksimal NIP sekali "Tambah beberapa". */
    public const BATAS_IMPOR = 100;

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
            'q' => $q,
            'hasil' => $hasil,
            'error' => DirektoriIrm::error(),
            /* NIP → akun yang sudah ada. */
            'akun' => User::with('satker')->whereIn('nip', $hasil->pluck('nip'))->get()->keyBy('nip'),
        ];
    }

    public function tambah(Request $r)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate([
            'nip' => ['required', 'string', 'max:30'],
            'peran' => ['required_without:perans', Rule::in(array_map(fn ($p) => $p->value, $pelaku->peranAktif()->bolehMemberi()))],
            'perans' => ['required_without:peran', 'array', 'min:1', 'max:6'],
            'perans.*' => ['required', 'distinct', Rule::in(array_map(fn ($p) => $p->value, $pelaku->peranAktif()->bolehMemberi()))],
        ], [
            'peran.required' => 'Pilih perannya.',
            'peran.required_without' => 'Pilih minimal satu peran.',
            'peran.in' => 'Anda tidak bisa memberikan peran itu.',
            'perans.required_without' => 'Pilih minimal satu peran.',
            'perans.min' => 'Pilih minimal satu peran.',
            'perans.*.in' => 'Anda tidak bisa memberikan peran itu.',
        ]);
        $pegawai = DirektoriIrm::nip($data['nip']);
        if (! $pegawai) {
            return $this->kembali()->with('gagal', DirektoriIrm::error() ?: 'Pegawai dengan NIP itu tidak ada di direktori.');
        }
        $dipilih = $data['perans'] ?? [$data['peran']];
        $peran = PeranPengguna::from($dipilih[0]);
        $cakupan = $this->cakupanPimpinan($r, $dipilih);

        [$akun, $alasan] = DB::transaction(function () use ($pelaku, $pegawai, $peran, $dipilih, $cakupan) {
            $this->kunciPelaku($pelaku);
            $ada = User::with('satker')->where('nip', $pegawai['nip'])->lockForUpdate()->first();
            if ($ada && $ada->peran === PeranPengguna::SATKER) {
                return [$ada, 'Akun satuan kerja sudah terdaftar. Atur aksesnya di User & akses; ganti penanggung jawab lewat tab Satuan kerja.'];
            }
            if ($ada && $ada->aktif) {
                return [$ada, $pegawai['nama'].' sudah punya akun sebagai '.$ada->peran->pendek().'.'];
            }
            $emailLain = User::where('email', $pegawai['email'])->when($ada, fn ($q) => $q->whereKeyNot($ada->id))->lockForUpdate()->first();
            if ($emailLain) {
                return [$emailLain, 'Email '.$pegawai['email'].' sudah dipakai akun lain ('.$emailLain->name.').'];
            }

            return [$this->daftarkan($pegawai, $peran, $ada, array_slice($dipilih, 1), $cakupan), null];
        }, 3);
        if ($alasan) {
            return $this->kembali($akun)->with('gagal', $alasan);
        }

        return $this->kembali($akun)->with('pesan', $pegawai['nama'].' kini bisa masuk sebagai '.collect($akun->semuaPeran())->map->pendek()->join(', ').'.');
    }

    /** Mendaftarkan pegawai dengan peran yang dipilih (akun baru atau diaktifkan kembali). */
    private function daftarkan(array $pegawai, PeranPengguna $peran, ?User $ada, array $tambahan = [], ?array $cakupan = null): User
    {
        $akun = $ada ?? new User(['password' => Akun::passwordAwal()]);
        $sebelum = $ada ? ['peran' => $ada->peran->value, 'perans' => array_map(fn ($p) => $p->value, $ada->semuaPeran()), 'aktif' => false,
            'satker_pimpinan' => self::sebutCakupan(self::cakupanAkun($ada))] : [];
        $akun->fill([
            'name' => $pegawai['nama'], 'nip' => $pegawai['nip'], 'jabatan' => $pegawai['jabatan'],
            'email' => $pegawai['email'], 'peran' => $peran->value, 'satker_id' => null, 'aktif' => true,
        ])->forceFill(['peran_tambahan' => $tambahan] + self::isiCakupan($cakupan))->save();
        if ($ada) {
            Akun::putuskanSesi($akun);
        }
        Aktivitas::catat('master.pengguna.tambah', ($ada ? 'Mengaktifkan kembali akun ' : 'Menambah user ')
            .$pegawai['nama'].' sebagai '.$peran->pendek(), [
                'subjek' => $akun,
                'rincian' => ['sebelum' => $sebelum, 'sesudah' => ['peran' => $peran->value, 'perans' => [$peran->value, ...$tambahan], 'aktif' => true, 'nip' => $pegawai['nip'],
                    'satker_pimpinan' => self::sebutCakupan($cakupan)]],
            ]);

        return $akun;
    }

    /** Mengubah peran akun pusat. */
    public function simpan(Request $r, User $pengguna)
    {
        if ($r->has('perans') || $r->boolean('perans_dikirim')) {
            $r->merge(['perans' => $r->input('perans', [])]);

            return $this->simpanPerans($r, $pengguna);
        }
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate(['peran' => ['required', Rule::in(array_map(fn ($p) => $p->value, PeranPengguna::cases()))]]);
        $baru = PeranPengguna::from($data['peran']);
        $cakupan = $this->cakupanPimpinan($r, [$baru->value], $pengguna);
        $alasan = DB::transaction(function () use ($pelaku, $pengguna, $baru, $cakupan) {
            $pelaku = $this->kunciPelaku($pelaku);
            $target = $this->kunciPengguna($pengguna->id);
            if ($alasan = $this->alasanTolak($pelaku, $target) ?? (! in_array($baru, $pelaku->peranAktif()->bolehMemberi(), true)
                ? 'Anda tidak bisa memberikan peran '.$baru->pendek().'.' : null)) {
                return $alasan;
            }
            $lama = $target->peran;
            $peransLama = array_map(fn ($p) => $p->value, $target->semuaPeran());
            $cakupanLama = self::sebutCakupan(self::cakupanAkun($target));
            if ($baru === $lama && count($peransLama) === 1 && self::cakupanAkun($target) === $cakupan) {
                return null;
            }
            if ($alasan = $this->alasanTerakhir($target, 'peran', [$baru])) {
                return $alasan;
            }
            $target->forceFill(['peran' => $baru->value, 'peran_tambahan' => []] + self::isiCakupan($cakupan))->save();
            Akun::putuskanSesi($target);
            Aktivitas::catat('master.pengguna.peran', 'Mengubah peran '.$target->name.' dari '.$lama->pendek().' menjadi '.$baru->pendek(), [
                'subjek' => $target,
                'rincian' => [
                    'sebelum' => (count($peransLama) > 1 ? ['perans' => $peransLama] : ['peran' => $lama->value])
                        + ($cakupanLama || $cakupan ? ['satker_pimpinan' => $cakupanLama] : []),
                    'sesudah' => (count($peransLama) > 1 ? ['perans' => [$baru->value]] : ['peran' => $baru->value])
                        + ($cakupanLama || $cakupan ? ['satker_pimpinan' => self::sebutCakupan($cakupan)] : []),
                ],
            ]);

            return null;
        }, 3);
        if ($alasan) {
            return $this->kembali($pengguna, $r)->with('gagal', $alasan);
        }

        return $this->kembali($pengguna, $r)->with('pesan', 'Peran '.$pengguna->name.' kini '.$baru->pendek().'.');
    }

    /** Satu identitas, beberapa peran; peran Satker tetap melalui penetapan PJ. */
    private function simpanPerans(Request $r, User $pengguna)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate([
            'perans' => ['present', 'array', 'max:6'],
            'perans.*' => ['required', 'distinct', Rule::in(array_map(fn ($p) => $p->value, PeranPengguna::pusat()))],
        ]);
        $pilihan = array_values($data['perans']);
        $cakupan = $this->cakupanPimpinan($r, $pilihan, $pengguna);
        $alasan = DB::transaction(function () use ($pelaku, $pengguna, $pilihan, $cakupan) {
            $pelaku = $this->kunciPelaku($pelaku);
            $target = $this->kunciPengguna($pengguna->id);
            if ($alasan = $this->alasanTolak($pelaku, $target, false)) {
                return $alasan;
            }
            // PJ lama yang sudah diganti dapat kembali bekerja dengan peran pusat.
            $mantanPj = $target->peran === PeranPengguna::SATKER && ! $target->aktif
                && (! $target->satker_id || User::where('peran', 'satker')->where('satker_id', $target->satker_id)
                    ->where('aktif', true)->whereKeyNot($target->id)->exists());
            $baru = $target->peran === PeranPengguna::SATKER && ! $mantanPj ? ['satker', ...$pilihan] : $pilihan;
            if (! $baru) {
                return 'Pilih minimal satu peran. Untuk mencabut seluruh akses, nonaktifkan user.';
            }
            $lama = array_map(fn ($p) => $p->value, $target->semuaPeran());
            sort($lama);
            $urutBaru = $baru;
            sort($urutBaru);
            $cakupanLama = self::sebutCakupan(self::cakupanAkun($target));
            if ($lama === $urutBaru && self::cakupanAkun($target) === $cakupan) {
                return null;
            }
            foreach ([PeranPengguna::SETBA, PeranPengguna::ADMIN] as $p) {
                if ($target->aktif && $target->punyaPeran($p) && ! in_array($p->value, $baru, true)
                    && ! User::denganPeran($p)->where('aktif', true)->whereKeyNot($target->id)->exists()) {
                    return 'Paling tidak satu akun '.$p->pendek().' harus tetap aktif.';
                }
            }
            $bawaan = in_array($target->peran->value, $baru, true) ? $target->peran->value : $baru[0];
            $target->forceFill(['peran' => $bawaan, 'peran_tambahan' => array_values(array_diff($baru, [$bawaan])),
                'satker_id' => $mantanPj ? null : $target->satker_id] + self::isiCakupan($cakupan))->save();
            Akun::putuskanSesi($target);
            Aktivitas::catat('master.pengguna.peran', 'Mengatur peran '.$target->name, [
                'subjek' => $target,
                'rincian' => ['sebelum' => ['perans' => $lama, 'satker_pimpinan' => $cakupanLama],
                    'sesudah' => ['perans' => $urutBaru, 'satker_pimpinan' => self::sebutCakupan($cakupan)]],
            ]);

            return null;
        }, 3);

        return $this->kembali($pengguna, $r)->with($alasan ? 'gagal' : 'pesan', $alasan ?: 'Peran '.$pengguna->name.' diperbarui. User perlu masuk ulang.');
    }

    /** Izin masuk akun, terpisah dari aktif/tidaknya unit kerja. */
    public function saklar(Request $r, User $pengguna)
    {
        $pelaku = $this->pastikanBoleh($r);
        [$alasan, $aktif] = DB::transaction(function () use ($pelaku, $pengguna) {
            $pelaku = $this->kunciPelaku($pelaku);
            $target = $this->kunciPengguna($pengguna->id);
            if ($alasan = $this->alasanTolak($pelaku, $target, false)) {
                return [$alasan, null];
            }
            $aktif = ! $target->aktif;
            if ($alasan = $aktif ? $this->alasanAktivasi($target) : $this->alasanTerakhir($target, 'aktif')) {
                return [$alasan, null];
            }
            $this->ubahKeadaan($target, $aktif);

            return [null, $aktif];
        }, 3);
        if ($alasan) {
            return $this->kembali($pengguna, $r)->with('gagal', $alasan);
        }

        return $this->kembali($pengguna, $r)->with('pesan', $pengguna->name.($aktif ? ' bisa masuk lagi.' : ' tidak bisa masuk lagi.'));
    }

    /**
     * Tindakan massal (nonaktifkan, aktifkan, akhiri sesi) dengan penjaga yang sama seperti
     * tindakan tunggal; yang dilewati disebut alasannya.
     */
    public function massal(Request $r)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate([
            'aksi' => ['required', Rule::in(['nonaktif', 'aktif', 'sesi'])],
            'id' => ['required', 'array', 'min:1', 'max:200'],
            'id.*' => ['integer'],
        ], ['id.required' => 'Centang paling tidak satu akun.', 'id.min' => 'Centang paling tidak satu akun.']);
        $aksi = $data['aksi'];

        [$ok, $lewat, $perangkat] = DB::transaction(function () use ($pelaku, $data, $aksi) {
            $pelaku = $this->kunciPelaku($pelaku);
            $akun = User::whereIn('id', $data['id'])->orderBy('id')->get();
            $ok = [];
            $lewat = [];
            $perangkat = 0;
            foreach ($akun as $baris) {
                $u = $this->kunciPengguna($baris->id);
                /* Yang terakhir disebut penjaga umum lebih dulu, seperti tindakan tunggal. */
                if ($alasan = $this->alasanTolak($pelaku, $u, false)) {
                    $lewat[] = $alasan;

                    continue;
                }
                if ($aksi === 'sesi') {
                    $n = Sesi::milik($u)->count();
                    if (! $n) {
                        $lewat[] = 'Sebagian tidak punya sesi yang berjalan.';

                        continue;
                    }
                    Akun::putuskanSesi($u);
                    Aktivitas::catat('master.pengguna.sesi', 'Mengakhiri sesi '.$u->name.' di '.$n.' perangkat', [
                        'subjek' => $u, 'rincian' => ['sesudah' => ['sesi' => $n.' perangkat']]]);
                    $perangkat += $n;
                    $ok[] = $u;

                    continue;
                }
                if ($aksi === 'nonaktif') {
                    if (! $u->aktif) {
                        $lewat[] = 'Sebagian sudah nonaktif.';

                        continue;
                    }
                    if ($alasan = $this->alasanTerakhir($u, 'aktif')) {
                        $lewat[] = $alasan;

                        continue;
                    }
                } else {
                    if ($u->aktif) {
                        $lewat[] = 'Sebagian sudah aktif.';

                        continue;
                    }
                    if ($alasan = $this->alasanAktivasi($u)) {
                        $lewat[] = $alasan;

                        continue;
                    }
                }
                $this->ubahKeadaan($u, $aksi === 'aktif');
                $ok[] = $u;
            }

            return [$ok, $lewat, $perangkat];
        }, 3);

        $teksOk = match ($aksi) {
            'nonaktif' => count($ok).' akun dinonaktifkan.',
            'aktif' => count($ok).' akun diaktifkan kembali.',
            'sesi' => 'Sesi '.count($ok).' akun diakhiri di '.$perangkat.' perangkat.',
        };
        $teksNol = match ($aksi) {
            'nonaktif' => 'Tidak ada akun yang dinonaktifkan.',
            'aktif' => 'Tidak ada akun yang diaktifkan kembali.',
            'sesi' => 'Tidak ada sesi yang sedang berjalan.',
        };
        $alasan = array_values(array_unique($lewat));
        $teks = ($ok ? $teksOk : $teksNol).($lewat ? ' '.count($lewat).' dilewati: '.implode(' ', $alasan) : '');

        return $this->kembali(null, $r)->with($ok ? 'pesan' : 'gagal', $teks);
    }

    /** Mengakhiri seluruh sesi satu akun — yang dipakai jendela Detail pengguna. */
    public function sesi(Request $r, User $pengguna)
    {
        $pelaku = $this->pastikanBoleh($r);
        $ke = fn () => redirect()->route('master', ['tab' => 'pengguna', 'jendela' => 'detail-'.$pengguna->id]);
        [$alasan, $n] = DB::transaction(function () use ($pelaku, $pengguna) {
            $pelaku = $this->kunciPelaku($pelaku);
            $target = $this->kunciPengguna($pengguna->id);
            if ($alasan = $this->alasanTolak($pelaku, $target, false)) {
                return [$alasan, 0];
            }
            $n = Sesi::milik($target)->count();
            if (! $n) {
                return ['Tidak ada sesi yang sedang berjalan.', 0];
            }
            Akun::putuskanSesi($target);
            Aktivitas::catat('master.pengguna.sesi', 'Mengakhiri sesi '.$target->name.' di '.$n.' perangkat', [
                'subjek' => $target, 'rincian' => ['sesudah' => ['sesi' => $n.' perangkat']]]);

            return [null, $n];
        }, 3);
        if ($alasan) {
            return $ke()->with('gagal', $alasan);
        }

        return $ke()->with('pesan', 'Sesi '.$pengguna->name.' diakhiri di '.$n.' perangkat.');
    }

    /** Tambah beberapa pengguna dari daftar NIP; hasil pratinjau dihitung ulang di sini. */
    public function banyak(Request $r)
    {
        $pelaku = $this->pastikanBoleh($r);
        $data = $r->validate([
            'nip' => ['required', 'string', 'max:5000'],
            'peran' => ['required', Rule::in(array_map(fn ($p) => $p->value, $pelaku->peranAktif()->bolehMemberi()))],
        ], [
            'nip.required' => 'Tempel paling tidak satu NIP.',
            'peran.required' => 'Pilih perannya.',
            'peran.in' => 'Anda tidak bisa memberikan peran itu.',
        ]);
        $jendela = fn () => redirect()->route('master', ['tab' => 'pengguna', 'jendela' => 'impor'])->withInput();
        $nips = PusatData::uraiNip($data['nip']);
        if (! $nips) {
            return $jendela()->with('gagal', 'Tempel paling tidak satu NIP.');
        }
        if (count($nips) > self::BATAS_IMPOR) {
            return $jendela()->with('gagal', 'Paling banyak '.self::BATAS_IMPOR.' NIP sekali tambah.');
        }

        $baris = self::periksaBanyak($nips);
        $siap = array_values(array_filter($baris, fn ($b) => in_array($b['status'], ['siap', 'kembali'], true)));
        if (! $siap) {
            return $jendela()->with('gagal', 'Tidak ada yang bisa ditambahkan.');
        }
        $peran = PeranPengguna::from($data['peran']);
        $cakupan = $this->cakupanPimpinan($r, [$peran->value]);
        $ditambah = DB::transaction(function () use ($pelaku, $siap, $peran, $cakupan) {
            $this->kunciPelaku($pelaku);
            $n = 0;
            foreach ($siap as $b) {
                /* Pratinjau bisa sudah lama: jangan menimpa akun yang baru
                   diaktifkan atau ditetapkan sebagai penanggung jawab. */
                $ada = User::where('nip', $b['pegawai']['nip'])->lockForUpdate()->first();
                if ($ada && ($ada->aktif || $ada->peran === PeranPengguna::SATKER)) {
                    continue;
                }
                if (User::where('email', $b['pegawai']['email'])->when($ada, fn ($q) => $q->whereKeyNot($ada->id))->lockForUpdate()->first()) {
                    continue;
                }
                $this->daftarkan($b['pegawai'], $peran, $ada, [], $cakupan);
                $n++;
            }

            return $n;
        }, 3);
        $lewat = count($baris) - $ditambah;
        if (! $ditambah) {
            return $jendela()->with('gagal', 'Tidak ada yang bisa ditambahkan. Daftar akun sudah berubah; periksa kembali.');
        }

        return $this->kembali()->with('pesan', $ditambah.' user sekarang bisa masuk sebagai '.$peran->pendek().'.'
            .($lewat ? ' '.$lewat.' dilewati.' : ''));
    }

    /**
     * Periksa daftar NIP satu per satu: siap, diaktifkan kembali, sudah aktif, email dipakai, atau
     * tidak ada di direktori.
     */
    public static function periksaBanyak(array $nips): array
    {
        $hasil = [];
        foreach ($nips as $nip) {
            $pegawai = DirektoriIrm::nip($nip);
            if (! $pegawai) {
                $hasil[] = ['nip' => $nip, 'status' => 'tidak-ada', 'pegawai' => null, 'lama' => null];

                continue;
            }
            $ada = User::with('satker')->where('nip', $pegawai['nip'])->first();
            if ($ada && ($ada->aktif || $ada->peran === PeranPengguna::SATKER)) {
                $hasil[] = ['nip' => $nip, 'status' => 'sudah', 'pegawai' => $pegawai, 'lama' => $ada];

                continue;
            }
            $emailLain = User::where('email', $pegawai['email'])->when($ada, fn ($q) => $q->whereKeyNot($ada->id))->first();
            if ($emailLain) {
                $hasil[] = ['nip' => $nip, 'status' => 'email', 'pegawai' => $pegawai, 'lama' => $emailLain];

                continue;
            }
            $hasil[] = ['nip' => $nip, 'status' => $ada ? 'kembali' : 'siap', 'pegawai' => $pegawai, 'lama' => $ada];
        }

        return $hasil;
    }

    /* ---- Penjaga ---- */

    /**
     * Pimpinan wajib menunjuk Satker yang nyata (satu atau lebih) atau "Semua Satker", termasuk bila
     * hanya peran tambahan. Isian lama `pimpinan_satker_id` (satu Satker) tetap diterima.
     *
     * @return array{ids: list<int>, semua: bool}|null
     */
    private function cakupanPimpinan(Request $r, array $perans, ?User $target = null): ?array
    {
        if (! in_array(PeranPengguna::PIMPINAN->value, $perans, true)) {
            return null;
        }
        if ($r->boolean('pimpinan_semua')) {
            return ['ids' => [], 'semua' => true];
        }
        /* Formulir yang tidak memuat isian cakupan (mis. hanya mengubah peran lain) memakai yang lama. */
        if (! $r->exists('pimpinan_satker') && ! $r->exists('pimpinan_satker_id') && ! $r->exists('pimpinan_semua')
            && $target && ($lama = self::cakupanAkun($target))) {
            return $lama;
        }
        $pilih = $r->exists('pimpinan_satker') ? (array) $r->input('pimpinan_satker') : [$r->input('pimpinan_satker_id')];
        $r->merge(['pimpinan_satker' => array_values(array_filter($pilih, fn ($x) => $x !== null && $x !== ''))]);
        $data = $r->validate([
            'pimpinan_satker' => ['required', 'array', 'max:200'],
            'pimpinan_satker.*' => ['integer', Rule::exists('satkers', 'id')],
        ], [
            'pimpinan_satker.required' => 'Pilih Satker yang dipimpin untuk peran Pimpinan, atau Semua Satker.',
            'pimpinan_satker.*.integer' => 'Pilih Satker dari daftar yang tersedia.',
            'pimpinan_satker.*.exists' => 'Satker yang dipilih tidak ditemukan. Muat ulang daftar.',
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['pimpinan_satker'])));
        sort($ids);

        return ['ids' => $ids, 'semua' => false];
    }

    /** Cakupan pimpinan yang tersimpan, bentuknya sama dengan cakupanPimpinan(). */
    private static function cakupanAkun(User $u): ?array
    {
        if ($u->pimpinan_semua) {
            return ['ids' => [], 'semua' => true];
        }
        $ids = array_values(array_unique(array_map('intval', (array) ($u->pimpinan_satker_ids ?? []))));
        sort($ids);

        return $ids ? ['ids' => $ids, 'semua' => false] : null;
    }

    private static function isiCakupan(?array $c): array
    {
        return ['pimpinan_satker_ids' => $c && ! $c['semua'] ? $c['ids'] : null, 'pimpinan_semua' => (bool) ($c['semua'] ?? false)];
    }

    /** Untuk log: "Semua Satker" atau nama Satker-nya. */
    private static function sebutCakupan(?array $c): ?string
    {
        if (! $c) {
            return null;
        }

        return $c['semua'] ? 'Semua Satker'
            : Satker::whereKey($c['ids'])->orderBy('id')->get()->map->namaPendek()->join(', ');
    }

    private function pastikanBoleh(Request $r): User
    {
        $u = $r->user();
        abort_unless($u->peranAktif()->kelolaMaster(), 403, 'User hanya bisa diatur Admin DTI.');

        return $u;
    }

    /** Penolakan yang berlaku untuk semua perubahan akun orang lain. */
    private function alasanTolak(User $pelaku, User $target, bool $ubahPeran = true): ?string
    {
        return match (true) {
            $pelaku->is($target) => 'Akun Anda sendiri tidak bisa diubah dari sini. Minta Admin DTI lain.',
            $ubahPeran && $target->peran === PeranPengguna::SATKER => 'Peran dan penanggung jawab akun satuan kerja diatur lewat tab Satuan kerja.',
            $target->peran !== PeranPengguna::SATKER && ! in_array($target->peran, $pelaku->peranAktif()->bolehMemberi(), true) => 'Akun '.$target->peran->pendek().' hanya bisa diatur Admin DTI.',
            default => null,
        };
    }

    /** Semua perubahan izin memakai kunci yang sama agar akun terakhir tetap ada. */
    private function kunciPelaku(User $pelaku): User
    {
        $penting = User::denganPeran([PeranPengguna::ADMIN, PeranPengguna::SETBA])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $segar = $penting->get($pelaku->id);
        abort_unless($segar?->aktif && $segar->peranAktif()->kelolaMaster(), 403, 'Izin akun Anda berubah. Muat ulang halaman.');

        return $segar;
    }

    /** Kunci unit lebih dulu, sama seperti pergantian penanggung jawab. */
    private function kunciPengguna(int $id): User
    {
        $unit = User::whereKey($id)->value('satker_id');
        if ($unit) {
            Satker::whereKey($unit)->lockForUpdate()->first();
        }
        $target = User::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($target->satker_id && (int) $target->satker_id !== (int) $unit) {
            Satker::whereKey($target->satker_id)->lockForUpdate()->first();
        }

        return $target;
    }

    /** Aktivasi Satker tetap menjaga satu penanggung jawab aktif per unit. */
    private function alasanAktivasi(User $target): ?string
    {
        if ($target->nip && User::where('nip', $target->nip)->whereKeyNot($target->id)->where('aktif', true)->exists()) {
            return 'NIP ini sudah dipakai akun aktif lain.';
        }
        if ($target->punyaPeran(PeranPengguna::PIMPINAN) && ! $target->satkerDipimpin()) {
            return 'Tentukan Satker yang dipimpin melalui Kelola akses sebelum mengaktifkan user Pimpinan.';
        }
        if ($target->peran !== PeranPengguna::SATKER) {
            return null;
        }
        $unit = $target->satker;
        if (! $unit) {
            return 'Akun satuan kerja belum terhubung ke unit. Tetapkan penanggung jawab lewat tab Satuan kerja.';
        }
        $lain = User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $unit->id)
            ->where('aktif', true)->whereKeyNot($target->id)->lockForUpdate()->first();

        return $lain ? 'Unit '.$unit->namaPendek().' sudah memiliki penanggung jawab aktif. Ganti penanggung jawab lewat tab Satuan kerja.' : null;
    }

    /** Setba dan Admin DTI terakhir yang aktif tidak boleh hilang. */
    private function alasanTerakhir(User $target, string $apa, array $tetap = []): ?string
    {
        foreach ([PeranPengguna::SETBA, PeranPengguna::ADMIN] as $p) {
            if (! in_array($p, $tetap, true) && $target->punyaPeran($p) && $target->aktif
                && ! User::denganPeran($p)->where('aktif', true)->whereKeyNot($target->id)->exists()) {
                return 'Paling tidak satu akun '.$p->pendek().' harus tetap aktif'
                    .($apa === 'peran' ? ', jadi peran akun ini belum bisa diubah.' : '.');
            }
        }

        return null;
    }

    /** Menyalakan atau mematikan satu akun, putus sesinya bila dimatikan, dan mencatatnya. */
    private function ubahKeadaan(User $u, bool $aktif): void
    {
        $u->update(['aktif' => $aktif]);
        if (! $aktif) {
            Akun::putuskanSesi($u);
        }
        Aktivitas::catat('master.pengguna.saklar', ($aktif ? 'Mengaktifkan kembali akun ' : 'Menonaktifkan akun ')
            .$u->name.' ('.$u->peran->pendek().')', [
                'subjek' => $u,
                'rincian' => ['peran_target' => $u->peran->value, 'perans_target' => array_map(fn ($p) => $p->value, $u->semuaPeran()),
                    'sebelum' => ['aktif' => ! $aktif], 'sesudah' => ['aktif' => $aktif]],
            ]);
    }

    /** Kembali ke daftar Pengguna beserta filternya; hanya alamat di Data master yang dipercaya. */
    private function kembali(?User $u = null, ?Request $r = null)
    {
        if ($k = PusatData::alamatKembali($r?->input('kembali'))) {
            return redirect($k.($u ? (str_contains($k, '?') ? '&' : '?').'kartu=akun-'.$u->id : ''));
        }

        return redirect()->route('master', array_filter(['tab' => 'pengguna', 'kartu' => $u ? 'akun-'.$u->id : null]));
    }
}
