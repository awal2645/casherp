<?php

namespace App\Http\Controllers;

class DashboardConfiguratorController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for DashboardConfiguratorController::. This overlay folder is not a complete application.");
    }
}
