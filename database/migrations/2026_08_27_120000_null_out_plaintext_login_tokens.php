<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears every existing login_token.
 *
 * Tokens are now stored as SHA-256 hashes. Any row written before that change
 * holds a raw, still-redeemable token in plaintext — exactly the exposure the
 * hashing was meant to remove — and would no longer match the hashed lookup in
 * the auto.login route anyway.
 *
 * The cost is that any magic link already in flight stops working. Those links
 * live 15 minutes (the one 24-hour minter wrote a token that was never
 * delivered and could never be redeemed), and the recovery is self-service:
 * request another link, or use Forgot Password.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('login_token')
            ->update([
                'login_token' => null,
                'login_token_expires_at' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible by design: the plaintext values are gone, and
        // recreating them is neither possible nor desirable.
    }
};
