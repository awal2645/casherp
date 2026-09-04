<?php

namespace App\Utils;

use App\Restaurant\Booking;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestaurantUtil extends Util
{
    public function is_service_staff($user_id): bool
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasColumn('roles', 'is_service_staff')) {
            return false;
        }

        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user_id)
            ->where('roles.is_service_staff', 1)
            ->exists();
    }

    public function service_staff_dropdown($business_id)
    {
        return $this->serviceStaffDropdown($business_id);
    }

    public function getBookingsForCalendar($filters)
    {
        if (! Schema::hasTable('bookings')) {
            return [];
        }

        $query = Booking::where('business_id', $filters['business_id'] ?? null);

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('booking_start', [$filters['start_date'], $filters['end_date']])
                    ->orWhereBetween('booking_end', [$filters['start_date'], $filters['end_date']]);
            });
        }

        if (! empty($filters['user_id'])) {
            $query->where('created_by', $filters['user_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        $color = $filters['color'] ?? '#007FFF';
        $events = [];

        foreach ($query->with('customer')->get() as $booking) {
            $events[] = [
                'title' => $booking->customer->name ?? ('Booking #'.$booking->id),
                'start' => $booking->booking_start,
                'end' => $booking->booking_end,
                'url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'index']),
                'backgroundColor' => $color,
                'borderColor' => $color,
            ];
        }

        return $events;
    }

    public function getAllOrders($business_id, $filter = [])
    {
        if (! Schema::hasTable('transactions')) {
            return collect();
        }

        $query = Transaction::query()
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final');

        if (Schema::hasTable('transaction_sell_lines')) {
            $query->leftJoin('transaction_sell_lines as tsl', 'transactions.id', '=', 'tsl.transaction_id');

            if (! empty($filter['line_order_status'])) {
                if ($filter['line_order_status'] === 'received') {
                    $query->where(function ($q) {
                        $q->whereNull('tsl.res_line_order_status')
                            ->orWhere('tsl.res_line_order_status', 'received');
                    });
                } else {
                    $query->where('tsl.res_line_order_status', $filter['line_order_status']);
                }
            }
        }

        if (! empty($filter['waiter_id'])) {
            $query->where('transactions.res_waiter_id', $filter['waiter_id']);
        }

        $query->select('transactions.*', 'contacts.name as customer_name')
            ->groupBy('transactions.id')
            ->orderByDesc('transactions.created_at');

        if (class_exists(\App\TransactionSellLine::class)) {
            $query->with(['sell_lines']);
        }

        return $query->get();
    }

    public function getLineOrders($business_id, $filter = [])
    {
        if (! Schema::hasTable('transaction_sell_lines') || ! Schema::hasTable('transactions')) {
            return collect();
        }

        $query = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->leftJoin('contacts', 't.contact_id', '=', 'contacts.id')
            ->leftJoin('products as p', 'tsl.product_id', '=', 'p.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final');

        if (! empty($filter['order_status']) && $filter['order_status'] === 'received') {
            $query->where(function ($q) {
                $q->whereNull('tsl.res_line_order_status')
                    ->orWhere('tsl.res_line_order_status', 'received');
            });
        }

        if (! empty($filter['waiter_id'])) {
            $query->where(function ($q) use ($filter) {
                $q->where('t.res_waiter_id', $filter['waiter_id'])
                    ->orWhere('tsl.res_service_staff_id', $filter['waiter_id']);
            });
        }

        if (! empty($filter['line_id'])) {
            $query->where('tsl.id', $filter['line_id']);
        }

        return $query->select(
            'tsl.*',
            't.invoice_no',
            'contacts.name as customer_name',
            'p.name as product_name'
        )->get();
    }
}
