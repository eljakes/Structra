<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => (string) config('security.headers.permissions_policy'),
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            if ($value !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $contentSecurityPolicy = (string) config('security.headers.content_security_policy');
        if ($contentSecurityPolicy !== '' && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $contentSecurityPolicy);
        }

        if ($request->isSecure()) {
            $maxAge = max(0, (int) config('security.headers.hsts_max_age', 31536000));
            if ($maxAge > 0 && ! $response->headers->has('Strict-Transport-Security')) {
                $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
            }
        }

        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
