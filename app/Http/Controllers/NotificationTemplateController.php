<?php

namespace App\Http\Controllers;

class NotificationTemplateController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for NotificationTemplateController::. This overlay folder is not a complete application.");
    }
}
