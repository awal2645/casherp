<?php

namespace App\Http\Controllers;

class BrandController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for BrandController::. This overlay folder is not a complete application.");
    }
}
