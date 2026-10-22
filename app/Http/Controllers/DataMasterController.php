<?php

namespace App\Http\Controllers;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Models\AlertKeamanan;
use App\Models\KategoriTemuan;
use App\Models\LogAktivitas;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\SumberBukti;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\BedaLog;
use App\Support\DirektoriIrm;
use App\Support\PusatData;
use App\Support\Sesi;
use App\Support\TampilanLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Data master milik Admin DTI: satu halaman bertab. */
class DataMasterController extends Controller
{
    public const TAB = ['ringkasan', 'pengguna', 'peran', 'unit', 'temuan', 'intern', 'sifat', 'bentuk', 'sumber', 'riwayat'];

    /** Daftar yang satu tab di deret ("Kategori & referensi"). */
    public const DAFTAR = ['temuan', 'intern', 'sifat', 'bentuk'];

    /** Batas dokumen usulan satu bentuk tindak lanjut. */
    private const MAKS_DOKUMEN = 20;

    private const RENTANG = ['7', '30', '90', 'semua'];

    public function index(Request $req)
    {
        $this->pastikanAdmin($req);

        /* Alamat lama (?sorot=m-unit) tetap mendarat di tab yang sama. */
        $lama = ['m-unit' => 'unit', 'm-temuan' => 'temuan', 'm-intern' => 'intern', 'm-sifat' => 'sifat'];
        $tab = (string) $req->query('tab', $lama[(string) $req->query('sorot')] ?? 'ringkasan');
        $tab = in_array($tab, self::TAB, true) ? $tab : 'ringkasan';
        if ($req->filled('pj')) {
            $tab = 'unit';
        }
        $hariIni = Aktivitas::waktuKini();
        $kategoriTab = in_array($tab, self::DAFTAR, true);

        $unit = Satker::with('penanggungJawab')->orderBy('id')->get();
        $akun = $this->akun();
        $f = $this->saring($req, $tab);

        $data = [
            'tab' => $tab,
            'cari' => $f['q'],
            'filter' => $f['filter'],
            'f' => $f,
            'param' => $this->param($tab, $f),
            'hariIni' => $hariIni,
            'unit' => $unit,
            'akun' => $akun,
            'jmlAkun' => PusatData::hitungAkun($akun),
            'tanpaPj' => $unit->filter(fn ($u) => $u->aktif && ! $u->penanggungJawab)->count(),
            /* Hanya butir yang awas, untuk penanda merah di deret tab. */
            'jumlahAwas' => count(PusatData::periksaKesehatan($unit, $akun, $hariIni, null)),
            'jumlahKategori' => KategoriTemuan::count() + Referensi::whereIn('jenis',
                [JenisReferensi::KATEGORI_INTERN->value, JenisReferensi::SIFAT_REKOM->value, JenisReferensi::BENTUK_TL->value])->count(),
            'saya' => $req->user(),
            'bolehBeri' => $req->user()->peranAktif()->bolehMemberi(),
            'jumlahRiwayat' => null,
            'riwayat' => collect(),
            'jendela' => null,
            'pilih' => null,
        ];

        if ($tab === 'ringkasan') {
            $data += $this->ringkasan($unit, $akun, $hariIni);
        }
        if ($tab === 'pengguna') {
            $data['akunTampil'] = PusatData::urutkanAkun(
                PusatData::saringAkun($akun, $f, $hariIni), $f['urut'], $f['arah']);
        }
        if ($tab === 'peran') {
            $data['perAkun'] = $akun->where('aktif', true)->flatMap(fn ($a) => array_map(fn ($p) => $p->value, $a->semuaPeran()))->countBy();
        }
        if ($tab === 'sumber') {
            $data['sumberBukti'] = SumberBukti::orderBy('id')->get();
        }
        if ($tab === 'unit') {
            $pakai = $this->pakaiUnit();
            $data['pakaiUnit'] = $pakai;
            $data['kunciUnit'] = $this->kunciUnit();
            $data['unitTampil'] = PusatData::saringUnit($unit, $f, $pakai);
            $data['jmlUnit'] = [
                'semua' => $unit->count(), 'aktif' => $unit->where('aktif', true)->count(),
                'nonaktif' => $unit->where('aktif', false)->count(), 'tanpa-pj' => $data['tanpaPj'],
            ];
        }
        if ($kategoriTab) {
            $data += $this->kategoriData();
        }
        if ($tab === 'riwayat') {
            [$data['riwayat'], $data['jumlahRiwayat']] = $this->riwayat($f, max(30, $req->integer('batas', 30)));
            $data['pelaku'] = LogAktivitas::whereIn('kelompok', ['master', 'pengguna'])->whereNotNull('user_id')
                ->select('user_id', 'nama')->distinct()->orderBy('nama')->get();
            $data['totalRiwayat'] = LogAktivitas::whereIn('kelompok', ['master', 'pengguna'])->count();
        }

        /* Jendela pemilih penanggung jawab digambar di server, jadi jalan tanpa skrip. */
        if ($req->filled('pj') && ($unitPilih = $unit->firstWhere('id', $req->integer('pj')))) {
            $data['pilih'] = UnitKerjaController::hasilIrm($unitPilih, (string) $req->query('q', ''));
        }
        $data['jendela'] = $this->jendela($req, $data);

        return view('data-master', $data);
    }

    /* ---- Ringkasan ---- */

    /** Ringkasan administrasi dan tiga log terbaru. */
    private function ringkasan(Collection $unit, Collection $akun, Carbon $hariIni): array
    {
        $kat = $this->kategoriData();
        $aktif = [
            'temuan' => $kat['kategoriTemuan']->flatten()->where('aktif', true)->count(),
            'intern' => $kat['kategori']->where('aktif', true)->count(),
            'sifat' => $kat['sifat']->where('aktif', true)->count(),
            'bentuk' => $kat['bentuk']->where('aktif', true)->count(),
        ];
        $alertTerbuka = AlertKeamanan::whereIn('status', ['baru', 'perlu_tindak_lanjut']);

        return [
            'aktifKategori' => $aktif,
            'perluDiurus' => PusatData::periksaKesehatan($unit, $akun, $hariIni, null),
            'jumlahAlert' => (clone $alertTerbuka)->count(),
            'mulaiPantau' => \App\Support\AlertKeamanan::mulaiPada(),
            'terbaru' => LogAktivitas::whereIn('kelompok', ['master', 'pengguna'])
                ->orderByDesc('waktu')->orderByDesc('id')->limit(3)->get(),
            'totalRiwayat' => LogAktivitas::whereIn('kelompok', ['master', 'pengguna'])->count(),
        ];
    }

