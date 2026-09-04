<?php

return [
    'enabled' => filter_var(env('DPO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'mode' => env('DPO_MODE', 'test'),
    'company_token' => env('DPO_COMPANY_TOKEN'),
    'service_type' => env('DPO_SERVICE_TYPE'),
    'service_description' => env('DPO_SERVICE_DESCRIPTION', 'CashERP SaaS subscription'),
    'api_endpoint' => env('DPO_API_ENDPOINT', 'https://secure.3gdirectpay.com/API/v6/'),
    'payment_url' => env('DPO_PAYMENT_URL', 'https://secure.3gdirectpay.com/payv3.php'),
    'payment_time_limit_minutes' => (int) env('DPO_PTL_MINUTES', 30),
    'http_timeout_seconds' => (int) env('DPO_HTTP_TIMEOUT', 20),
];
