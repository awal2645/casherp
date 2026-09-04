<?php

namespace App\Http\Controllers;

class GroupTaxController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for GroupTaxController::. This overlay folder is not a complete application.");
    }
}
