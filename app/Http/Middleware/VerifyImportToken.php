<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifieert dat een inkomend push-request voorzien is van de bearer-token
 * die gelijk is aan config('services.rzvg_import.token'). Zonder token is
 * de /api/pages/import-endpoint uitgeschakeld (503) — expliciet, zodat een
 * verkeerde omgeving nooit stilletjes accepteert.
 */
class VerifyImportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.rzvg_import.token');

        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'message' => 'Import-endpoint is niet geconfigureerd op deze omgeving.',
            ], 503);
        }

        $provided = $request->bearerToken();

        if ($provided === null || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Ongeldige of ontbrekende bearer-token.',
            ], 401);
        }

        return $next($request);
    }
}
