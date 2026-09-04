<?php

namespace App\Http\Controllers;

class AccountTypeController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for AccountTypeController::. This overlay folder is not a complete application.");
    }
}
