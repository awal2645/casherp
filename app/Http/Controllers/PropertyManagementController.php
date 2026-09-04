<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Business;
use App\BusinessLocation;
use App\Property;
use App\PropertyAccountMapping;
use App\PropertyAccountingPosting;
use App\PropertyDashboardPreference;
use App\PropertyLease;
use App\PropertyMaintenanceTicket;
use App\PropertyRentDue;
use App\PropertyRentPayment;
use App\PropertyUnit;
use App\Services\PropertyAccountingPostingService;
use App\Services\PropertyDashboardService;
use App\Services\PremiumModuleEntitlementService;
use App\Services\PropertyAccessService;
use App\Services\PropertyPaymentDepositService;
use App\Services\SecurityDepositService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entities\AccountingAccount;

class PropertyManagementController extends Controller
{
    private function businessId(): int
    {
        return (int) request()->session()->get('user.business_id');
    }

    public function index(Request $request, PropertyDashboardService $dashboardService)
    {
        $this->ensureCanView();
        $businessId = $this->businessId();
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'property_id' => ['nullable', 'integer'],
        ]);
        $preference = $dashboardService->preference($businessId, auth()->id());
        $usingSavedDefaults = ! $request->exists('location_id') && ! $request->exists('property_id');
        $locationId = $request->exists('location_id')
            ? ($request->filled('location_id') ? (int) $validated['location_id'] : null)
            : ($preference->default_business_location_id ? (int) $preference->default_business_location_id : null);
        $propertyId = $request->exists('property_id')
            ? ($request->filled('property_id') ? (int) $validated['property_id'] : null)
            : ($preference->default_property_id ? (int) $preference->default_property_id : null);

        $business = Business::with('industry')->whereKey($businessId)->firstOrFail();
        try {
            $dashboard = $dashboardService->dashboard(
                $business,
                $request->user(),
                $locationId,
                $propertyId
            );
        } catch (ValidationException $exception) {
            if (! $usingSavedDefaults) {
                throw $exception;
            }

            // Permission or portfolio changes can make an old personal default
            // unavailable. Fall back to the user's complete permitted scope.
            $locationId = null;
            $propertyId = null;
            $dashboard = $dashboardService->dashboard(
                $business,
                $request->user(),
                null,
                null
            );
        }

        $availableWidgets = $dashboardService->availableWidgets();
        $visibleWidgets = array_values(array_intersect(
            (array) $preference->visible_widgets,
            array_keys($availableWidgets)
        ));
        if (empty($visibleWidgets)) {
            $visibleWidgets = config('property_management.default_widgets', []);
        }

        return view('property.dashboard', $dashboard + [
            'business' => $business,
            'preference' => $preference,
            'availableWidgets' => $availableWidgets,
            'visibleWidgets' => $visibleWidgets,
            'compactMode' => (bool) $preference->compact_mode,
        ]);
    }

    public function createProperty()
    {
        $this->ensurePermission('property.manage');
        $businessId = $this->businessId();

        return view('property.create_property', [
            'locations' => BusinessLocation::forDropdown($businessId),
            'portfolioCategories' => config('property_management.portfolio_categories', []),
            'propertySubtypes' => config('property_management.property_subtypes', []),
        ]);
    }

    public function storeProperty(Request $request)
    {
        $this->ensurePermission('property.manage');
        $businessId = $this->businessId();
        $data = $request->validate([
            'name' => 'required|max:255',
            'business_location_id' => 'required|integer',
            'portfolio_category' => [
                'required',
                Rule::in(array_keys(config('property_management.portfolio_categories', []))),
            ],
            'property_subtype' => [
                'required',
                Rule::in(array_keys(config('property_management.property_subtypes', []))),
            ],
            'address' => 'nullable|max:2000',
            'description' => 'nullable|string|max:5000',
            'amenities_text' => 'nullable|string|max:3000',
        ]);

        $subtypeCategory = data_get(
            config('property_management.property_subtypes', []),
            $data['property_subtype'].'.category'
        );
        if ($data['portfolio_category'] !== 'mixed_portfolio'
            && $subtypeCategory !== $data['portfolio_category']) {
            throw ValidationException::withMessages([
                'property_subtype' => 'Choose a property subtype that matches the selected portfolio category.',
            ]);
        }

        $location = BusinessLocation::where('business_id', $businessId)
            ->active()
            ->findOrFail($data['business_location_id']);
        $permittedLocations = $request->user()->permitted_locations($businessId);
        abort_unless(
            $permittedLocations === 'all'
                || in_array((int) $location->id, array_map('intval', $permittedLocations), true),
            403
        );

        $data['business_id'] = $businessId;
        // Preserve the legacy type column for reports and integrations while
        // the structured category and subtype provide precise classification.
        $data['type'] = $data['portfolio_category'];
        $data['amenities'] = collect(preg_split('/[,;\r\n]+/', (string) ($data['amenities_text'] ?? '')))
            ->map(fn ($amenity) => trim($amenity))->filter()->unique()->take(100)->values()->all();
        unset($data['amenities_text']);

        Property::create($data);

        return redirect()->route('property.index')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function updateDashboardPreferences(
        Request $request,
        PropertyDashboardService $dashboardService
    ) {
        $this->ensureCanView();
        $businessId = $this->businessId();
        $widgetKeys = array_keys($dashboardService->availableWidgets());
        $data = $request->validate([
            'default_business_location_id' => ['nullable', 'integer'],
            'default_property_id' => ['nullable', 'integer'],
            'visible_widgets' => ['required', 'array', 'min:1'],
            'visible_widgets.*' => ['string', Rule::in($widgetKeys)],
            'compact_mode' => ['nullable', 'boolean'],
        ]);

        $locationId = ! empty($data['default_business_location_id'])
            ? (int) $data['default_business_location_id']
            : null;
        $propertyId = ! empty($data['default_property_id'])
            ? (int) $data['default_property_id']
            : null;
        $dashboardService->validateDefaults(
            $businessId,
            $request->user(),
            $locationId,
            $propertyId
        );

        $visibleWidgets = array_values(array_unique(array_intersect(
            $data['visible_widgets'],
            $widgetKeys
        )));

        PropertyDashboardPreference::updateOrCreate(
            ['business_id' => $businessId, 'user_id' => auth()->id()],
            [
                'default_business_location_id' => $locationId,
                'default_property_id' => $propertyId,
                'visible_widgets' => $visibleWidgets,
                'widget_order' => $visibleWidgets,
                'compact_mode' => $request->boolean('compact_mode'),
            ]
        );

        return redirect()->route('property.index')->with('status', [
            'success' => 1,
            'msg' => 'Your property dashboard preferences were saved.',
        ]);
    }

    public function resetDashboardPreferences()
    {
        $this->ensureCanView();
        PropertyDashboardPreference::where('business_id', $this->businessId())
            ->where('user_id', auth()->id())
            ->delete();

        return redirect()->route('property.index')->with('status', [
            'success' => 1,
            'msg' => 'Your property dashboard was reset to the recommended layout.',
        ]);
    }

    public function createUnit(Property $property)
    {
        $this->ensurePermission('property.manage');
        $this->ensurePropertyAccessible($property, 'manage');

        return view('property.create_unit', compact('property'));
    }

    public function units(Request $request)
    {
        $this->ensureCanView();
        $filters = $this->propertyFilterContext($request);

        return view('property.units', $filters + [
            'units' => $this->scopePermittedUnits(PropertyUnit::query())
                ->when($filters['selectedLocationId'], fn ($query) => $query->whereHas('property', fn ($property) => $property->where('business_location_id', $filters['selectedLocationId'])))
                ->when($filters['selectedPropertyId'], fn ($query) => $query->where('property_id', $filters['selectedPropertyId']))
                ->with('property.businessLocation')->orderBy('property_id')->orderBy('unit_code')->paginate(50)->withQueryString(),
        ]);
    }

    public function storeUnit(Request $request, Property $property)
    {
        $this->ensurePermission('property.manage');
        $this->ensurePropertyAccessible($property, 'manage');

        $data = $request->validate([
            'unit_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('property_units', 'unit_code')->where('property_id', $property->id),
            ],
            'unit_type' => 'nullable|max:100',
            'listing_purpose' => ['required', Rule::in(['rent', 'lease', 'sale', 'not_listed'])],
            'monthly_rent' => 'nullable|numeric|min:0',
            'asking_price' => 'nullable|required_if:listing_purpose,sale|numeric|min:0',
            'available_from' => 'nullable|date',
            'is_listed' => 'nullable|boolean',
            'listing_status' => ['required', Rule::in(['draft', 'active', 'paused', 'under_offer', 'closed'])],
        ]);
        $data['monthly_rent'] = $data['monthly_rent'] ?? 0;
        $data['is_listed'] = $request->boolean('is_listed') && $data['listing_purpose'] !== 'not_listed';

        app(PremiumModuleEntitlementService::class)->assertCapacity(
            $this->businessId(),
            'property_management',
            PropertyUnit::whereHas('property', function ($query) {
                $query->where('business_id', $this->businessId());
            })->count()
        );

        $property->units()->create($data);

        return redirect()->route('property.index')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function editUnit(PropertyUnit $unit)
    {
        $this->ensurePermission('property.manage');
        $this->ensureUnitAccessible($unit, 'manage');

        return view('property.edit_unit', ['unit' => $unit, 'property' => $unit->property]);
    }

    public function updateUnit(Request $request, PropertyUnit $unit)
    {
        $this->ensurePermission('property.manage');
        $this->ensureUnitAccessible($unit, 'manage');
        $data = $request->validate([
            'unit_code' => ['required', 'string', 'max:100', Rule::unique('property_units', 'unit_code')->where('property_id', $unit->property_id)->ignore($unit->id)],
            'unit_type' => 'nullable|max:100',
            'listing_purpose' => ['required', Rule::in(['rent', 'lease', 'sale', 'not_listed'])],
            'monthly_rent' => 'nullable|numeric|min:0',
            'asking_price' => 'nullable|required_if:listing_purpose,sale|numeric|min:0',
            'available_from' => 'nullable|date',
            'status' => ['required', Rule::in(['vacant', 'occupied', 'reserved', 'maintenance', 'unavailable'])],
            'is_listed' => 'nullable|boolean',
            'listing_status' => ['required', Rule::in(['draft', 'active', 'paused', 'under_offer', 'closed'])],
        ]);
        $data['monthly_rent'] = $data['monthly_rent'] ?? 0;
        $data['is_listed'] = $request->boolean('is_listed') && $data['listing_purpose'] !== 'not_listed';

        $hasActiveLease = $unit->leases()->where('status', 'active')->exists();
        if ($hasActiveLease && $data['status'] !== 'occupied') {
            throw ValidationException::withMessages([
                'status' => 'This unit has an active lease. End the lease before changing its occupancy status.',
            ]);
        }
        if (! $hasActiveLease && $unit->status !== 'occupied' && $data['status'] === 'occupied') {
            throw ValidationException::withMessages([
                'status' => 'Create a lease to mark this unit occupied. Occupancy cannot be set manually.',
            ]);
        }

        $unit->update($data);

        return redirect()->route('property.units.index')->with('status', ['success' => 1, 'msg' => 'Unit listing and availability updated.']);
    }

    public function createLease()
    {
        $this->ensurePermission('property.manage');
        $businessId = $this->businessId();

        return view('property.create_lease', [
            'units' => $this->scopePermittedUnits(PropertyUnit::query(), null, null, 'manage')
                ->where('status', 'vacant')
                ->with('property.businessLocation')
                ->get(),
            'tenants' => Contact::where('business_id', $businessId)
                ->whereIn('type', ['customer', 'both'])
                ->pluck('name', 'id'),
        ]);
    }

    public function storeLease(Request $request, SecurityDepositService $depositService)
    {
        $this->ensurePermission('property.manage');
        $businessId = $this->businessId();
        $data = $request->validate([
            'property_unit_id' => 'required|integer',
            'contact_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'monthly_rent' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
        ]);

        $unit = $this->scopePermittedUnits(PropertyUnit::query(), null, null, 'manage')
            ->where('status', 'vacant')
            ->findOrFail($data['property_unit_id']);

        if (! empty($data['contact_id'])) {
            $tenantExists = Contact::where('business_id', $businessId)
                ->whereIn('type', ['customer', 'both'])
                ->whereKey($data['contact_id'])
                ->exists();
            abort_unless($tenantExists, 422, 'The selected tenant is not available to this business.');
        }

        DB::transaction(function () use ($data, $unit, $businessId, $depositService) {
            $lease = PropertyLease::create($data + [
                'business_id' => $businessId,
                'status' => 'active',
            ]);
            $unit->update(['status' => 'occupied']);
            if ((float) ($data['security_deposit'] ?? 0) > 0) {
                $depositService->forPropertyLease($lease, (float) $data['security_deposit'], (int) auth()->id());
            }
        });

        return redirect()->route('property.index')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function endLease(Request $request, PropertyLease $lease, SecurityDepositService $depositService)
    {
        $this->ensurePermission('property.manage');
        $businessId = $this->businessId();
        $this->ensureLeaseAccessible($lease, 'manage');

        $data = $request->validate([
            'end_date' => 'required|date|after_or_equal:'.$lease->start_date->toDateString(),
        ]);
        $depositService->assertContextCanClose($businessId, 'property_lease', (int) $lease->id);

        DB::transaction(function () use ($lease, $data) {
            $lockedLease = PropertyLease::whereKey($lease->id)->lockForUpdate()->firstOrFail();
            if ($lockedLease->status !== 'active') {
                throw ValidationException::withMessages([
                    'lease' => 'This lease is no longer active.',
                ]);
            }

            $lockedLease->update([
                'end_date' => $data['end_date'],
                'status' => 'ended',
            ]);
            $lockedLease->unit()->update(['status' => 'vacant']);
        });

        return redirect()->route('property.index')->with(
            'status',
            ['success' => 1, 'msg' => 'Lease ended and the unit is now vacant.']
        );
    }

    public function rents(Request $request)
    {
        $this->ensureAnyPermission(['property.view', 'property.rent.manage']);
        $filters = $this->propertyFilterContext($request, 'rent');

        return view('property.rents', $filters + [
            'dues' => $this->scopePermittedDues(
                PropertyRentDue::query(),
                $filters['selectedLocationId'],
                $filters['selectedPropertyId'],
                'rent'
            )
                ->with(['lease.unit.property.businessLocation', 'lease.tenant', 'payments'])
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function generateRents(
        Request $request,
        PropertyAccountingPostingService $postingService,
        PropertyPaymentDepositService $paymentDepositService
    ) {
        $this->ensurePermission('property.rent.manage');
        $businessId = $this->businessId();
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'location_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
        ]);
        $filters = $this->propertyFilterContext($request, 'rent');
        $date = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        DB::transaction(function () use ($businessId, $date, $postingService, $paymentDepositService, $filters) {
            $leases = $this->scopePermittedLeases(
                PropertyLease::query(),
                $filters['selectedLocationId'],
                $filters['selectedPropertyId'],
                'rent'
            )
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $date->copy()->endOfMonth())
                ->where(function ($query) use ($date) {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->get();

            foreach ($leases as $lease) {
                $due = PropertyRentDue::firstOrCreate(
                    [
                        'property_lease_id' => $lease->id,
                        'due_date' => $date->toDateString(),
                    ],
                    [
                        'business_id' => $businessId,
                        'amount_due' => $lease->monthly_rent,
                        'amount_paid' => 0,
                        'status' => 'due',
                    ]
                );

                $postingService->postRentDue($due, auth()->id());
                $paymentDepositService->allocateForDue($due, (int) auth()->id());
            }
        });

        return redirect()->route('property.rents', array_filter([
            'location_id' => $filters['selectedLocationId'],
            'property_id' => $filters['selectedPropertyId'],
        ]))->with(
            'status',
            ['success' => 1, 'msg' => 'Monthly rent dues generated.']
        );
    }

    public function createPayment(PropertyRentDue $due)
    {
        $this->ensurePermission('property.rent.manage');
        $businessId = $this->businessId();
        $this->ensureDueAccessible($due, 'rent');
        abort_if(
            (float) $due->amount_paid >= (float) $due->amount_due,
            422,
            'This rent due is already paid.'
        );

        $accounts = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->where('account_primary_type', 'asset')
            ->pluck('name', 'id');
        $autoPost = PropertyAccountMapping::where('business_id', $businessId)
            ->where('auto_post_enabled', true)
            ->exists();

        return view('property.create_rent_payment', compact('due', 'accounts', 'autoPost'));
    }

    public function storePayment(
        Request $request,
        PropertyRentDue $due,
        PropertyAccountingPostingService $postingService
    ) {
        $this->ensurePermission('property.rent.manage');
        $businessId = $this->businessId();
        $this->ensureDueAccessible($due, 'rent');

        $autoPost = PropertyAccountMapping::where('business_id', $businessId)
            ->where('auto_post_enabled', true)
            ->exists();
        $receiptRule = $autoPost ? 'required|integer' : 'nullable|integer';
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'method' => 'required|in:cash,card,bank_transfer,cheque,other',
            'receipt_account_id' => $receiptRule,
            'reference' => 'nullable|max:255',
        ]);

        if (! empty($data['receipt_account_id'])) {
            $this->accountForBusiness(
                $businessId,
                (int) $data['receipt_account_id'],
                ['asset']
            );
        }

        DB::transaction(function () use (
            $data,
            $due,
            $businessId,
            $postingService
        ) {
            $lockedDue = PropertyRentDue::where('business_id', $businessId)
                ->whereKey($due->id)
                ->lockForUpdate()
                ->firstOrFail();
            $balance = (float) $lockedDue->amount_due - (float) $lockedDue->amount_paid;

            if ((float) $data['amount'] > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the remaining rent balance.',
                ]);
            }

            $payment = PropertyRentPayment::create($data + [
                'business_id' => $businessId,
                'property_rent_due_id' => $lockedDue->id,
                'created_by' => auth()->id(),
            ]);

            $paid = (float) $lockedDue->amount_paid + (float) $data['amount'];
            $lockedDue->update([
                'amount_paid' => $paid,
                'status' => $paid >= (float) $lockedDue->amount_due ? 'paid' : 'partial',
            ]);

            $postingService->postRentPayment($payment, auth()->id());
        });

        return redirect()->route('property.rents')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function maintenance(Request $request)
    {
        $this->ensureAnyPermission(['property.view', 'property.maintenance.manage']);
        $filters = $this->propertyFilterContext($request, 'maintenance');

        return view('property.maintenance', $filters + [
            'tickets' => $this->scopePermittedMaintenance(
                PropertyMaintenanceTicket::query(),
                $filters['selectedLocationId'],
                $filters['selectedPropertyId'],
                'maintenance'
            )
                ->with(['unit.property.businessLocation', 'vendor'])
                ->latest()
                ->get(),
        ]);
    }

    public function createMaintenance()
    {
        $this->ensurePermission('property.maintenance.manage');
        $businessId = $this->businessId();

        return view('property.create_maintenance', [
            'units' => $this->scopePermittedUnits(PropertyUnit::query(), null, null, 'maintenance')
                ->with('property.businessLocation')
                ->get(),
            'vendors' => Contact::where('business_id', $businessId)
                ->whereIn('type', ['supplier', 'both'])
                ->pluck('name', 'id'),
        ]);
    }

    public function storeMaintenance(Request $request)
    {
        $this->ensurePermission('property.maintenance.manage');
        $businessId = $this->businessId();
        $data = $request->validate([
            'property_unit_id' => 'required|integer',
            'contact_id' => 'nullable|integer',
            'title' => 'required|max:255',
            'description' => 'nullable|max:3000',
            'priority' => 'required|in:low,normal,high,urgent',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        $this->scopePermittedUnits(PropertyUnit::query(), null, null, 'maintenance')
            ->findOrFail($data['property_unit_id']);

        if (! empty($data['contact_id'])) {
            $vendorExists = Contact::where('business_id', $businessId)
                ->whereIn('type', ['supplier', 'both'])
                ->whereKey($data['contact_id'])
                ->exists();
            abort_unless($vendorExists, 422, 'The selected supplier is not available.');
        }

        PropertyMaintenanceTicket::create($data + [
            'business_id' => $businessId,
            'reported_on' => now()->toDateString(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('property.maintenance')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function completeMaintenance(PropertyMaintenanceTicket $ticket)
    {
        $this->ensurePermission('property.maintenance.manage');
        $businessId = $this->businessId();
        $this->ensureMaintenanceAccessible($ticket, 'maintenance');
        abort_if(
            $ticket->status === 'completed',
            422,
            'This maintenance ticket is already completed.'
        );

        $accounts = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('account_primary_type', ['asset', 'liability'])
            ->pluck('name', 'id');
        $autoPost = PropertyAccountMapping::where('business_id', $businessId)
            ->where('auto_post_enabled', true)
            ->exists();

        return view(
            'property.complete_maintenance',
            compact('ticket', 'accounts', 'autoPost')
        );
    }

    public function storeMaintenanceCompletion(
        Request $request,
        PropertyMaintenanceTicket $ticket,
        PropertyAccountingPostingService $postingService
    ) {
        $this->ensurePermission('property.maintenance.manage');
        $businessId = $this->businessId();
        $this->ensureMaintenanceAccessible($ticket, 'maintenance');

        $autoPost = PropertyAccountMapping::where('business_id', $businessId)
            ->where('auto_post_enabled', true)
            ->exists();
        $data = $request->validate([
            'actual_cost' => 'required|numeric|min:0',
            'resolved_on' => 'required|date',
            'payment_account_id' => 'nullable|integer',
        ]);

        if (
            $autoPost
            && (float) $data['actual_cost'] > 0
            && empty($data['payment_account_id'])
        ) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Choose the cash, bank, or payable account used.',
            ]);
        }

        if (! empty($data['payment_account_id'])) {
            $this->accountForBusiness(
                $businessId,
                (int) $data['payment_account_id'],
                ['asset', 'liability']
            );
        }

        DB::transaction(function () use (
            $ticket,
            $businessId,
            $data,
            $postingService
        ) {
            $lockedTicket = PropertyMaintenanceTicket::where('business_id', $businessId)
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->status === 'completed') {
                throw ValidationException::withMessages([
                    'maintenance' => 'This maintenance ticket is already completed.',
                ]);
            }

            $lockedTicket->update([
                'actual_cost' => $data['actual_cost'],
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'resolved_on' => $data['resolved_on'],
                'completed_at' => now(),
                'completed_by' => auth()->id(),
                'status' => 'completed',
            ]);

            $postingService->postMaintenanceCost($lockedTicket, auth()->id());
        });

        return redirect()->route('property.maintenance')->with(
            'status',
            ['success' => 1, 'msg' => 'Maintenance ticket completed.']
        );
    }

    public function accountingSettings()
    {
        $this->ensureAccountingManager();
        $businessId = $this->businessId();

        return view('property.accounting_settings', [
            'mapping' => PropertyAccountMapping::firstOrNew(['business_id' => $businessId]),
            'incomeAccounts' => $this->accountsByType($businessId, ['income']),
            'assetAccounts' => $this->accountsByType($businessId, ['asset']),
            'liabilityAccounts' => $this->accountsByType($businessId, ['liability']),
            'expenseAccounts' => $this->accountsByType($businessId, ['expense']),
        ]);
    }

    public function saveAccountingSettings(Request $request)
    {
        $this->ensureAccountingManager();
        $businessId = $this->businessId();
        $autoPost = $request->boolean('auto_post_enabled');
        $accountRule = $autoPost ? 'required|integer' : 'nullable|integer';
        $data = $request->validate([
            'rental_income_account_id' => $accountRule,
            'tenant_receivable_account_id' => $accountRule,
            'payment_advance_liability_account_id' => $accountRule,
            'maintenance_expense_account_id' => $accountRule,
            'auto_post_enabled' => 'nullable|boolean',
        ]);

        $expectedTypes = [
            'rental_income_account_id' => ['income'],
            'tenant_receivable_account_id' => ['asset'],
            'payment_advance_liability_account_id' => ['liability'],
            'maintenance_expense_account_id' => ['expense'],
        ];
        foreach ($expectedTypes as $field => $types) {
            if (! empty($data[$field])) {
                $this->accountForBusiness($businessId, (int) $data[$field], $types);
            }
        }

        $data['auto_post_enabled'] = $autoPost;
        PropertyAccountMapping::updateOrCreate(['business_id' => $businessId], $data);

        return redirect()->route('property.accounting.settings')->with(
            'status',
            ['success' => 1, 'msg' => __('lang_v1.success')]
        );
    }

    public function reports(Request $request)
    {
        $this->ensureCanView();
        $businessId = $this->businessId();
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        $from = Carbon::parse($validated['date_from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($validated['date_to'] ?? now())->endOfDay();
        $filters = $this->propertyFilterContext($request);

        $dues = $this->scopePermittedDues(
            PropertyRentDue::query(),
            $filters['selectedLocationId'],
            $filters['selectedPropertyId']
        )
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with(['lease.unit.property.businessLocation', 'lease.tenant', 'payments'])
            ->get();
        $payments = $this->scopePermittedPayments(
            PropertyRentPayment::query(),
            $filters['selectedLocationId'],
            $filters['selectedPropertyId']
        )
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->with('due.lease.unit.property.businessLocation')
            ->get();
        $maintenance = $this->scopePermittedMaintenance(
            PropertyMaintenanceTicket::query(),
            $filters['selectedLocationId'],
            $filters['selectedPropertyId']
        )
            ->where('status', 'completed')
            ->whereBetween('resolved_on', [$from->toDateString(), $to->toDateString()])
            ->with('unit.property.businessLocation')
            ->get();

        $propertyRows = [];
        foreach ($dues as $due) {
            $property = optional(optional($due->lease)->unit)->property;
            $name = $this->propertyDisplayName($property);
            $propertyRows[$name] = $propertyRows[$name] ?? $this->emptyFinanceRow($name);
            $propertyRows[$name]['billed'] += (float) $due->amount_due;
            $paidByEnd = $due->payments
                ->filter(function ($payment) use ($to) {
                    return $payment->paid_on->lte($to);
                })
                ->sum('amount');
            $propertyRows[$name]['outstanding'] += max(
                0,
                (float) $due->amount_due - (float) $paidByEnd
            );
        }
        foreach ($payments as $payment) {
            $property = optional(optional(optional($payment->due)->lease)->unit)->property;
            $name = $this->propertyDisplayName($property);
            $propertyRows[$name] = $propertyRows[$name] ?? $this->emptyFinanceRow($name);
            $propertyRows[$name]['collected'] += (float) $payment->amount;
        }
        foreach ($maintenance as $ticket) {
            $name = $this->propertyDisplayName(optional($ticket->unit)->property);
            $propertyRows[$name] = $propertyRows[$name] ?? $this->emptyFinanceRow($name);
            $propertyRows[$name]['maintenance'] += (float) $ticket->actual_cost;
        }
        foreach ($propertyRows as &$row) {
            $row['net_cash'] = $row['collected'] - $row['maintenance'];
        }
        unset($row);

        $ageingRows = $this->ageingRows(
            $businessId,
            $to,
            $filters['selectedLocationId'],
            $filters['selectedPropertyId']
        );
        $summary = [
            'billed' => collect($propertyRows)->sum('billed'),
            'collected' => collect($propertyRows)->sum('collected'),
            'outstanding' => collect($propertyRows)->sum('outstanding'),
            'maintenance' => collect($propertyRows)->sum('maintenance'),
            'net_cash' => collect($propertyRows)->sum('net_cash'),
        ];

        return view('property.reports', $filters + [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'propertyRows' => collect($propertyRows)->sortKeys(),
            'ageingRows' => $ageingRows,
            'postings' => $this->scopePermittedPostings(
                PropertyAccountingPosting::query(),
                $filters['selectedLocationId'],
                $filters['selectedPropertyId']
            )
                ->with(['debitAccount', 'creditAccount'])
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    public function reversePosting(
        PropertyAccountingPosting $posting,
        PropertyAccountingPostingService $postingService
    ) {
        $this->ensureAccountingManager();
        abort_unless(
            $this->scopePermittedPostings(
                PropertyAccountingPosting::query(),
                null,
                null,
                'accounting'
            )
                ->whereKey($posting->id)
                ->exists(),
            403,
            'This Accounting entry is outside your permitted locations.'
        );

        $postingService->reverse($posting, auth()->id());

        return redirect()->route('property.reports')->with(
            'status',
            ['success' => 1, 'msg' => 'Accounting posting reversed with balanced entries.']
        );
    }

    private function ageingRows(
        int $businessId,
        Carbon $asOf,
        ?int $locationId = null,
        ?int $propertyId = null
    )
    {
        $dues = $this->scopePermittedDues(
            PropertyRentDue::query(),
            $locationId,
            $propertyId
        )
            ->whereDate('due_date', '<=', $asOf->toDateString())
            ->with(['lease.unit.property.businessLocation', 'lease.tenant', 'payments'])
            ->orderBy('due_date')
            ->get();
        $rows = [];

        foreach ($dues as $due) {
            $paidByEnd = $due->payments
                ->filter(function ($payment) use ($asOf) {
                    return $payment->paid_on->lte($asOf);
                })
                ->sum('amount');
            $balance = max(0, (float) $due->amount_due - (float) $paidByEnd);
            if ($balance <= 0) {
                continue;
            }

            $key = optional($due->lease)->id
                ? 'lease_'.optional($due->lease)->id
                : 'due_'.$due->id;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'tenant' => optional(optional($due->lease)->tenant)->name
                        ?: 'Unassigned tenant',
                    'property' => $this->propertyDisplayName(
                        optional(optional($due->lease)->unit)->property
                    ),
                    'unit' => optional(optional($due->lease)->unit)->unit_code ?: '-',
                    'current' => 0,
                    'days_1_30' => 0,
                    'days_31_60' => 0,
                    'days_61_90' => 0,
                    'days_over_90' => 0,
                    'total' => 0,
                ];
            }

            $days = $due->due_date->lt($asOf)
                ? (int) $due->due_date->diffInDays($asOf)
                : 0;
            if ($days === 0) {
                $rows[$key]['current'] += $balance;
            } elseif ($days <= 30) {
                $rows[$key]['days_1_30'] += $balance;
            } elseif ($days <= 60) {
                $rows[$key]['days_31_60'] += $balance;
            } elseif ($days <= 90) {
                $rows[$key]['days_61_90'] += $balance;
            } else {
                $rows[$key]['days_over_90'] += $balance;
            }
            $rows[$key]['total'] += $balance;
        }

        return collect($rows)->sortByDesc('total');
    }

    /**
     * Return null when the current user may access every company location,
     * otherwise return the normalised list of permitted location IDs.
     */
    private function propertyFilterContext(Request $request, string $ability = 'view'): array
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'property_id' => ['nullable', 'integer'],
        ]);
        $locationId = $request->filled('location_id')
            ? (int) $validated['location_id']
            : null;
        $propertyId = $request->filled('property_id')
            ? (int) $validated['property_id']
            : null;

        $locationsQuery = BusinessLocation::where('business_id', $this->businessId())
            ->active()
            ->orderBy('name');
        $permittedLocationIds = $this->permittedLocationIds();
        if ($permittedLocationIds !== null) {
            $locationsQuery->whereIn('id', $permittedLocationIds);
        }
        $locations = $locationsQuery->get(['id', 'name']);

        if ($locationId !== null && ! $locations->contains('id', $locationId)) {
            throw ValidationException::withMessages([
                'location_id' => 'The selected location is not available to your account.',
            ]);
        }

        $properties = $this->scopePermittedProperties(
            Property::query(),
            $locationId,
            null,
            $ability
        )
            ->where('is_active', true)
            ->with('businessLocation:id,name')
            ->orderBy('name')
            ->get(['id', 'business_location_id', 'name']);

        if ($propertyId !== null && ! $properties->contains('id', $propertyId)) {
            throw ValidationException::withMessages([
                'property_id' => 'The selected property is not available in this location.',
            ]);
        }

        return [
            'filterLocations' => $locations,
            'filterProperties' => $properties,
            'selectedLocationId' => $locationId,
            'selectedPropertyId' => $propertyId,
        ];
    }

    private function permittedLocationIds(): ?array
    {
        $permitted = auth()->user()->permitted_locations($this->businessId());

        if ($permitted === 'all') {
            return null;
        }

        return array_values(array_unique(array_map('intval', (array) $permitted)));
    }

    private function scopePermittedProperties(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        app(PropertyAccessService::class)->scopeProperties(
            $query,
            $this->businessId(),
            auth()->user(),
            $ability
        );
        if ($locationId !== null) {
            $query->where('properties.business_location_id', $locationId);
        }
        if ($propertyId !== null) {
            $query->whereKey($propertyId);
        }

        return $query;
    }

    private function scopePermittedUnits(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        return $query->whereHas('property', function ($propertyQuery) use (
            $locationId,
            $propertyId,
            $ability
        ) {
            $this->scopePermittedProperties($propertyQuery, $locationId, $propertyId, $ability);
        });
    }

    private function scopePermittedLeases(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        return $query->where('business_id', $this->businessId())
            ->whereHas('unit.property', function ($propertyQuery) use (
                $locationId,
                $propertyId,
                $ability
            ) {
                $this->scopePermittedProperties($propertyQuery, $locationId, $propertyId, $ability);
            });
    }

    private function scopePermittedDues(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        return $query->where('business_id', $this->businessId())
            ->whereHas('lease.unit.property', function ($propertyQuery) use (
                $locationId,
                $propertyId,
                $ability
            ) {
                $this->scopePermittedProperties($propertyQuery, $locationId, $propertyId, $ability);
            });
    }

    private function scopePermittedPayments(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        return $query->where('business_id', $this->businessId())
            ->whereHas('due.lease.unit.property', function ($propertyQuery) use (
                $locationId,
                $propertyId,
                $ability
            ) {
                $this->scopePermittedProperties($propertyQuery, $locationId, $propertyId, $ability);
            });
    }

    private function scopePermittedMaintenance(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        return $query->where('business_id', $this->businessId())
            ->whereHas('unit.property', function ($propertyQuery) use (
                $locationId,
                $propertyId,
                $ability
            ) {
                $this->scopePermittedProperties($propertyQuery, $locationId, $propertyId, $ability);
            });
    }

    private function scopePermittedPostings(
        $query,
        ?int $locationId = null,
        ?int $propertyId = null,
        string $ability = 'view'
    )
    {
        $businessId = $this->businessId();
        $query->where('business_id', $businessId);

        $dueIds = $this->scopePermittedDues(
            PropertyRentDue::query(),
            $locationId,
            $propertyId,
            $ability
        )->pluck('id');
        $paymentIds = $this->scopePermittedPayments(
            PropertyRentPayment::query(),
            $locationId,
            $propertyId,
            $ability
        )->pluck('id');
        $maintenanceIds = $this->scopePermittedMaintenance(
            PropertyMaintenanceTicket::query(),
            $locationId,
            $propertyId,
            $ability
        )->pluck('id');

        $originalPostingIds = PropertyAccountingPosting::where('business_id', $businessId)
            ->where(function ($sourceQuery) use ($dueIds, $paymentIds, $maintenanceIds) {
                $sourceQuery->where(function ($dueQuery) use ($dueIds) {
                    $dueQuery->where('source_type', 'rent_due')->whereIn('source_id', $dueIds);
                })->orWhere(function ($paymentQuery) use ($paymentIds) {
                    $paymentQuery->where('source_type', 'rent_payment')->whereIn('source_id', $paymentIds);
                })->orWhere(function ($maintenanceQuery) use ($maintenanceIds) {
                    $maintenanceQuery->where('source_type', 'maintenance_cost')
                        ->whereIn('source_id', $maintenanceIds);
                });
            })
            ->pluck('id');

        return $query->where(function ($sourceQuery) use (
            $dueIds,
            $paymentIds,
            $maintenanceIds,
            $originalPostingIds
        ) {
            $sourceQuery->where(function ($dueQuery) use ($dueIds) {
                $dueQuery->where('source_type', 'rent_due')->whereIn('source_id', $dueIds);
            })->orWhere(function ($paymentQuery) use ($paymentIds) {
                $paymentQuery->where('source_type', 'rent_payment')
                    ->whereIn('source_id', $paymentIds);
            })->orWhere(function ($maintenanceQuery) use ($maintenanceIds) {
                $maintenanceQuery->where('source_type', 'maintenance_cost')
                    ->whereIn('source_id', $maintenanceIds);
            })->orWhere(function ($reversalQuery) use ($originalPostingIds) {
                $reversalQuery->where('source_type', 'reversal')
                    ->whereIn('source_id', $originalPostingIds);
            });
        });
    }

    private function ensurePropertyAccessible(Property $property, string $ability = 'view'): void
    {
        abort_unless(
            $this->scopePermittedProperties(Property::query(), null, null, $ability)
                ->whereKey($property->id)
                ->exists(),
            403,
            'This property is outside your assigned property or location access.'
        );
    }

    private function ensureUnitAccessible(PropertyUnit $unit, string $ability = 'view'): void
    {
        abort_unless(
            $this->scopePermittedUnits(PropertyUnit::query(), null, null, $ability)
                ->whereKey($unit->id)
                ->exists(),
            404
        );
    }

    private function ensureLeaseAccessible(PropertyLease $lease, string $ability = 'view'): void
    {
        abort_unless(
            $this->scopePermittedLeases(PropertyLease::query(), null, null, $ability)
                ->whereKey($lease->id)
                ->exists(),
            403,
            'This lease is outside your assigned property or location access.'
        );
    }

    private function ensureDueAccessible(PropertyRentDue $due, string $ability = 'view'): void
    {
        abort_unless(
            $this->scopePermittedDues(PropertyRentDue::query(), null, null, $ability)
                ->whereKey($due->id)
                ->exists(),
            403,
            'This rent record is outside your assigned property or location access.'
        );
    }

    private function ensureMaintenanceAccessible(
        PropertyMaintenanceTicket $ticket,
        string $ability = 'view'
    ): void
    {
        abort_unless(
            $this->scopePermittedMaintenance(
                PropertyMaintenanceTicket::query(),
                null,
                null,
                $ability
            )
                ->whereKey($ticket->id)
                ->exists(),
            403,
            'This maintenance record is outside your assigned property or location access.'
        );
    }

    private function propertyDisplayName($property): string
    {
        if (! $property) {
            return 'Unassigned';
        }

        $location = optional($property->businessLocation)->name;

        return $location ? $location.' — '.$property->name : $property->name;
    }

    private function emptyFinanceRow(string $name): array
    {
        return [
            'property' => $name,
            'billed' => 0,
            'collected' => 0,
            'outstanding' => 0,
            'maintenance' => 0,
            'net_cash' => 0,
        ];
    }

    private function accountsByType(int $businessId, array $types)
    {
        return AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->whereIn('account_primary_type', $types)
            ->pluck('name', 'id');
    }

    private function accountForBusiness(
        int $businessId,
        int $accountId,
        array $types = []
    ): AccountingAccount {
        $query = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active');
        if (! empty($types)) {
            $query->whereIn('account_primary_type', $types);
        }

        $account = $query->find($accountId);
        if ($account === null) {
            throw ValidationException::withMessages([
                'accounting' => 'The selected Accounting account is invalid for this business.',
            ]);
        }

        return $account;
    }

    private function ensureAccountingManager(): void
    {
        $this->ensurePermission('property.accounting.manage');
        abort_unless(class_exists(AccountingAccount::class), 404);
    }

    private function ensureCanView(): void
    {
        $this->ensurePermission('property.view');
    }

    private function ensureAnyPermission(array $permissions): void
    {
        abort_unless(collect($permissions)->contains(fn ($permission) => auth()->user()->can($permission)), 403, 'Unauthorized action.');
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403, 'Unauthorized action.');
    }
}
