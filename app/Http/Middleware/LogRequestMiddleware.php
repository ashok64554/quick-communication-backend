<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Log;

class LogRequestMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if(config('app.env') === 'local' && config('app.debug') === true) 
        {
            $requestFrom = ['url' => request()->fullUrl(), 'body' => $request->all()];
            Log::channel('log_request')->info(json_encode($requestFrom));
        }
        return $response;
    }
}
