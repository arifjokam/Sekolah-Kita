<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isProduction = app()->environment('production');
        $isPrivatePpdbResponse = $request->routeIs('ppdb.status.*');
        $isPrivateAdminResponse = $request->routeIs('admin.*');
        $isPrivateResponse = $isPrivatePpdbResponse || $isPrivateAdminResponse || $request->user() !== null;

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', $isPrivateResponse ? 'no-referrer' : 'strict-origin-when-cross-origin');

        if ($isPrivateResponse) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "connect-src 'self'".($isProduction ? '' : ' ws: http: https:'),
            "object-src 'none'",
        ]));

        if ($isProduction) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