    /** Keempat daftar beserta berapa yang memakainya. */
    private function kategoriData(): array
    {
        $sifat = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->orderBy('urutan')->orderBy('id')->get();

        return [
            'sifat' => $sifat,
            'pakaiSifat' => $this->pakaiSifat($sifat),
            'bentuk' => $this->sesamaQuery('bentuk', null)->get(),
            'pakaiBentuk' => Tindakan::selectRaw('bentuk_id, count(*) n')->whereNotNull('bentuk_id')
                ->groupBy('bentuk_id')->pluck('n', 'bentuk_id'),
            'kategori' => $this->kategoriIntern(),
            /* Jumlah temuan yang memakainya, diingatkan sebelum dinonaktifkan. */
            'terpakai' => Temuan::selectRaw('kategori_intern_id, count(*) n')
                ->whereNotNull('kategori_intern_id')
                ->groupBy('kategori_intern_id')->pluck('n', 'kategori_intern_id'),
            'kategoriTemuan' => KategoriTemuan::orderBy('urutan')->orderBy('id')->get()->groupBy(fn ($k) => $k->sumber->value),
            'terpakaiTemuan' => Temuan::selectRaw('kategori_temuan_id, count(*) n')
                ->whereNotNull('kategori_temuan_id')
                ->groupBy('kategori_temuan_id')->pluck('n', 'kategori_temuan_id'),
        ];
    }

    /* ---- Filter di alamat ---- */

    /** Parameter filter tab ini, sudah dibersihkan; semuanya ikut alamat. */
    private function saring(Request $req, string $tab): array
    {
        $q = mb_substr(trim((string) $req->query('cari', '')), 0, 100);
        $filter = (string) $req->query('filter', '');
        $urut = (string) $req->query('urut', '');
        $arah = (string) $req->query('arah', '');
        $peran = (string) $req->query('peran', '');

        $f = [
            'q' => $q, 'filter' => '', 'peran' => '', 'masuk' => '', 'urut' => '', 'arah' => '',
            'rentang' => 'semua', 'pelaku' => 0, 'sumber' => 'LHP',
        ];
        switch ($tab) {
            case 'pengguna':
                $f['filter'] = in_array($filter, PusatData::KEADAAN, true) ? $filter : 'semua';
                $f['peran'] = PeranPengguna::tryFrom($peran)?->value ?? '';
                $f['masuk'] = array_key_exists((string) $req->query('masuk', ''), PusatData::PILIHAN_MASUK)
                    ? (string) $req->query('masuk', '') : '';
                $f['urut'] = in_array($urut, ['nama', 'peran', 'masuk'], true) ? $urut : '';
                $f['arah'] = $f['urut'] && $arah === 'turun' ? 'turun' : 'naik';
                break;
            case 'unit':
                $f['filter'] = in_array($filter, PusatData::KEADAAN_UNIT, true) ? $filter : 'semua';
                $f['urut'] = in_array($urut, ['nama', 'rekomendasi'], true) ? $urut : '';
                $f['arah'] = $f['urut'] === 'rekomendasi'
                    ? ($arah === 'naik' ? 'naik' : 'turun') : ($arah === 'turun' ? 'turun' : 'naik');
                break;
            case 'temuan':
                $f['sumber'] = in_array($filter, ['LHP', 'LHA'], true) ? $filter : 'LHP';
                $f['filter'] = $f['sumber'];
                break;
            case 'riwayat':
                $f['filter'] = in_array($filter, ['unit', 'pengguna', 'temuan', 'intern', 'sifat', 'bentuk'], true) ? $filter : 'semua';
                $rentang = (string) $req->query('rentang', 'semua');
                $f['rentang'] = in_array($rentang, self::RENTANG, true) ? $rentang : 'semua';
                $f['pelaku'] = max(0, $req->integer('pelaku'));
                break;
        }

        return $f;
    }

    /** Parameter alamat tab ini tanpa yang bernilai bawaan — untuk tautan filter dan urutan. */
    private function param(string $tab, array $f): array
    {
        $p = match ($tab) {
            'pengguna' => ['filter' => $f['filter'] !== 'semua' ? $f['filter'] : null, 'peran' => $f['peran'],
                'masuk' => $f['masuk'], 'urut' => $f['urut'], 'arah' => $f['urut'] ? $f['arah'] : null, 'cari' => $f['q']],
            'unit' => ['filter' => $f['filter'] !== 'semua' ? $f['filter'] : null, 'urut' => $f['urut'],
                'arah' => $f['urut'] ? $f['arah'] : null, 'cari' => $f['q']],
            'temuan' => ['filter' => $f['sumber']],
            'riwayat' => ['filter' => $f['filter'] !== 'semua' ? $f['filter'] : null,
                'rentang' => $f['rentang'] !== 'semua' ? $f['rentang'] : null, 'pelaku' => $f['pelaku'] ?: null, 'cari' => $f['q']],
            default => [],
        };

        return array_filter(['tab' => $tab] + $p, fn ($v) => $v !== null && $v !== '');
    }

    /* ---- Kategori internal ---- */

    public function tambah(Request $req)
    {
        $this->pastikanAdmin($req);
        $data = $req->validate(['nama' => ['required', 'string', 'max:120']], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);
        if ($this->kembarReferensi(JenisReferensi::KATEGORI_INTERN, $nama)) {
            return $this->gagal('intern', 'Kategori dengan nama itu sudah ada.', 'intern');
        }

        $k = Referensi::create([
            'jenis' => JenisReferensi::KATEGORI_INTERN->value,
            'nama' => $nama,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->max('urutan') + 1,
            'aktif' => true,
        ]);
        Aktivitas::catat('master.intern.tambah', 'Menambah kategori internal '.$nama, [
            'subjek' => $k, 'rincian' => ['sesudah' => ['nama' => $nama, 'aktif' => true]],
        ]);

        return $this->kembali('intern', 'kat-'.$k->id);
    }

