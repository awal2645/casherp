<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CashERP native restaurant operations
    |--------------------------------------------------------------------------
    |
    | These profiles extend the existing UltimatePOS restaurant tools. They do
    | not replace POS, products, transactions, stock, payments or HRM. Every
    | record created by this layer remains scoped to the active company and
    | operating location.
    |
    */
    'industry_profiles' => [
        'restaurant_food_service' => [
            'default_channels' => ['dine_in', 'counter', 'takeaway', 'delivery'],
            'room_posting' => false,
        ],
        'hotel_lodge_guesthouse' => [
            'default_channels' => ['dine_in', 'room_service', 'takeaway'],
            'room_posting' => true,
        ],
        'hotel_with_restaurant' => [
            'default_channels' => ['dine_in', 'counter', 'takeaway', 'delivery', 'room_service', 'banqueting'],
            'room_posting' => true,
        ],
    ],

    'service_channels' => [
        'dine_in' => 'Dine-in / table service',
        'counter' => 'Counter / quick service',
        'takeaway' => 'Takeaway / collection',
        'delivery' => 'Delivery',
        'room_service' => 'Hotel room service',
        'banqueting' => 'Catering / banqueting / events',
        'online' => 'Online order',
    ],

    'fulfilment_statuses' => [
        'received' => 'Received',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'dispatched' => 'Dispatched',
        'served' => 'Served / collected',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'fulfilment_transitions' => [
        'received' => ['confirmed', 'preparing', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['dispatched', 'served', 'delivered', 'completed'],
        'dispatched' => ['delivered', 'cancelled'],
        'served' => ['completed'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ],

    'kitchen_statuses' => [
        'queued' => 'Queued',
        'accepted' => 'Accepted',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'kitchen_transitions' => [
        'queued' => ['accepted', 'preparing', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ],

    'station_types' => [
        'main' => 'Main kitchen',
        'grill' => 'Grill',
        'fryer' => 'Fryer',
        'pastry' => 'Pastry / bakery',
        'cold' => 'Cold kitchen',
        'bar' => 'Bar / beverages',
        'room_service' => 'Room service',
        'banqueting' => 'Banqueting / events',
        'expedite' => 'Expedite / pass',
        'other' => 'Other preparation station',
    ],

    'reservation_statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'waitlisted' => 'Wait-listed',
        'arrived' => 'Arrived',
        'seated' => 'Seated',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ],

    'waiter_request_types' => [
        'call_waiter' => 'Call waiter',
        'place_order' => 'Place an order',
        'water' => 'Water / refreshments',
        'bill' => 'Request the bill',
        'assistance' => 'Assistance',
    ],

    'register_movement_types' => [
        'cash_in' => 'Cash in',
        'cash_out' => 'Cash out',
        'safe_drop' => 'Safe drop',
        'petty_cash' => 'Petty cash',
        'refund' => 'Cash refund',
        'tip_payout' => 'Tip payout',
        'change' => 'Change given',
    ],

    'permissions' => [
        'restaurant.dashboard.view' => 'View restaurant operations dashboard',
        'restaurant.orders.view' => 'View restaurant orders and fulfilment',
        'restaurant.orders.manage' => 'Manage restaurant order fulfilment',
        'restaurant.kitchen.view' => 'View kitchen display and tickets',
        'restaurant.kitchen.manage' => 'Advance or cancel kitchen tickets',
        'restaurant.stations.manage' => 'Manage kitchen stations and item routing',
        'restaurant.recipes.view' => 'View recipes, ingredient usage and food cost',
        'restaurant.recipes.manage' => 'Manage recipes and ingredient mappings',
        'restaurant.inventory.adjust' => 'Record restaurant waste and ingredient adjustments',
        'restaurant.reservations.view' => 'View restaurant reservations',
        'restaurant.reservations.manage' => 'Create and manage restaurant reservations',
        'restaurant.waiter_requests.manage' => 'Manage waiter and table requests',
        'restaurant.register.view' => 'View cash register sessions and reports',
        'restaurant.register.manage' => 'Record controlled cash movements',
        'restaurant.register.reconcile' => 'Submit register counts and discrepancies',
        'restaurant.register.approve' => 'Approve or reject register reconciliation',
        'restaurant.reports.view' => 'View restaurant operational and food-cost reports',
        'restaurant.settings.manage' => 'Manage restaurant operational settings',
    ],

    'default_settings' => [
        'reservation_hold_minutes' => 15,
        'default_table_turn_minutes' => 90,
        'kitchen_warning_minutes' => 15,
        'kitchen_critical_minutes' => 30,
        'allow_negative_ingredient_stock' => false,
        'require_void_reason' => true,
        'require_register_variance_reason' => true,
        'require_register_approval' => true,
        'enable_qr_ordering' => false,
        'enable_online_ordering' => false,
    ],
];
