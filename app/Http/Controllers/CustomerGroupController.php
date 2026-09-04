<?php

namespace App\Http\Controllers;

class CustomerGroupController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for CustomerGroupController::. This overlay folder is not a complete application.");
    }
}