    /** Nama yang sudah dipakai temuan terkunci (aturan sama untuk ketiga daftar). */
    public function simpan(Request $req, Referensi $referensi)
    {
        $this->pastikanAdmin($req);
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
     * Menyalakan/memadamkan, bukan menghapus: kategori yang dipakai harus tetap terbaca di berkas
     * lama.
     */
    public function saklar(Request $req, Referensi $referensi)
    {
        $this->pastikanAdmin($req);
        $this->pastikanIntern($referensi);

        $referensi->update(['aktif' => ! $referensi->aktif]);
        Aktivitas::catat('master.intern.saklar', ($referensi->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' kategori internal '.$referensi->nama, [
            'subjek' => $referensi, 'rincian' => ['sebelum' => ['aktif' => ! $referensi->aktif], 'sesudah' => ['aktif' => $referensi->aktif]],
        ]);

        return $this->kembali('intern', 'kat-'.$referensi->id);
    }

    /* ---- Kategori temuan ---- */

    public function tambahTemuan(Request $req)
    {
        $this->pastikanAdmin($req);

        $data = $req->validate([
            'nama' => ['required', 'string', 'max:160'],
            'sumber' => ['required', 'in:LHP,LHA'],
        ], ['nama.required' => 'Nama kategori harus terisi.']);
        $nama = $this->rapikan($data['nama']);

        /* Nama kembar pada sumber yang sama ditolak. */
        $ada = KategoriTemuan::where('sumber', $data['sumber'])
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->exists();
        if ($ada) {
            return $this->gagal('temuan', 'Kategori dengan nama itu sudah ada.', 'temuan', ['sumber' => $data['sumber'], 'filter' => $data['sumber']]);
        }

        $k = KategoriTemuan::create([
            'sumber' => $data['sumber'],
            'nama' => $nama,
            'urutan' => (int) KategoriTemuan::where('sumber', $data['sumber'])->max('urutan') + 1,
            'aktif' => true,
        ]);
        Aktivitas::catat('master.temuan.tambah', 'Menambah kategori temuan '.$data['sumber'].': '.$nama, [
            'subjek' => $k, 'rincian' => ['sesudah' => ['nama' => $nama, 'sumber' => $data['sumber'], 'aktif' => true]],
        ]);

        return $this->kembali('temuan', 'kt-'.$k->id, ['filter' => $data['sumber']]);
    }

    /** Nama yang sudah dipakai temuan tidak diubah: berkas lama membaca nama itu. */
    public function simpanTemuan(Request $req, KategoriTemuan $kategori)
    {
        $this->pastikanAdmin($req);

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
        $this->pastikanAdmin($req);

        $kategori->update(['aktif' => ! $kategori->aktif]);
        Aktivitas::catat('master.temuan.saklar', ($kategori->aktif ? 'Mengaktifkan' : 'Menonaktifkan')
            .' kategori temuan '.$kategori->sumber->value.': '.$kategori->nama, [
                'subjek' => $kategori, 'rincian' => ['sebelum' => ['aktif' => ! $kategori->aktif], 'sesudah' => ['aktif' => $kategori->aktif]],
            ]);

        return $this->kembali('temuan', 'kt-'.$kategori->id, ['filter' => $kategori->sumber->value]);
    }

    /* ---- Sifat rekomendasi ----
       `perlu_nilai` ditetapkan saat dibuat dan terkunci begitu dipakai. */

    public function tambahSifat(Request $req)
    {
        $this->pastikanAdmin($req);

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
            'jenis' => JenisReferensi::SIFAT_REKOM->value,
            'nama' => $nama,
            'perlu_nilai' => $uang,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->max('urutan') + 1,
            'aktif' => true,
        ]);
        Aktivitas::catat('master.sifat.tambah', 'Menambah sifat rekomendasi '.$nama, [
            'subjek' => $x, 'rincian' => ['sesudah' => ['nama' => $nama, 'uang' => $uang, 'aktif' => true]],
        ]);

        return $this->kembali('sifat', 'sifat-'.$x->id);
    }

