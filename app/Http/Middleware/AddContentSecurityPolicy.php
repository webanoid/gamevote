<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a Content-Security-Policy with a per-request nonce for the page's own scripts:
 * the inline theme script in app.blade.php and the tags Vite renders.
 *
 * On in production; off in local development, where the Vite dev server would need its
 * own origin and websocket allowed (see config/app.php content_security_policy).
 */
class AddContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.content_security_policy')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // Inline style attributes (React style props, toasts) and the font-face styles.
            "style-src 'self' 'unsafe-inline'",
            // data: covers the brick wall SVG used as a CSS background.
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));

        return $response;
    }
}
