<?php

namespace App\Http\Controllers;

class BackUpController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for BackUpController::. This overlay folder is not a complete application.");
    }
}
