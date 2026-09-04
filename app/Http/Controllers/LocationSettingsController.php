<?php

namespace App\Http\Controllers;

class LocationSettingsController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for LocationSettingsController::. This overlay folder is not a complete application.");
    }
}
