<?php

namespace App\Http\Controllers;

class PrinterController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for PrinterController::. This overlay folder is not a complete application.");
    }
}
