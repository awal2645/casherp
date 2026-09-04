<?php

namespace Modules\Hms\Http\Controllers;

use App\Business;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Menu;
use Modules\Hms\Entities\HmsRoomType;

class DataController extends Controller
{
    /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'hms.manage_rooms',
                'label' => __('hms::lang.manage_rooms'),
                'default' => false,
            ],
            [
                'value' => 'hms.view_bookings',
                'label' => __('hms::lang.view_bookings'),
                'default' => false,
            ],
            [
                'value' => 'hms.view_reports',
                'label' => __('hms::lang.view_reports'),
                'default' => false,
            ],
            [
                'value' => 'hms.front_desk',
                'label' => __('hms::lang.view_front_desk'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_front_desk',
                'label' => __('hms::lang.manage_front_desk'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_housekeeping',
                'label' => __('hms::lang.manage_housekeeping'),
                'default' => false,
            ],
            [
                'value' => 'hms.perform_housekeeping',
                'label' => __('hms::lang.perform_housekeeping'),
                'default' => false,
            ],
            [
                'value' => 'hms.inspect_housekeeping',
                'label' => __('hms::lang.inspect_housekeeping'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_price',
                'label' => __('hms::lang.manage_price'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_unavailable',
                'label' => __('hms::lang.manage_unavailable'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_extra',
                'label' => __('hms::lang.manage_extra'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_coupon',
                'label' => __('hms::lang.manage_coupon'),
                'default' => false,
            ],
            [
                'value' => 'hms.add_booking',
                'label' => __('hms::lang.add_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.edit_booking',
                'label' => __('hms::lang.edit_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.delete_booking',
                'label' => __('hms::lang.delete_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_amenities',
                'label' => __('hms::lang.manage_amenities'),
                'default' => false,
            ],

            [
                'value' => 'hms.manage_settings',
                'label' => __('hms::lang.manage_settings'),
                'default' => false,
            ],

            [
                'value' => 'hms.add_booking_payment',
                'label' => __('hms::lang.add_booking_payment'),
                'default' => false,
            ],
            [
                'value' => 'hms.edit_booking_payment',
                'label' => __('hms::lang.edit_booking_payment'),
                'default' => false,
            ],
            [
                'value' => 'hms.delete_booking_payment',
                'label' => __('hms::lang.delete_booking_payment'),
                'default' => false,
            ],
            ['value' => 'hms.manage_properties', 'label' => __('hms::lang.manage_properties'), 'default' => false],
            ['value' => 'hms.manage_event_venues', 'label' => 'Manage event venues', 'default' => false],
            ['value' => 'hms.manage_security_deposits', 'label' => 'Manage refundable security deposits', 'default' => false],
            ['value' => 'hms.approve_security_deposit_refunds', 'label' => 'Approve security deposit refunds', 'default' => false],
            ['value' => 'hms.manage_rate_plans', 'label' => __('hms::lang.manage_rate_plans'), 'default' => false],
            ['value' => 'hms.manage_groups', 'label' => __('hms::lang.manage_groups'), 'default' => false],
            ['value' => 'hms.manage_folios', 'label' => __('hms::lang.manage_folios'), 'default' => false],
            ['value' => 'hms.approve_refunds', 'label' => __('hms::lang.approve_refunds'), 'default' => false],
            ['value' => 'hms.run_night_audit', 'label' => __('hms::lang.run_night_audit'), 'default' => false],
            ['value' => 'hms.override_night_audit', 'label' => __('hms::lang.override_night_audit'), 'default' => false],
            ['value' => 'hms.manage_cashier_shifts', 'label' => __('hms::lang.manage_cashier_shifts'), 'default' => false],
            ['value' => 'hms.manage_operations', 'label' => __('hms::lang.manage_operations'), 'default' => false],
            ['value' => 'hms.manage_guests', 'label' => __('hms::lang.manage_guests'), 'default' => false],
            ['value' => 'hms.anonymize_guests', 'label' => __('hms::lang.anonymize_guests'), 'default' => false],
            ['value' => 'hms.manage_guest_messages', 'label' => __('hms::lang.manage_guest_messages'), 'default' => false],
            ['value' => 'hms.view_revenue', 'label' => __('hms::lang.view_revenue'), 'default' => false],
            ['value' => 'hms.manage_revenue', 'label' => __('hms::lang.manage_revenue'), 'default' => false],
            ['value' => 'hms.manage_channels', 'label' => __('hms::lang.manage_channels'), 'default' => false],
            ['value' => 'hms.room_status_board', 'label' => 'View live room status board', 'default' => false],
            ['value' => 'hms.manage_stay_guests', 'label' => 'Manage named stay occupants', 'default' => false],
            ['value' => 'hms.manage_room_moves', 'label' => 'Move rooms and view room-move history', 'default' => false],
            ['value' => 'hms.manage_events', 'label' => 'Manage event and banquet bookings', 'default' => false],
            ['value' => 'hms.post_restaurant_charges', 'label' => 'Post restaurant charges to hotel and event folios', 'default' => false],

        ];
    }

    /**
     * Superadmin package permissions
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'hms_module',
                'label' => __('hms::lang.hms_module'),
                'default' => false,
            ],
            [
                'name' => 'hms_max_properties',
                'label' => __('hms::lang.hms_max_properties'),
                'default' => 1,
                'field_type' => 'number',
                'tooltip' => __('hms::lang.hms_max_properties_help'),
            ],
            ['name' => 'hms_finance_operations', 'label' => __('hms::lang.hms_finance_operations'), 'default' => true],
            ['name' => 'hms_revenue_operations', 'label' => __('hms::lang.hms_revenue_operations'), 'default' => true],
            ['name' => 'hms_group_operations', 'label' => __('hms::lang.hms_group_operations'), 'default' => true],
            ['name' => 'hms_guest_experience', 'label' => __('hms::lang.hms_guest_experience'), 'default' => true],
            ['name' => 'hms_channel_manager', 'label' => __('hms::lang.hms_channel_manager'), 'default' => false],
            ['name' => 'hms_event_operations', 'label' => 'HMS Events & Banqueting', 'default' => true],
        ];
    }

    /**
     * Hms Report options
     *
     * @return array
     */
    public function InboxReportOptions()
    {
        return [
            [
                'name' => 'hms_report',
                'label' => __('hms::lang.hms_report'),
                'default' => false,
            ],
        ];
    }

    /**
     * Inbox Report hms
     *
     * @return array
     */

    public function hms_report($business)
    {

        $business_id = $business->id;

        $inbox_settings = json_decode($business->inbox_report_settings);
        $frequency = $inbox_settings->frequency ?? null;

        $commonUtil = new Util();

        $transactionUtil = new TransactionUtil();

        $date_from = Carbon::now()->subDays(1)->format('Y-m-d') . ' 23:59:59';

        $dates = ['date_to' => null, 'date_from' => $date_from];

        if ($frequency === 'weekly') {
            $date_to = Carbon::now()->startOfWeek()->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => $date_from];
        } elseif ($frequency === 'monthly') {
            $date_to = Carbon::now()->startOfMonth()->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => $date_from];
        } elseif ($frequency === 'daily') {
            $date_to = Carbon::now()->subDays(1)->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => null];
        }

        $pending_room_types = $this->room_type_count('pending', $date_to, $date_from, $business);

        $cancelled_room_types = $this->room_type_count('cancelled', $date_to, $date_from, $business);
        $confirmed_room_types = $this->room_type_count('confirmed', $date_to, $date_from, $business);

        $all_room_types = HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?) as no_of_guest'),
        )
            ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
            ->where('hms_room_types.business_id', $business_id)
            ->get();

        return [
            'html' => view('hms::report.email_report', compact('pending_room_types', 'cancelled_room_types', 'confirmed_room_types', 'all_room_types', 'business', 'transactionUtil', 'dates')),
        ];

    }

    /**
     * Adds hms menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        $business_id = session()->get('user.business_id');
        $is_hms_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'hms_module');

        if ($is_hms_enabled && app(\App\Services\FeatureAccessService::class)->enabled('hms', $business_id)) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Hms\Http\Controllers\HmsController::class, 'index']),
                    __('hms::lang.hms'),
                    ['icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="tw-size-5 tw-shrink-0" width="24" height="24" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M19.072 21h-14.144a1.928 1.928 0 0 1 -1.928 -1.928v-6.857c0 -.512 .203 -1 .566 -1.365l7.07 -7.063a1.928 1.928 0 0 1 2.727 0l7.071 7.063c.363 .362 .566 .853 .566 1.365v6.857a1.928 1.928 0 0 1 -1.928 1.928z" />
                        <path d="M7 13v4h10v-4l-5 -5" />
                        <path d="M14.8 5.2l-11.8 11.8" />
                        <path d="M7 17v4" />
                        <path d="M17 17v4" />
                        </svg>', 'style' => config('app.env') == 'demo' ? 'background-color: yellow !important;' : '', 'active' => request()->segment(1) == 'hms']
                )->order(50);
            });
        }

    }

    /**
     * Function to add essential module taxonomies
     *
     * @return array
     */
    public function addTaxonomies()
    {
        $module_util = new ModuleUtil();
        $business_id = request()->session()->get('user.business_id');

        $output = [
            'amenities' => [],
        ];

        if (auth()->user()->can('hms.manage_amenities')) {
            $output['amenities'] = [
                'taxonomy_label' => __('hms::lang.amenity'),
                'heading' => __('hms::lang.amenities'),
                'sub_heading' => __('hms::lang.amenities'),
                'enable_taxonomy_code' => false,
                'enable_sub_taxonomy' => false,
                'heading_tooltip' => __('hms::lang.amenity_help_text'),
                'navbar' => 'hms::layouts.nav',
            ];
        }
        return $output;
    }

    public function profitLossReportData($data)
    {
        $business_id = $data['business_id'];
        $location_id = !empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = !empty($data['end_date']) ? $data['end_date'] : null;
        $user_id = !empty($data['user_id']) ? $data['user_id'] : null;

        $final_total = $this->get_hms_total(
            $business_id,
            $start_date,
            $end_date,
            $location_id,
            $user_id
        );

        $report_data = [
            //left side data
            [],
            //right side data
            [
                [
                    'value' => $final_total,
                    'label' => __('hms::lang.hms_total'),
                    'add_to_net_profit' => true,
                ],
            ],
        ];

        return $report_data;
    }

/**
 * get gross project from
 * project
 *
 * @param $business_id, $start_date, $end_date,
 *  $location_id
 * @return decimal
 */
    public function grossProfit($data)
    {
        $business_id = $data['business_id'];
        $location_id = !empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = !empty($data['end_date']) ? $data['end_date'] : null;
        $user_id = !empty($data['user_id']) ? $data['user_id'] : null;

        $final_total = $this->get_hms_total(
            $business_id,
            $start_date,
            $end_date,
            $location_id,
            $user_id
        );

        $data = [
            'value' => $final_total,
            'label' => __('hms::lang.hms_total'),
        ];

        return $data;
    }

    /**
     * Calculates final total of hms booking
     *
     * @param  int  $business_id
     * @param  string  $start_date = null
     * @param  string  $end_date = null
     * @param  int  $location_id = null
     * @return array
     */

    public function get_hms_total($business_id, $start_date = null, $end_date = null, $location_id = null, $user_id = null)
    {

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'hms_booking')
            ->where('status', 'confirmed');

        if (!empty($start_date) && !empty($end_date)) {
            if ($start_date == $end_date) {
                $transaction->whereDate('hms_booking_arrival_date_time', $end_date);
            } else {
                $transaction->whereBetween(DB::raw('hms_booking_arrival_date_time'), [$start_date, $end_date]);
            }
        }

        // if(!empty($location_id)){
        //     $transaction->where('location_id', $location_id);
        // }

        if (!empty($user_id)) {
            $transaction->where('created_by', $user_id);
        }

        $transaction = $transaction->sum('final_total');

        return $transaction;
    }

    public function room_type_count($status, $date_to, $date_from, $business)
    {
        $business_id = $business->id;

        return HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "' . $status . '") as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "' . $status . '") as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "' . $status . '") as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "' . $status . '") as no_of_guest'),
        )
            ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
            ->where('hms_room_types.business_id', $business_id)
            ->get();
    }

    /**
     * Hms Report options
     *
     * @return array
     */
    public function CustomDashboardOptions()
    {
        return [
            [
                'name' => 'customdashboard_widget_hms_reports',
                'label' => __('hms::lang.hms_report'),
                'size' => 100,
                'module_name' => __('hms::lang.hms_module'),
                'range' => true,
                'html_text' => false,
                'location' => false,
                'show_data' => false,
            ],
        ];
    }

    // hms report custom dashboard
    public function customdashboard_widget_hms_reports($dashboard_detail){
        

        $business_id = session()->get('user.business_id');

        $business = Business::findOrFail($business_id);

        $dates = ['date_to' => $dashboard_detail->start_date, 'date_from' => $dashboard_detail->end_date];

        $pending_room_types = $this->room_type_count('pending', $dates['date_to'], $dates['date_from'], $business);

        $cancelled_room_types = $this->room_type_count('cancelled', $dates['date_to'], $dates['date_from'], $business);
        $confirmed_room_types = $this->room_type_count('confirmed', $dates['date_to'], $dates['date_from'], $business);

        $all_room_types = HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?) as no_of_guest'),
        )
            ->setBindings([$dates['date_to'], $dates['date_from'], $dates['date_to'], $dates['date_from'], $dates['date_to'], $dates['date_from'], $dates['date_to'], $dates['date_from']])
            ->where('hms_room_types.business_id', $business_id)
            ->get();

        $transactionUtil = new TransactionUtil();

        return [
            'html' => view('hms::report.custom_dashboard_report', compact('pending_room_types', 'cancelled_room_types', 'confirmed_room_types', 'all_room_types', 'business', 'dates', 'dashboard_detail')),
        ];

    }
}
