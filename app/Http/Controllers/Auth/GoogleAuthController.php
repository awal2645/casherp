<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BusinessContextService;
use App\Services\GoogleSocialIdentityService;
use App\SocialAccount;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private GoogleSocialIdentityService $googleIdentity,
        private BusinessContextService $businessContext,
        private BusinessUtil $businessUtil,
        private ModuleUtil $moduleUtil
    ) {
        $this->middleware('guest');
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->googleIdentity->isConfigured()) {
            return $this->failure('Google sign-in is not currently available. Please use your CashERP username and password.');
        }

        $request->session()->put(
            'auth.google.intent',
            $request->query('intent') === 'signup' ? 'signup' : 'login'
        );

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleIdentity->isConfigured()) {
            return $this->failure('Google sign-in is not currently available.');
        }

        if ($request->filled('error')) {
            return $this->failure('Google sign-in was cancelled or could not be completed.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $raw = (array) $googleUser->getRaw();
            $email = mb_strtolower(trim((string) $googleUser->getEmail()));
            $providerUserId = trim((string) $googleUser->getId());
            $emailVerified = filter_var(
                $raw['verified_email'] ?? $raw['email_verified'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if ($providerUserId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $emailVerified) {
                return $this->failure('CashERP requires a verified Google email address.');
            }

            $identity = [
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'first_name' => trim((string) ($raw['given_name'] ?? '')),
                'last_name' => trim((string) ($raw['family_name'] ?? '')),
            ];

            if ($identity['first_name'] === '') {
                [$identity['first_name'], $identity['last_name']] = $this->splitName((string) $googleUser->getName());
            }

            $socialAccount = SocialAccount::with('user')
                ->where('provider', 'google')
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($socialAccount) {
                if (! $socialAccount->user) {
                    return $this->failure('The linked CashERP account is unavailable. Contact support for assistance.');
                }

                $socialAccount->update(['provider_email' => $email, 'last_used_at' => now()]);

                return $this->login($request, $socialAccount->user);
            }

            $existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existingUser) {
                $this->googleIdentity->attach($existingUser, $identity);

                return $this->login($request, $existingUser);
            }

            if (! config('constants.allow_registration')) {
                return $this->failure('No CashERP account uses this Google email and new registrations are currently closed.');
            }

            $this->googleIdentity->storePending($request, $identity);

            return redirect('/get-started')->with('status', [
                'success' => 1,
                'msg' => 'Google verified your email. Choose the company industry and package to finish registration.',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception,
                'ip' => $request->ip(),
            ]);

            return $this->failure('Google sign-in could not be completed. Please try again or use your CashERP password.');
        } finally {
            $request->session()->forget('auth.google.intent');
        }
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        $business = $this->businessContext->recoverActiveBusiness($user);
        if (! $business) {
            return $this->failure(__('lang_v1.business_inactive'));
        }

        if ($user->status !== 'active') {
            return $this->failure(__('lang_v1.user_inactive'));
        }

        if (! $user->allow_login) {
            return $this->failure(__('lang_v1.login_not_allowed'));
        }

        if ($user->user_type === 'user_customer'
            && ! $this->moduleUtil->hasThePermissionInSubscription($business->id, 'crm_module')) {
            return $this->failure(__('lang_v1.business_dont_have_crm_subscription'));
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->businessContext->activate($request, $user, $business);
        $this->businessUtil->activityLog($user, 'login', null, ['provider' => 'google'], false, $business->id);

        if (! $user->can('dashboard.data') && $user->can('sell.create')) {
            return redirect('/pos/create');
        }

        if ($user->user_type === 'user_customer') {
            return redirect('contact/contact-dashboard');
        }

        return redirect('/home');
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect('/login')->with('status', ['success' => 0, 'msg' => $message]);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$parts[0] ?? 'CashERP', $parts[1] ?? 'User'];
    }
}
