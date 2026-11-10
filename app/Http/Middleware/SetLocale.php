<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = self::supportedLocales();
        $fallback = config('app.fallback_locale') ?: 'en';

        if (! in_array($fallback, $supported, true)) {
            $supported[] = $fallback;
        }

        $locale = $request->query('locale')
            ?: $request->user()?->locale
            ?: $request->getPreferredLanguage($supported);

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $fallback;
        }

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /** @return list<string> */
    public static function supportedLocales(): array
    {
        $raw = config('app.supported_locales');

        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        $list = array_values(array_unique(array_filter(
            array_map(fn ($v) => strtolower(trim((string) $v)), $list),
            fn ($v) => $v !== '',
        )));

        return $list === [] ? ['en'] : $list;
    }
}
