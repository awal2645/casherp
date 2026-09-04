<?php

namespace App\Http\Controllers;

class TaxonomyController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for TaxonomyController::. This overlay folder is not a complete application.");
    }
}
