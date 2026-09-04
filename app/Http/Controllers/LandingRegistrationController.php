<?php

namespace App\Http\Controllers;

use App\Industry;
use App\RegistrationIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LandingRegistrationController extends Controller
{
    public function show(Request $request)
    {
        $intent = null;
        if (Schema::hasTable('registration_intents')) {
            if ($request->session()->has('registration_intent_id')) {
                $intent = RegistrationIntent::whereKey($request->session()->get('registration_intent_id'))
                    ->whereNull('completed_at')
                    ->first();
            }
        }

        return view('landing.get-started', compact('intent'));
    }

    public function store(Request $request)
    {
        $industryCodes = Industry::query()->where('is_active', true)->pluck('code')->all();
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'in:'.implode(',', $industryCodes)],
            'country' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'reminder_consent' => ['nullable', 'accepted'],
        ]);

        if (! Schema::hasTable('registration_intents')) {
            return redirect()->route('pricing', $this->pricingQuery($data));
        }

        $normalisedEmail = Str::lower(trim($data['email']));
        $normalisedBusiness = Str::lower(trim(preg_replace('/\s+/', ' ', $data['business_name'])));
        $fingerprint = hash_hmac(
            'sha256',
            implode('|', [$normalisedEmail, $normalisedBusiness, $data['industry'], Str::lower(trim($data['country']))]),
            (string) config('app.key')
        );

        $intent = $request->session()->has('registration_intent_id')
            ? RegistrationIntent::whereKey($request->session()->get('registration_intent_id'))->whereNull('completed_at')->first()
            : null;
        $intent ??= RegistrationIntent::where('fingerprint', $fingerprint)->whereNull('completed_at')->latest('id')->first();
        $intent ??= new RegistrationIntent(['uuid' => (string) Str::uuid()]);
        $intent->fill([
            'email' => $normalisedEmail,
            'fingerprint' => $fingerprint,
            'business_name' => trim($data['business_name']),
            'industry_code' => $data['industry'],
            'country' => trim($data['country']),
            'source' => data_get($request->session()->get('auth.google.pending_identity'), 'email') ? 'google' : 'website',
            'last_step' => 'package_selection',
            'reminder_consent' => $request->boolean('reminder_consent'),
            'last_activity_at' => now(),
            'next_reminder_at' => $request->boolean('reminder_consent') ? now()->addDay() : null,
            'metadata' => [
                'locale' => app()->getLocale(),
                'referrer_host' => parse_url((string) $request->headers->get('referer'), PHP_URL_HOST),
            ],
        ]);
        $intent->save();
        $request->session()->put('registration_intent_id', $intent->id);

        return redirect()->route('pricing', $this->pricingQuery($data));
    }

    public function resume(Request $request, string $intent)
    {
        if (Schema::hasTable('registration_intents')) {
            $registrationIntent = RegistrationIntent::where('uuid', $intent)->whereNull('completed_at')->first();
            if ($registrationIntent) {
                $request->session()->put('registration_intent_id', $registrationIntent->id);
            }
        }

        return redirect()->route('landing.get-started');
    }

    public function stopReminders(string $intent)
    {
        if (Schema::hasTable('registration_intents')) {
            RegistrationIntent::where('uuid', $intent)->whereNull('completed_at')->update([
                'reminder_consent' => false,
                'next_reminder_at' => null,
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('landing.get-started')->with('status', [
            'success' => true,
            'msg' => 'Registration reminders have been stopped. You can still register whenever you are ready.',
        ]);
    }

    private function pricingQuery(array $data): array
    {
        return [
            'business_name' => $data['business_name'],
            'industry' => $data['industry'],
            'country' => $data['country'],
        ];
    }
}
