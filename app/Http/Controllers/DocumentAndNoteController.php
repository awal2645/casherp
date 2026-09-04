<?php

namespace App\Http\Controllers;

class DocumentAndNoteController extends Controller
{
    public function __call($method, $parameters)
    {
        abort(503, "CashERP baseline is required for DocumentAndNoteController::. This overlay folder is not a complete application.");
    }
}
