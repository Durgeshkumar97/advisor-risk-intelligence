<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Server-side Cloudflare Turnstile verification — the one policy every
 * unauthenticated account-creating form goes through.
 *
 * It exists because there used to be two such forms and only one of them was
 * checked. /register verified the token; /ifa-submit, which creates a User AND
 * grants a trial subscription, verified nothing. Every one of the 72 bot
 * accounts created in September 2026 came through /ifa-submit. One class means
 * a new form either calls this or visibly doesn't.
 *
 * Fails closed: the request is rejected unless Cloudflare explicitly answers
 * success === true. A missing token, an invalid one, a missing secret, and an
 * error response from Cloudflare all end in the same ValidationException.
 */
class TurnstileVerifier
{
    public const FIELD = 'cf-turnstile-response';

    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @throws ValidationException
     */
    public function verify(Request $request, string $field = self::FIELD): void
    {
        $success = Http::asForm()->post(self::SITEVERIFY_URL, [
            'secret' => config('services.turnstile.secret'),
            'response' => $request->input($field),
            'remoteip' => $request->ip(),
        ])->json('success');

        // Strictly true. A 4xx/5xx body has no `success` key (null), and
        // anything short of an explicit yes is a no.
        if ($success !== true) {
            throw ValidationException::withMessages([
                $field => 'Captcha verification failed. Please try again.',
            ]);
        }
    }
}
