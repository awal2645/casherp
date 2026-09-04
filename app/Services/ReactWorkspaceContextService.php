<?php

namespace App\Services;

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

class ReactWorkspaceContextService
{
    public function context(Request $request, int $businessId): array
    {
        $business = Business::with('industry:id,name,code')->findOrFail($businessId);
        $businesses = $request->user()->accessibleBusinesses()
            ->active()
            ->with('industry:id,name,code')
            ->orderBy('business.name')
            ->get(['business.id', 'business.name', 'business.industry_id']);
        $features = app(FeatureAccessService::class);
        $navigation = [
            ['key' => 'home', 'label' => 'Home', 'icon' => 'fa-home', 'url' => route('home')],
        ];
        $commercialAllowed = $request->user()->can('superadmin')
            || collect([
                'sell.view', 'direct_sell.view', 'direct_sell.access', 'view_own_sell_only',
                'quotation.view_all', 'quotation.view_own', 'customer.view', 'customer.view_own',
            ])->contains(fn ($permission) => $request->user()->canForBusiness($permission, $businessId));
        $commercialAllowed = $commercialAllowed
            || ($features->enabled('smart_documents', $businessId)
                && $request->user()->canForBusiness('smart_documents.view', $businessId));
        if ($commercialAllowed) {
            $navigation[] = ['key' => 'sales', 'label' => 'Sales', 'icon' => 'fa-line-chart', 'url' => route('sales.workspace')];
        }
        if ($request->user()->can('account.access') && in_array('account', (array) session('business.enabled_modules', []), true)) {
            $navigation[] = ['key' => 'accounting', 'label' => 'Accounting', 'icon' => 'fa-balance-scale', 'url' => route('accounting.workspace')];
        }
        $crmAllowed = $request->user()->hasAnyPermission([
            'crm.workspace.view', 'crm.access_all_leads', 'crm.access_own_leads',
        ]);
        if ($features->enabled('crm', $businessId)
            && ($request->user()->can('superadmin') || (new ModuleUtil())->hasThePermissionInSubscription($businessId, 'crm_module'))
            && $crmAllowed) {
            $navigation[] = ['key' => 'crm', 'label' => 'CRM', 'icon' => 'fa-bullseye', 'url' => route('crm.workspace')];
        }
        if ($features->enabled('company_hub', $businessId)
            && ($request->user()->can('superadmin') || $request->user()->canForBusiness('company_hub.view', $businessId))) {
            $navigation[] = ['key' => 'hub', 'label' => 'Company Hub', 'icon' => 'fa-comments', 'url' => route('company-hub.index')];
        }

        return [
            'active_business' => $business->only(['id', 'name']) + [
                'industry' => optional($business->industry)->only(['name', 'code']),
            ],
            'businesses' => $businesses->map(fn ($item) => $item->only(['id', 'name']) + [
                'industry' => optional($item->industry)->only(['name', 'code']),
            ])->values(),
            'user' => [
                'id' => $request->user()->id,
                'name' => trim($request->user()->first_name.' '.$request->user()->last_name),
            ],
            'switch_url' => route('business.switch'),
            'home_url' => route('home'),
            'logout_url' => route('logout'),
            'profile_url' => action([\App\Http\Controllers\UserController::class, 'getProfile']),
            'navigation' => $navigation,
            'notifications_url' => route('notifications.in-app.index'),
            'notifications_read_all_url' => route('notifications.in-app.read-all'),
            'unread_notifications' => $request->user()->notifications()
                ->whereNull('read_at')
                ->where(function ($query) use ($businessId) {
                    $query->whereNull('data->business_id')->orWhere('data->business_id', $businessId);
                })->count(),
        ];
    }
}
