<?php

namespace Modules\Superadmin\Console;

use App\System;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Notifications\SendSubscriptionExpiryAlert;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SubscriptionExpiryAlert extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pos:sendSubscriptionExpiryAlert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends package expiry alerts to all the subscribers before a specified time';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $min_alert_days = (int) System::where('key', 'package_expiry_alert_days')->value('value');
        if ($min_alert_days < 0) {
            return self::SUCCESS;
        }

        //Get all subscription which will expire in $min_alert_days
        $today = Carbon::today();
        $targetEndDate = $today->copy()->addDays($min_alert_days)->toDateString();
        $expiring_subscriptions = Subscription::approved()
                                ->with(['business', 'business.owner'])
                                ->whereNull('covered_by_subscription_id')
                                ->whereDate('end_date', $targetEndDate)
                                ->whereDate('start_date', '<=', $today)
                                ->get();

        $notifiedOwners = [];
        foreach ($expiring_subscriptions as $subscription) {
            $owner = optional($subscription->business)->owner;
            if (! $owner || in_array($owner->id, $notifiedOwners, true)) {
                continue;
            }

            $accountBusinessIds = $owner->ownedBusinesses()->pluck('id');
            $dayAfterExpiry = $subscription->end_date->copy()->addDay()->toDateString();
            $next_subscription = Subscription::whereIn('business_id', $accountBusinessIds)
                                    ->whereNull('covered_by_subscription_id')
                                    ->where('id', '!=', $subscription->id)
                                    ->whereDate('start_date', '<=', $dayAfterExpiry)
                                    ->whereDate('end_date', '>=', $dayAfterExpiry)
                                    ->approved()
                                    ->first();
            //If next subscription is empty send alert to business owner
            if (empty($next_subscription)) {
                $owner->notify(new SendSubscriptionExpiryAlert($subscription));
                $notifiedOwners[] = $owner->id;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    // protected function getArguments()
    // {
    //     return [
    //         ['example', InputArgument::REQUIRED, 'An example argument.'],
    //     ];
    // }

    /**
     * Get the console command options.
     *
     * @return array
     */
    // protected function getOptions()
    // {
    //     return [
    //         ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
    //     ];
    // }
}
