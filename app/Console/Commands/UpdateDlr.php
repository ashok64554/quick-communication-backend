<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\SendSmsQueue;

class UpdateDlr extends Command
{
    protected $signature = 'update:dlr';

    protected $description = 'Command description';

    public function handle()
    {
        // Check Redis connection
        try {
            //$redis = Redis::connect('127.0.0.1', 6379);
            $redis = Redis::connection();
        } catch(\Predis\Connection\ConnectionException $e){
            \Log::error('error connection redis');
            die;
        }

        $this->updateDlr($redis);
    }

    public function updateDlr($redis)
    {
        $redisConn = $redis;
        
        // Set value
        /*for ($i=0; $i < 100 ; $i++) { 
            $redis->rpush('dlrkey', json_encode([
                    "msgid"=> '1692769975698849'.$i,
                    "d"=> '8',
                    "oo"=> '00',
                    "ff"=> '19627234112256043'.$i,
                    "s"=> '',
                    "ss"=> '',
                    "aa"=> 'ACK/',
                    "wh_url"=> '',
                    "uuid"=> '8d73b400-c8d3-11ef-91a1-1b7c59e67e66',
                    "mobile"=> '919977131341',
                    "used_credit"=> '3',
                    "finalDateTime"=> date('Y-m-d H:i:s')
                ])
            );

            $delivered = {
              msgid: '1735799638321043311',
              d: '1',
              oo: '00',
              ff: '1962800112035910104',
              s: 'sub:001',
              ss: 'dlvrd:001',
              aa: 'id:1962800112035910104 sub:001 dlvrd:001 submit date:2501021203 done date:2501021204 stat:DELIVRD err:000 text:',
              wh_url: '',
              uuid: '8c2aafc0-c8d3-11ef-87d2-4fb68cae4750',
              mobile: '919340934755',
              used_credit: '1',
              finalDateTime: '2025-01-02 12:04:01'
            }

        }
        dd('Done');*/      

        // Get the stored data and print it
        // $arList = Redis::command('lrange', ['dlrkey', 0, 10]);
        // OR

        $arList = $redis->lrange("dlrkey", 0 ,5000);
        if(sizeof($arList)>0)
        {
            $submitted_to_smsc = []; // 8 code
            $rejected_by_smsc = []; //16 code
            $acknowledgement = []; //other than 8 & 16 code
            $webhook_arr = []; // send api message response through webhook 

            $done_date = date('Y-m-d H:i:s');
            $stat = 'FAILED';
            $stat_code = null;
            foreach ($arList as $key => $value) 
            {
                $json_decode = json_decode($value, true);
                if($json_decode['d']=='8')
                {
                    $submitted_to_smsc[] = [
                        'unique_key' => $json_decode['msgid'],
                        'response_token' => $json_decode['ff'],
                        'submit_date' => $json_decode['finalDateTime'],
                        'stat' => 'ACCEPTED',
                        'status' => 'Completed'
                    ];
                    $stat = 'ACCEPTED';
                    $stat_code = null;
                    $redis->lrem('dlrkey',1, $value);
                }
                elseif($json_decode['d']=='16')
                {
                    $rejected_by_smsc[] = [
                        'unique_key' => $json_decode['msgid'],
                        'err' => $json_decode['d'],
                        'done_date' => date('Y-m-d H:i:s'),
                        'stat' => 'REJECTED',
                        'status' => 'Completed'
                    ];
                    $stat = 'REJECTED';
                    $stat_code = $json_decode['d'];
                    $redis->lrem('dlrkey',1, $value);
                }
                else
                {
                    if(!empty($json_decode['aa']))
                    {
                        $dataExplode = explode(' ', $json_decode['aa']);
                        $id = explode(':', $dataExplode[0]);
                        $sub = explode(':', $dataExplode[1]);
                        $dlvrd = explode(':', $dataExplode[2]);
                        $done_date = $json_decode['finalDateTime'];
                        $stat = explode(':', $dataExplode[7]);
                        $err = explode(':', $dataExplode[8]);

                        $acknowledgement[] = [
                           'unique_key' => $json_decode['msgid'],
                           'response_token' => @$id[1],
                           'sub' => @$sub[1],
                           'dlvrd' => @$dlvrd[1],
                           'done_date' => $done_date,
                           'stat' => @$stat[1],
                           'err' => @$err[1],
                           'status' => 'Completed',
                        ];

                        $stat = @$stat[1];
                        $stat_code = @$err[1];
                    }
                    $redis->lrem('dlrkey',1, $value);
                }

                if(!empty($json_decode['wh_url']))
                {
                    if($json_decode['d']=='8')
                    {
                        $wb_response = [
                            "campaign_token" => $json_decode['uuid'],
                            "mobile_number" => $json_decode['mobile'],
                            "used_credit" => $json_decode['used_credit'],
                            "submit_date" => $json_decode['finalDateTime'],
                            "done_date" => null,
                            "status" => $stat,
                            "status_code" => $stat_code
                        ];
                    }
                    else
                    {
                        $wb_response = [
                            "campaign_token" => $json_decode['uuid'],
                            "mobile_number" => $json_decode['mobile'],
                            "used_credit" => $json_decode['used_credit'],
                            "submit_date" => null,
                            "done_date" => $done_date,
                            "status" => $stat,
                            "status_code" => $stat_code
                        ];
                    }

                    $webhook_arr[] = [
                        'message_type' => 1,
                        'webhook_url' => $json_decode['wh_url'],
                        'response' => json_encode($wb_response)
                    ];
                }
                
            }
            if(sizeof($submitted_to_smsc)>0)
            {
                SendSmsQueue::massUpdate(
                    values: $submitted_to_smsc,
                    uniqueBy: 'unique_key'
                );
            }
            if(sizeof($acknowledgement)>0)
            {
                SendSmsQueue::massUpdate(
                    values: $acknowledgement,
                    uniqueBy: 'unique_key'
                );
            }
            if(sizeof($rejected_by_smsc)>0)
            {
                SendSmsQueue::massUpdate(
                    values: $rejected_by_smsc,
                    uniqueBy: 'unique_key'
                );
            }

            if(sizeof($webhook_arr)>0)
            {
                \DB::table('callback_webhooks')->insert($webhook_arr);
            }

            sleep(2);
            $arList = $redis->lrange("dlrkey", 0 ,1);
            if(sizeof($arList)>0)
            {
                $this->updateDlr($redisConn);
            }
        }
        return true;
    }
}
