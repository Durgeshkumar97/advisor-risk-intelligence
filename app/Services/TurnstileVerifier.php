<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
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
 * success === true. A missing token, an invalid one, a missing secret, an error
 * response, a timeout and an unreachable Cloudflare all end in the same
 * ValidationException — the user sees "try again", never a 500.
 */
class TurnstileVerifier
{
    public const FIELD = 'cf-turnstile-response';

    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Seconds, total. Guzzle's default is 30: on shared hosting with a small
     * PHP worker pool, a hanging Cloudflare would pin one worker per signup
     * attempt for half a minute. Siteverify normally answers in well under a
     * second.
     */
    private const TIMEOUT_SECONDS = 5;

    /**
     * @throws ValidationException
     */
    public function verify(Request $request, string $field = self::FIELD): void
    {
        try {
            $success = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::SITEVERIFY_URL, [
                    'secret' => config('services.turnstile.secret'),
                    'response' => $request->input($field),
                    'remoteip' => $request->ip(),
                ])
                ->json('success');
        } catch (ConnectionException) {
            // Timed out or unreachable. Still closed — no account without a
            // yes — but as the same validation error rather than an uncaught
            // exception and a 500.
            $success = null;
        }

        // Strictly true. A 4xx/5xx body has no `success` key (null), and
        // anything short of an explicit yes is a no.
        if ($success !== true) {
            throw ValidationException::withMessages([
                $field => 'Captcha verification failed. Please try again.',
            ]);
        }
    }
}
