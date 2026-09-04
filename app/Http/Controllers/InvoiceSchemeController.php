<?php

namespace App\Http\Controllers;

class InvoiceSchemeController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for InvoiceSchemeController::. This overlay folder is not a complete application.");
    }
}
