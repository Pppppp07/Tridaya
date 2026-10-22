<?php

namespace App\Http\Middleware;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Aktivitas;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jaring terakhir log aktivitas: kiriman berhasil yang belum tercatat di tempat lain, setiap 403
 * bagi akun yang masuk, dan setiap 429.
 */
class CatatAktivitas
{
    /** Rute yang tidak mengubah data bersama (tercatat sendiri bila ada yang berubah). */
    private const LEWATI = ['pemberitahuan.*', 'keluar', 'masuk', 'masuk.*', 'sso.*', 'laporan.baru.simpan', 'akun.*', 'sesi.*'];

    /** Tanda permintaan yang dibatasi tetapi dijawab pengalihan berpesan (bootstrap/app.php). */
    public const DIBATASI = 'simtlhp.dibatasi';

    public function handle(Request $request, Closure $next): Response
    {
        $res = $next($request);

        /* Log yang gagal ditulis tidak boleh menggagalkan jawaban; kesalahannya dilaporkan. */
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
        if (Aktivitas::sudahTercatat($req)) {
            return;
        }
        $status = $req->attributes->get(self::DIBATASI) ? 429 : $res->getStatusCode();
        $rute = (string) $req->route()?->getName();

        if ($status === 429 || ($u && $status === 403)) {
            Aktivitas::catat($status === 429 ? 'akses.dibatasi' : 'akses.ditolak',
                ($status === 429 ? 'Permintaan dibatasi sementara: ' : 'Ditolak: ').$this->sebutAlamat($req), [
                    'subjek' => $this->subjek($req),
                    'rincian' => array_filter(['code_http' => $status, 'metode' => $req->method(), 'alamat' => '/'.ltrim($req->path(), '/'), 'rute' => $rute ?: null]),
                ]);

            return;
        }
        if (! $u) {
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
            default => 'Mengirim isian '.$this->sebutAlamat($req),
        };
    }

    private function sebutAlamat(Request $req): string
    {
        return $req->method().' /'.ltrim($req->path(), '/');
    }

    /** Rekomendasi atau laporan yang disentuh, dibaca dari parameter rutenya. */
    private function subjek(Request $req): ?Model
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
