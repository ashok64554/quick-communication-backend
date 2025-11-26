<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\FileProcess::class,
        \App\Console\Commands\KannelCheck::class,
        \App\Console\Commands\CreditBack::class,
        \App\Console\Commands\UpdateDlr::class,
        \App\Console\Commands\RemoveLogs::class,
        \App\Console\Commands\IsCampaignComplete::class,
        \App\Console\Commands\WAUpdateDlr::class,
        \App\Console\Commands\CallbackWebhookSend::class,
        \App\Console\Commands\WACreditBack::class,
        \App\Console\Commands\WaCloseExpiredChatbotSessions::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        /*
            * * * * * php /var/www/oursms.in/artisan schedule:run 1>> /dev/null 2>&1
        */
        $schedule->command('file:process')
            ->everyMinute()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('wafile:process')
            ->everyMinute()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('update:dlr')
            ->everyMinute()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('callback:webhook')
            ->everyMinute()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));
/*
        $schedule->command('waupdate:dlr')
            ->everyMinute()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));
*/
        $schedule->command('kannel:status')
            ->everyFifteenMinutes()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('check:campaign')
            ->everyFifteenMinutes()
            //->everyFiveMinutes()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('credit:back')
            ->dailyAt('04:00')
            ->runInBackground()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('wacredit:back')
            ->dailyAt('02:00')
            ->runInBackground()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));

        $schedule->command('remove:logs')
            ->everySixHours()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));
/*
        $schedule->command('chatbot:close-expired')
            ->everyFiveMinutes()
            ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));
*/
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
