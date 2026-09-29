<?php

namespace App\Http\Middleware;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Aktivitas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jaring terakhir log aktivitas (27 Sep).
 *
 * Gerak berkas sudah tercatat lewat `Jejak::riwayat()`, dan Data master,
 * Pengguna, serta masuk/keluar mencatat dirinya sendiri lengkap dengan sebelum
 * → sesudah. Yang ditangkap di sini:
 *
 * - kiriman BERHASIL yang belum tercatat lewat jalan itu — supaya rute baru
 *   yang lupa mencatat tetap meninggalkan jejak;
 * - setiap penolakan 403 bagi akun yang sudah masuk. Satuan kerja yang menebak
 *   alamat rekomendasi milik unit lain, atau DTI yang mencoba mengirim isian,
 *   terbaca di log.
 *
 * Kiriman yang gagal diperiksa (kembali dengan pesan salah) tidak mengubah apa
 * pun, jadi tidak dicatat. Membaca halaman juga tidak — yang dijaga perubahan
 * data, bukan siapa membuka apa.
 */
class CatatAktivitas
{
    /** Rute yang tidak mengubah data bersama — menandai pemberitahuan sendiri, masuk/keluar, dan akun
        sendiri (tercatat sendiri bila memang ada yang berubah). */
    private const LEWATI = ['pemberitahuan.*', 'keluar', 'masuk', 'masuk.*', 'sso.*', 'laporan.baru.simpan', 'akun.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $res = $next($request);

        /* Log yang gagal ditulis tidak boleh menggagalkan jawaban yang sudah
           jadi — perubahannya sendiri sudah tersimpan. Kesalahannya dilaporkan. */
        try {
            $this->catat($request, $res);
        } catch (\Throwable $e) {
            report($e);
        }

        return $res;
    }

    private function catat(Request $req, Response $res): void
    {
        $u = $req->user();
        if (! $u || Aktivitas::sudahTercatat($req)) {
            return;
        }
        $status = $res->getStatusCode();
        $rute = (string) $req->route()?->getName();

        if ($status === 403) {
            Aktivitas::catat('akses.ditolak', 'Ditolak: '.$this->sebutAlamat($req), [
                'subjek'  => $this->subjek($req),
                'rincian' => array_filter(['metode' => $req->method(), 'alamat' => '/'.ltrim($req->path(), '/'), 'rute' => $rute ?: null]),
            ]);

            return;
        }
        if ($req->isMethodSafe() || $status >= 400 || ($rute !== '' && Str::is(self::LEWATI, $rute))) {
            return;
        }
        $baru = $req->hasSession() ? (array) $req->session()->get('_flash.new', []) : [];
        if (array_intersect($baru, ['errors', 'gagal'])) {
            return;
        }

        Aktivitas::catat($rute ?: 'lainnya', $this->kalimat($rute, $req), ['subjek' => $this->subjek($req)]);
    }

    /** Kalimat untuk kiriman yang tidak mencatat dirinya sendiri. */
    private function kalimat(string $rute, Request $req): string
    {
        return match ($rute) {
            'laporan.baru.buang' => 'Membuang draf laporan baru',
            default              => 'Mengirim isian '.$this->sebutAlamat($req),
        };
    }

    private function sebutAlamat(Request $req): string
    {
        return $req->method().' /'.ltrim($req->path(), '/');
    }

    /** Rekomendasi atau laporan yang disentuh, dibaca dari parameter rutenya. */
    private function subjek(Request $req): ?\Illuminate\Database\Eloquent\Model
    {
        foreach ((array) $req->route()?->parameters() as $p) {
            if ($p instanceof Sasaran) {
                return $p->tindakan?->rekomendasi;
            }
            if ($p instanceof Rekomendasi || $p instanceof Laporan) {
                return $p;
            }
        }

        return null;
    }
}
