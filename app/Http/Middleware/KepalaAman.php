<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kepala keamanan: CSP (skrip hanya dari aplikasi ini atau berisi `nonce`), anti-bingkai,
 * anti-cache halaman berlogin, HSTS bila HTTPS.
 */
class KepalaAman
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);
        View::share('nonce', $nonce);

        $res = $next($request);

        $h = $res->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        if ($request->is('sso-login', 'api/sso-check') || $request->routeIs('sso.dwaradaya.*')) {
            $h->set('Referrer-Policy', 'no-referrer');
        } elseif ($h->get('Referrer-Policy') !== 'no-referrer') {
            $h->set('Referrer-Policy', 'same-origin');
        }
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $html = str_contains((string) $h->get('Content-Type'), 'text/html');
        if ($html && config('simtlhp.keamanan.csp', true)) {
            $h->set('Content-Security-Policy', $this->csp($nonce));
        }
        /* Halaman berlogin, halaman masuk, dan JSON milik akun — semuanya pribadi. */
        if ($html || $request->user() || $request->routeIs('masuk*', 'sso.*') || $request->is('api/sso-check')) {
            $h->set('Cache-Control', 'no-store, private, max-age=0');
            $h->set('Pragma', 'no-cache');
        }

        return $res;
    }

    private function csp(string $nonce): string
    {
        /* Pintu SSO boleh jadi tujuan formulir keluar (RP-initiated logout). */
        $sso = array_filter(array_map(fn ($u) => $this->asal((string) $u), [
            config('simtlhp.sso.oidc.authorize'), config('simtlhp.sso.oidc.logout'),
        ]));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'".($sso ? ' '.implode(' ', array_unique($sso)) : ''),
        ]);
    }

    /** "https://sso.pu.go.id/realms/x/auth" → "https://sso.pu.go.id". */
    private function asal(string $url): ?string
    {
        $p = parse_url($url);

        return ! empty($p['scheme']) && ! empty($p['host'])
            ? $p['scheme'].'://'.$p['host'].(! empty($p['port']) ? ':'.$p['port'] : '')
            : null;
    }
}
