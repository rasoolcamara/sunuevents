<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function wave_callback(Request $request)
    {
        logger("Le callback de wave");
        logger($request->all());
    }

    public function om_senegal_callback(Request $request)
    {
        logger("Le callback de om_senegal");
        logger($request->all());
    }
}
