<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\DrafHistoris;
use App\Support\Aktivitas;
use App\Support\Historis\BacaExcel;
use App\Support\Historis\ExcelTidakValid;
use App\Support\Historis\ImporHistoris;
use App\Support\Historis\PetaKolom;
use App\Support\Historis\ReviewHistoris;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HistorisController extends Controller
{
    private function boleh(?DrafHistoris $draf = null): void
    {
        abort_unless(in_array(auth()->user()->peranAktif(), [PeranPengguna::SETBA, PeranPengguna::ADMIN], true), 403);
        // Draft pribadi. Role Admin tidak otomatis membuka upload milik user lain.
        if ($draf) {
            abort_unless($draf->user_id === auth()->id(), 404);
        }
    }

    public function index()
    {
        $this->boleh();

        // Isian terkompresi menyimpan jumlah barisnya; draft lama masih JSON biasa.
        $hitung = DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(json_extract(isian, '$.jumlah'), json_array_length(isian, '$.baris'))"
            : "COALESCE(JSON_EXTRACT(isian, '$.jumlah'), JSON_LENGTH(isian, '$.baris'))";

        return view('laporan.historis.index', ['drafts' => DrafHistoris::where('user_id', auth()->id())
            ->select(['id', 'nama_berkas', 'created_at', 'diimpor_pada'])->selectRaw($hitung.' as jumlah_baris')->latest()->paginate(15)]);
    }

    public function upload(Request $req, BacaExcel $pembaca, ReviewHistoris $review)
    {
        $this->boleh();
        $req->validate(['excel' => 'required|file|extensions:xlsx,csv|max:2048'], [
            'excel.required' => 'Pilih file Excel atau CSV terlebih dahulu.', 'excel.extensions' => 'Gunakan file .xlsx atau .csv.', 'excel.max' => 'Maksimal ukuran file 2 MB.',
        ]);
        $file = $req->file('excel');
        $jenis = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        $hash = hash_file('sha256', $file->getRealPath());
        $sama = DrafHistoris::where('user_id', auth()->id())->where('sha256', $hash)->latest()->first();
        if ($sama) {
            return redirect()->route('laporan.historis.review', $sama)->with('pesan', 'File ini sudah pernah diupload. Review sebelumnya dibuka kembali.');
        }
        $path = $file->storeAs('historis/'.auth()->id(), Str::uuid().'.'.$jenis, 'local');
        abort_unless($path, 500, 'File belum bisa disimpan. Coba lagi atau hubungi Admin DTI.');
        try {
            $isian = $review->awal($pembaca->baca(Storage::disk('local')->path($path), $jenis));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof ExcelTidakValid || $e instanceof \UnexpectedValueException) {
                throw ValidationException::withMessages(['excel' => $e instanceof ExcelTidakValid ? $e->getMessage() : 'File tidak bisa dibaca. Simpan kembali sebagai XLSX atau CSV biasa.']);
            }
            throw $e;
        }
        try {
            $draf = DrafHistoris::create(['user_id' => auth()->id(), 'nama_berkas' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'berkas' => $path, 'sha256' => $hash, 'isian' => $isian]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof QueryException && str_contains($e->getMessage(), 'max_allowed_packet')) {
                throw ValidationException::withMessages(['excel' => 'Isi file terlalu besar untuk disimpan sebagai draft. Pisahkan file menjadi beberapa bagian.']);
            }
            throw $e;
        }
        Aktivitas::catat('laporan.historis.upload', 'Mengupload '.($jenis === 'csv' ? 'CSV' : 'Excel').' untuk review data historis', ['kelompok' => 'laporan', 'subjek' => $draf, 'rincian' => ['baris' => count($isian['baris']), 'sha256' => $hash]]);

        return redirect()->route('laporan.historis.review', $draf);
    }

    public function review(Request $req, DrafHistoris $draf, ReviewHistoris $review)
    {
        $this->boleh($draf);
        $rows = $review->periksa($draf->isian);
        $pilihan = $review->pilihan();
        $banding = array_map(fn ($r) => PetaKolom::bandingkan($r, $pilihan), $rows);
        $filter = in_array($req->query('filter'), ['perlu', 'siap', 'keluar', 'duplikat', 'kolom'], true) ? $req->query('filter') : '';
        /* Saringan dari ringkasan kolom: baris dengan kolom tertentu pada keadaan tertentu (bawaan: bermasalah). */
        $kolom = array_key_exists((string) $req->query('kolom'), PetaKolom::KOLOM) ? (string) $req->query('kolom') : '';
        $keadaan = $kolom !== '' && array_key_exists((string) $req->query('keadaan'), PetaKolom::KEADAAN) ? (string) $req->query('keadaan') : '';
        $semua = collect($rows)->map(fn ($r, $i) => $r + ['index' => $i, 'banding' => $banding[$i], 'masalah' => PetaKolom::masalah($banding[$i])]);
        $tampil = $semua->filter(fn ($r) => match ($filter) {
            'perlu' => $r['ikut'] && ! $r['siap'], 'siap' => $r['siap'], 'keluar' => ! $r['ikut'], 'duplikat' => (bool) $r['duplikat_id'],
            'kolom' => (bool) $r['masalah'], default => true,
        })->filter(fn ($r) => $kolom === '' || ($keadaan !== '' ? $r['banding'][$kolom]['keadaan'] === $keadaan : isset($r['masalah'][$kolom])));
        $page = max(1, min((int) $req->query('page', 1), max(1, (int) ceil($tampil->count() / 30))));

        return view('laporan.historis.review', compact('draf', 'semua', 'filter', 'page', 'kolom', 'keadaan') + [
            'rows' => $tampil->forPage($page, 30), 'jumlahTampil' => $tampil->count(), 'ringkas' => PetaKolom::ringkas($banding, $rows)]);
    }

    public function baris(DrafHistoris $draf, int $baris, ReviewHistoris $review)
    {
        $this->boleh($draf);
        $rows = $review->periksa($draf->isian);
        abort_unless(isset($rows[$baris]), 404);

        return view('laporan.historis.baris', ['draf' => $draf, 'row' => $rows[$baris], 'i' => $baris, 'pilihan' => $review->pilihan(),
            'banding' => PetaKolom::bandingkan($rows[$baris], $review->pilihan()), 'lain' => PetaKolom::kolomLain($rows[$baris])]);
    }

    public function simpan(Request $req, DrafHistoris $draf, int $baris)
    {
        $this->boleh($draf);
        $aturan = ['versi' => 'required|integer|min:1', 'ikut' => 'required|boolean', 'diperiksa' => 'required|boolean'];
        foreach (ReviewHistoris::LABEL as $k => $_) {
            $aturan['data.'.$k] = 'nullable|string|max:20000';
        }
        $data = $req->validate($aturan);
        DB::transaction(function () use ($draf, $baris, $data) {
            $draf = DrafHistoris::whereKey($draf->id)->lockForUpdate()->firstOrFail();
            abort_if($draf->diimpor_pada, 409, 'Review yang sudah diimpor tidak bisa diubah.');
            if ($draf->versi !== (int) $data['versi']) {
                throw ValidationException::withMessages(['review' => 'Draft sudah berubah di tab lain. Muat ulang sebelum menyimpan.']);
            }
            $isian = $draf->isian;
            abort_unless(isset($isian['baris'][$baris]), 404);
            foreach (ReviewHistoris::LABEL as $k => $_) {
                $isian['baris'][$baris]['data'][$k] = trim((string) ($data['data'][$k] ?? ''));
            }
            $isian['baris'][$baris]['ikut'] = (bool) $data['ikut'];
            $isian['baris'][$baris]['diperiksa'] = (bool) $data['diperiksa'];
            $draf->update(['isian' => $isian, 'versi' => $draf->versi + 1]);
            Aktivitas::catat('laporan.historis.review', 'Menyimpan review baris historis', ['kelompok' => 'laporan', 'subjek' => $draf, 'rincian' => ['baris' => $isian['baris'][$baris]['baris'], 'sheet' => $isian['baris'][$baris]['sheet'], 'diperiksa' => (bool) $data['diperiksa'], 'ikut' => (bool) $data['ikut']]]);
        });

        return redirect()->route('laporan.historis.baris', [$draf, $baris])->with('pesan', 'Review tersimpan.');
    }

    /** Tandai sekaligus baris yang tidak bermasalah; yang masih perlu diperbaiki tidak ikut. */
    public function periksaSemua(Request $req, DrafHistoris $draf, ReviewHistoris $review)
    {
        $this->boleh($draf);
        $data = $req->validate(['versi' => 'required|integer|min:1']);
        $n = DB::transaction(function () use ($draf, $data, $review) {
            $draf = DrafHistoris::whereKey($draf->id)->lockForUpdate()->firstOrFail();
            abort_if($draf->diimpor_pada, 409, 'Review yang sudah diimpor tidak bisa diubah.');
            if ($draf->versi !== (int) $data['versi']) {
                throw ValidationException::withMessages(['review' => 'Draft sudah berubah di tab lain. Muat ulang sebelum menandai.']);
            }
            $isian = $draf->isian;
            $n = 0;
            foreach ($review->periksa($isian) as $i => $row) {
                if ($row['ikut'] && ! $row['errors'] && ! $row['diperiksa']) {
                    $isian['baris'][$i]['diperiksa'] = true;
                    $n++;
                }
            }
            if ($n) {
                $draf->update(['isian' => $isian, 'versi' => $draf->versi + 1]);
                Aktivitas::catat('laporan.historis.review', 'Menandai '.$n.' baris historis tanpa masalah sudah diperiksa',
                    ['kelompok' => 'laporan', 'subjek' => $draf, 'rincian' => ['baris' => $n, 'sekaligus' => true]]);
            }

            return $n;
        });

        return redirect()->route('laporan.historis.review', $draf)
            ->with('pesan', $n ? $n.' baris ditandai sudah diperiksa.' : 'Tidak ada baris tanpa masalah yang belum diperiksa.');
    }

    public function massal(Request $req, DrafHistoris $draf)
    {
        $this->boleh($draf);
        $data = $req->validate(['versi' => 'required|integer|min:1', 'nomor' => 'required|string|max:255',
            'tgl_surat' => 'nullable|date_format:Y-m-d', 'tgl_terima' => 'nullable|date_format:Y-m-d', 'tanggal_acuan' => 'nullable|date_format:Y-m-d']);
        if (! ($data['tgl_surat'] ?? null) && ! ($data['tgl_terima'] ?? null) && ! ($data['tanggal_acuan'] ?? null)) {
            throw ValidationException::withMessages(['review' => 'Isi setidaknya satu tanggal untuk diterapkan.']);
        }
        DB::transaction(function () use ($draf, $data) {
            $draf = DrafHistoris::whereKey($draf->id)->lockForUpdate()->firstOrFail();
            abort_if($draf->diimpor_pada, 409);
            if ($draf->versi !== (int) $data['versi']) {
                throw ValidationException::withMessages(['review' => 'Draft sudah berubah di tab lain. Muat ulang sebelum menerapkan tanggal.']);
            }
            $isian = $draf->isian;
            foreach ($isian['baris'] as &$row) {
                // "*": semua laporan, mis. tanggal acuan yang sama untuk seluruh file.
                if ($data['nomor'] !== '*' && $row['data']['nomor'] !== $data['nomor']) {
                    continue;
                }
                foreach (['tgl_surat', 'tgl_terima', 'tanggal_acuan'] as $k) {
                    if (($data[$k] ?? '') !== '' && $data[$k] !== null && $row['data'][$k] !== $data[$k]) {
                        $row['data'][$k] = $data[$k];
                        $row['diperiksa'] = false;
                    }
                }
            }
            unset($row);
            $draf->update(['isian' => $isian, 'versi' => $draf->versi + 1]);
            Aktivitas::catat('laporan.historis.tanggal', 'Menerapkan tanggal pada draft historis', ['kelompok' => 'laporan', 'subjek' => $draf]);
        });

        return redirect()->route('laporan.historis.review', $draf)->with('pesan', 'Tanggal diterapkan. Periksa kembali baris yang berubah.');
    }

    public function impor(Request $req, DrafHistoris $draf, ImporHistoris $impor)
    {
        $this->boleh($draf);
        $data = $req->validate(['versi' => 'required|integer|min:1', 'konfirmasi' => 'accepted'], ['konfirmasi.accepted' => 'Konfirmasikan bahwa data pilihan sudah diperiksa.']);
        try {
            $impor->jalankan($draf, (int) $data['versi']);
        } catch (QueryException $e) {
            if (! str_starts_with((string) $e->getCode(), '23') && (string) $e->getCode() !== '19') {
                throw $e;
            }
            throw ValidationException::withMessages(['review' => 'Data berubah atau bertabrakan saat impor. Tidak ada data dari konfirmasi ini yang disimpan. Muat ulang review.']);
        }

        return redirect()->route('laporan.historis.review', $draf)->with('pesan', 'Data historis berhasil dimasukkan ke database utama.');
    }

    public function sumber(DrafHistoris $draf)
    {
        $this->boleh($draf);
        abort_unless(Storage::disk('local')->exists($draf->berkas), 404);

        $ekstensi = strtolower(pathinfo($draf->berkas, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'xlsx';

        return Storage::disk('local')->download($draf->berkas, 'sumber-historis-'.$draf->id.'.'.$ekstensi, ['X-Content-Type-Options' => 'nosniff']);
    }
}
