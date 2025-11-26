<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DlrGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dlr:generator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // We are not using this right now, we are directly created dlr
        return Command::SUCCESS;
    }
}
