<?php

namespace Modules\Essentials\Services;

use Illuminate\Support\Str;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\HrmAuditEvent;
use Modules\Essentials\Entities\SensitiveAccessLog;

class HrmAuditService
{
    private const SENSITIVE_KEYS = [
        'compensation',
        'bank_details',
        'tax_identifiers',
        'medical_data',
        'diversity_data',
        'disciplinary_data',
        'personal_data',
        'password',
        'token',
    ];

    public function record(
        int $businessId,
        string $eventType,
        string $subjectType,
        ?int $subjectId,
        array $previous = [],
        array $new = [],
        ?string $reason = null
    ): HrmAuditEvent {
        $request = app()->bound('request') ? request() : null;
        $user = auth()->user();

        return HrmAuditEvent::create([
            'correlation_id' => (string) Str::uuid(),
            'business_id' => $businessId,
            'actor_user_id' => optional($user)->id,
            'actor_role' => $user ? mb_substr((string) $user->role_name, 0, 191) : 'system',
            'event_type' => mb_substr($eventType, 0, 100),
            'subject_type' => mb_substr($subjectType, 0, 191),
            'subject_id' => $subjectId,
            'previous_values' => $this->redact($previous),
            'new_values' => $this->redact($new),
            'reason' => $reason ? mb_substr($reason, 0, 191) : null,
            'ip_address' => $request ? mb_substr((string) $request->ip(), 0, 64) : null,
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'occurred_at' => now(),
        ]);
    }

    public function recordSensitiveAccess(EmploymentProfile $profile, string $fieldGroup, string $purpose, string $action = 'view'): SensitiveAccessLog
    {
        $request = app()->bound('request') ? request() : null;

        return SensitiveAccessLog::create([
            'correlation_id' => (string) Str::uuid(),
            'business_id' => $profile->business_id,
            'actor_user_id' => auth()->id(),
            'employment_profile_id' => $profile->id,
            'field_group' => mb_substr($fieldGroup, 0, 60),
            'action' => mb_substr($action, 0, 30),
            'purpose' => mb_substr($purpose, 0, 191),
            'ip_address' => $request ? mb_substr((string) $request->ip(), 0, 64) : null,
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'accessed_at' => now(),
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[protected]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
