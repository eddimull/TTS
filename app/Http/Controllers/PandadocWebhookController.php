<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PandadocWebhookController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->all();
        Log::info($data);
        return response()->json(['success' => true]);
    }
}
