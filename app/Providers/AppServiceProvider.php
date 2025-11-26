<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        if(config('app.env') === 'production') {
            //\URL::forceScheme('https');
        }
        Passport::tokensExpireIn(Carbon::now()->addMinutes(180));
        Passport::refreshTokensExpireIn(Carbon::now()->addMinutes(240));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addMinutes(120));
    }
}
