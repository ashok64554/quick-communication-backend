<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SendSms;
use DB;

class IsCampaignComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:campaign';

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
        //This is only for get-campaign-info api uses.
        // report ll automatically updated by mysql event.

        $oneHrTime = date("Y-m-d H:i:s", strtotime('-30 minutes', time()));

        $campaigns = DB::table('send_sms')
            ->select('id')
            ->where('status', 'Ready-to-complete')
            ->where('campaign_send_date_time', '>=', $oneHrTime)
            ->where('campaign', 'API')
            ->get();
        foreach ($campaigns as $key => $campaign) 
        {
            $delivrd = 0;
            $pending = 0;
            $accepted = 0;
            $invalid = 0;
            $black = 0;
            $failed = 0;

            $totalRecords = \DB::table('send_sms_queues')
                ->select('stat', \DB::raw('COUNT(stat) as stat_counts'))
                ->where('send_sms_id', $campaign->id)
                ->groupBy('stat')
                ->get();
            foreach ($totalRecords as $key => $value) 
            {
                switch (strtolower($value->stat)) 
                {
                    case strtolower('DELIVRD'):
                        $delivrd += $value->stat_counts;
                        break;
                    case strtolower('Pending'):
                        $pending += $value->stat_counts;
                        break;
                    case strtolower('Accepted'):
                        $accepted += $value->stat_counts;
                        break;
                    case strtolower('Invalid'):
                        $invalid += $value->stat_counts;
                        break;
                    case strtolower('BLACK'):
                        $black += $value->stat_counts;
                        break;
                    default:
                        $failed += $value->stat_counts;
                        break;
                }
            }
            /*
            $return = [
                'delivrd' => $delivrd,
                'pending' => $pending,
                'accepted' => $accepted,
                'invalid' => $invalid,
                'black' => $black,
                'failed' => $failed,
            ];

            dd($return);
            */

            DB::statement("UPDATE `send_sms` SET 

            `total_delivered` = $delivrd, 

            `total_failed` = $failed,

            `total_block_number` = $black,

            `total_invalid_number` = $invalid,

            `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
            
            WHERE `id` = $campaign->id;");
        }
        return Command::SUCCESS;
    }
}
