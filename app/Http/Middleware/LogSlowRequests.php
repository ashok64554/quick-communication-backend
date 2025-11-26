<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        if ($duration > 2) { // seconds
            Log::channel('slow')->warning('Slow request detected', [
                'method' => $request->method(),
                'uri'    => $request->fullUrl(),
                'time'   => round($duration, 3) . 's',
                'ip'     => $request->ip(),
                'payload'=> $request->all(),
            ]);
        }

        return $response;
    }
}
