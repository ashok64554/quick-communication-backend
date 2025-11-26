<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SendSms;
use App\Models\User;
use App\Models\SecondaryRoute;
use App\Models\DltTemplate;
use App\Models\ContactNumber;
use App\Models\Blacklist;
use Illuminate\Support\Str;
use Excel;
use Carbon\Carbon;
use DB;
use App\Imports\CampaignImport;
use Log;

class CreateFile extends Command
{
    protected $signature = 'file:create';

    protected $description = 'Command description';

    public function handle()
    {
        return;
    }
}
