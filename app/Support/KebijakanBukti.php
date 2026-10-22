<?php

namespace App\Support;

use App\Models\Sasaran;
use App\Models\SumberBukti;
use App\Rules\LinkAman;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/** Pemeriksaan lokal saja: tidak mengambil halaman/file dari URL user. */
final class KebijakanBukti
{
    public static function sumber(): array
    {
        $req = request();
        if (! $req->attributes->has('bukti.sumber')) {
            $req->attributes->set('bukti.sumber', SumberBukti::where('aktif', true)->get()->toArray());
        }

        return $req->attributes->get('bukti.sumber');
    }

    public static function lupakan(): void
    {
        request()->attributes->remove('bukti.sumber');
    }

    /** @return array{boleh:bool,kode:string,alasan:string,url:string,host:string} */
    public static function periksa(?string $link): array
    {
        $t = trim((string) $link);
        $p = parse_url($t);
        $host = is_array($p) ? strtolower($p['host'] ?? '') : '';
        $tolak = fn (string $kode, string $alasan) => ['boleh' => false, 'kode' => $kode, 'alasan' => $alasan, 'url' => '', 'host' => mb_substr($host, 0, 253)];
        if (! LinkAman::sah($t) || ! is_array($p)) {
            return $tolak('format', 'Link dokumen tidak valid. Gunakan alamat HTTPS lengkap.');
        }
        if (strtolower($p['scheme'] ?? '') !== 'https' || (isset($p['port']) && $p['port'] !== 443)) {
            return $tolak('https', 'Link bukti harus memakai HTTPS tanpa port khusus.');
        }
        if (! self::hostSah($host)) {
            return $tolak('host', 'Gunakan domain penyimpanan yang diizinkan, bukan alamat IP atau domain tersamarkan.');
        }
        $sumber = collect(self::sumber())->firstWhere('host', $host);
        if (! $sumber) {
            return $tolak('sumber', 'Sumber link belum diizinkan. Gunakan sumber yang disetujui Admin DTI.');
        }
        $path = $p['path'] ?? '';
        $decoded = rawurldecode($path);
        if (preg_match('~[\x00-\x1f\x7f\\\\%]|(?:^|/)\.{1,2}(?:/|$)|//~', $decoded)) {
            return $tolak('path', 'Alamat dokumen mengandung jalur yang tidak diizinkan.');
        }
        $q = [];
        parse_str($p['query'] ?? '', $q);
        $id = null;
        $url = '';
        if ($sumber['pola'] === 'drive' && $host === 'drive.google.com') {
            if (preg_match('~^/file/d/([A-Za-z0-9_-]{10,200})(?:/(?:view|preview|edit))?/?$~D', $path, $m)) {
                $id = $m[1];
            } elseif (in_array($path, ['/open', '/uc'], true) && is_string($q['id'] ?? null)
                && preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $q['id'])) {
                $id = $q['id'];
            }
            if ($id) {
                $url = 'https://drive.google.com/file/d/'.$id.'/view';
            }
            $izinQuery = ['id', 'export', 'usp', 'resourcekey', 'authuser', 'pli'];
        } elseif ($sumber['pola'] === 'docs' && $host === 'docs.google.com') {
            if (preg_match('~^/(document|spreadsheets|presentation)/d/([A-Za-z0-9_-]{10,200})(?:/(?:edit|view|preview|export))?/?$~D', $path, $m)) {
                $url = 'https://docs.google.com/'.$m[1].'/d/'.$m[2].'/edit';
            } elseif (preg_match('~^/(document|spreadsheets|presentation)/d/e/([A-Za-z0-9_-]{10,200})/pub(?:html)?/?$~D', $path, $m)) {
                $url = 'https://docs.google.com'.$path;
            }
            $izinQuery = ['usp', 'resourcekey', 'authuser', 'pli', 'format', 'gid', 'single', 'widget', 'headers', 'embedded', 'rm', 'tab'];
        } elseif ($sumber['pola'] === 'repositori') {
            $prefix = $sumber['prefix'];
            if (str_starts_with($decoded, $prefix) && strlen($decoded) > strlen($prefix)) {
                $ext = strtolower(pathinfo($decoded, PATHINFO_EXTENSION));
                if ($ext !== '' && ! in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'], true)) {
                    return $tolak('jenis', 'Jenis file pada link tidak diizinkan untuk bukti dokumen atau foto.');
                }
                $url = $t;
            }
            $izinQuery = ['id', 'download', 'token', 'signature', 'expires', 'key'];
        } else {
            $izinQuery = [];
        }
        if ($url === '') {
            return $tolak('dokumen', 'Gunakan link ke satu file dokumen, bukan folder, halaman utama, video, atau halaman website.');
        }
        foreach ($q as $k => $v) {
            if (! in_array($k, $izinQuery, true) || ! is_string($v) || strlen($v) > 500
                || preg_match('~[\x00-\x1f\x7f]|(?:https?:|//)~i', $v)) {
                return $tolak('redirect', 'Parameter pengalihan atau parameter link tidak diizinkan.');
            }
        }
        /* Link Google dibakukan ke halaman file; parameter pengalihan tidak diteruskan. */
        if (in_array($sumber['pola'], ['drive', 'docs'], true) && isset($q['resourcekey'])) {
            if (! preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $q['resourcekey'])) {
                return $tolak('parameter', 'Kunci akses dokumen tidak valid.');
            }
            $url .= '?resourcekey='.rawurlencode($q['resourcekey']);
        }

        return ['boleh' => true, 'kode' => 'diizinkan', 'alasan' => 'Sumber diizinkan; isi bukti tetap diperiksa UKI/ITJEN.', 'url' => $url, 'host' => $host];
    }

    public static function hostSah(string $host): bool
    {
        return strlen($host) <= 253 && ! filter_var($host, FILTER_VALIDATE_IP)
            && ! str_contains($host, 'xn--')
            && (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host);
    }

    public static function sah(?string $link): bool
    {
        return self::periksa($link)['boleh'];
    }

    public static function catatPenolakan(string $link, array $hasil): void
    {
        $req = request();
        /* Satu request hanya satu log; query/token link tidak disalin ke audit. */
        if (! auth()->check() || $req->attributes->get('bukti.penolakan_tercatat')) {
            return;
        }
        $req->attributes->set('bukti.penolakan_tercatat', true);
        $s = $req->route('sasaran');
        Aktivitas::catat('bukti.link.ditolak', 'Link bukti ditolak: '.$hasil['alasan'], [
            'subjek' => $s instanceof Sasaran ? $s->tindakan->rekomendasi : null,
            'rincian' => ['domain' => $hasil['host'], 'alasan' => $hasil['kode'], 'kode_http' => 422,
                'sidik_link' => hash('sha256', $link)],
        ]);
    }

    /** Pengaman kedua sebelum draf disimpan atau tindak lanjut dikirim. */
    public static function isianSah(array $isi): bool
    {
        $bukti = $isi['bukti'] ?? [];
        $setoran = $isi['setoran'] ?? [];
        if (! is_array($bukti) || ! is_array($setoran)) {
            return false;
        }
        foreach (array_merge($bukti, $setoran) as $b) {
            if (! is_array($b)) {
                return false;
            }
            $link = $b['link'] ?? '';
            if (! is_string($link) || ($link !== '' && ! self::sah($link))) {
                return false;
            }
        }

        return true;
    }

    public static function pastikanIsian(array $isi): void
    {
        if (! self::isianSah($isi)) {
            throw ValidationException::withMessages(['bukti' => 'Ada link bukti yang tidak lagi diizinkan. Perbarui link sebelum mengirim.']);
        }
    }

    /** Gateway bertanda tangan: tujuan tidak menjadi open redirect dari query bebas. */
    public static function pintu(string $link): string
    {
        return URL::signedRoute('berkas.link', ['tujuan' => Crypt::encryptString($link)]);
    }
}
