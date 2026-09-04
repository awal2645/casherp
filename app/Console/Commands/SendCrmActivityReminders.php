<?php

namespace App\Console\Commands;

use App\Notifications\CrmActivityReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Crm\Entities\Activity;

class SendCrmActivityReminders extends Command
{
    protected $signature = 'casherp:send-crm-activity-reminders';

    protected $description = 'Send each due CRM activity reminder once';

    public function handle(): int
    {
        $sent = 0;
        Activity::query()
            ->where('status', 'planned')
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->with(['owner', 'opportunity'])
            ->orderBy('id')
            ->chunkById(100, function ($activities) use (&$sent) {
                foreach ($activities as $activity) {
                    $claimed = Activity::whereKey($activity->id)
                        ->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => now()]);
                    if (! $claimed) {
                        continue;
                    }
                    try {
                        if ($activity->owner && $activity->owner->canAccessBusiness((int) $activity->business_id)) {
                            $activity->owner->notify(new CrmActivityReminderNotification($activity));
                            $sent++;
                        }
                    } catch (\Throwable $exception) {
                        Activity::whereKey($activity->id)->update(['reminder_sent_at' => null]);
                        Log::error('CRM activity reminder failed', [
                            'activity_id' => $activity->id,
                            'business_id' => $activity->business_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info('CRM reminders sent: '.$sent);

        return self::SUCCESS;
    }
}
