<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RemoveLogs extends Command
{
    protected $signature = 'remove:logs';

    protected $description = 'Clear daily log';

    public function handle()
    {
        $operating_system = PHP_OS_FAMILY;
        if ($operating_system != 'Windows') 
        {
            // PM2 logs delete
            shell_exec('rm -rf /root/.pm2/logs/*');
            sleep(1);
            $this->pm2Restart();
        }
        
        return true;
    }

    private function pm2Restart()
    {
        $checkFile = shell_exec('ls -1U /root/.pm2/logs/ | wc -l');
        $checkFile = trim($checkFile);
        if($checkFile<1)
        {
            shell_exec('pm2 restart all');
        }
        else
        {
            sleep(1);
            $this->pm2Restart();
        }
        return true;
    }
}
