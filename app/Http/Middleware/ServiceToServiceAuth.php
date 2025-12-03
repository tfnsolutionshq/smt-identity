<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ServiceToServiceAuth
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Service-Key');
        
        if (!$apiKey || $apiKey !== config('services.api_key')) {
            return response()->json(['error' => 'Unauthorized service'], 401);
        }

        return $next($request);
    }
}