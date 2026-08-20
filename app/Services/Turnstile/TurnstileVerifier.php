<?php

declare(strict_types=1);

namespace App\Services\Turnstile;

use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, ?string $ip = null): bool
    {
        if ($token === '') {
            return false;
        }

        $response = Http::asForm()->post(self::VERIFY_URL, [
            'secret' => config('services.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return $response->successful() && $response->json('success') === true;
    }
}
