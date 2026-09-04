<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EnforceCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalUrl = self::usablePublicUrl((string) config('canonical.url'));

        if ($canonicalUrl === null) {
            return $next($request);
        }

        $canonicalHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        $canonicalScheme = strtolower((string) parse_url($canonicalUrl, PHP_URL_SCHEME));
        $currentHost = strtolower($request->getHost());

        if ($canonicalHost === '' || $canonicalScheme === '') {
            return $next($request);
        }

        if ($currentHost === $canonicalHost && $request->getScheme() === $canonicalScheme) {
            return $next($request);
        }

        $knownHosts = array_map('strtolower', (array) config('canonical.legacy_hosts', []));
        if ($currentHost !== $canonicalHost && ! in_array($currentHost, $knownHosts, true)) {
            // Once a canonical origin is enabled, an unknown Host must never
            // become another working CashERP subdomain. Do not reflect it into
            // a redirect; reject it as a misdirected request.
            return new Response('Misdirected Request', 421, [
                'Cache-Control' => 'no-store',
                'Vary' => 'Host',
            ]);
        }

        $target = $canonicalUrl.$request->getRequestUri();
        $status = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;

        return new RedirectResponse($target, $status, [
            'Cache-Control' => 'no-store',
            'Vary' => 'Host',
        ]);
    }

    /**
     * A loopback or private CANONICAL_URL is a local-dev value, not a public
     * origin. Treating it as canonical rejects www.casherp.com with HTTP 421.
     */
    public static function usablePublicUrl(?string $canonicalUrl): ?string
    {
        $canonicalUrl = rtrim((string) $canonicalUrl, '/');
        if ($canonicalUrl === '' || ! filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || $host === '::1') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($isPublic === false) {
                return null;
            }
        }

        return $canonicalUrl;
    }
}
