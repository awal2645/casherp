<?php

namespace Modules\Hms\Console;

use Illuminate\Console\Command;
use Modules\Hms\Services\GuestMessagingService;

class DispatchGuestMessages extends Command
{
    protected $signature = 'hms:dispatch-guest-messages';
    protected $description = 'Dispatch due, consent-aware HMS guest messages';

    public function handle(GuestMessagingService $service): int
    {
        $result = $service->dispatchDue();
        $this->info("Sent {$result['sent']}; failed {$result['failed']}.");

        return self::SUCCESS;
    }
}
