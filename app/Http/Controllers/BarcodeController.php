<?php

namespace App\Http\Controllers;

class BarcodeController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for BarcodeController::. This overlay folder is not a complete application.");
    }
}
