<?php

return [
    'retention_days' => 400,

    'defaults' => [
        'database_enabled' => true,
        'email_enabled' => false,
        'lead_days' => 7,
        'overdue_after_hours' => 24,
    ],

    /*
     * One catalog supplies stable keys, labels and default governance for the
     * whole product. Existing event-specific notifications continue to work;
     * this catalog covers scheduled risks and gives new modules a standard
     * contract instead of inventing another notification table or vocabulary.
     */
    'events' => [
        'registration.abandoned' => ['category' => 'platform', 'severity' => 'warning', 'email' => true, 'audience' => 'prospect'],
        'trial.expiring' => ['category' => 'subscription', 'severity' => 'warning', 'email' => true, 'permissions' => []],
        'trial.expired' => ['category' => 'subscription', 'severity' => 'critical', 'email' => true, 'permissions' => []],
        'subscription.expiring' => ['category' => 'subscription', 'severity' => 'warning', 'email' => true, 'permissions' => []],
        'subscription.expired' => ['category' => 'subscription', 'severity' => 'critical', 'email' => true, 'permissions' => []],
        'subscription.payment_failed' => ['category' => 'subscription', 'severity' => 'critical', 'email' => true, 'permissions' => []],

        'sales.invoice_overdue' => ['category' => 'sales', 'severity' => 'warning', 'permissions' => ['sell.view', 'sell.view_own']],
        'purchases.payment_overdue' => ['category' => 'purchases', 'severity' => 'warning', 'permissions' => ['purchase.view']],
        'inventory.low_stock' => ['category' => 'inventory', 'severity' => 'warning', 'permissions' => ['product.view']],
        'procurement.approval_overdue' => ['category' => 'procurement', 'severity' => 'warning', 'permissions' => ['procurement.approve.manager', 'procurement.approve.finance', 'procurement.approve.admin']],
        'procurement.delivery_overdue' => ['category' => 'procurement', 'severity' => 'critical', 'permissions' => ['purchase.view', 'procurement.audit.view', 'procurement.submit']],

        'restaurant.order_delayed' => ['category' => 'restaurant', 'severity' => 'warning', 'permissions' => ['restaurant.kitchen.manage', 'restaurant.orders.manage']],
        'restaurant.waiter_request_overdue' => ['category' => 'restaurant', 'severity' => 'warning', 'permissions' => ['restaurant.waiter_requests.manage', 'restaurant.orders.manage']],
        'restaurant.cash_reconciliation_pending' => ['category' => 'restaurant', 'severity' => 'critical', 'permissions' => ['restaurant.register.approve', 'restaurant.register.manage']],
        'restaurant.reservation_attention' => ['category' => 'restaurant', 'severity' => 'warning', 'permissions' => ['restaurant.reservations.view', 'restaurant.reservations.manage']],

        'hospitality.checkin_upcoming' => ['category' => 'hospitality', 'severity' => 'info', 'permissions' => ['hms.view_bookings', 'hms.front_desk', 'hms.manage_front_desk']],
        'hospitality.arrival_overdue' => ['category' => 'hospitality', 'severity' => 'critical', 'permissions' => ['hms.view_bookings', 'hms.front_desk', 'hms.manage_front_desk']],
        'hospitality.checkout_overdue' => ['category' => 'hospitality', 'severity' => 'critical', 'permissions' => ['hms.view_bookings', 'hms.front_desk', 'hms.manage_front_desk']],
        'hospitality.housekeeping_overdue' => ['category' => 'hospitality', 'severity' => 'warning', 'permissions' => ['hms.manage_housekeeping', 'hms.perform_housekeeping']],
        'hospitality.group_hold_expiring' => ['category' => 'hospitality', 'severity' => 'warning', 'permissions' => ['hms.manage_groups', 'hms.view_bookings']],

        'property.rent_overdue' => ['category' => 'property', 'severity' => 'critical', 'permissions' => ['property.rent.manage', 'property.accounting.manage']],
        'property.lease_expiring' => ['category' => 'property', 'severity' => 'warning', 'permissions' => ['property.manage', 'property.rent.manage']],
        'property.lease_expired' => ['category' => 'property', 'severity' => 'critical', 'permissions' => ['property.manage', 'property.rent.manage']],
        'property.maintenance_overdue' => ['category' => 'property', 'severity' => 'warning', 'permissions' => ['property.maintenance.manage']],
        'property.viewing_upcoming' => ['category' => 'property', 'severity' => 'info', 'permissions' => ['property.viewings.manage']],

        'deposit.security_pending' => ['category' => 'deposits', 'severity' => 'warning', 'permissions' => ['property.deposit.manage', 'hms.manage_security_deposits']],
        'deposit.refund_overdue' => ['category' => 'deposits', 'severity' => 'critical', 'permissions' => ['property.deposit.refund', 'hms.approve_security_deposit_refunds']],

        'hr.contract_expiring' => ['category' => 'hr', 'severity' => 'warning', 'permissions' => ['essentials.view_employee_profiles', 'essentials.manage_employee_profiles']],
        'hr.leave_pending' => ['category' => 'hr', 'severity' => 'warning', 'permissions' => ['essentials.approve_leave']],
        'hr.payroll_action_required' => ['category' => 'hr', 'severity' => 'critical', 'permissions' => ['essentials.manage_payroll_runs', 'essentials.approve_payroll_runs']],

        'crm.activity_overdue' => ['category' => 'crm', 'severity' => 'warning', 'permissions' => ['crm.activity.manage']],
        'project.task_overdue' => ['category' => 'projects', 'severity' => 'warning', 'permissions' => ['project.view', 'project.manage']],
        'company_hub.acknowledgement_overdue' => ['category' => 'company_hub', 'severity' => 'warning', 'permissions' => ['company_hub.publish_announcements']],
        'data_import.failed' => ['category' => 'data', 'severity' => 'critical', 'permissions' => ['data_import.manage', 'data_import.view']],
    ],

    'industry_categories' => [
        'general_business' => ['subscription', 'sales', 'purchases', 'inventory', 'procurement', 'assets', 'hr', 'crm', 'projects', 'company_hub', 'data', 'system'],
        'restaurant_food_service' => ['subscription', 'sales', 'purchases', 'inventory', 'procurement', 'restaurant', 'assets', 'hr', 'crm', 'company_hub', 'data', 'system'],
        'hotel_lodge_guesthouse' => ['subscription', 'sales', 'purchases', 'procurement', 'hospitality', 'deposits', 'assets', 'hr', 'crm', 'company_hub', 'data', 'system'],
        'hotel_with_restaurant' => ['subscription', 'sales', 'purchases', 'inventory', 'procurement', 'restaurant', 'hospitality', 'deposits', 'assets', 'hr', 'crm', 'company_hub', 'data', 'system'],
        'property_management_rentals' => ['subscription', 'sales', 'purchases', 'procurement', 'property', 'deposits', 'assets', 'hr', 'crm', 'projects', 'company_hub', 'data', 'system'],
        'professional_services' => ['subscription', 'sales', 'purchases', 'procurement', 'assets', 'hr', 'crm', 'projects', 'company_hub', 'data', 'system'],
    ],
];
