<?php

namespace App\Http\Controllers;

class TypesOfServiceController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for TypesOfServiceController::. This overlay folder is not a complete application.");
    }
}
