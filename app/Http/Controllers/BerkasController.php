<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Lampiran;

class BerkasController extends Controller
{
    /**
     * Berkas tidak pernah disajikan lewat link langsung ke folder
     * penyimpanan. Rute ini memeriksa dulu apakah yang meminta memang berhak
     * melihatnya, baru berkasnya dikirim.
     */
    public function show(Lampiran $lampiran)
    {
        if (auth()->user()->peran === PeranPengguna::SATKER) {
            $satker = auth()->user()->satker_id;

            /* Surat pemeriksaan asli tidak pernah sampai ke satuan kerja: satu
               surat memuat temuan seluruh satuan kerja, dan yang lain bukan
               urusannya. Filter hak lihat sudah membuangnya dari tampilan;
               ini penjaga terakhirnya, untuk yang menebak alamatnya sendiri. */
            abort_if($lampiran->surat_asli, 403,
                'Surat pemeriksaan asli memuat temuan seluruh satuan kerja.');

            /* Berkas milik satu baris jelas pemiliknya; yang menempel pada
               rekomendasi cukup menyangkut salah satu barisnya. */
            if ($lampiran->sasaran_id) {
                abort_unless($lampiran->sasaran?->satker_id === $satker, 403,
                    'Berkas ini milik satuan kerja lain.');
            } elseif ($rek = $lampiran->rekomendasi) {
                abort_unless($rek->daftarSasaran()->contains('satker_id', $satker), 403,
                    'Berkas ini milik satuan kerja lain.');
            } elseif ($lampiran->laporan_id) {
                abort(403, 'Berkas laporan hanya dibuka Setba.');
            }
        }

        /* Data contoh belum punya berkas sungguhan di penyimpanan, jadi yang
           ditampilkan keterangannya. Begitu unggahan sungguhan dipasang, baris
           ini berganti Storage::download(). */
        return view('berkas', ['b' => $lampiran]);
    }
}
