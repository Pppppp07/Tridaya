<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\DirektoriIrm;
use App\Support\PenanggungJawab;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Data master unit kerja — padanan kartu "Unit kerja" di `LayarMaster`.
 *
 * Kata Bang Kamal: "Master unit kerja nggak ada nih." Daftar satuan kerja dulu
 * cuma bisa diisi lewat seeder; sekarang Setba boleh menambah, menonaktifkan,
 * dan memilih penanggung jawab tiap unit kerja dari IRM.
 *
 * Tidak ada yang dihapus. Unit kerja yang sudah tertulis di berkas juga tidak
 * bisa berganti nama — kalau namanya berubah karena penataan organisasi,
 * tambahkan yang baru lalu nonaktifkan yang lama.
 */
class UnitKerjaController extends Controller
{
    public function tambah(Request $req)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama'   => ['required', 'string', 'max:200'],
            'pendek' => ['nullable', 'string', 'max:80'],
        ], ['nama.required' => 'Nama lengkap unit kerja harus terisi.']);
        $nama = $this->rapikan($data['nama']);
        $pendek = $this->rapikan($data['pendek'] ?? '') ?: $nama;

        if ($bentrok = $this->bentrok([$nama, $pendek])) {
            return $this->gagal('unit', 'Namanya sudah dipakai '.$bentrok->namaPendek().'.');
        }

        $unit = Satker::create([
            'kode'        => Satker::kodeBaru($pendek),
            'nama'        => $nama,
            'nama_pendek' => $pendek,
            'jenis'       => $this->jenis($nama),
            'aktif'       => true,
        ]);
        Terlihat::lupakan();
        Aktivitas::catat('master.unit.tambah', 'Menambah unit kerja '.$pendek, [
            'subjek' => $unit, 'rincian' => ['sesudah' => ['nama' => $nama, 'pendek' => $pendek, 'aktif' => true]],
        ]);

        return $this->kembali($unit)->with('pesan', $pendek.' ditambahkan. Pilih penanggung jawabnya dari IRM.');
    }

    /**
     * Nama yang sudah tertulis di berkas tidak diubah dari sini: berkas lama
     * menyebut nama itu, dan kalimat yang menyebutnya disamarkan dari satuan
     * kerja lain menurut daftar nama ini.
     */
    public function simpan(Request $req, Satker $satker)
    {
        $this->pastikanSetba($req);

        $data = $req->validate([
            'nama'   => ['required', 'string', 'max:200'],
            'pendek' => ['nullable', 'string', 'max:80'],
        ], ['nama.required' => 'Nama lengkap unit kerja harus terisi.']);
        if ($satker->dipakai()) {
            return $this->kembali($satker)->with('gagal', 'Unit kerja ini sudah tertulis di berkas, jadi namanya tidak bisa diganti.');
        }
        $nama = $this->rapikan($data['nama']);
        $pendek = $this->rapikan($data['pendek'] ?? '') ?: $nama;
        if ($bentrok = $this->bentrok([$nama, $pendek], $satker)) {
            return $this->gagal('unit-'.$satker->id, 'Namanya sudah dipakai '.$bentrok->namaPendek().'.');
        }

        $sebelum = ['nama' => $satker->nama, 'pendek' => $satker->namaPendek()];
        $satker->update(['nama' => $nama, 'nama_pendek' => $pendek]);
        Terlihat::lupakan();
        if ($beda = Aktivitas::beda($sebelum, ['nama' => $nama, 'pendek' => $pendek])) {
            Aktivitas::catat('master.unit.ubah', 'Mengubah nama unit kerja '.$sebelum['pendek'], ['subjek' => $satker, 'rincian' => $beda]);
        }

        return $this->kembali($satker);
    }

    /**
     * Menyalakan atau memadamkan. Unit kerja yang dipadamkan tidak lagi
     * ditawarkan saat mencatat laporan baru; pekerjaan lamanya tetap berjalan
     * dan penanggung jawabnya tetap bisa masuk.
     */
    public function saklar(Request $req, Satker $satker)
    {
        $this->pastikanSetba($req);

        $satker->update(['aktif' => ! $satker->aktif]);
        Aktivitas::catat('master.unit.saklar', ($satker->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' unit kerja '.$satker->namaPendek(), [
            'subjek' => $satker, 'rincian' => ['sebelum' => ['aktif' => ! $satker->aktif], 'sesudah' => ['aktif' => $satker->aktif]],
        ]);

        return $this->kembali($satker);
    }

    /**
     * Menetapkan penanggung jawab. Yang dikirim formulir cuma NIP; nama,
     * jabatan, dan email diambil dari IRM — "jangan tertukar atributnya".
     */
    public function penanggungJawab(Request $req, Satker $satker)
    {
        $this->pastikanSetba($req);

        $data = $req->validate(['nip' => ['required', 'string', 'max:30']]);
        $pegawai = DirektoriIrm::nip($data['nip']);
        if (! $pegawai) {
            return redirect()->route('master', ['pj' => $satker->id])
                ->with('gagal', DirektoriIrm::error() ?: 'Pegawai dengan NIP itu tidak ada di IRM.');
        }

        $lama = $satker->penanggungJawab;
        if ($alasan = PenanggungJawab::tetapkan($satker, $pegawai)) {
            return redirect()->route('master', ['pj' => $satker->id])->with('gagal', $alasan);
        }
        if (! $lama || $lama->nip !== $pegawai['nip']) {
            Aktivitas::catat('master.unit.pj', $lama
                ? 'Mengganti penanggung jawab '.$satker->namaPendek().': '.$lama->name.' → '.$pegawai['nama']
                : 'Menetapkan '.$pegawai['nama'].' sebagai penanggung jawab '.$satker->namaPendek(), [
                    'subjek' => $satker,
                    'rincian' => [
                        'sebelum' => ['pj' => $lama ? $lama->name.' (NIP '.$lama->nip.')' : null],
                        'sesudah' => ['pj' => $pegawai['nama'].' (NIP '.$pegawai['nip'].')'],
                    ],
                ]);
        }

        return $this->kembali($satker)->with('pesan', $lama && $lama->nip !== $pegawai['nip']
            ? 'Penanggung jawab '.$satker->namaPendek().' kini '.$pegawai['nama'].'. Akun '.$lama->name.' dinonaktifkan.'
            : $pegawai['nama'].' kini penanggung jawab '.$satker->namaPendek().'.');
    }

    /**
     * Pencarian IRM untuk jendela pemilih. Mengembalikan potongan HTML daftar
     * hasilnya — digambar oleh templat yang sama dengan tampilan tanpa skrip,
     * jadi keduanya tidak mungkin berbeda.
     */
    public function cariIrm(Request $req, Satker $satker)
    {
        $this->pastikanSetba($req);

        return view('data-master.hasil-irm', $this->hasilIrm($satker, (string) $req->query('q', '')));
    }

    /** @return array<string, mixed> isi untuk `data-master.hasil-irm` */
    public static function hasilIrm(Satker $unit, string $q): array
    {
        $mencari = mb_strlen(trim($q)) >= 2;

        return [
            'unit'     => $unit,
            'q'        => trim($q),
            'mencari'  => $mencari,
            'hasil'    => $mencari ? DirektoriIrm::cari($q) : DirektoriIrm::diUnit($unit->kode),
            /* Unit kerja pegawai menurut IRM, disebut dengan nama pendeknya. */
            'pendekUnit' => Satker::pluck('nama_pendek', 'kode'),
            /* NIP → unit kerja yang dipegangnya sekarang. */
            'pemegang' => User::where('peran', PeranPengguna::SATKER->value)
                ->where('aktif', true)->whereNotNull('nip')->with('satker')->get()
                ->mapWithKeys(fn ($u) => [$u->nip => $u->satker]),
            /* NIP → akun pusat aktif. Petugas Setba, UKI, … tidak bisa
               sekaligus jadi penanggung jawab unit kerja (27 Sep). */
            'pusat' => User::where('peran', '!=', PeranPengguna::SATKER->value)
                ->where('aktif', true)->whereNotNull('nip')->get()->keyBy('nip'),
            'error' => DirektoriIrm::error(),
        ];
    }

    /* ================================================================
       PEMBANTU
       ================================================================ */

    private function kembali(?Satker $unit = null)
    {
        return redirect()->route('master', array_filter([
            'tab' => 'unit',
            'kartu' => $unit ? 'unit-'.$unit->id : null,
        ]));
    }

    /** Kembali ke jendela yang sama, isian dan pesan salahnya dibawa. */
    private function gagal(string $jendela, string $pesan)
    {
        return redirect()->route('master', ['tab' => 'unit', 'jendela' => $jendela])->withInput()->with('gagal', $pesan);
    }

    private function rapikan(?string $x): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $x));
    }

    /** Unit kerja lain yang nama panjang atau pendeknya sama dengan salah satu nama ini. */
    private function bentrok(array $nama, ?Satker $kecuali = null): ?Satker
    {
        $kecil = array_map('mb_strtolower', array_filter($nama));

        return Satker::when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))->get()
            ->first(fn ($s) => in_array(mb_strtolower($s->nama), $kecil, true)
                || in_array(mb_strtolower((string) $s->nama_pendek), $kecil, true));
    }

    /** Golongan unit kerja baru, ditebak dari namanya. */
    private function jenis(string $nama): string
    {
        $n = mb_strtolower($nama);

        return match (true) {
            str_starts_with($n, 'sekretariat') => 'sekretariat',
            str_starts_with($n, 'pusat')       => 'pusat',
            str_starts_with($n, 'politeknik')  => 'politeknik',
            default                            => 'balai',
        };
    }

    private function pastikanSetba(Request $req): void
    {
        abort_unless($req->user()->peran->kelolaMaster(), 403,
            'Data master hanya bisa diubah Setba.');
    }
}
