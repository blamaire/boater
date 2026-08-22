<?php

namespace App\Services\Contact;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side verificatie van een Cloudflare Turnstile-token (privacyvriendelijke
 * CAPTCHA op /contact, geen cookies/tracking). Twee bewuste fail-open-momenten:
 *
 * 1. Geen secret-key geconfigureerd → verificatie wordt overgeslagen (lokale of
 *    per ongeluk onvolledige omgevingen blokkeren het formulier niet; honeypot
 *    + rate-limiter blijven dan de enige lagen).
 * 2. Cloudflare zelf onbereikbaar (timeout/netwerkfout) → ook overgeslagen,
 *    zodat een storing bij Cloudflare geen legitieme bezoekers blokkeert.
 *
 * Een daadwerkelijk mislukte verificatie (Cloudflare antwoordt maar met
 * success=false) leidt wél tot afwijzing — alleen infra-falen is fail-open.
 */
class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, ?string $ip): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            return true;
        }

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile-verificatie niet bereikbaar', ['exception' => $e->getMessage()]);

            return true;
        }

        if ($response->failed()) {
            Log::warning('Turnstile siteverify gaf een foutstatus', ['status' => $response->status()]);

            return true;
        }

        return (bool) $response->json('success', false);
    }
}
