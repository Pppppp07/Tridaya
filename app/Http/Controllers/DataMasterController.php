<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Models\KategoriTemuan;
use App\Models\LogAktivitas;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data master — padanan `LayarMaster` prototipe.
 *
 * Sejak 27 Sep satu halaman bertab (Bang Kamal: "Gua tuh menghindari banyak
 * scroll"): Unit kerja (UnitKerjaController), Pengguna (PenggunaController),
 * Kategori temuan, Kategori internal, Sifat rekomendasi, dan Riwayat
 * perubahan. Baris tabel hanya dibaca; menambah dan mengubah lewat jendela
 * melayang (`?jendela=`), yang digambar di server supaya tetap jalan tanpa
 * skrip.
 *
 * Daftar-daftar ini boleh berubah mengikuti kebiasaan mereka sendiri tanpa
 * mengubah kode. Bang Kamal: "Jangan nanti mentang-mentang ngubahnya di
 * hardcode … Bisa di-on-off." Tidak ada yang dihapus, hanya dinonaktifkan:
 * menghapusnya membuat berkas lama menunjuk ke isi daftar yang sudah tidak ada.
 * Nama yang sudah dipakai berkas terkunci.
 *
 * Setiap perubahan tercatat di log aktivitas, lengkap dengan sebelum →
 * sesudah — terbaca di tab Riwayat perubahan dan di Log aktivitas milik DTI.
 *
 * Yang TIDAK bisa diubah dari sini: bentuk tindak lanjut dan alasan sah tidak
 * dapat ditindaklanjuti. Keduanya menyalin SOP dan peraturan.
 */
class DataMasterController extends Controller
{
    public const TAB = ['unit', 'pengguna', 'temuan', 'intern', 'sifat', 'riwayat'];

    /** Urutan daftar Pengguna, sama dengan prototipe (URUT_PERAN). */
    private const URUT_PERAN = ['setba', 'uki', 'inspektorat', 'pimpinan', 'dti', 'admin', 'satker'];

    public function index(Request $req)
    {
        $this->pastikanSetba($req);

        /* Alamat lama (?sorot=m-unit) tetap mendarat di tab yang sama. */
        $lama = ['m-unit' => 'unit', 'm-temuan' => 'temuan', 'm-intern' => 'intern', 'm-sifat' => 'sifat'];
        $tab = (string) $req->query('tab', $lama[(string) $req->query('sorot')] ?? 'unit');
        $tab = in_array($tab, self::TAB, true) ? $tab : 'unit';
        if ($req->filled('pj')) {
            $tab = 'unit';
        }

        $unit = Satker::with('penanggungJawab')->orderBy('id')->get();
        $sifat = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->orderBy('urutan')->orderBy('id')->get();
        $kategori = $this->kategoriIntern();
        $kategoriTemuan = KategoriTemuan::orderBy('urutan')->orderBy('id')->get();
        $akun = $this->akun();

        $data = [
            'tab'        => $tab,
            'cari'       => mb_substr(trim((string) $req->query('cari', '')), 0, 100),
            'filter'     => (string) $req->query('filter', ''),
            'unit'       => $unit,
            'pakaiUnit'  => $this->pakaiUnit(),
            'kunciUnit'  => $this->kunciUnit(),
            'sifat'      => $sifat,
            'pakaiSifat' => $this->pakaiSifat($sifat),
            'kategori'   => $kategori,
            /* Berapa temuan memakainya. Yang dipakai tidak boleh hilang
               diam-diam — angkanya yang membuat orang berpikir dua kali sebelum
               menonaktifkan. */
            'terpakai' => Temuan::selectRaw('kategori_intern_id, count(*) n')
                ->whereNotNull('kategori_intern_id')
                ->groupBy('kategori_intern_id')->pluck('n', 'kategori_intern_id'),
            'kategoriTemuan' => $kategoriTemuan->groupBy(fn ($k) => $k->sumber->value),
            'terpakaiTemuan' => Temuan::selectRaw('kategori_temuan_id, count(*) n')
                ->whereNotNull('kategori_temuan_id')
                ->groupBy('kategori_temuan_id')->pluck('n', 'kategori_temuan_id'),
            'akun'       => $akun,
            'saya'       => $req->user(),
            'bolehBeri'  => $req->user()->peran->bolehMemberi(),
            'jumlahRiwayat' => null,
            'riwayat'    => collect(),
            'jendela'    => null,
            'pilih'      => null,
        ];

        if ($tab === 'riwayat') {
            [$data['riwayat'], $data['jumlahRiwayat']] = $this->riwayat($data['filter'], $data['cari'], max(30, $req->integer('batas', 30)));
        }

        /* Jendela pemilih penanggung jawab, kalau sedang dibuka. Digambar di
           server supaya jalan tanpa skrip; skrip cuma mencarikan sambil
           mengetik. */
        if ($req->filled('pj') && ($unitPilih = $unit->firstWhere('id', $req->integer('pj')))) {
            $data['pilih'] = UnitKerjaController::hasilIrm($unitPilih, (string) $req->query('q', ''));
        }
        $data['jendela'] = $this->jendela($req, $data);

        return view('data-master', $data);
    }

    /* ================================================================
       KATEGORI INTERNAL
       ================================================================ */

    public function tambah(Request $req)
    {
        $this->pastikanSetba($req);
        $data = $req->validate(['nama' => ['required', 'string', 'max:120']], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);
        if ($this->kembarReferensi(JenisReferensi::KATEGORI_INTERN, $nama)) {
            return $this->gagal('intern', 'Kategori dengan nama itu sudah ada.', 'intern');
        }

        $k = Referensi::create([
            'jenis'  => JenisReferensi::KATEGORI_INTERN->value,
            'nama'   => $nama,
            /* Tidak berwarna lagi (27 Sep); kolomnya tetap diisi abu, sama
               dengan prototipe, supaya data lama dan baru seragam. */
            'warna'  => \App\Enums\WarnaLabel::ABU->value,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->max('urutan') + 1,
            'aktif'  => true,
        ]);
        Aktivitas::catat('master.intern.tambah', 'Menambah kategori internal '.$nama, [
            'subjek' => $k, 'rincian' => ['sesudah' => ['nama' => $nama, 'aktif' => true]],
        ]);

        return $this->kembali('intern', 'kat-'.$k->id);
    }

    /**
     * Nama yang sudah dipakai temuan terkunci (27 Sep), sama dengan kategori
     * temuan dan sifat — prototipe menyimpan kategori menurut namanya, jadi
     * mengganti nama di sana memutus temuan lama. Di sini temuan menunjuk id,
     * tapi aturannya disamakan: satu aturan untuk ketiga daftar.
     */
    public function simpan(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanIntern($referensi);
        $data = $req->validate(['nama' => ['required', 'string', 'max:120']], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);

        if (Temuan::where('kategori_intern_id', $referensi->id)->exists()) {
            return $this->kembali('intern', 'kat-'.$referensi->id)
                ->with('gagal', 'Kategori ini sudah dipakai temuan, jadi namanya tidak bisa diganti.');
        }
        if ($this->kembarReferensi(JenisReferensi::KATEGORI_INTERN, $nama, $referensi->id)) {
            return $this->gagal('intern', 'Kategori dengan nama itu sudah ada.', 'intern-'.$referensi->id);
        }

        $lama = $referensi->nama;
        $referensi->update(['nama' => $nama]);
        if ($lama !== $nama) {
            Aktivitas::catat('master.intern.ubah', 'Mengubah nama kategori internal', [
                'subjek' => $referensi, 'rincian' => ['sebelum' => ['nama' => $lama], 'sesudah' => ['nama' => $nama]],
            ]);
        }

        return $this->kembali('intern', 'kat-'.$referensi->id);
    }

    /**
     * Menyalakan atau memadamkan, bukan menghapus. Kategori yang sudah dipakai
     * temuan tidak boleh hilang: keterangannya tetap harus terbaca di berkas
     * lama. Yang berubah cuma munculnya di pilihan saat mencatat laporan baru.
     */
    public function saklar(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanIntern($referensi);

        $referensi->update(['aktif' => ! $referensi->aktif]);
        Aktivitas::catat('master.intern.saklar', ($referensi->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' kategori internal '.$referensi->nama, [
            'subjek' => $referensi, 'rincian' => ['sebelum' => ['aktif' => ! $referensi->aktif], 'sesudah' => ['aktif' => $referensi->aktif]],
        ]);

        return $this->kembali('intern', 'kat-'.$referensi->id);
    }

    /* ================================================================
       KATEGORI TEMUAN
       ================================================================ */

    public function tambahTemuan(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama'   => ['required', 'string', 'max:160'],
            'sumber' => ['required', 'in:LHP,LHA'],
        ], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);

        /* Nama yang sama pada sumber yang sama cuma membingungkan yang memilih:
           dua baris bertulisan sama, dan tidak ada cara membedakannya. */
        $ada = KategoriTemuan::where('sumber', $data['sumber'])
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->exists();
        if ($ada) {
            return $this->gagal('temuan', 'Kategori dengan nama itu sudah ada.', 'temuan', ['sumber' => $data['sumber'], 'filter' => $data['sumber']]);
        }

        $k = KategoriTemuan::create([
            'sumber' => $data['sumber'],
            'nama'   => $nama,
            'urutan' => (int) KategoriTemuan::where('sumber', $data['sumber'])->max('urutan') + 1,
            'aktif'  => true,
        ]);
        Aktivitas::catat('master.temuan.tambah', 'Menambah kategori temuan '.$data['sumber'].': '.$nama, [
            'subjek' => $k, 'rincian' => ['sesudah' => ['nama' => $nama, 'sumber' => $data['sumber'], 'aktif' => true]],
        ]);

        return $this->kembali('temuan', 'kt-'.$k->id, ['filter' => $data['sumber']]);
    }

    /**
     * Nama yang sudah dipakai temuan tidak diubah dari sini: yang dibaca orang
     * pada berkas lama adalah nama itu, dan menggantinya berarti mengubah
     * keterangan berkas yang sudah selesai.
     */
    public function simpanTemuan(Request $req, KategoriTemuan $kategori)
    {
        $this->pastikanSetba($req);

        $data = $req->validate(['nama' => ['required', 'string', 'max:160']], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);
        $filter = ['filter' => $kategori->sumber->value];

        if (Temuan::where('kategori_temuan_id', $kategori->id)->exists()) {
            return $this->kembali('temuan', 'kt-'.$kategori->id, $filter)
                ->with('gagal', 'Kategori ini sudah dipakai temuan, jadi namanya tidak bisa diganti.');
        }
        if (KategoriTemuan::where('sumber', $kategori->sumber->value)->whereKeyNot($kategori->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->exists()) {
            return $this->gagal('temuan', 'Kategori dengan nama itu sudah ada.', 'temuan-'.$kategori->id, $filter);
        }

        $lama = $kategori->nama;
        $kategori->update(['nama' => $nama]);
        if ($lama !== $nama) {
            Aktivitas::catat('master.temuan.ubah', 'Mengubah nama kategori temuan '.$kategori->sumber->value, [
                'subjek' => $kategori, 'rincian' => ['sebelum' => ['nama' => $lama], 'sesudah' => ['nama' => $nama]],
            ]);
        }

        return $this->kembali('temuan', 'kt-'.$kategori->id, $filter);
    }

    public function saklarTemuan(Request $req, KategoriTemuan $kategori)
    {
        $this->pastikanSetba($req);

        $kategori->update(['aktif' => ! $kategori->aktif]);
        Aktivitas::catat('master.temuan.saklar', ($kategori->aktif ? 'Mengaktifkan' : 'Menonaktifkan')
            .' kategori temuan '.$kategori->sumber->value.': '.$kategori->nama, [
                'subjek' => $kategori, 'rincian' => ['sebelum' => ['aktif' => ! $kategori->aktif], 'sesudah' => ['aktif' => $kategori->aktif]],
            ]);

        return $this->kembali('temuan', 'kt-'.$kategori->id, ['filter' => $kategori->sumber->value]);
    }

    /* ================================================================
       SIFAT REKOMENDASI
       ================================================================

       Bang Kamal, menunjuk isian Sifat di formulir: "Iya, ini master." Tiap
       sifat membawa satu keterangan yang mengubah perilaku formulir — menuntut
       penyetoran uang (`perlu_nilai`) atau tidak — jadi keterangan itu
       ditetapkan saat sifatnya dibuat dan terkunci begitu dipakai. */

    public function tambahSifat(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama' => ['required', 'string', 'max:120'],
            'uang' => ['nullable', 'boolean'],
        ], ['nama.required' => 'Nama sifat harus terisi.']);
        $nama = $this->rapikan($data['nama']);
        if ($this->kembarReferensi(JenisReferensi::SIFAT_REKOM, $nama)) {
            return $this->gagal('sifat', 'Sifat dengan nama itu sudah ada.', 'sifat');
        }

        $uang = (bool) ($data['uang'] ?? false);
        $x = Referensi::create([
            'jenis'       => JenisReferensi::SIFAT_REKOM->value,
            'nama'        => $nama,
            'perlu_nilai' => $uang,
            'urutan'      => (int) Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->max('urutan') + 1,
            'aktif'       => true,
        ]);
        Aktivitas::catat('master.sifat.tambah', 'Menambah sifat rekomendasi '.$nama, [
            'subjek' => $x, 'rincian' => ['sesudah' => ['nama' => $nama, 'uang' => $uang, 'aktif' => true]],
        ]);

        return $this->kembali('sifat', 'sifat-'.$x->id);
    }

    /** Nama dan keterangan uangnya hanya boleh diganti selama belum dipakai. */
    public function simpanSifat(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanSifat($referensi);

        $data = $req->validate([
            'nama' => ['required', 'string', 'max:120'],
            'uang' => ['nullable', 'boolean'],
        ], ['nama.required' => 'Nama sifat harus terisi.']);
        if ($this->sifatDipakai($referensi)) {
            return $this->kembali('sifat', 'sifat-'.$referensi->id)
                ->with('gagal', 'Sifat ini sudah dipakai rekomendasi, jadi tidak bisa diubah.');
        }
        $nama = $this->rapikan($data['nama']);
        if ($this->kembarReferensi(JenisReferensi::SIFAT_REKOM, $nama, $referensi->id)) {
            return $this->gagal('sifat', 'Sifat dengan nama itu sudah ada.', 'sifat-'.$referensi->id);
        }

        $sebelum = ['nama' => $referensi->nama, 'uang' => (bool) $referensi->perlu_nilai];
        $sesudah = ['nama' => $nama, 'uang' => (bool) ($data['uang'] ?? false)];
        $referensi->update(['nama' => $nama, 'perlu_nilai' => $sesudah['uang']]);
        if ($beda = Aktivitas::beda($sebelum, $sesudah)) {
            Aktivitas::catat('master.sifat.ubah', 'Mengubah sifat rekomendasi '.$sebelum['nama'], ['subjek' => $referensi, 'rincian' => $beda]);
        }

        return $this->kembali('sifat', 'sifat-'.$referensi->id);
    }

    /** Paling tidak satu sifat tetap aktif — formulir rekomendasi butuh pilihan. */
    public function saklarSifat(Request $req, Referensi $referensi)
    {
        $this->pastikanSetba($req);
        $this->pastikanSifat($referensi);

        $sisa = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
            ->where('aktif', true)->whereKeyNot($referensi->id)->count();
        if ($referensi->aktif && $sisa === 0) {
            return $this->kembali('sifat', 'sifat-'.$referensi->id)
                ->with('gagal', 'Paling tidak satu sifat harus tetap aktif.');
        }

        $referensi->update(['aktif' => ! $referensi->aktif]);
        Aktivitas::catat('master.sifat.saklar', ($referensi->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' sifat rekomendasi '.$referensi->nama, [
            'subjek' => $referensi, 'rincian' => ['sebelum' => ['aktif' => ! $referensi->aktif], 'sesudah' => ['aktif' => $referensi->aktif]],
        ]);

        return $this->kembali('sifat', 'sifat-'.$referensi->id);
    }

    /* ================================================================
       ISI HALAMAN
       ================================================================ */

    /**
     * Rekomendasi yang menugasi tiap unit kerja, dan yang belum selesai —
     * angka kedua yang ditanyakan sebelum unit kerjanya dipadamkan. `beres()`
     * butuh seluruh baris dan laporannya, jadi rekomendasinya dimuat sekali
     * dengan muatan bersama, bukan satu kueri per unit kerja.
     *
     * @return array<int, array{rek:int, jalan:int}>
     */
    private function pakaiUnit(): array
    {
        $h = [];
        /* `sasaran` (lintas tindak lanjut) yang dibaca semuaBaris() — dimuat
           sekaligus. Dulu tidak, dan tiap rekomendasi menanyakannya sendiri:
           98 kueri di halaman ini untuk data contoh (27 Sep). */
        foreach (Rekomendasi::with('sasaran', 'tindakan', 'temuan.laporan')->get() as $r) {
            $beres = $r->beres();
            foreach ($r->semuaBaris()->pluck('satker_id')->unique() as $id) {
                $h[$id]['rek'] = ($h[$id]['rek'] ?? 0) + 1;
                $h[$id]['jalan'] = ($h[$id]['jalan'] ?? 0) + ($beres ? 0 : 1);
            }
        }

        return $h;
    }

    /** Unit kerja yang namanya sudah tertulis di berkas — terkunci. */
    private function kunciUnit(): Collection
    {
        return Sasaran::distinct()->pluck('satker_id')
            ->merge(DB::table('temuan_satker')->distinct()->pluck('satker_id'))
            ->unique()->flip();
    }

    private function pakaiSifat(Collection $sifat): array
    {
        /* Rekomendasi tanpa sifat dibaca Administratif, sama dengan prototipe. */
        $administratif = $sifat->firstWhere('nama', 'Administratif')?->id;

        return Rekomendasi::selectRaw('sifat_id, count(*) n')->groupBy('sifat_id')->get()
            ->reduce(function ($h, $x) use ($administratif) {
                $id = $x->sifat_id ?? $administratif;
                $h[$id] = ($h[$id] ?? 0) + $x->n;

                return $h;
            }, []);
    }

    /** Seluruh akun, urut sama dengan prototipe: aktif dulu, lalu peran, unit, nama. */
    private function akun(): Collection
    {
        $urutUnit = Satker::orderBy('id')->pluck('id')->flip();
        $kunci = fn ($u) => [(int) ! $u->aktif, (int) array_search($u->peran->value, self::URUT_PERAN, true),
            $u->peran === PeranPengguna::SATKER ? ($urutUnit[$u->satker_id] ?? 999) : 0, $u->name];

        return User::with('satker')->get()->sort(fn ($a, $b) => $kunci($a) <=> $kunci($b))->values();
    }

    /**
     * Riwayat perubahan data master dan hak akses — potongan log aktivitas.
     *
     * @return array{0: Collection, 1: int}
     */
    private function riwayat(string $filter, string $cari, int $batas): array
    {
        $q = LogAktivitas::query()->whereIn('kelompok', ['master', 'pengguna'])
            ->when($filter === 'pengguna', fn ($q) => $q->where('kelompok', 'pengguna'))
            ->when(in_array($filter, ['unit', 'temuan', 'intern', 'sifat'], true), fn ($q) => $q->where('aksi', 'like', 'master.'.$filter.'%'))
            ->when($cari !== '', function ($q) use ($cari) {
                $kata = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari).'%';
                $q->where(fn ($w) => $w->where('ringkasan', 'like', $kata)->orWhere('nama', 'like', $kata));
            });
        $n = (clone $q)->count();

        return [$q->orderByDesc('waktu')->orderByDesc('id')->limit($batas)->get(), $n];
    }

    /**
     * Jendela yang sedang dibuka, dibaca dari `?jendela=`: "unit" (tambah),
     * "unit-5" (ubah), "pengguna", "akun-7", "temuan", "temuan-3", "intern",
     * "intern-2", "sifat", "sifat-1". Yang menunjuk isi yang tidak ada, atau
     * yang terkunci, diabaikan.
     */
    private function jendela(Request $req, array $d): ?array
    {
        $j = (string) $req->query('jendela', '');
        if ($j === '') {
            return null;
        }
        [$jenis, $id] = array_pad(explode('-', $j, 2), 2, null);
        $id = $id !== null ? (int) $id : null;

        switch ($jenis) {
            case 'unit':
                if ($id === null) {
                    return ['jenis' => 'unit', 'x' => null];
                }
                $u = $d['unit']->firstWhere('id', $id);

                return $u && ! isset($d['kunciUnit'][$u->id]) ? ['jenis' => 'unit', 'x' => $u] : null;
            case 'pengguna':
                if (! $d['bolehBeri']) {
                    return null;
                }
                $nip = preg_replace('/\D/', '', (string) $req->query('nip', ''));

                return ['jenis' => 'pengguna', 'cari' => PenggunaController::hasilCari((string) $req->query('q', '')),
                    'pilih' => $nip ? \App\Support\DirektoriIrm::nip($nip) : null];
            case 'akun':
                $a = $d['akun']->firstWhere('id', $id);

                return $a && $a->aktif ? ['jenis' => 'akun', 'x' => $a] : null;
            case 'temuan':
                if ($id === null) {
                    return ['jenis' => 'temuan', 'x' => null,
                        'sumber' => in_array($req->query('sumber'), ['LHP', 'LHA'], true) ? $req->query('sumber') : 'LHP'];
                }
                $k = $d['kategoriTemuan']->flatten()->firstWhere('id', $id);

                return $k && ! isset($d['terpakaiTemuan'][$k->id]) ? ['jenis' => 'temuan', 'x' => $k] : null;
            case 'intern':
                if ($id === null) {
                    return ['jenis' => 'intern', 'x' => null];
                }
                $k = $d['kategori']->firstWhere('id', $id);

                return $k && ! isset($d['terpakai'][$k->id]) ? ['jenis' => 'intern', 'x' => $k] : null;
            case 'sifat':
                if ($id === null) {
                    return ['jenis' => 'sifat', 'x' => null];
                }
                $x = $d['sifat']->firstWhere('id', $id);

                return $x && ! ($d['pakaiSifat'][$x->id] ?? 0) ? ['jenis' => 'sifat', 'x' => $x] : null;
        }

        return null;
    }

    /* ================================================================
       PENJAGA
       ================================================================ */

    private function kategoriIntern()
    {
        return Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->orderBy('urutan')->orderBy('id')->get();
    }

    /** Kembali ke tabnya sendiri, dengan baris yang baru diubah disorot. */
    private function kembali(string $tab, ?string $baris = null, array $lain = [])
    {
        return redirect()->route('master', array_filter(['tab' => $tab, 'kartu' => $baris] + $lain));
    }

    /** Kembali ke jendela yang sama, isian dan pesan salahnya dibawa. */
    private function gagal(string $tab, string $pesan, string $jendela, array $lain = [])
    {
        return redirect()->route('master', ['tab' => $tab, 'jendela' => $jendela] + $lain)
            ->withInput()->with('gagal', $pesan);
    }

    private function rapikan(string $x): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $x));
    }

    private function kembarReferensi(JenisReferensi $jenis, string $nama, ?int $kecuali = null): bool
    {
        return Referensi::where('jenis', $jenis->value)->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->exists();
    }

    private function sifatDipakai(Referensi $r): bool
    {
        return Rekomendasi::where('sifat_id', $r->id)->exists()
            || ($r->nama === 'Administratif' && Rekomendasi::whereNull('sifat_id')->exists());
    }

    private function pastikanSifat(Referensi $r): void
    {
        abort_unless($r->jenis === JenisReferensi::SIFAT_REKOM, 403,
            'Daftar ini bukan sifat rekomendasi.');
    }

    private function pastikanSetba(Request $req): void
    {
        abort_unless($req->user()->peran->kelolaMaster(), 403,
            'Data master hanya bisa diubah Setba.');
    }

    private function pastikanIntern(Referensi $r): void
    {
        abort_unless($r->jenis === JenisReferensi::KATEGORI_INTERN, 403,
            'Daftar ini menyalin SOP dan tidak bisa diubah dari layar ini.');
    }
}
