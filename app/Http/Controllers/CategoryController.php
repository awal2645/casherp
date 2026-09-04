<?php

namespace App\Http\Controllers;

class CategoryController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for CategoryController::. This overlay folder is not a complete application.");
    }
}
