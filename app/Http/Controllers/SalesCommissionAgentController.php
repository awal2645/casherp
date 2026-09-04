<?php

namespace App\Http\Controllers;

class SalesCommissionAgentController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for SalesCommissionAgentController::. This overlay folder is not a complete application.");
    }
}
