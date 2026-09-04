<?php

namespace App\Http\Controllers;

class TaxRateController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for TaxRateController::. This overlay folder is not a complete application.");
    }
}
