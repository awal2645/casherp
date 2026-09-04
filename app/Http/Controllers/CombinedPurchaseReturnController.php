<?php

namespace App\Http\Controllers;

class CombinedPurchaseReturnController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for CombinedPurchaseReturnController::. This overlay folder is not a complete application.");
    }
}
