<?php

namespace App\Http\Controllers;

class InvoiceLayoutController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for InvoiceLayoutController::. This overlay folder is not a complete application.");
    }
}
