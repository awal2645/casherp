<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for UserController::. This overlay folder is not a complete application.");
    }
}
