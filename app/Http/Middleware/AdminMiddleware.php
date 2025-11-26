<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if(auth()->id()!=1)
        {
            if(!$request->user()->parent_id == '1'){
                $intime = \Carbon\Carbon::now();
                return response()->json(prepareResult(true, [], 'User not authorized to access admin functionality.', $intime), config('httpcodes.unauthorized'));
            }
        }
        return $next($request);
    }
}
