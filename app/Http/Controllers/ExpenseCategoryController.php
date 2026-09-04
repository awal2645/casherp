<?php

namespace App\Http\Controllers;

class ExpenseCategoryController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for ExpenseCategoryController::. This overlay folder is not a complete application.");
    }
}
