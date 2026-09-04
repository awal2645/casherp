<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;

class ProductModifierSetController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for Restaurant\\ProductModifierSetController::.");
    }
}
