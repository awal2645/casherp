<?php

namespace App\Http\Controllers;

class StockAdjustmentController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for StockAdjustmentController::. This overlay folder is not a complete application.");
    }
}
