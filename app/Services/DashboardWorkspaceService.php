<?php

namespace App\Services;

use App\Business;
use App\BusinessLocation;
use App\SecurityDeposit;
use App\Transaction;
use App\UserDashboardPreference;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardWorkspaceService
{
    public function __construct(
        private TransactionUtil $transactions,
        private ProductUtil $products,
        private OnboardingProgressService $onboarding,
        private FeatureAccessService $features,
    ) {
    }

    public function payload(Request $request, array $filters): array
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);

        $business = Business::with(['industry:id,name,code', 'currency:id,code,symbol'])
            ->findOrFail($businessId);
        $preference = $this->preference($businessId, (int) $request->user()->id);
        $locations = $this->locations($request, $businessId);
        $locationId = $this->locationId($filters, $preference, $locations);
        [$start, $end, $preset] = $this->dateRange($filters, $preference);
        $permittedLocations = $request->user()->permitted_locations($businessId);
        $canSeeFinancials = $request->user()->can('dashboard.data');

        return [
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'csrf_token' => csrf_token(),
            'company' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => optional($business->industry)->only(['name', 'code']),
            ],
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'preset' => $preset,
                'label' => $this->periodLabel($start, $end, $preset),
            ],
            'currency' => [
                'code' => optional($business->currency)->code,
                'symbol' => optional($business->currency)->symbol,
            ],
            'locations' => $locations->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
            ])->values(),
            'selected_location_id' => $locationId,
            'preferences' => $preference,
            'permissions' => [
                'financials' => $canSeeFinancials,
                'configure' => true,
                'add_company' => (int) $business->owner_id === (int) $request->user()->id,
            ],
            'metrics' => $canSeeFinancials
                ? $this->financials($businessId, $start, $end, $locationId, $permittedLocations)
                : null,
            'trend' => $canSeeFinancials
                ? $this->salesTrend($businessId, $start, $end, $locationId, $permittedLocations)
                : [],
            'attention' => $this->attention($request, $businessId, $locationId, $permittedLocations, $canSeeFinancials),
            'quick_actions' => $this->quickActions($request, $business),
            'recent_transactions' => $canSeeFinancials
                ? $this->recentTransactions($businessId, $start, $end, $locationId, $permittedLocations)
                : [],
            'onboarding' => $this->onboarding->forBusiness($businessId, $request->user()),
            'legacy_dashboard_url' => route('home', ['legacy' => 1]),
        ];
    }

    public function savePreference(Request $request, array $data): UserDashboardPreference
    {
        abort_unless(Schema::hasTable('user_dashboard_preferences'), 503, 'Dashboard preferences are not ready. Run the V17 migration.');
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);

        if (! empty($data['default_location_id'])) {
            $allowed = $this->locations($request, $businessId)->contains('id', (int) $data['default_location_id']);
            abort_unless($allowed, 403, 'That location is outside your active company access.');
        }

        return UserDashboardPreference::updateOrCreate(
            ['business_id' => $businessId, 'user_id' => $request->user()->id],
            [
                'default_location_id' => $data['default_location_id'] ?? null,
                'date_preset' => $data['date_preset'],
                'density' => $data['density'],
                'accent' => $data['accent'],
                'hidden_sections' => array_values(array_unique($data['hidden_sections'] ?? [])),
            ]
        );
    }

    private function preference(int $businessId, int $userId): array
    {
        $defaults = [
            'default_location_id' => null,
            'date_preset' => 'this_month',
            'density' => 'comfortable',
            'accent' => 'ocean',
            'hidden_sections' => [],
        ];
        if (! Schema::hasTable('user_dashboard_preferences')) {
            return $defaults;
        }

        $preference = UserDashboardPreference::where('business_id', $businessId)
            ->where('user_id', $userId)->first();

        return $preference ? array_merge($defaults, $preference->only(array_keys($defaults))) : $defaults;
    }

    private function locations(Request $request, int $businessId): Collection
    {
        $query = BusinessLocation::where('business_id', $businessId)->active()->orderBy('name');
        $permitted = $request->user()->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('id', array_map('intval', $permitted ?: []));
        }

        return $query->get(['id', 'name']);
    }

    private function locationId(array $filters, array $preference, Collection $locations): ?int
    {
        $selected = $filters['location_id'] ?? $preference['default_location_id'] ?? null;
        if (empty($selected)) {
            return null;
        }
        abort_unless($locations->contains('id', (int) $selected), 403, 'That location is outside your active company access.');

        return (int) $selected;
    }

    private function dateRange(array $filters, array $preference): array
    {
        $preset = $filters['preset'] ?? $preference['date_preset'] ?? 'this_month';
        $today = now()->startOfDay();
        [$start, $end] = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'last_7_days' => [$today->copy()->subDays(6), $today->copy()],
            'last_30_days' => [$today->copy()->subDays(29), $today->copy()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [Carbon::parse($filters['start']), Carbon::parse($filters['end'])],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        abort_if($start->gt($end) || $start->diffInDays($end) > 366, 422, 'Choose a valid reporting period of no more than 366 days.');

        return [$start, $end, $preset];
    }

    private function financials(int $businessId, Carbon $start, Carbon $end, ?int $locationId, $permittedLocations): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();
        $sales = $this->transactions->getSellTotals($businessId, $from, $to, $locationId, null, $permittedLocations);
        $purchases = $this->transactions->getPurchaseTotals($businessId, $from, $to, $locationId, null, $permittedLocations);
        $others = $this->transactions->getTransactionTotals(
            $businessId,
            ['purchase_return', 'sell_return', 'expense'],
            $from,
            $to,
            $locationId,
            null,
            $permittedLocations
        );
        $ledger = empty($locationId)
            ? $this->transactions->getTotalLedgerDiscount($businessId, $from, $to)
            : ['total_sell_discount' => 0, 'total_purchase_discount' => 0];
        $grossSales = (float) ($sales['total_sell_inc_tax'] ?? 0);
        $salesReturns = (float) ($others['total_sell_return_inc_tax'] ?? 0);
        $expenses = max(0, (float) ($others['total_expense'] ?? 0) - (float) ($others['total_expense_refund'] ?? 0));
        $salesDue = max(0, (float) ($sales['invoice_due'] ?? 0) - (float) ($ledger['total_sell_discount'] ?? 0));
        $purchaseDue = max(0, (float) ($purchases['purchase_due'] ?? 0) - (float) ($ledger['total_purchase_discount'] ?? 0));

        return [
            'net_sales' => $grossSales - $salesReturns,
            'sales_due' => $salesDue,
            'purchases' => (float) ($purchases['total_purchase_inc_tax'] ?? 0) - (float) ($others['total_purchase_return_inc_tax'] ?? 0),
            'purchase_due' => $purchaseDue,
            'expenses' => $expenses,
            // This is deliberately not labelled cash balance: it is the same
            // sales-based operating indicator as the legacy dashboard and does
            // not replace bank reconciliation or a cash-flow statement.
            'sales_less_due_expenses' => $grossSales - $salesReturns - $salesDue - $expenses,
        ];
    }

    private function salesTrend(int $businessId, Carbon $start, Carbon $end, ?int $locationId, $permittedLocations): array
    {
        $query = Transaction::where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where(function ($query) {
                $query->where(fn ($sales) => $sales->where('type', 'sell')->where('status', 'final'))
                    ->orWhere('type', 'sell_return');
            })
            ->selectRaw("DATE(transaction_date) as day, SUM(CASE WHEN type = 'sell_return' THEN -final_total ELSE final_total END) as total")
            ->groupBy(DB::raw('DATE(transaction_date)'));
        $this->scopeLocations($query, $locationId, $permittedLocations);
        $totals = $query->pluck('total', 'day');

        $days = max(1, $start->diffInDays($end) + 1);
        $step = $days > 93 ? 'month' : ($days > 45 ? 'week' : 'day');
        if ($step === 'day') {
            return collect(CarbonPeriod::create($start, $end))->map(fn ($date) => [
                'label' => $date->format('j M'),
                'date' => $date->toDateString(),
                'value' => (float) ($totals[$date->toDateString()] ?? 0),
            ])->values()->all();
        }

        $grouped = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $step === 'month' ? $date->format('Y-m') : $date->copy()->startOfWeek()->toDateString();
            $grouped[$key] = ($grouped[$key] ?? 0) + (float) ($totals[$date->toDateString()] ?? 0);
        }

        return collect($grouped)->map(fn ($value, $key) => [
            'label' => $step === 'month' ? Carbon::parse($key.'-01')->format('M Y') : Carbon::parse($key)->format('j M'),
            'date' => $key,
            'value' => $value,
        ])->values()->all();
    }

    private function attention(Request $request, int $businessId, ?int $locationId, $permittedLocations, bool $canSeeFinancials): array
    {
        $items = [];
        if ($canSeeFinancials) {
            $salesDue = Transaction::where('business_id', $businessId)->where('type', 'sell')
                ->where('status', 'final')->where('payment_status', '!=', 'paid');
            $purchaseDue = Transaction::where('business_id', $businessId)->where('type', 'purchase')
                ->where('payment_status', '!=', 'paid');
            $this->scopeLocations($salesDue, $locationId, $permittedLocations);
            $this->scopeLocations($purchaseDue, $locationId, $permittedLocations);
            $items[] = ['key' => 'sales_due', 'label' => 'Unsettled sales', 'count' => $salesDue->count(), 'url' => '/sells?payment_status=due', 'tone' => 'amber'];
            $items[] = ['key' => 'purchase_due', 'label' => 'Supplier balances', 'count' => $purchaseDue->count(), 'url' => '/purchases?payment_status=due', 'tone' => 'violet'];
        }

        if ($request->user()->can('product.view')) {
            $lowStock = $this->products->getProductAlert($businessId, $permittedLocations);
            if ($locationId) {
                $lowStock->where('variation_location_details.location_id', $locationId);
            }
            $items[] = ['key' => 'low_stock', 'label' => 'Low-stock items', 'count' => $lowStock->get()->count(), 'url' => '/reports/stock-report', 'tone' => 'red'];
        }

        if (Schema::hasTable('security_deposits')
            && ($request->user()->can('superadmin') || $request->user()->hasRole('Admin#'.$businessId)
                || $request->user()->hasAnyPermission(['property.deposit.manage', 'property.deposit.refund', 'hms.manage_security_deposits', 'hms.approve_security_deposit_refunds']))
            && ($this->features->enabled('property_management', $businessId) || $this->features->enabled('hms', $businessId))) {
            $deposits = SecurityDeposit::forBusiness($businessId)->whereNotIn('status', ['settled', 'waived']);
            $this->scopeLocations($deposits, $locationId, $permittedLocations, 'business_location_id');
            $items[] = [
                'key' => 'security_deposits', 'label' => 'Deposits to clear', 'count' => $deposits->count(),
                'url' => $this->features->enabled('property_management', $businessId) ? '/property-management/deposits' : '/hms/folios',
                'tone' => 'blue',
            ];
        }

        if (Schema::hasTable('crm_activities') && $request->user()->hasAnyPermission(['crm.activity.manage', 'crm.access_all_schedule', 'crm.access_own_schedule'])) {
            $activities = DB::table('crm_activities')->where('business_id', $businessId)->where('status', 'planned')
                ->whereNotNull('due_at')->where('due_at', '<=', now()->addDays(7));
            if (! $request->user()->hasAnyPermission(['crm.activity.manage', 'crm.access_all_schedule'])) {
                $activities->where('owner_id', $request->user()->id);
            }
            $items[] = ['key' => 'crm_actions', 'label' => 'CRM actions due', 'count' => $activities->count(), 'url' => '/crm/dashboard', 'tone' => 'green'];
        }

        return $items;
    }

    private function quickActions(Request $request, Business $business): array
    {
        $businessId = (int) $business->id;
        $user = $request->user();
        $isAdmin = (int) $business->owner_id === (int) $user->id || $user->hasRole('Admin#'.$businessId) || $user->can('superadmin');
        $actions = [];
        $add = function (string $key, string $label, string $description, string $url, string $icon, string $tone, bool $allowed = true) use (&$actions) {
            if ($allowed) {
                $actions[] = compact('key', 'label', 'description', 'url', 'icon', 'tone');
            }
        };

        $add('sale', 'Create sale', 'Invoice or counter sale', '/sells/create', 'fa-file-text-o', 'blue', $user->can('sell.create'));
        $add('pos', 'Open POS', 'Start a fast counter sale', '/pos/create', 'fa-shopping-cart', 'sunset', $user->can('sell.create') && in_array('pos_sale', (array) $business->enabled_modules, true));
        $documentUrl = $user->can('smart_documents.view') ? '/smart-documents' : '/smart-documents/create';
        $add('documents', 'Smart documents', 'Quotes, invoices and receipts', $documentUrl, 'fa-file-pdf-o', 'violet', $this->features->enabled('smart_documents', $businessId) && $user->hasAnyPermission(['smart_documents.view', 'smart_documents.create']));
        $add('products', 'Products & services', 'Catalogue, pricing and stock', '/products', 'fa-cubes', 'emerald', $user->can('product.view'));
        $add('purchases', 'Purchasing', 'Suppliers and procurement', '/purchases', 'fa-truck', 'amber', $user->hasAnyPermission(['purchase.view', 'purchase.create']));
        $add('customers', 'Customers', 'Accounts and relationships', '/contacts?type=customer', 'fa-address-book-o', 'blue', $user->hasAnyPermission(['customer.view', 'customer.view_own']));

        $industry = optional($business->industry)->code;
        if (in_array($industry, ['hotel_lodge_guesthouse', 'hotel_with_restaurant'], true) && $this->features->enabled('hms', $businessId)) {
            $add('front_desk', 'Hotel front desk', 'Arrivals, stays and room status', '/hms/front-desk', 'fa-bed', 'violet', $isAdmin || $user->hasAnyPermission(['hms.front_desk', 'hms.manage_front_desk']));
            $add('bookings', 'Reservations', 'Rooms, groups and events', '/hms/bookings', 'fa-calendar-check-o', 'blue', $isAdmin || $user->hasAnyPermission(['hms.view_bookings', 'hms.add_booking', 'hms.edit_booking']));
            $add('housekeeping', 'Housekeeping', 'Room readiness and assignments', '/hms/housekeeping', 'fa-check-square-o', 'emerald', $isAdmin || $user->hasAnyPermission(['hms.manage_housekeeping', 'hms.perform_housekeeping', 'hms.inspect_housekeeping']));
        }
        if (in_array($industry, ['restaurant_food_service', 'hotel_with_restaurant'], true) && $this->features->enabled('restaurant_operations', $businessId)) {
            $add('restaurant', 'Restaurant operations', 'Orders, kitchen and fulfilment', '/restaurant-operations', 'fa-cutlery', 'sunset', $isAdmin || $user->hasAnyPermission(['restaurant.dashboard.view', 'restaurant.orders.view']));
            $add('kitchen', 'Kitchen board', 'Preparation tickets and stations', '/restaurant-operations/kitchen-board', 'fa-fire', 'red', $isAdmin || $user->hasAnyPermission(['restaurant.kitchen.view', 'restaurant.kitchen.manage']));
        }
        if ($industry === 'property_management_rentals' && $this->features->enabled('property_management', $businessId)) {
            $add('property', 'Property portfolio', 'Properties, units and occupancy', '/property-management', 'fa-building-o', 'violet', $isAdmin || $user->canForBusiness('property.view', $businessId));
            $add('leases', 'Leases & tenants', 'Contracts, rent and deposits', '/property-management/rents', 'fa-key', 'amber', $isAdmin || $user->canForBusiness('property.rent.manage', $businessId));
            $add('maintenance', 'Property maintenance', 'Requests and work orders', '/property-management/maintenance', 'fa-wrench', 'emerald', $isAdmin || $user->canForBusiness('property.maintenance.manage', $businessId));
        }

        $add('crm', 'CRM workspace', 'Pipeline and follow-ups', '/crm/dashboard', 'fa-bullseye', 'green', $this->features->enabled('crm', $businessId) && $user->hasAnyPermission(['crm.workspace.view', 'crm.access_all_leads', 'crm.access_own_leads']));
        $add('hrm', 'Human resources', 'People, workforce and payroll', '/hrm/dashboard', 'fa-users', 'blue', $this->features->enabled('hrm', $businessId));
        $add('hub', 'Company Hub', 'Announcements and knowledge', '/company-hub', 'fa-comments-o', 'violet', $this->features->enabled('company_hub', $businessId) && ($isAdmin || $user->canForBusiness('company_hub.view', $businessId)));
        $add('reports', 'Reports', 'Financial and operational insights', '/reports/profit-loss', 'fa-line-chart', 'emerald', $user->hasAnyPermission(['profit_loss_report.view', 'purchase_n_sell_report.view', 'stock_report.view']));
        $add('settings', 'Company settings', 'Locations, preferences and access', '/business/settings', 'fa-cog', 'slate', $isAdmin);

        return array_slice($actions, 0, 14);
    }

    private function recentTransactions(int $businessId, Carbon $start, Carbon $end, ?int $locationId, $permittedLocations): array
    {
        $query = Transaction::with(['contact:id,name,supplier_business_name', 'location:id,name'])
            ->where('business_id', $businessId)
            ->whereIn('type', ['sell', 'purchase', 'expense', 'sell_return', 'purchase_return'])
            ->whereBetween('transaction_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->latest('transaction_date')->latest('id')->limit(10);
        $this->scopeLocations($query, $locationId, $permittedLocations);

        return $query->get([
            'id', 'contact_id', 'location_id', 'type', 'status', 'payment_status',
            'invoice_no', 'ref_no', 'transaction_date', 'final_total',
        ])->map(function ($transaction) {
            $reference = $transaction->invoice_no ?: $transaction->ref_no ?: '#'.$transaction->id;
            $label = [
                'sell' => 'Sale', 'purchase' => 'Purchase', 'expense' => 'Expense',
                'sell_return' => 'Sales return', 'purchase_return' => 'Purchase return',
            ][$transaction->type] ?? ucfirst(str_replace('_', ' ', $transaction->type));
            $url = match ($transaction->type) {
                'sell', 'sell_return' => '/sells/'.$transaction->id,
                'purchase', 'purchase_return' => '/purchases/'.$transaction->id,
                default => '/expenses',
            };

            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'type_label' => $label,
                'reference' => $reference,
                'contact' => optional($transaction->contact)->supplier_business_name ?: optional($transaction->contact)->name,
                'location' => optional($transaction->location)->name,
                'date' => Carbon::parse($transaction->transaction_date)->toIso8601String(),
                'amount' => (float) $transaction->final_total,
                'payment_status' => $transaction->payment_status,
                'url' => $url,
            ];
        })->values()->all();
    }

    private function scopeLocations($query, ?int $locationId, $permittedLocations, string $column = 'location_id'): void
    {
        if ($locationId) {
            $query->where($column, $locationId);
        } elseif ($permittedLocations !== 'all') {
            $query->whereIn($column, array_map('intval', $permittedLocations ?: []));
        }
    }

    private function periodLabel(Carbon $start, Carbon $end, string $preset): string
    {
        return match ($preset) {
            'today' => 'Today, '.$start->format('j M Y'),
            'this_month' => $start->format('F Y'),
            'this_quarter' => 'Quarter '.$start->quarter.' · '.$start->format('Y'),
            'this_year' => $start->format('Y'),
            default => $start->format('j M Y').' – '.$end->format('j M Y'),
        };
    }
}
