<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PublicSmtpHost implements Rule
{
    protected $reason = 'Enter a valid SMTP hostname without a protocol or path.';

    public function passes($attribute, $value): bool
    {
        $host = strtolower(trim((string) $value));
        if ($host === '' || strlen($host) > 253 || preg_match('/[\s\/:?#@]/', $host)) {
            return false;
        }

        if ((bool) config('casherp_mail.allow_private_smtp_hosts', false)) {
            return $this->isSyntacticallyValid($host);
        }

        foreach (['localhost', '.localhost', '.local', '.internal', '.test', '.invalid'] as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                $this->reason = 'Private or local SMTP hosts are not permitted on this SaaS service.';

                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->reason = 'Private or reserved SMTP addresses are not permitted on this SaaS service.';

                return false;
            }

            return true;
        }

        if (! $this->isSyntacticallyValid($host)) {
            return false;
        }

        // DNS is advisory here: an unresolved hostname is allowed to be saved
        // and the explicit connection test will explain the delivery failure.
        // If it does resolve, every address must be public to prevent an SSRF
        // path into a private network.
        $resolved = @gethostbynamel($host) ?: [];
        foreach ($resolved as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->reason = 'This SMTP hostname resolves to a private or reserved network address.';

                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->reason;
    }

    public static function isPublicAddress(string $address): bool
    {
        return (bool) filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    protected function isSyntacticallyValid(string $host): bool
    {
        return (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }
}
