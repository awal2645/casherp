<?php

namespace App\Http\Controllers;

class DiscountController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for DiscountController::. This overlay folder is not a complete application.");
    }
}
