<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Lampiran;
use App\Support\KebijakanBukti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class BerkasController extends Controller
{
    /** Berkas tidak disajikan langsung dari folder penyimpanan: hak lihat diperiksa dulu. */
    public function show(Lampiran $lampiran)
    {
        $this->pastikanBoleh($lampiran);

        return view('berkas', ['b' => $lampiran, 'linkInfo' => KebijakanBukti::periksa($lampiran->link)]);
    }

    public function buka(Lampiran $lampiran)
    {
        $this->pastikanBoleh($lampiran);
        abort_if($lampiran->ditolak_pada, 422, 'Bukti ini ditolak dan perlu diganti. Arsipnya tetap tersimpan.');

        return $this->alihkan($lampiran->link);
    }

    public function tautan(Request $req)
    {
        try {
            $link = Crypt::decryptString((string) $req->query('tujuan'));
        } catch (\Throwable $e) {
            abort(403, 'Tujuan link tidak valid.');
        }

        return $this->alihkan($link);
    }

    private function alihkan(?string $link)
    {
        $hasil = KebijakanBukti::periksa($link);
        abort_unless($hasil['boleh'], 422, 'Link perlu diperbarui. '.$hasil['alasan']);

        return redirect()->away($hasil['url'], 302, ['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }

    private function pastikanBoleh(Lampiran $lampiran): void
    {
        if (auth()->user()->peranAktif()->terbatasSatker()) {
            $satker = array_map('intval', (array) auth()->user()->satkerAktifId());
            abort_unless($satker, 403, 'Akun ini belum memiliki cakupan satuan kerja.');

            /* Surat pemeriksaan asli tidak boleh sampai ke satuan kerja (memuat temuan semua
               satuan kerja). Penjaga terakhir bagi alamat yang ditebak. */
            abort_if($lampiran->surat_asli, 403,
                'Surat pemeriksaan asli memuat temuan seluruh satuan kerja.');

            /* Berkas milik satu baris jelas pemiliknya; yang menempel pada
               rekomendasi cukup menyangkut salah satu barisnya. */
            if ($lampiran->sasaran_id) {
                abort_unless(in_array((int) $lampiran->sasaran?->satker_id, $satker, true), 403,
                    'Berkas ini milik satuan kerja lain.');
            } elseif ($rek = $lampiran->rekomendasi) {
                abort_unless($rek->daftarSasaran()->whereIn('satker_id', $satker)->isNotEmpty(), 403,
                    'Berkas ini milik satuan kerja lain.');
            } elseif ($lampiran->laporan_id) {
                abort(403, 'Berkas laporan hanya dibuka Setba.');
            } else {
                abort(403, 'Berkas ini belum memiliki cakupan satuan kerja yang jelas.');
            }
        }

    }
}
