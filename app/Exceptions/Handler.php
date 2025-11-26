<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Carbon\Carbon;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        \League\OAuth2\Server\Exception\OAuthServerException::class
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                $intime = Carbon::now();
                return response()->json(prepareResult(true, [], trans('translate.page_or_record_not_found'), $intime), config('httpcodes.not_found'));
            }
        });

        $this->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            $intime = Carbon::now();
            return response()->json(prepareResult(true, [], trans('translate.permission_not_defined'), $intime), config('httpcodes.forbidden'));
        });
        
        $this->renderable(function (\League\OAuth2\Server\Exception\OAuthServerException $e, $request) {
            if($e->getCode() == 9)
            {
                $intime = Carbon::now();
                return response()->json(prepareResult(true, [], trans('translate.unauthorized_login'), $intime), config('httpcodes.unauthorized'));
            }
        });
    }
}
