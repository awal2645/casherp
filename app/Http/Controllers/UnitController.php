<?php

namespace App\Http\Controllers;

class UnitController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for UnitController::. This overlay folder is not a complete application.");
    }
}
