<?php

namespace App\Console\Commands;

use App\Business;
use App\Services\BusinessMailConfigurationService;
use Illuminate\Console\Command;

class EncryptLegacySmtpCredentials extends Command
{
    protected $signature = 'casherp:encrypt-legacy-smtp-credentials {--dry-run : Count legacy credentials without changing them}';

    protected $description = 'Encrypt legacy plaintext company SMTP passwords without printing secret values';

    public function handle(BusinessMailConfigurationService $mailConfiguration): int
    {
        $found = 0;
        $encrypted = 0;
        $dryRun = (bool) $this->option('dry-run');

        Business::query()->select(['id', 'email_settings'])->chunkById(100, function ($businesses) use (
            $mailConfiguration,
            $dryRun,
            &$found,
            &$encrypted
        ) {
            foreach ($businesses as $business) {
                $settings = $business->email_settings ?? [];
                if (! is_array($settings)
                    || empty($settings['mail_password'])
                    || ! empty($settings['password_encrypted'])) {
                    continue;
                }

                $found++;
                if (! $dryRun && $mailConfiguration->migrateLegacyCredential($business)) {
                    $encrypted++;
                }
            }
        });

        if ($dryRun) {
            $this->info("{$found} company SMTP credential(s) require encryption. No data was changed.");
        } else {
            $this->info("Encrypted {$encrypted} legacy company SMTP credential(s). No secret values were displayed.");
        }

        return self::SUCCESS;
    }
}
