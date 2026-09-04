<?php

namespace App\Http\Controllers;

class AccountReportsController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for AccountReportsController::. This overlay folder is not a complete application.");
    }
}
