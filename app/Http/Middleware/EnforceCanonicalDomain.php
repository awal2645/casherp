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
        $canonicalUrl = rtrim((string) config('canonical.url'), '/');

        if ($canonicalUrl === '') {
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
}