    /** Nama dan keterangan uangnya hanya boleh diganti selama belum dipakai. */
    public function simpanSifat(Request $req, Referensi $referensi)
    {
        $this->pastikanAdmin($req);
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
        $this->pastikanAdmin($req);
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

    /* ---- Bentuk tindak lanjut ----
       Nama terkunci begitu dipakai tindak lanjut; dokumen usulannya tetap bisa disesuaikan karena
       hanya mengisi formulir baru. */

    public function tambahBentuk(Request $req)
    {
        $this->pastikanAdmin($req);
        $data = $this->validasiBentuk($req);
        $nama = $this->rapikan($data['nama']);
        if ($this->kembarReferensi(JenisReferensi::BENTUK_TL, $nama)) {
            return $this->gagal('bentuk', 'Bentuk dengan nama itu sudah ada.', 'bentuk');
        }
        $dokumen = PusatData::uraiBaris($data['dokumen'] ?? '');
        if ($salah = $this->salahDokumen($dokumen)) {
            return $this->gagal('bentuk', $salah, 'bentuk');
        }

        $x = Referensi::create([
            'jenis' => JenisReferensi::BENTUK_TL->value,
            'nama' => $nama,
            'dokumen' => $dokumen,
            'urutan' => (int) Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->max('urutan') + 1,
            'aktif' => true,
        ]);
        Aktivitas::catat('master.bentuk.tambah', 'Menambah bentuk tindak lanjut '.$nama, [
            'subjek' => $x, 'rincian' => ['sesudah' => ['nama' => $nama, 'dokumen' => implode('; ', $dokumen), 'aktif' => true]],
        ]);

        return $this->kembali('bentuk', 'bentuk-'.$x->id);
    }

    public function simpanBentuk(Request $req, Referensi $referensi)
    {
        $this->pastikanAdmin($req);
        $this->pastikanBentuk($referensi);
        $data = $this->validasiBentuk($req);
        $nama = $this->rapikan($data['nama']);
        $jendela = 'bentuk-'.$referensi->id;

        if ($nama !== $referensi->nama) {
            if (Tindakan::where('bentuk_id', $referensi->id)->exists()) {
                return $this->gagal('bentuk', 'Bentuk ini sudah dipakai tindak lanjut, jadi namanya tidak bisa diganti.', $jendela);
            }
            if ($this->kembarReferensi(JenisReferensi::BENTUK_TL, $nama, $referensi->id)) {
                return $this->gagal('bentuk', 'Bentuk dengan nama itu sudah ada.', $jendela);
            }
        }
        $dokumen = PusatData::uraiBaris($data['dokumen'] ?? '');
        if ($salah = $this->salahDokumen($dokumen)) {
            return $this->gagal('bentuk', $salah, $jendela);
        }

        $sebelum = ['nama' => $referensi->nama, 'dokumen' => implode('; ', (array) $referensi->dokumen)];
        $sesudah = ['nama' => $nama, 'dokumen' => implode('; ', $dokumen)];
        $referensi->update(['nama' => $nama, 'dokumen' => $dokumen]);
        if ($beda = Aktivitas::beda($sebelum, $sesudah)) {
            Aktivitas::catat('master.bentuk.ubah', 'Mengubah bentuk tindak lanjut '.$sebelum['nama'], ['subjek' => $referensi, 'rincian' => $beda]);
        }

        return $this->kembali('bentuk', 'bentuk-'.$referensi->id);
    }

    /** Paling tidak satu bentuk tetap aktif — formulir tindak lanjut butuh pilihan. */
    public function saklarBentuk(Request $req, Referensi $referensi)
    {
        $this->pastikanAdmin($req);
        $this->pastikanBentuk($referensi);

        $sisa = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
            ->where('aktif', true)->whereKeyNot($referensi->id)->count();
        if ($referensi->aktif && $sisa === 0) {
            return $this->kembali('bentuk', 'bentuk-'.$referensi->id)
                ->with('gagal', 'Paling tidak satu bentuk harus tetap aktif.');
        }

        $referensi->update(['aktif' => ! $referensi->aktif]);
        Aktivitas::catat('master.bentuk.saklar', ($referensi->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' bentuk tindak lanjut '.$referensi->nama, [
            'subjek' => $referensi, 'rincian' => ['sebelum' => ['aktif' => ! $referensi->aktif], 'sesudah' => ['aktif' => $referensi->aktif]],
        ]);

        return $this->kembali('bentuk', 'bentuk-'.$referensi->id);
    }

    private function validasiBentuk(Request $req): array
    {
        return $req->validate([
            'nama' => ['required', 'string', 'max:160'],
            'dokumen' => ['nullable', 'string', 'max:5000'],
        ], ['nama.required' => 'Nama bentuk harus terisi.']);
    }

    private function salahDokumen(array $dokumen): ?string
    {
        if (count($dokumen) > self::MAKS_DOKUMEN) {
            return 'Dokumen usulan paling banyak '.self::MAKS_DOKUMEN.' baris.';
        }
        foreach ($dokumen as $x) {
            if (mb_strlen($x) > 200) {
                return 'Nama dokumen terlalu panjang: “'.mb_substr($x, 0, 40).'…”.';
            }
        }

        return null;
    }

    /* ---- Keempat daftar: urutan, gabungkan, tambah banyak, ekspor ---- */

    /** Geser satu kategori di antara sesamanya; urutan dinomori ulang 1…n. */
    public function urut(Request $req, string $daftar, int $id)
    {
        $this->pastikanAdmin($req);
        $k = $this->butir($daftar, $id);
        $arah = $req->input('arah') === 'bawah' ? 'bawah' : 'atas';

        $sesama = $this->sesama($daftar, $k)->get();
        $baru = PusatData::geser($sesama->pluck('id')->all(), $k->id, $arah);
        if ($baru === null) {
            return $this->kembaliDaftar($daftar, $k);
        }
        $sebelum = array_search($k->id, $sesama->pluck('id')->all(), true) + 1;
        DB::transaction(function () use ($k, $baru) {
            foreach ($baru as $i => $x) {
                $k::whereKey($x)->update(['urutan' => $i + 1]);
            }
        });
        Aktivitas::catat("master.$daftar.urut", 'Memindahkan '.$this->sebutLog($daftar, $k).' ke '.$arah, [
            'subjek' => $k,
            'rincian' => ['sebelum' => ['urutan' => $sebelum], 'sesudah' => ['urutan' => array_search($k->id, $baru, true) + 1]],
        ]);

        return $this->kembaliDaftar($daftar, $k);
    }

    /**
     * Gabungkan: pemakainya pindah ke kategori tujuan (aktif dan sesama), lalu yang pertama
     * dinonaktifkan.
     */
    public function gabung(Request $req, string $daftar, int $id)
    {
        $this->pastikanAdmin($req);
        $dari = $this->butir($daftar, $id);
        $ke = $this->butirAtauNull($daftar, $req->integer('ke'));
        $kembali = fn (string $pesan) => $this->gagal($daftar, $pesan, "gabung-$daftar-".$dari->id,
            $daftar === 'temuan' ? ['filter' => $dari->sumber->value] : []);

        if (! $ke) {
            return $kembali('Pilih kategori tujuannya.');
        }
        $sah = $ke->id !== $dari->id && $ke->aktif
            && $this->sesama($daftar, $dari)->whereKey($ke->id)->exists()
            && ($daftar !== 'sifat' || (bool) $ke->perlu_nilai === (bool) $dari->perlu_nilai);
        if (! $sah) {
            return $kembali('Kategori tujuan itu tidak bisa dipakai. Pilih yang aktif dan sejenis.');
        }

        $satuan = $this->satuan($daftar);
        $n = DB::transaction(function () use ($daftar, $dari, $ke, $satuan) {
            $n = match ($daftar) {
                'temuan' => Temuan::where('kategori_temuan_id', $dari->id)->update(['kategori_temuan_id' => $ke->id]),
                'intern' => Temuan::where('kategori_intern_id', $dari->id)->update(['kategori_intern_id' => $ke->id]),
                /* Rekomendasi tanpa sifat dibaca Administratif, seperti pakaiSifat(). */
                'sifat' => Rekomendasi::where('sifat_id', $dari->id)->update(['sifat_id' => $ke->id])
                    + ($dari->nama === 'Administratif' ? Rekomendasi::whereNull('sifat_id')->update(['sifat_id' => $ke->id]) : 0),
                /* Dokumen yang diminta tiap tindak lanjut tidak ikut berubah. */
                'bentuk' => Tindakan::where('bentuk_id', $dari->id)->update(['bentuk_id' => $ke->id]),
            };
            $adaAktif = $dari->aktif;
            $dari->update(['aktif' => false]);
            Aktivitas::catat("master.$daftar.gabung", 'Menggabungkan '.($daftar === 'temuan'
                ? 'kategori temuan '.$dari->sumber->value.': '.$dari->nama.' → '.$ke->nama
                : $this->sebut($daftar, $dari).' '.$dari->nama.' → '.$ke->nama), [
                    'subjek' => $dari,
                    'rincian' => [
                        'sebelum' => ['kategori' => $dari->nama] + ($adaAktif ? ['aktif' => true] : []),
                        'sesudah' => ['kategori' => $ke->nama] + ($adaAktif ? ['aktif' => false] : []) + ['dipindah' => $n.' '.$satuan],
                    ],
                ]);

            return $n;
        });

        return $this->kembaliDaftar($daftar, $ke)
            ->with('pesan', '“'.$dari->nama.'” digabung ke “'.$ke->nama.'”: '.$n.' '.$satuan.' dipindahkan.');
    }

    /** Tambah banyak: satu nama per baris; nama yang sudah ada dilewati. */
    public function banyak(Request $req, string $daftar)
    {
        $this->pastikanAdmin($req);
        abort_unless(in_array($daftar, self::DAFTAR, true), 404);
        $sumber = $daftar === 'temuan' ? ($req->input('sumber') === 'LHA' ? 'LHA' : 'LHP') : null;
        $lain = $sumber ? ['filter' => $sumber] : [];
        $nama = PusatData::uraiBaris((string) $req->input('nama'));
        $jendela = "banyak-$daftar";

        if (! $nama) {
            return $this->gagal($daftar, 'Tulis paling tidak satu nama.', $jendela, $lain);
        }
        $ada = $this->sesamaQuery($daftar, $sumber)->pluck('nama')->map(fn ($x) => mb_strtolower($x))->flip();
        $baru = array_values(array_filter($nama, fn ($x) => ! isset($ada[mb_strtolower($x)])));
        if (! $baru) {
            return $this->gagal($daftar, 'Semua nama itu sudah ada.', $jendela, $lain);
        }
        foreach ($baru as $x) {
            if (mb_strlen($x) > (in_array($daftar, ['temuan', 'bentuk'], true) ? 160 : 120)) {
                return $this->gagal($daftar, 'Nama terlalu panjang: “'.mb_substr($x, 0, 40).'…”.', $jendela, $lain);
            }
        }

        $uang = (bool) $req->boolean('uang');
        DB::transaction(function () use ($daftar, $sumber, $baru, $uang) {
            $urutan = (int) $this->sesamaQuery($daftar, $sumber)->max('urutan');
            foreach ($baru as $x) {
                $urutan++;
                [$model, $ringkas, $sesudah] = match ($daftar) {
                    'temuan' => [KategoriTemuan::create(['sumber' => $sumber, 'nama' => $x, 'urutan' => $urutan, 'aktif' => true]),
                        "Menambah kategori temuan $sumber: $x", ['nama' => $x, 'sumber' => $sumber, 'aktif' => true]],
                    'intern' => [Referensi::create(['jenis' => JenisReferensi::KATEGORI_INTERN->value, 'nama' => $x,
                        'urutan' => $urutan, 'aktif' => true]),
                        "Menambah kategori internal $x", ['nama' => $x, 'aktif' => true]],
                    'sifat' => [Referensi::create(['jenis' => JenisReferensi::SIFAT_REKOM->value, 'nama' => $x,
                        'perlu_nilai' => $uang, 'urutan' => $urutan, 'aktif' => true]),
                        "Menambah sifat rekomendasi $x", ['nama' => $x, 'uang' => $uang, 'aktif' => true]],
                    /* Dokumen usulannya diisi lewat Ubah. */
                    'bentuk' => [Referensi::create(['jenis' => JenisReferensi::BENTUK_TL->value, 'nama' => $x,
                        'dokumen' => [], 'urutan' => $urutan, 'aktif' => true]),
                        "Menambah bentuk tindak lanjut $x", ['nama' => $x, 'aktif' => true]],
                };
                Aktivitas::catat("master.$daftar.tambah", $ringkas, ['subjek' => $model, 'rincian' => ['sesudah' => $sesudah]]);
            }
        });

        return $this->kembali($daftar, null, $lain)->with('pesan', count($baru).($daftar === 'bentuk' ? ' bentuk' : ' kategori').' ditambahkan.'
            .(count($nama) > count($baru) ? ' '.(count($nama) - count($baru)).' dilewati karena namanya sudah ada.' : ''));
    }

    /** Ekspor salah satu daftar sebagai CSV — yang tampil di layar, dengan urutan dan pemakaiannya. */
    public function unduhDaftar(Request $req, string $daftar)
    {
        $this->pastikanAdmin($req);
        abort_unless(in_array($daftar, self::DAFTAR, true), 404);
        $sumber = $daftar === 'temuan' ? ($req->query('filter') === 'LHA' ? 'LHA' : 'LHP') : null;
        $butir = $this->sesamaQuery($daftar, $sumber)->get();
        $kat = $this->kategoriData();
        $pakai = fn ($x) => (int) match ($daftar) {
            'temuan' => $kat['terpakaiTemuan'][$x->id] ?? 0,
            'intern' => $kat['terpakai'][$x->id] ?? 0,
            'sifat' => $kat['pakaiSifat'][$x->id] ?? 0,
            'bentuk' => $kat['pakaiBentuk'][$x->id] ?? 0,
        };
        $nama = match ($daftar) {
            'temuan' => "Kategori temuan $sumber", 'intern' => 'Kategori internal', 'sifat' => 'Sifat rekomendasi',
            'bentuk' => 'Bentuk tindak lanjut',
        };
        Aktivitas::catat('master.unduh', 'Mengunduh daftar '.mb_strtolower($nama).' ('.$butir->count().' baris)', [
            'kelompok' => 'akses', 'rincian' => ['daftar' => $daftar]]);

        return PusatData::unduhCsv(
            match ($daftar) {
                'temuan' => 'kategori-temuan-'.mb_strtolower($sumber), 'intern' => 'kategori-internal', 'sifat' => 'sifat-rekomendasi',
                'bentuk' => 'bentuk-tindak-lanjut',
            },
            match ($daftar) {
                'sifat' => ['Daftar', 'Urutan', 'Nama', 'Menuntut penyetoran', 'Dipakai', 'Keadaan'],
                'bentuk' => ['Daftar', 'Urutan', 'Nama', 'Dokumen usulan', 'Dipakai', 'Keadaan'],
                default => ['Daftar', 'Urutan', 'Nama', 'Dipakai', 'Keadaan'],
            },
            $butir->values()->map(fn ($x, $i) => match ($daftar) {
                'sifat' => [$nama, $i + 1, $x->nama, $x->perlu_nilai ? 'Ya' : 'Tidak', $pakai($x), $x->aktif ? 'Aktif' : 'Nonaktif'],
                'bentuk' => [$nama, $i + 1, $x->nama, implode('; ', (array) $x->dokumen), $pakai($x), $x->aktif ? 'Aktif' : 'Nonaktif'],
                default => [$nama, $i + 1, $x->nama, $pakai($x), $x->aktif ? 'Aktif' : 'Nonaktif'],
            }));
    }

    /* ---- Unduhan lain ---- */

    /** Daftar Pengguna sebagai CSV — sama dengan yang tampil menurut filternya. */
    public function unduhPengguna(Request $req)
    {
        $this->pastikanAdmin($req);
        $f = $this->saring($req, 'pengguna');
        $daftar = PusatData::urutkanAkun(PusatData::saringAkun($this->akun(), $f, now()), $f['urut'], $f['arah']);
        Aktivitas::catat('master.unduh', 'Mengunduh daftar user ('.$daftar->count().' akun)', [
            'kelompok' => 'akses', 'rincian' => ['daftar' => 'pengguna']]);

        return PusatData::unduhCsv('pengguna',
            ['Nama', 'NIP', 'Email', 'Jabatan', 'Peran', 'Unit kerja', 'Satker yang dipimpin', 'Keadaan', 'Terakhir masuk'],
            $daftar->map(fn ($a) => [$a->name, PusatData::teksExcel((string) $a->nip), $a->email, $a->jabatan ?? '', implode(', ', array_map(fn ($p) => $p->pendek(), $a->semuaPeran())),
                $a->peran === PeranPengguna::SATKER ? ($a->satker?->nama ?? '') : '', $a->punyaPeran(PeranPengguna::PIMPINAN) ? $a->daftarSatkerDipimpin(true) : '', $a->aktif ? 'Aktif' : 'Nonaktif',
                $a->terakhir_masuk_pada?->format('Y-m-d H:i:s') ?? '']));
    }

    /** Daftar Unit kerja sebagai CSV, menurut filternya. */
    public function unduhUnit(Request $req)
    {
        $this->pastikanAdmin($req);
        $f = $this->saring($req, 'unit');
        $pakai = $this->pakaiUnit();
        $daftar = PusatData::saringUnit(Satker::with('penanggungJawab')->orderBy('id')->get(), $f, $pakai);
        Aktivitas::catat('master.unduh', 'Mengunduh daftar unit kerja ('.$daftar->count().' unit)', [
            'kelompok' => 'akses', 'rincian' => ['daftar' => 'unit']]);

        return PusatData::unduhCsv('unit-kerja',
            ['Unit kerja', 'Nama pendek', 'Kode satker', 'Penanggung jawab', 'NIP', 'Email', 'Rekomendasi', 'Berjalan', 'Keadaan'],
            $daftar->map(fn ($u) => [$u->nama, $u->namaPendek(), PusatData::teksExcel((string) $u->kode_satker), $u->penanggungJawab?->name ?? '',
                PusatData::teksExcel((string) ($u->penanggungJawab?->nip ?? '')), $u->penanggungJawab?->email ?? '',
                $pakai[$u->id]['rek'] ?? 0, $pakai[$u->id]['jalan'] ?? 0, $u->aktif ? 'Aktif' : 'Nonaktif']));
    }

    /** Riwayat perubahan sebagai CSV — semua baris yang cocok dengan filter, bukan hanya yang tampil. */
    public function unduhRiwayat(Request $req)
    {
        $this->pastikanAdmin($req);
        $f = $this->saring($req, 'riwayat');
        $q = $this->kueriRiwayat($f);
        Aktivitas::catat('master.unduh', 'Mengunduh riwayat perubahan ('.(clone $q)->count().' baris)', [
            'kelompok' => 'akses', 'rincian' => ['daftar' => 'riwayat']]);

        return PusatData::unduhCsv('riwayat-perubahan',
            ['Waktu', 'Oleh', 'Peran', 'Perubahan', 'Sebelum → sesudah'],
            (function () use ($q) {
                foreach ($q->orderByDesc('waktu')->orderByDesc('id')->lazy(500) as $x) {
                    yield [$x->waktu?->format('Y-m-d H:i:s'), $x->nama ?: 'Sistem (otomatis)', $x->peran ? $x->sebutanPeran() : '',
                        $x->ringkasan, BedaLog::teks($x->rincian)];
                }
            })());
    }

    /* ---- Isi halaman ---- */

    /**
     * Jumlah rekomendasi tiap unit kerja dan yang belum selesai (dimuat sekali, bukan per unit).
     */
    public function pakaiUnit(): array
    {
        $h = [];
        /* `sasaran` dimuat sekaligus supaya tidak satu kueri per rekomendasi. */
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
        /* Rekomendasi tanpa sifat dibaca Administratif. */
        $administratif = $sifat->firstWhere('nama', 'Administratif')?->id;

        return Rekomendasi::selectRaw('sifat_id, count(*) n')->groupBy('sifat_id')->get()
            ->reduce(function ($h, $x) use ($administratif) {
                $id = $x->sifat_id ?? $administratif;
                $h[$id] = ($h[$id] ?? 0) + $x->n;

                return $h;
            }, []);
    }

    /** Seluruh akun: aktif dulu, lalu peran, unit, nama. */
    private function akun(): Collection
    {
        $urutUnit = Satker::orderBy('id')->pluck('id')->flip();
        $kunci = fn ($u) => [(int) ! $u->aktif, (int) array_search($u->peran->value, PusatData::URUT_PERAN, true),
            $u->peran === PeranPengguna::SATKER ? ($urutUnit[$u->satker_id] ?? 999) : 0, $u->name];

        return User::with('satker')->get()->sort(fn ($a, $b) => $kunci($a) <=> $kunci($b))->values();
    }

    /** Kueri Riwayat perubahan menurut filternya: kelompok, rentang, pelaku, dan kata kunci. */
    private function kueriRiwayat(array $f)
    {
        $filter = $f['filter'];

        return LogAktivitas::query()->whereIn('kelompok', ['master', 'pengguna'])
            ->when($filter === 'pengguna', fn ($q) => $q->where('kelompok', 'pengguna'))
            ->when(in_array($filter, ['unit', 'temuan', 'intern', 'sifat', 'bentuk'], true), fn ($q) => $q->where('aksi', 'like', 'master.'.$filter.'%'))
            ->when($f['rentang'] !== 'semua', fn ($q) => $q->where('waktu', '>=',
                now()->startOfDay()->subDays((int) $f['rentang'] - 1)))
            ->when($f['pelaku'] > 0, fn ($q) => $q->where('user_id', $f['pelaku']))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $q->where(function ($w) use ($f) {
                    foreach (TampilanLog::varianCari($f['q']) as $varian) {
                        $kata = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $varian).'%';
                        $w->orWhere('ringkasan', 'like', $kata)->orWhere('nama', 'like', $kata);
                    }
                });
            });
    }

    /** Riwayat perubahan data master dan hak akses (potongan log aktivitas). */
    private function riwayat(array $f, int $batas): array
    {
        $q = $this->kueriRiwayat($f);
        $n = (clone $q)->count();

        return [$q->orderByDesc('waktu')->orderByDesc('id')->limit($batas)->get(), $n];
    }

    /**
     * Jendela yang dibuka lewat `?jendela=` (mis. unit-5, akun-7); yang tidak ada atau terkunci
     * diabaikan.
     */
    private function jendela(Request $req, array $d): ?array
    {
        $j = (string) $req->query('jendela', '');
        if ($j === '') {
            return null;
        }
        $bagian = explode('-', $j);
        $jenis = $bagian[0];
        $id = isset($bagian[1]) && ctype_digit($bagian[1]) ? (int) $bagian[1] : null;

        switch ($jenis) {
            case 'unit':
                if (count($bagian) === 1) {
                    return ['jenis' => 'unit', 'x' => null];
                }
                $u = $d['unit']->firstWhere('id', $id);

                /* Unit yang terkunci tetap bisa dibuka untuk melengkapi kode satker. */
                return $u && $d['tab'] === 'unit'
                    ? ['jenis' => 'unit', 'x' => $u, 'kunci' => isset(($d['kunciUnit'] ?? collect())[$u->id])] : null;
            case 'riwayat':
                $u = $d['unit']->firstWhere('id', $id);

                return $u ? ['jenis' => 'riwayat-unit', 'x' => $u, 'log' => LogAktivitas::where('subjek_tipe', 'satker')
                    ->where('subjek_id', $u->id)->whereIn('kelompok', ['master', 'pengguna'])
                    ->orderByDesc('waktu')->orderByDesc('id')->limit(30)->get()] : null;
            case 'pengguna':
                if (! $d['bolehBeri']) {
                    return null;
                }
                $nip = preg_replace('/\D/', '', (string) $req->query('nip', ''));

                return ['jenis' => 'pengguna', 'cari' => PenggunaController::hasilCari((string) $req->query('q', '')),
                    'pilih' => $nip ? DirektoriIrm::nip($nip) : null];
            case 'impor':
                if (! $d['bolehBeri']) {
                    return null;
                }
                $teks = (string) $req->query('nip', $req->old('nip', ''));
                /* `peran_baru`, bukan `peran`: nama itu dipakai filter daftar Pengguna. */
                $peran = PeranPengguna::tryFrom((string) $req->query('peran_baru', $req->old('peran', '')));
                $periksa = $req->boolean('periksa') && $teks !== '' && $peran && in_array($peran, $d['bolehBeri'], true);
                /* Cakupan pimpinan: beberapa Satker atau Semua Satker. */
                $cakupan = ['ids' => array_values(array_filter(array_map('intval',
                    (array) $req->query('pimpinan_satker', $req->old('pimpinan_satker', []))),
                    fn ($id) => $d['unit']->contains('id', $id))),
                    'semua' => (bool) $req->query('pimpinan_semua', $req->old('pimpinan_semua', false))];
                if ($periksa && $peran === PeranPengguna::PIMPINAN && ! $cakupan['semua'] && ! $cakupan['ids']) {
                    $req->validate(['pimpinan_satker' => ['required']], [
                        'pimpinan_satker.required' => 'Pilih Satker yang dipimpin, atau Semua Satker.',
                    ]);
                    $periksa = false;
                }
                $nip = PusatData::uraiNip($teks);

                return ['jenis' => 'impor', 'teks' => $teks, 'peran' => $peran?->value ?? '', 'jumlah' => count($nip), 'pimpinan' => $cakupan,
                    'kelebihan' => count($nip) > PenggunaController::BATAS_IMPOR,
                    'hasil' => $periksa && count($nip) <= PenggunaController::BATAS_IMPOR ? PenggunaController::periksaBanyak($nip) : null];
            case 'akun':
                $a = $d['akun']->firstWhere('id', $id);

                return $a ? ['jenis' => 'akun', 'x' => $a] : null;
            case 'detail':
                $a = $d['akun']->firstWhere('id', $id);

                return $a ? ['jenis' => 'detail', 'x' => $a, 'sesi' => Sesi::milik($a),
                    'aktivitas' => LogAktivitas::where('user_id', $a->id)->whereNotIn('aksi', ['masuk', 'keluar'])
                        ->orderByDesc('waktu')->orderByDesc('id')->limit(6)->get(),
                    'perbuatan30' => LogAktivitas::where('user_id', $a->id)->whereNotIn('aksi', ['masuk', 'keluar'])
                        ->where('waktu', '>=', now()->startOfDay()->subDays(PusatData::HARI_SEPI - 1))->count(),
                    'hak' => LogAktivitas::where('subjek_tipe', 'user')->where('subjek_id', $a->id)->where('kelompok', 'pengguna')
                        ->orderByDesc('waktu')->orderByDesc('id')->limit(5)->get()] : null;
            case 'temuan':
                if (count($bagian) === 1) {
                    return ['jenis' => 'temuan', 'x' => null,
                        'sumber' => in_array($req->query('sumber'), ['LHP', 'LHA'], true) ? $req->query('sumber') : 'LHP'];
                }
                $k = ($d['kategoriTemuan'] ?? collect())->flatten()->firstWhere('id', $id);

                return $k && ! isset($d['terpakaiTemuan'][$k->id]) ? ['jenis' => 'temuan', 'x' => $k] : null;
            case 'intern':
                if (count($bagian) === 1) {
                    return ['jenis' => 'intern', 'x' => null];
                }
                $k = ($d['kategori'] ?? collect())->firstWhere('id', $id);

                return $k && ! isset($d['terpakai'][$k->id]) ? ['jenis' => 'intern', 'x' => $k] : null;
            case 'sifat':
                if (count($bagian) === 1) {
                    return ['jenis' => 'sifat', 'x' => null];
                }
                $x = ($d['sifat'] ?? collect())->firstWhere('id', $id);

                return $x && ! ($d['pakaiSifat'][$x->id] ?? 0) ? ['jenis' => 'sifat', 'x' => $x] : null;
            case 'bentuk':
                if (count($bagian) === 1) {
                    return ['jenis' => 'bentuk', 'x' => null, 'n' => 0];
                }
                $x = ($d['bentuk'] ?? collect())->firstWhere('id', $id);

                /* Yang sudah dipakai tetap bisa dibuka: dokumen usulannya boleh diubah. */
                return $x ? ['jenis' => 'bentuk', 'x' => $x, 'n' => (int) ($d['pakaiBentuk'][$x->id] ?? 0)] : null;
            case 'banyak':
                $daftar = $bagian[1] ?? '';

                return in_array($daftar, self::DAFTAR, true) && $d['tab'] === $daftar
                    ? ['jenis' => 'banyak', 'daftar' => $daftar, 'sumber' => $d['f']['sumber']] : null;
            case 'gabung':
                $daftar = $bagian[1] ?? '';
                $idButir = isset($bagian[2]) && ctype_digit($bagian[2]) ? (int) $bagian[2] : 0;
                if (! in_array($daftar, self::DAFTAR, true) || $d['tab'] !== $daftar) {
                    return null;
                }
                $x = $this->butirAtauNull($daftar, $idButir);
                if (! $x) {
                    return null;
                }
                $pilihan = $this->sesama($daftar, $x)->where('aktif', true)->whereKeyNot($x->id)->get()
                    ->when($daftar === 'sifat', fn ($c) => $c->filter(fn ($y) => (bool) $y->perlu_nilai === (bool) $x->perlu_nilai))->values();

                return ['jenis' => 'gabung', 'daftar' => $daftar, 'x' => $x, 'pilihan' => $pilihan, 'n' => $this->dipakaiN($daftar, $x)];
        }

        return null;
    }

    /* ---- Penjaga dan pembantu ---- */

    private function kategoriIntern()
    {
        return Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->orderBy('urutan')->orderBy('id')->get();
    }

    /** Model satu butir dari salah satu dari tiga daftar; 404 kalau tidak ada. */
    private function butir(string $daftar, int $id): Model
    {
        return $this->butirAtauNull($daftar, $id) ?? abort(404);
    }

    private function butirAtauNull(string $daftar, int $id): ?Model
    {
        if (! in_array($daftar, self::DAFTAR, true) || $id <= 0) {
            return null;
        }

        return match ($daftar) {
            'temuan' => KategoriTemuan::find($id),
            'intern' => Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->find($id),
            'sifat' => Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->find($id),
            'bentuk' => Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->find($id),
        };
    }

    /** Sesama satu butir: sumber laporan yang sama, atau jenis referensi yang sama — sudah berurutan. */
    private function sesama(string $daftar, Model $k)
    {
        return $this->sesamaQuery($daftar, $daftar === 'temuan' ? $k->sumber->value : null);
    }

    private function sesamaQuery(string $daftar, ?string $sumber)
    {
        return match ($daftar) {
            'temuan' => KategoriTemuan::where('sumber', $sumber)->orderBy('urutan')->orderBy('id'),
            'intern' => Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->orderBy('urutan')->orderBy('id'),
            'sifat' => Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->orderBy('urutan')->orderBy('id'),
            'bentuk' => Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->orderBy('urutan')->orderBy('id'),
        };
    }

    /** Berapa temuan, rekomendasi, atau tindak lanjut yang memakai satu butir. */
    private function dipakaiN(string $daftar, Model $k): int
    {
        return match ($daftar) {
            'temuan' => Temuan::where('kategori_temuan_id', $k->id)->count(),
            'intern' => Temuan::where('kategori_intern_id', $k->id)->count(),
            'sifat' => Rekomendasi::where('sifat_id', $k->id)->count()
                + ($k->nama === 'Administratif' ? Rekomendasi::whereNull('sifat_id')->count() : 0),
            'bentuk' => Tindakan::where('bentuk_id', $k->id)->count(),
        };
    }

    /** Pemakai tiap daftar, untuk kalimat "N temuan dipindahkan". */
    private function satuan(string $daftar): string
    {
        return match ($daftar) {
            'sifat' => 'rekomendasi',
            'bentuk' => 'tindak lanjut',
            default => 'temuan',
        };
    }

    private function sebut(string $daftar, Model $k): string
    {
        return match ($daftar) {
            'temuan' => 'kategori temuan '.$k->sumber->value,
            'intern' => 'kategori internal',
            'sifat' => 'sifat rekomendasi',
            'bentuk' => 'bentuk tindak lanjut',
        };
    }

    /** Sebutan butir di kalimat log: "kategori temuan LHP: Kepatuhan". */
    private function sebutLog(string $daftar, Model $k): string
    {
        return match ($daftar) {
            'temuan' => 'kategori temuan '.$k->sumber->value.': '.$k->nama,
            'intern' => 'kategori internal '.$k->nama,
            'sifat' => 'sifat rekomendasi '.$k->nama,
            'bentuk' => 'bentuk tindak lanjut '.$k->nama,
        };
    }

    /** Kembali ke daftarnya, dengan baris yang baru diubah disorot. */
    private function kembaliDaftar(string $daftar, Model $k)
    {
        return match ($daftar) {
            'temuan' => $this->kembali('temuan', 'kt-'.$k->id, ['filter' => $k->sumber->value]),
            'intern' => $this->kembali('intern', 'kat-'.$k->id),
            'sifat' => $this->kembali('sifat', 'sifat-'.$k->id),
            'bentuk' => $this->kembali('bentuk', 'bentuk-'.$k->id),
        };
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

    private function pastikanBentuk(Referensi $r): void
    {
        abort_unless($r->jenis === JenisReferensi::BENTUK_TL, 403,
            'Daftar ini bukan bentuk tindak lanjut.');
    }

    private function pastikanAdmin(Request $req): void
    {
        abort_unless($req->user()->peranAktif()->kelolaMaster(), 403,
            'Data Master hanya bisa diubah Admin DTI.');
    }

    private function pastikanIntern(Referensi $r): void
    {
        abort_unless($r->jenis === JenisReferensi::KATEGORI_INTERN, 403,
            'Daftar ini bukan kategori internal.');
    }
}
