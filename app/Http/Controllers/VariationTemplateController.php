<?php

namespace App\Http\Controllers;

class VariationTemplateController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for VariationTemplateController::. This overlay folder is not a complete application.");
    }
}
