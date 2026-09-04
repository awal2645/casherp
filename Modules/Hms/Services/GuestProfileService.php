<?php

namespace Modules\Hms\Services;

use Modules\Hms\Entities\HmsGuestProfile;

class GuestProfileService
{
    public function sync(int $businessId, int $contactId, array $input): HmsGuestProfile
    {
        $profile = HmsGuestProfile::firstOrNew([
            'business_id' => $businessId,
            'contact_id' => $contactId,
        ]);

        $profile->fill(collect($input)->only([
            'preferred_language', 'nationality', 'date_of_birth', 'identity_document_type',
            'identity_document_last_four', 'vip_level', 'preferences', 'accessibility_needs',
            'retention_until',
        ])->all());

        if (array_key_exists('marketing_consent', $input)) {
            $newConsent = (bool) $input['marketing_consent'];
            if ($newConsent !== (bool) $profile->marketing_consent) {
                $profile->consent_recorded_at = now();
                $profile->consent_source = $input['consent_source'] ?? 'reservation';
            }
            $profile->marketing_consent = $newConsent;
        }

        if (array_key_exists('do_not_contact', $input)) {
            $profile->do_not_contact = (bool) $input['do_not_contact'];
        }

        $profile->save();

        return $profile;
    }

    public function anonymize(HmsGuestProfile $profile): void
    {
        $profile->fill([
            'preferred_language' => null,
            'nationality' => null,
            'date_of_birth' => null,
            'identity_document_type' => null,
            'identity_document_last_four' => null,
            'vip_level' => null,
            'preferences' => null,
            'accessibility_needs' => null,
            'marketing_consent' => false,
            'do_not_contact' => true,
            'anonymized_at' => now(),
        ])->save();
    }
}
