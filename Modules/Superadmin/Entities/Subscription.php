<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'trial_end_date' => 'datetime',
        'package_price' => 'decimal:4',
        'original_price' => 'decimal:4',
        'package_details' => 'array',
    ];

    /**
     * Scope a query to only include approved subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Get the package that belongs to the subscription.
     */
    public function package()
    {
        return $this->belongsTo('\Modules\Superadmin\Entities\Package')
            ->withTrashed();
    }

    /**
     * Returns the active subscription details for a business
     *
     * @param $business_id int
     * @return Response
     */
    public static function active_subscription($business_id)
    {
        $date_today = \Carbon::today()->toDateString();

        $subscription = Subscription::where('business_id', $business_id)
                            ->whereDate('start_date', '<=', $date_today)
                            ->whereDate('end_date', '>=', $date_today)
                            ->approved()
                            ->orderByDesc('end_date')
                            ->first();

        // Additional companies are covered by the owner's account plan. If a
        // copied coverage row has expired but the owner renewed the root plan,
        // resolve that active root plan instead of incorrectly locking only the
        // additional workspace.
        if (empty($subscription)) {
            $ownerId = \App\Business::whereKey($business_id)->value('owner_id');
            if (! empty($ownerId)) {
                $ownedBusinessIds = \App\Business::where('owner_id', $ownerId)->pluck('id');
                $subscription = Subscription::whereIn('business_id', $ownedBusinessIds)
                    ->whereNull('covered_by_subscription_id')
                    ->whereDate('start_date', '<=', $date_today)
                    ->whereDate('end_date', '>=', $date_today)
                    ->approved()
                    ->orderByDesc('end_date')
                    ->first();
            }
        }

        return $subscription;
    }

    /**
     * Returns the upcoming subscription details for a business
     *
     * @param $business_id int
     * @return Response
     */
    public static function upcoming_subscriptions($business_id)
    {
        $date_today = \Carbon::today();
        $ownerId = \App\Business::whereKey($business_id)->value('owner_id');
        $businessIds = $ownerId
            ? \App\Business::where('owner_id', $ownerId)->pluck('id')
            : collect([$business_id]);

        $subscription = Subscription::whereIn('business_id', $businessIds)
                            ->whereNull('covered_by_subscription_id')
                            ->whereDate('start_date', '>', $date_today)
                            ->approved()
                            ->get();

        return $subscription;
    }

    /**
     * Returns the subscriptions waiting for approval for superadmin
     *
     * @param $business_id int
     * @return Response
     */
    public static function waiting_approval($business_id)
    {
        $ownerId = \App\Business::whereKey($business_id)->value('owner_id');
        $businessIds = $ownerId
            ? \App\Business::where('owner_id', $ownerId)->pluck('id')
            : collect([$business_id]);

        $subscriptions = Subscription::whereIn('business_id', $businessIds)
                            ->whereNull('covered_by_subscription_id')
                            ->whereNull('start_date')
                            ->waiting()
                            ->get();

        return $subscriptions;
    }

    public static function end_date($business_id)
    {
        $date_today = \Carbon::today();

        $ownerId = \App\Business::whereKey($business_id)->value('owner_id');
        $businessIds = $ownerId
            ? \App\Business::where('owner_id', $ownerId)->pluck('id')
            : collect([$business_id]);

        $subscription = Subscription::whereIn('business_id', $businessIds)
                            ->whereNull('covered_by_subscription_id')
                            ->approved()
                            ->select(DB::raw('MAX(end_date) as end_date'))
                            ->first();

        if (empty($subscription->end_date)) {
            return $date_today;
        } else {
            $end_date = $subscription->end_date->copy()->addDay();
            if ($date_today->lte($end_date)) {
                return $end_date;
            } else {
                return $date_today;
            }
        }
    }

    /**
     * Returns the list of packages status
     *
     * @return array
     */
    public static function package_subscription_status()
    {
        return ['approved' => trans('superadmin::lang.approved'), 'declined' => trans('superadmin::lang.declined'), 'waiting' => trans('superadmin::lang.waiting')];
    }

    /**
     * Get the created_by.
     */
    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_id');
    }

    /**
     * Get the subscription business relationship.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    /**
     * The paid account subscription that covers this additional company.
     */
    public function coverageSource()
    {
        return $this->belongsTo(self::class, 'covered_by_subscription_id');
    }
}
