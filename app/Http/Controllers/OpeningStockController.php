<?php

namespace App\Http\Controllers;

class OpeningStockController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for OpeningStockController::. This overlay folder is not a complete application.");
    }
}
