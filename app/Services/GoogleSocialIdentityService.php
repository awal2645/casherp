<?php

namespace App\Services;

use App\SocialAccount;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleSocialIdentityService
{
    private const SESSION_KEY = 'auth.google.pending_identity';

    private const PENDING_LIFETIME_MINUTES = 20;

    public function isConfigured(): bool
    {
        return (bool) config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function storePending(Request $request, array $identity): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'provider' => 'google',
            'provider_user_id' => (string) $identity['provider_user_id'],
            'email' => mb_strtolower(trim((string) $identity['email'])),
            'first_name' => trim((string) ($identity['first_name'] ?? '')),
            'last_name' => trim((string) ($identity['last_name'] ?? '')),
            'verified' => true,
            'expires_at' => now()->addMinutes(self::PENDING_LIFETIME_MINUTES)->getTimestamp(),
        ]);
    }

    public function pending(Request $request): ?array
    {
        if (! $this->isConfigured()) {
            $this->clear($request);

            return null;
        }

        $pending = $request->session()->get(self::SESSION_KEY);
        $valid = is_array($pending)
            && ($pending['provider'] ?? null) === 'google'
            && ! empty($pending['provider_user_id'])
            && filter_var($pending['email'] ?? null, FILTER_VALIDATE_EMAIL)
            && ($pending['verified'] ?? false) === true
            && (int) ($pending['expires_at'] ?? 0) >= now()->getTimestamp();

        if (! $valid) {
            $this->clear($request);

            return null;
        }

        return $pending;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function attach(User $user, array $identity): SocialAccount
    {
        return DB::transaction(function () use ($user, $identity) {
            $providerUserId = (string) $identity['provider_user_id'];
            $existingIdentity = SocialAccount::where('provider', 'google')
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($existingIdentity && (int) $existingIdentity->user_id !== (int) $user->id) {
                throw new \RuntimeException('This Google identity is already linked to another CashERP account.');
            }

            $existingForUser = SocialAccount::where('provider', 'google')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existingForUser && $existingForUser->provider_user_id !== $providerUserId) {
                throw new \RuntimeException('This CashERP account is already linked to a different Google identity.');
            }

            return SocialAccount::updateOrCreate(
                ['provider' => 'google', 'provider_user_id' => $providerUserId],
                [
                    'user_id' => $user->id,
                    'provider_email' => mb_strtolower(trim((string) $identity['email'])),
                    'last_used_at' => now(),
                ]
            );
        });
    }
}
