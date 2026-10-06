<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->csp());
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->remove('X-Powered-By');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /** Alpine évalue ses expressions (unsafe-eval) et Filament/Livewire injectent des scripts et styles en ligne. */
    private function csp(): string
    {
        $vite = Vite::isRunningHot() ? trim((string) file_get_contents(Vite::hotFile())) : null;
        $viteWs = $vite ? preg_replace('#^http#', 'ws', $vite) : null;

        $reverb = config('broadcasting.connections.reverb.options');
        $websocket = filled($reverb['host'] ?? null)
            ? ($reverb['scheme'] === 'https' ? 'wss' : 'ws')."://{$reverb['host']}:{$reverb['port']}"
            : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", $vite],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net', $vite],
            'font-src' => ["'self'", 'data:', 'https://fonts.bunny.net', $vite],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", $websocket, $vite, $viteWs],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }
}
