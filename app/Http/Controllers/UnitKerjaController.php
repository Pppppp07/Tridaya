<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\DirektoriIrm;
use App\Support\PenanggungJawab;
use App\Support\PusatData;
use App\Support\Terlihat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Unit kerja (Admin DTI): tambah, nonaktifkan, pilih penanggung jawab dari IRM. Tidak ada yang
 * dihapus; nama yang sudah tertulis di berkas tidak diganti.
 */
class UnitKerjaController extends Controller
{
    public function tambah(Request $req)
    {
        $this->pastikanAdmin($req);

        $data = $req->validate($this->aturan(true), self::PESAN);
        $nama = $this->rapikan($data['nama']);
        $pendek = $this->rapikan($data['pendek'] ?? '') ?: $nama;
        $kode = $data['kode_satker'] ?? null;

        if ($bentrok = $this->bentrok([$nama, $pendek])) {
            return $this->gagal('unit', 'Namanya sudah dipakai '.$bentrok->namaPendek().'.');
        }

        $unit = Satker::create([
            'kode' => Satker::kodeBaru($pendek),
            'kode_satker' => $kode,
            'nama' => $nama,
            'nama_pendek' => $pendek,
            'aktif' => true,
        ]);
        Terlihat::lupakan();
        Aktivitas::catat('master.unit.tambah', 'Menambah unit kerja '.$pendek, [
            'subjek' => $unit, 'rincian' => ['sesudah' => array_filter(['nama' => $nama, 'pendek' => $pendek,
                'kode_satker' => $kode, 'aktif' => true], fn ($v) => $v !== null)],
        ]);

        return $this->kembali($unit, $req)->with('pesan', $pendek.' ditambahkan. Pilih penanggung jawabnya dari IRM.');
    }

    /** Nama yang sudah tertulis di berkas tidak diubah dari sini; kode satker tetap bisa dilengkapi. */
    public function simpan(Request $req, Satker $satker)
    {
        $this->pastikanAdmin($req);

        $kunci = $satker->dipakai();
        $data = $req->validate($this->aturan(! $kunci, $satker), self::PESAN);
        $nama = $this->rapikan($data['nama'] ?? '') ?: $satker->nama;
        $pendek = $this->rapikan($data['pendek'] ?? '') ?: $nama;
        $kode = $data['kode_satker'] ?? null;
        if ($kunci && ($nama !== $satker->nama || $pendek !== $satker->namaPendek())) {
            return $this->kembali($satker, $req)->with('gagal', 'Unit kerja ini sudah tertulis di berkas, jadi namanya tidak bisa diganti.');
        }
        if ($bentrok = $this->bentrok([$nama, $pendek], $satker)) {
            return $this->gagal('unit-'.$satker->id, 'Namanya sudah dipakai '.$bentrok->namaPendek().'.');
        }

        $sebelum = ['nama' => $satker->nama, 'pendek' => $satker->namaPendek(), 'kode_satker' => $satker->kode_satker];
        $sesudah = ['nama' => $nama, 'pendek' => $pendek, 'kode_satker' => $kode];
        $satker->update(['nama' => $nama, 'nama_pendek' => $pendek, 'kode_satker' => $kode]);
        Terlihat::lupakan();
        if ($beda = Aktivitas::beda($sebelum, $sesudah)) {
            $hanyaKode = $sebelum['nama'] === $nama && $sebelum['pendek'] === $pendek;
            Aktivitas::catat('master.unit.ubah', ($hanyaKode ? 'Mengubah kode satker ' : 'Mengubah nama unit kerja ').$sebelum['pendek'],
                ['subjek' => $satker, 'rincian' => $beda]);
        }

        return $this->kembali($satker, $req);
    }

