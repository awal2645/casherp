<?php

namespace App\Http\Controllers;

class WarrantyController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for WarrantyController::. This overlay folder is not a complete application.");
    }
}
