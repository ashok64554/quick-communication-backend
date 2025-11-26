<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PrimaryRoute;
use Log;

class CheckConnectionSMPP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $primaryRouteInfo;

    public function __construct($primaryRouteInfo)
    {
        $this->primaryRouteInfo = $primaryRouteInfo;
    }

    public function handle()
    {
        $gateway_url        = rtrim($this->primaryRouteInfo->ip_address, '/');
        $gateway_smsc_id    = $this->primaryRouteInfo->smsc_id;
        $gateway_port       = $this->primaryRouteInfo->port;
        $gateway_user_name  = $this->primaryRouteInfo->smsc_username;
        $gateway_password   = $this->primaryRouteInfo->smsc_password;

        try {
            $kannel_admin_pass  = env("KANNEL_ADMIN_PASS");
            $kannel_admin_port  = env("KANNEL_ADMIN_PORT");
            $kannel_ip          = env("KANNEL_IP");
            $url                = 'http://'.$kannel_ip.':'.$kannel_admin_port.'/status.xml?password='.$kannel_admin_pass.'';
            $response           = file_get_contents($url);
            $xml                = simplexml_load_string($response);
            $response           = json_encode($xml);
            $array              = json_decode($response,TRUE);
            $smscs              = $array['smscs']['smsc'];

            foreach($smscs as $key => $value) 
            {
                if($value['admin-id'] == $gateway_smsc_id) 
                {
                    $onlinefrom = null;
                    $status = explode(' ',$value['status']);
                    if(isset($status[1]))
                    {
                        $onlinefrom = rtrim($status[1],'s');
                        $days = intval(intval($onlinefrom) / (3600*24));
                        $onlinefrom = $days.' Days, '.gmdate("H:i:s", $onlinefrom);
                    } 
                    

                    if(@$status[0] == 'online' && !empty($status)) {
                        $updateStatus = PrimaryRoute::find($this->primaryRouteInfo->id);
                        $updateStatus->status = '1';
                        $updateStatus->online_from =  $onlinefrom;
                        $updateStatus->save();
                    }
                    else
                    {
                        $updateStatus = PrimaryRoute::find($this->primaryRouteInfo->id);
                        $updateStatus->status = '0';
                        $updateStatus->online_from =  null;
                        $updateStatus->save();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::info('SMPP Not Connected: Exception is: '.$e.',& ID is '.$this->primaryRouteInfo->id);
        }
    }
}