    /**
     * Unit yang dipadamkan tidak ditawarkan untuk laporan baru; pekerjaan lamanya tetap berjalan.
     */
    public function saklar(Request $req, Satker $satker)
    {
        $this->pastikanAdmin($req);

        $satker->update(['aktif' => ! $satker->aktif]);
        Aktivitas::catat('master.unit.saklar', ($satker->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' unit kerja '.$satker->namaPendek(), [
            'subjek' => $satker, 'rincian' => ['sebelum' => ['aktif' => ! $satker->aktif], 'sesudah' => ['aktif' => $satker->aktif]],
        ]);

        return $this->kembali($satker, $req);
    }

    /**
     * Menetapkan penanggung jawab: formulir hanya mengirim NIP; nama, jabatan, dan email diambil
     * dari IRM.
     */
    public function penanggungJawab(Request $req, Satker $satker)
    {
        $this->pastikanAdmin($req);

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
            ? 'Penanggung jawab '.$satker->namaPendek().' kini '.$pegawai['nama'].'. '.($lama->fresh()->aktif
                ? 'Akses Satuan kerja '.$lama->name.' dihentikan; peran lainnya tetap tersedia.'
                : $lama->name.' tidak bisa masuk lagi.')
            : $pegawai['nama'].' kini penanggung jawab '.$satker->namaPendek().'.');
    }

    /** Pencarian IRM untuk jendela pemilih; templatnya sama dengan tampilan tanpa skrip. */
    public function cariIrm(Request $req, Satker $satker)
    {
        $this->pastikanAdmin($req);

        return view('data-master.hasil-irm', $this->hasilIrm($satker, (string) $req->query('q', '')));
    }

    /** @return array<string, mixed> isi untuk `data-master.hasil-irm` */
    public static function hasilIrm(Satker $unit, string $q): array
    {
        $mencari = mb_strlen(trim($q)) >= 2;

        return [
            'unit' => $unit,
            'q' => trim($q),
            'mencari' => $mencari,
            'hasil' => $mencari ? DirektoriIrm::cari($q) : DirektoriIrm::diUnit($unit->kode),
            /* Unit kerja pegawai menurut IRM, disebut dengan nama pendeknya. */
            'pendekUnit' => Satker::pluck('nama_pendek', 'kode'),
            /* NIP → unit kerja yang dipegangnya sekarang. */
            'pemegang' => User::where('peran', PeranPengguna::SATKER->value)
                ->where('aktif', true)->whereNotNull('nip')->with('satker')->get()
                ->mapWithKeys(fn ($u) => [$u->nip => $u->satker]),
            /* NIP → akun pusat aktif. Penetapan PJ menambahkan peran Satker tanpa mencabut peran
               pusatnya. */
            'pusat' => User::where('peran', '!=', PeranPengguna::SATKER->value)
                ->where('aktif', true)->whereNotNull('nip')->get()->keyBy('nip'),
            'error' => DirektoriIrm::error(),
        ];
    }

    /* ---- Pembantu ---- */

    private const PESAN = [
        'nama.required' => 'Nama lengkap unit kerja harus terisi.',
        'kode_satker.regex' => 'Kode satker terdiri dari 6 angka.',
        'kode_satker.unique' => 'Kode satker itu sudah dipakai satuan kerja lain.',
    ];

    private function aturan(bool $namaWajib, ?Satker $kecuali = null): array
    {
        return [
            'nama' => [$namaWajib ? 'required' : 'nullable', 'string', 'max:200'],
            'pendek' => ['nullable', 'string', 'max:80'],
            'kode_satker' => ['nullable', 'regex:/^\d{6}$/', Rule::unique('satkers', 'kode_satker')->ignore($kecuali?->id)],
        ];
    }

    /** Kembali ke daftar Unit kerja, baris yang diubah disorot, filternya tetap. */
    private function kembali(?Satker $unit = null, ?Request $req = null)
    {
        if ($k = PusatData::alamatKembali($req?->input('kembali'))) {
            return redirect($k.($unit ? (str_contains($k, '?') ? '&' : '?').'kartu=unit-'.$unit->id : ''));
        }

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

    private function pastikanAdmin(Request $req): void
    {
        abort_unless($req->user()->peranAktif()->kelolaMaster(), 403,
            'Data master hanya bisa diubah Admin DTI.');
    }
}
