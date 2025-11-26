<?php

namespace App\Console\Commands;

ini_set('memory_limit', '-1');

use Illuminate\Console\Command;
use App\Models\SendSms;
use App\Models\SendSmsQueue;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\User;
use App\Models\SecondaryRoute;
use App\Models\DltTemplate;
use App\Models\ContactNumber;
use App\Models\Blacklist;
use App\Models\InvalidSeries;
use App\Models\CampaignExecuter;
use Illuminate\Support\Str;
use Excel;
use Carbon\Carbon;
use DB;
use App\Imports\CampaignImport;
use Log;
use Illuminate\Support\Facades\Redis;

class FileProcess extends Command
{
    protected $signature = 'file:process';

    protected $description = 'Command description';

    public function handle()
    {
        set_time_limit(0);
        $wh_url = null;
        //checkKannelQueueStatus();

        $actDir = 'public/';
        $dateTime = date("Y-m-d H:i:s");
        $countBlankRow = 0;

        $executeCampaigns = CampaignExecuter::select('id', 'send_sms_id')
            ->whereIn('campaign_type', [1,2,3])
            ->where('campaign_send_date_time','<=', $dateTime)
            ->get();

        foreach ($executeCampaigns as $key => $executeCampaign) 
        {
            $campaignInfo = $executeCampaign;
            $executeCampaign->delete();

            $campaign = SendSms::select('id','parent_id','user_id', 'campaign','secondary_route_id','dlt_template_id','sender_id','country_id','sms_type','message','message_type','is_flash','file_path','file_mobile_field_name','priority','ratio_percent_set','failed_ratio','route_type','total_credit_deduct','reschedule_send_sms_id','reschedule_type','total_contacts','message_credit_size')
                ->find($campaignInfo->send_sms_id);

            if($campaign->campaign=='API')
            {
                //userInfo
                $userInfo = userInfo($campaign->user_id);
                $wh_url = $userInfo->webhook_callback_url;
            }
            else
            {
                // webhook only for api request now so we comment this line and pass null value.
                //userInfo
                // $userInfo = userInfo($smsInfo->user_id);
                // $wh_url = $userInfo->webhook_callback_url;
                $wh_url = null;
            }
            

            if($campaign && recheckCampaignStatusB4Process($campaign->id))
            {
                //update read file status
                $campaign->is_read_file_path = 1;
                $campaign->status = 'Ready-to-complete';
                $campaign->save();

                //Invalid Series
                $invalidSeries = \DB::table('invalid_series')
                    ->pluck('start_with')
                    ->toArray();

                $total_contacts = $campaign->total_contacts;

                $ratio_percent_set = $campaign->ratio_percent_set;
                $failed_ratio = ($ratio_percent_set > 0) ? $campaign->failed_ratio : null;

                /*******************************************
                 ******************************************/
                //kannel paramter
                $is_unicode = ($campaign->message_type==2) ? 1 : 0;
                $kannelPara = kannelParameter($campaign->is_flash, $is_unicode);
                $dltTemplate = \DB::table('dlt_templates')
                    ->select('id','entity_id','dlt_template_id','is_unicode','sender_id')
                    ->find($campaign->dlt_template_id);
                //Our SQL Box Code
                $kannel_domain = env('KANNEL_DOMAIN');
                $kannel_ip = env('KANNEL_IP');
                $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
                $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
                $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
                $node_port = env('NODE_PORT', 8009);
                $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
                $tagId = env('TagID', 5122);
                $tlv_tagId_hash = genSHA256($tagId, $dltTemplate->entity_id.','.$telemarketer_id);
                //$meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id.'&TagID='.$tlv_tagId_hash;
                $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$tlv_tagId_hash.'&TagID='.$tagId;

                $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->with('primaryRoute:id,smsc_id')->find($campaign->secondary_route_id);
                $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;
                /*******************************************
                 ******************************************/

                //get associated routes (gateway)
                $associated_routes = \DB::table('primary_route_associateds')
                    ->join('primary_routes', 'primary_routes.id', '=', 'primary_route_associateds.associted_primary_route')
                    ->where('primary_route_id', $secondaryRouteInfo->primary_route_id)
                    ->pluck('smsc_id', 'id')
                    ->toArray();
                if(sizeof($associated_routes)<1)
                {
                    $associated_routes = [$secondaryRouteInfo->primary_route_id => $secondaryRouteInfo->primaryRoute->smsc_id];
                }

                $file_path = $actDir.$campaign->file_path;
                if(file_exists($file_path) && !empty($campaign->file_path))
                {
                    // Start Blacklist merge and set condition for india only (91)
                    
                    $blacklist = \DB::table('blacklists')
                        ->select('mobile_number')
                        ->where('user_id', $campaign->user_id)
                        ->pluck('mobile_number')
                        ->toArray();

                    $withoutCountryCode = array_map(function($num) {
                        return (substr($num, 0, 2) === "91") ? substr($num, 2) : $num;
                    }, $blacklist);

                    $blacklist = collect([$blacklist,$withoutCountryCode]);
                    $blacklist = $blacklist->flatten()->toArray();

                    // End Blacklist merge and set condition for india only (91)

                    $file_mobile_field_name = $campaign->file_mobile_field_name;
                    
                    $message = $campaign->message;

                    $message_type = ($dltTemplate->is_unicode==1) ? 2 : 1;
                    $sender_id = $dltTemplate->sender_id;
                    $priority = $campaign->priority;

                    if($campaign->sms_type==1)
                    {
                        //normal SMS
                        $messageSizeInfo = messgeLenght($message_type, $message);

                        //file number
                        $csvFileData = Excel::toArray(new CampaignImport(), $file_path);
                        $file_numbers = $csvFileData[0];

                        //combine array
                        $numberFlatten = collect([$file_numbers]);
                        $all_numbers = $numberFlatten->flatten()->toArray();

                        //Removed duplicate numbers
                        $all_numbers = array_unique(preg_replace('/\s+/', '', $all_numbers), SORT_REGULAR);

                        //blacklist compare
                        $actualNumberForSend = array_diff($all_numbers, $blacklist);
                        $blackListNumber = array_intersect($all_numbers, $blacklist);
                        
                        $actualNumberForSend = collect($actualNumberForSend);
                        $chunks = $actualNumberForSend->chunk(env('CHUNK_SIZE', 1000));
                        $invalidNumber = 0;
                        $creditRevInvalid = 0;
                        $countBlankRow = 0;

                        //For Two way SMS
                        if($campaign->route_type==3)
                        {
                            $two_way_comms = \DB::table(env('DB_DATABASE2W').'.two_way_comms')
                                ->where('parent_id', $campaign->parent_id)
                                ->orderBy('id','ASC')
                                ->get();

                            $two_way_comms_arr = $two_way_comms->flatten()->toArray();
                        }

                        foreach ($chunks->toArray() as $chunk) 
                        {
                            $campaignData = [];
                            $kannelData = [];
                            $applyRatio = applyRatio($ratio_percent_set, count($chunk));

                            //failed Ratio Set
                            $totalFailedNumbers = round(((sizeof($applyRatio) * $failed_ratio)/100), 0);
                            $failedIndex = [];
                            if($totalFailedNumbers>1)
                            {
                                $failedIndex = array_rand($applyRatio, $totalFailedNumbers);
                            }

                            $count = 1;
                            $countFailed = 0;
                            foreach ($chunk as $number) 
                            {
                                $getPRInfo = getRandomSingleArray($associated_routes);
                                $primary_route_id = $getPRInfo['key'];
                                $smsc_id = $getPRInfo['value'];

                                if(!empty($number))
                                {
                                    $number = preg_replace('/\s+/', '', $number);
                                    $currentArrKey = $count;
                                    $isRatio = false;
                                    $isFailedRatio = false;
                                    if(in_array($currentArrKey, $applyRatio))
                                    {
                                        $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                        $isRatio = true;
                                        //failed ratio
                                        if(in_array($countFailed, $failedIndex))
                                        {
                                            $failedIndex = array_diff($failedIndex, [$countFailed]);
                                            $isFailedRatio = true;
                                        }
                                        $countFailed++;
                                    }
                                    $checkNumberValid = checkNumberValid($number, $isRatio, $invalidSeries);
                                    $unique_key = uniqueKey();
                                    $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);

                                    //For two way SMS
                                    if($campaign->route_type==3)
                                    {
                                        //Create Unique Link
                                        $actualArrayNumber = [];
                                        $createUniqueLink  = [];
                                        $newKeyword        = [];
                                        $message = $campaign->message;
                                        foreach (explode('{{', $message) as $key => $value) 
                                        {
                                            $getNumberOfLink = getStringBetweenTwoWord($value, '2way-sms-link-', '}}');
                                            //Actual array number (number - 1)
                                            if((int) $getNumberOfLink>0)
                                            {
                                                $arrkey = array_search($getNumberOfLink, array_column($two_way_comms_arr, 'id'));
                                                $actualArrayNumber[] = ($getNumberOfLink);
                                                $two_way_comm = @$two_way_comms[$arrkey];
                                                if($two_way_comm) 
                                                {
                                                    $createUniqueLink[] = createUniqueLink($two_way_comm->id, $two_way_comm->content_expired, $checkNumberValid['mobile_number'], $campaign->id, $campaign->parent_id);
                                                }
                                            }
                                        }

                                        //add link in message
                                        if(isset($createUniqueLink) && is_array($createUniqueLink) && count($createUniqueLink) > 0)
                                        {
                                            foreach ($createUniqueLink as $key => $addCode) {
                                                $newKeyword['{{2way-sms-link-'.$addCode->two_way_comm_id.'}}'] = $addCode['code'];
                                            }
                                        }
                                        $msg = strReplaceAssoc($newKeyword, $message);
                                        $messageSizeInfo = messgeLenght($message_type, $msg);
                                        $message = $msg;
                                    }

                                    $campaignData[] = [
                                        'send_sms_id' => $campaign->id,
                                        'primary_route_id' => $primary_route_id,
                                        'unique_key' => $unique_key,
                                        'mobile' => $checkNumberValid['mobile_number'],
                                        'message' => $message,
                                        'use_credit' => $messageSizeInfo['message_credit_size'],
                                        'is_auto' => $is_auto,
                                        'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                                        'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                                        'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                                        'created_at' => Carbon::now()->toDateTimeString(),
                                        'updated_at' => Carbon::now()->toDateTimeString(),
                                    ];
                                }
                                else
                                {
                                    $countBlankRow++;
                                }
                                    
                                if($checkNumberValid['is_auto']==0 && $checkNumberValid['number_status']==1)
                                {
                                    $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                                    $kannelData[] = [
                                        'momt' => 'MT',
                                        'sender' => $sender_id,
                                        'receiver' => $checkNumberValid['mobile_number'],
                                        'msgdata' => urlencode($message),
                                        'smsc_id' => $smsc_id,
                                        'mclass' => $kannelPara['mclass'],
                                        'coding' => $kannelPara['coding'],
                                        'dlr_mask' => $kannelPara['dlr_mask'],
                                        'dlr_url' => $dlr_url,
                                        'charset' => 'UTF-8',
                                        'boxc_id' => kannelSmsbox(),
                                        'meta_data' => $meta_data,
                                        'priority' => $priority,
                                        'sms_type' => null,
                                        'binfo' => $unique_key,
                                    ];
                                }
                                
                                if($checkNumberValid['number_status']==0)
                                {
                                    $invalidNumber += 1;
                                    $creditRevInvalid += $messageSizeInfo['message_credit_size'];
                                }
                                $count++;
                            }
                            executeQuery($campaignData);
                            if(count($kannelData)>0)
                            {
                                executeKannelQuery($kannelData);
                            }
                            $campaignData = [];
                            $kannelData = [];
                            usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                        }

                        //black list number import
                        if(count($blackListNumber)>0)
                        {
                            foreach ($blackListNumber as $key => $blacklist) {
                                $unique_key = uniqueKey();
                                $campaignData[] = [
                                    'send_sms_id' => $campaign->id,
                                    'primary_route_id' => $secondaryRouteInfo->primary_route_id,
                                    'unique_key' => $unique_key,
                                    'mobile' => $blacklist,
                                    'message' => $message,
                                    'use_credit' => $messageSizeInfo['message_credit_size'],
                                    'is_auto' => 0,
                                    'status' => 'Completed',
                                    'stat' => 'BLACK',
                                    'err' => 'XX1',
                                    'created_at' => Carbon::now()->toDateTimeString(),
                                    'updated_at' => Carbon::now()->toDateTimeString(),
                                ];
                            }
                            executeQuery($campaignData);
                            usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                        }
                        

                        //finally update status
                        if($countBlankRow>0)
                        {
                            $total_contacts = $total_contacts - $countBlankRow;
                            $campaign->total_contacts = $total_contacts;
                        }

                        $campaign->total_block_number = count($blackListNumber);
                        $campaign->total_invalid_number = $invalidNumber;

                        $actual_total_credit_used = ($total_contacts * $messageSizeInfo['message_credit_size']);

                        $total_invalid_black_blank = $creditRevInvalid + (count($blackListNumber) * $messageSizeInfo['message_credit_size']) + ($countBlankRow * $messageSizeInfo['message_credit_size']);

                        $totalCreditBack = (($campaign->total_credit_deduct + ($total_invalid_black_blank)) - $actual_total_credit_used);

                        $campaign->total_credit_deduct = $actual_total_credit_used - $total_invalid_black_blank;
                        $campaign->status = 'Ready-to-complete';
                        $campaign->save();

                        //Credit Back
                        if($totalCreditBack>0)
                        {
                            $log_type = ($campaign->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                            creditLog($campaign->user_id, 1, $campaign->route_type, 1, $totalCreditBack, null, $log_type, 'Black list and Invalid numbers credit reversed');

                            creditAdd($campaign->user, $campaign->route_type, $totalCreditBack);
                        }
                        elseif($totalCreditBack<=0)
                        {
                            $log_type = ($campaign->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                            creditLog($campaign->user_id, 1, $campaign->route_type, 2, abs($totalCreditBack), null, $log_type, 'Black list and Invalid numbers credit reversed and balance adjust according to your message size.');

                            creditDeduct($campaign->user, $campaign->route_type, abs($totalCreditBack));
                        }

                        // finally update number of records inserted in database (queues table)
                        $recheckEntry = \DB::table('send_sms_queues')
                            ->select(DB::raw('COUNT(id) as total_inserted'), DB::raw('SUM(use_credit) as total_credit_deduct'))
                            ->where('send_sms_id', $campaign->id)
                            ->first();
                        if(($campaign->total_contacts != $recheckEntry->total_inserted) && (!empty($recheckEntry->total_credit_deduct)))
                        {
                            // recheck if credit is deducted more
                            $reurnCredit = $campaign->total_credit_deduct - $recheckEntry->total_credit_deduct;

                            creditLog($campaign->user_id, 1, $campaign->route_type, 1, $reurnCredit, null, $log_type, 'Credit adjusted because more credit is deducted.');

                            creditAdd($campaign->user, $campaign->route_type, $reurnCredit);


                            $campaign->total_contacts = $recheckEntry->total_inserted;
                            $campaign->total_credit_deduct = $recheckEntry->total_credit_deduct;
                            $campaign->save();
                        }

                    }
                    elseif($campaign->sms_type==2)
                    {
                        //custom SMS campaign
                        //file number
                        $csvFileData = Excel::toArray(new CampaignImport(), $file_path);
                        $file_numbers = $csvFileData[0];
                        $total_contacts = count($file_numbers);

                        //get all numbers
                        $numbers = array_column($file_numbers, $file_mobile_field_name);

                        $actualNumberForSend = array_filter($file_numbers, function($var) use ($blacklist, $file_mobile_field_name){
                            return (!in_array($var[$file_mobile_field_name], $blacklist));
                        });

                        $blackListNumber = array_filter($file_numbers, function($var) use ($blacklist, $file_mobile_field_name){
                            return (in_array($var[$file_mobile_field_name], $blacklist));
                        });
                        
                        $actualNumberForSend = collect($actualNumberForSend);
                        $chunks = $actualNumberForSend->chunk(env('CHUNK_SIZE', 1000));

                        $invalidNumber = 0;
                        $creditRevInvalid = 0;

                        $total_block_number = 0;
                        $total_black_list_credit = 0;

                        //readFile
                        foreach ($chunks->toArray() as $key => $chunk) 
                        {
                            $campaignData = [];
                            $kannelData = [];
                            $applyRatio = applyRatio($ratio_percent_set, count($chunk));

                            //failed Ratio Set
                            $totalFailedNumbers = round(((sizeof($applyRatio) * $failed_ratio)/100), 0);
                            $failedIndex = [];
                            if($totalFailedNumbers>1)
                            {
                                $failedIndex = array_rand($applyRatio, $totalFailedNumbers);
                            }

                            $count = 1;
                            $countFailed = 0;
                            //\Log::info($chunk);
                            foreach ($chunk as $nkey => $arrVal) 
                            {
                                $getPRInfo = getRandomSingleArray($associated_routes);
                                $primary_route_id = $getPRInfo['key'];
                                $smsc_id = $getPRInfo['value'];

                                //\Log::info($nkey);
                                $number = $arrVal[$file_mobile_field_name];
                                if(!empty($number))
                                {
                                    $number = preg_replace('/\s+/', '', $number);
                                    $currentArrKey = $count;
                                    $isRatio = false;
                                    $isFailedRatio = false;
                                    if(in_array($currentArrKey, $applyRatio))
                                    {
                                        $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                        $isRatio = true;

                                        //failed ratio
                                        if(in_array($countFailed, $failedIndex))
                                        {
                                            $failedIndex = array_diff($failedIndex, [$countFailed]);
                                            $isFailedRatio = true;
                                        }
                                        $countFailed++;
                                    }
                                    $checkNumberValid = checkNumberValid($number, $isRatio, $invalidSeries);
                                    $unique_key = uniqueKey();
                                    $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);
                                    $excalRow = $chunk[$nkey];
                                    $changeArray    = [];
                                    $newKeyword     = [];
                                    if(isset($changeArray) && is_array($changeArray) && count($changeArray) < 1)
                                    {
                                        foreach ($excalRow as $word => $addCurlyBraces) {
                                            $newKeyword['{{'.$word.'}}'] = $addCurlyBraces;
                                        }
                                    }
                                    $message = strReplaceAssoc($newKeyword, $campaign->message);
                                    $messageSizeInfo = messgeLenght($message_type, $message);

                                    $campaignData[] = [
                                        'send_sms_id' => $campaign->id,
                                        'primary_route_id' => $primary_route_id,
                                        'unique_key' => $unique_key,
                                        'mobile' => $checkNumberValid['mobile_number'],
                                        'message' => $message,
                                        'use_credit' => $messageSizeInfo['message_credit_size'],
                                        'is_auto' => $is_auto,
                                        'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                                        'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                                        'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                                        'created_at' => Carbon::now()->toDateTimeString(),
                                        'updated_at' => Carbon::now()->toDateTimeString(),
                                    ];

                                    if($checkNumberValid['is_auto']==0 && $checkNumberValid['number_status']==1)
                                    {
                                        $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                                        $kannelData[] = [
                                            'momt' => 'MT',
                                            'sender' => $sender_id,
                                            'receiver' => $checkNumberValid['mobile_number'],
                                            'msgdata' => urlencode($message),
                                            'smsc_id' => $smsc_id,
                                            'mclass' => $kannelPara['mclass'],
                                            'coding' => $kannelPara['coding'],
                                            'dlr_mask' => $kannelPara['dlr_mask'],
                                            'dlr_url' => $dlr_url,
                                            'charset' => 'UTF-8',
                                            'boxc_id' => 'smsbox',
                                            'meta_data' => $meta_data,
                                            'priority' => $priority,
                                            'sms_type' => null,
                                            'binfo' => $unique_key,
                                        ];
                                    }
                                }
                                else
                                {
                                    $countBlankRow++;
                                }

                                if($checkNumberValid['number_status']==0)
                                {
                                    $invalidNumber += 1;
                                    $creditRevInvalid += $messageSizeInfo['message_credit_size'];
                                }
                                $count++;
                            }

                            executeQuery($campaignData);
                            if(count($kannelData)>0)
                            {
                                executeKannelQuery($kannelData);
                            }
                            $campaignData = [];
                            $kannelData = [];
                            usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                        }

                        //black list number import
                        if(count($blackListNumber)>0)
                        {
                            foreach ($blackListNumber as $key => $blacklist) {
                                $number = $blacklist[$file_mobile_field_name];
                                $unique_key = uniqueKey();

                                $excalRow = $blackListNumber[$key];
                                $changeArray    = [];
                                $newKeyword     = [];
                                if(isset($changeArray) && is_array($changeArray) && count($changeArray) < 1)
                                {
                                    foreach ($excalRow as $word => $addCurlyBraces) {
                                        $newKeyword['{{'.$word.'}}'] = $addCurlyBraces;
                                    }
                                }
                                $message = strReplaceAssoc($newKeyword, $campaign->message);
                                $messageSizeInfo = messgeLenght($message_type, $message);

                                $campaignData[] = [
                                    'send_sms_id' => $campaign->id,
                                    'primary_route_id' => $secondaryRouteInfo->primary_route_id,
                                    'unique_key' => $unique_key,
                                    'mobile' => $number,
                                    'message' => $message,
                                    'use_credit' => $messageSizeInfo['message_credit_size'],
                                    'is_auto' => 0,
                                    'status' => 'Completed',
                                    'stat' => 'BLACK',
                                    'err' => 'XX1',
                                    'created_at' => Carbon::now()->toDateTimeString(),
                                    'updated_at' => Carbon::now()->toDateTimeString(),
                                ];

                                $total_black_list_credit += $messageSizeInfo['message_credit_size'];
                            }
                            executeQuery($campaignData);
                            usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                        }

                        
                        //finally update status
                        if($countBlankRow>0)
                        {
                            $campaign->total_contacts = $campaign->total_contacts - $countBlankRow;
                        }
                        $campaign->total_block_number = count($blackListNumber);
                        $campaign->total_invalid_number = $invalidNumber;

                        $totalCreditBack = ($creditRevInvalid + $total_black_list_credit + ($countBlankRow * $campaign->message_credit_size));

                        $campaign->total_credit_deduct = $campaign->total_credit_deduct - ($totalCreditBack);
                        $campaign->save();

                        //credit reverse blacklist number
                        if($totalCreditBack>0)
                        {
                            $userInfo = $campaign->user;
                            if($userInfo && $totalCreditBack>0)
                            {
                                $log_type = ($campaign->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                                creditLog($userInfo->id, 1, $campaign->route_type, 1, $totalCreditBack, null, $log_type, 'Black list and Invalid numbers credit reversed:custom SMS');

                                creditAdd($userInfo, $campaign->route_type, $totalCreditBack);
                            }
                        }

                        // finally update number of records inserted in database (queues table)
                        $recheckEntry = \DB::table('send_sms_queues')
                            ->select(DB::raw('COUNT(id) as total_inserted'), DB::raw('SUM(use_credit) as total_credit_deduct'))
                            ->where('send_sms_id', $campaign->id)
                            ->first();
                        if(($campaign->total_contacts != $recheckEntry->total_inserted) && (!empty($recheckEntry->total_credit_deduct)))
                        {
                            // recheck if credit is deducted more
                            $reurnCredit = $campaign->total_credit_deduct - $recheckEntry->total_credit_deduct;

                            creditLog($campaign->user_id, 1, $campaign->route_type, 1, $reurnCredit, null, @$log_type, 'Credit adjusted because more credit is deducted.');

                            creditAdd($campaign->user, $campaign->route_type, $reurnCredit);


                            $campaign->total_contacts = $recheckEntry->total_inserted;
                            $campaign->total_credit_deduct = $recheckEntry->total_credit_deduct;
                            $campaign->save();
                        }
                    }
                }
                else
                {
                    //Reschedule campaign check
                    if(!empty($campaign->reschedule_send_sms_id))
                    {           
                        $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;
                        //now copy records
                        $priority = $campaign->priority;
                        $sender_id = $campaign->sender_id;
                        if($campaign->reschedule_type!='ALL')
                        {
                            $queue = $campaign->repushSendSmsQueues()->select(DB::raw('COUNT(id) as total_queue_number'), DB::raw('SUM(use_credit) as total_queue_credit_used'))
                            ->where('stat', $campaign->reschedule_type)
                            ->first();
                            $total_queue_number = $queue->total_queue_number;
                            $total_queue_credit_used = $queue->total_queue_credit_used;
                            $history = $campaign->repushSendSmsHistories()->select(DB::raw('COUNT(id) as total_history_number'), DB::raw('SUM(use_credit) as total_history_credit_used'))
                            ->where('stat', $campaign->reschedule_type)
                            ->first();
                            $total_history_number = $history->total_history_number;
                            $total_history_credit_used = $history->total_history_credit_used;

                            $total_contacts = $total_queue_number + $total_history_number;
                            $total_credit_used = $total_queue_credit_used + $total_history_credit_used;
                            if($total_contacts > $campaign->total_contacts)
                            {
                                //remainig credit deduct
                                $remainingCredit = $total_credit_used - $campaign->total_credit_deduct;
                                if($remainingCredit > 0)
                                {
                                    $userInfo = User::find($campaign->user_id);

                                    $campaign->total_contacts = $total_contacts;
                                    $campaign->total_credit_deduct = $total_credit_used;
                                    $campaign->save();

                                    $log_type = ($campaign->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                                    // Generate credit log
                                    $creditLog = creditLog($userInfo->id, 1, $campaign->route_type, 2, $remainingCredit, null, $log_type,'Credit campaign adjust');

                                    //credit deduct
                                    $creditDeduct = creditDeduct($userInfo, $campaign->route_type, $remainingCredit);
                                }
                            }

                            DB::statement("INSERT send_sms_queues
                            SELECT null, $campaign->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_histories WHERE `send_sms_id` = $campaign->reschedule_send_sms_id AND `stat` = '".$campaign->reschedule_type."';");

                            DB::statement("INSERT send_sms_queues
                            SELECT null, $campaign->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_queues WHERE `send_sms_id` = $campaign->reschedule_send_sms_id AND `stat` = '".$campaign->reschedule_type."';");
                        }
                        else
                        {
                            DB::statement("INSERT send_sms_queues
                            SELECT null, $campaign->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_histories WHERE `send_sms_id` = $campaign->reschedule_send_sms_id AND `stat` NOT IN ('Black','invalid');");

                            DB::statement("INSERT send_sms_queues
                            SELECT null, $campaign->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_queues WHERE `send_sms_id` = $campaign->reschedule_send_sms_id AND `stat` NOT IN ('Black','invalid');");
                        }

                        //now insert kannel table
                        $queue = SendSmsQueue::where('send_sms_id', $campaign->id)
                        ->chunk(env('CHUNK_SIZE', 1000), function ($records) use ($kannelPara, $meta_data, $kannel_ip, $node_port, $smsc_id, $priority, $sender_id, $ratio_percent_set, $failed_ratio, $wh_url)
                        {
                            $kannelData = [];
                            $applyRatio = applyRatio($ratio_percent_set, count($records));

                            //failed Ratio Set
                            $totalFailedNumbers = round(((sizeof($applyRatio) * $failed_ratio)/100), 0);
                            $failedIndex = [];
                            if($totalFailedNumbers>1)
                            {
                                $failedIndex = array_rand($applyRatio, $totalFailedNumbers);
                            }

                            $count = 1;
                            $countFailed = 0;
                            foreach ($records as $rec) 
                            {
                                $currentArrKey = $count;
                                $isNotInRatio = true;
                                if(in_array($currentArrKey, $applyRatio))
                                {
                                    $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                    //failed ratio
                                    if(in_array($countFailed, $failedIndex))
                                    {
                                        $failedIndex = array_diff($failedIndex, [$countFailed]);
                                        $rec->is_auto = 2; //for failed
                                    }
                                    else
                                    {
                                        $rec->is_auto = 1; //for delivered
                                    }
                                    $countFailed++;
                                    $isNotInRatio = false;
                                    $rec->save();
                                }

                                if($isNotInRatio)
                                {
                                    $unique_key = $rec->unique_key;
                                    $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                                    $kannelData[] = [
                                        'momt' => 'MT',
                                        'sender' => $sender_id,
                                        'receiver' => $rec->mobile,
                                        'msgdata' => urlencode($rec->message),
                                        'smsc_id' => $smsc_id,
                                        'mclass' => $kannelPara['mclass'],
                                        'coding' => $kannelPara['coding'],
                                        'dlr_mask' => $kannelPara['dlr_mask'],
                                        'dlr_url' => $dlr_url,
                                        'charset' => 'UTF-8',
                                        'boxc_id' => kannelSmsbox(),
                                        'meta_data' => $meta_data,
                                        'priority' => $priority,
                                        'sms_type' => null,
                                        'binfo' => $unique_key,
                                    ];
                                }
                                $count++;
                            }
                            executeKannelQuery($kannelData);
                            $kannelData = [];
                        });
                        Log::channel('import')->error('Campaign imported successfully, send_sms_id:'.$campaign->id);
                    }
                    else
                    {
                        Log::channel('import')->error('File OR Reschedule campaign ID not found, Please check send_sms_id:'.$campaign->id);
                    }
                }
            }
        }

        /*-------------------Voice SMS Start-------------------*/
        $executeVoiceCampaigns = CampaignExecuter::select('id', 'send_sms_id')
            ->where('campaign_type', 4)
            ->where('campaign_send_date_time','<=', $dateTime)
            ->get();
        foreach ($executeVoiceCampaigns as $key => $executeVoiceCampaign) 
        {
            $campaignInfo = $executeVoiceCampaign;
            $executeVoiceCampaign->delete();

            $route_type = 4;
            $voiceSMS = VoiceSms::select('id','parent_id','user_id','campaign_id','campaign','obd_type','dtmf','call_patch_number','secondary_route_id','voice_upload_id','voice_id','voice_file_path','file_path','file_mobile_field_name','is_read_file_path','campaign_send_date_time','total_credit_deduct','is_campaign_scheduled','priority','message_credit_size','total_contacts','ratio_percent_set','failed_ratio')
            ->find($campaignInfo->send_sms_id);

            if($campaign->campaign=='API')
            {
                //userInfo
                $userInfo = userInfo($voiceSMS->user_id);
                $wh_url = $userInfo->webhook_callback_url;
            }
            else
            {
                // webhook only for api request now so we comment this line and pass null value.
                //userInfo
                // $userInfo = userInfo($voiceSMS->user_id);
                // $wh_url = $userInfo->webhook_callback_url;
                $wh_url = null;
            }
            
            
            if($voiceSMS && recheckVoiceCampaignStatusB4Process($voiceSMS->id))
            {
                //update read file status
                $voiceSMS->is_read_file_path = 1;
                $voiceSMS->status = 'Ready-to-complete';
                $voiceSMS->save();

                //Invalid Series
                $invalidSeries = \DB::table('invalid_series')
                ->pluck('start_with')
                ->toArray();

                $total_contacts = $voiceSMS->total_contacts;
                $ratio_percent_set = $voiceSMS->ratio_percent_set;
                $failed_ratio = ($ratio_percent_set > 0) ? $voiceSMS->failed_ratio : null;

                $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->find($voiceSMS->secondary_route_id);
                $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;

                //get associated routes (gateway)
                $associated_routes = \DB::table('primary_route_associateds')
                    ->join('primary_routes', 'primary_routes.id', '=', 'primary_route_associateds.associted_primary_route')
                    ->where('primary_route_id', $secondaryRouteInfo->primary_route_id)
                    ->pluck('smsc_id', 'id')
                    ->toArray();
                if(sizeof($associated_routes)<1)
                {
                    $associated_routes = [$secondaryRouteInfo->primary_route_id => $secondaryRouteInfo->primaryRoute->smsc_id];
                }

                $file_path = $actDir.$voiceSMS->file_path;
                if(file_exists($file_path) && !empty($voiceSMS->file_path))
                {
                    $blacklist = \DB::table('blacklists')
                        ->select('mobile_number')
                        ->where('user_id', $voiceSMS->user_id)
                        ->pluck('mobile_number')
                        ->toArray();

                    $file_mobile_field_name = $voiceSMS->file_mobile_field_name;
                    
                    $voice_id = $voiceSMS->voice_id;
                    $priority = $voiceSMS->priority;

                    $voiceLenghtCredit = voiceLenghtCredit($voiceSMS->voiceUpload->file_time_duration);

                    //file number
                    $csvFileData = Excel::toArray(new CampaignImport(), $file_path);
                    $file_numbers = $csvFileData[0];

                    //combine array
                    $numberFlatten = collect([$file_numbers]);
                    $all_numbers = $numberFlatten->flatten()->toArray();

                    //Removed duplicate numbers
                    $all_numbers = array_unique(preg_replace('/\s+/', '', $all_numbers), SORT_REGULAR);

                    //blacklist compare
                    $actualNumberForSend = array_diff($all_numbers, $blacklist);
                    $blackListNumber = array_intersect($all_numbers, $blacklist);
                    
                    $actualNumberForSend = collect($actualNumberForSend);
                    $chunks = $actualNumberForSend->chunk(env('VOICE_CHUNK_SIZE', 25000));
                    $invalidNumber = 0;
                    $creditRevInvalid = 0;
                    $countBlankRow = 0;

                    foreach ($chunks->toArray() as $chunk) 
                    {
                        $campaignData = [];
                        $voiceSendData = [];
                        $applyRatio = applyRatio($ratio_percent_set, count($chunk));

                        //failed Ratio Set
                        $totalFailedNumbers = round(((sizeof($applyRatio) * $failed_ratio)/100), 0);
                        $failedIndex = [];
                        if($totalFailedNumbers>1)
                        {
                            $failedIndex = array_rand($applyRatio, $totalFailedNumbers);
                        }
                        $count = 1;
                        $countFailed = 0;
                        foreach ($chunk as $number) 
                        {
                            $getPRInfo = getRandomSingleArray($associated_routes);
                            $primary_route_id = $getPRInfo['key'];
                            $smsc_id = $getPRInfo['value'];

                            if(!empty($number))
                            {
                                $number = preg_replace('/\s+/', '', $number);
                                $currentArrKey = $count;
                                $isRatio = false;
                                $isFailedRatio = false;
                                if(in_array($currentArrKey, $applyRatio))
                                {
                                    $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                    $isRatio = true;

                                    //failed ratio
                                    if(in_array($countFailed, $failedIndex))
                                    {
                                        $failedIndex = array_diff($failedIndex, [$countFailed]);
                                        $isFailedRatio = true;
                                    }
                                    $countFailed++;
                                }

                                $checkNumberValid = checkVoiceNumberValid($number, $isRatio, $invalidSeries);
                                $unique_key = uniqueKey();
                                $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);

                                $campaignData[] = [
                                    'voice_sms_id' => $voiceSMS->id,
                                    'primary_route_id' => $primary_route_id,
                                    'unique_key' => $unique_key,
                                    'mobile' => $checkNumberValid['mobile_number'],
                                    'voice_id' => $voice_id,
                                    'use_credit' => $voiceLenghtCredit,
                                    'is_auto' => $is_auto,
                                    'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                                    'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                                    'submit_date' => Carbon::now()->toDateTimeString(),
                                    'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                                    'created_at' => Carbon::now()->toDateTimeString(),
                                    'updated_at' => Carbon::now()->toDateTimeString(),
                                ];
                            }
                            else
                            {
                                $countBlankRow++;
                            }
                                
                            if(@$checkNumberValid['is_auto']==0 && @$checkNumberValid['number_status']==1)
                            {
                                //Send Voice final data to the Api
                                $voiceSendData[] = @$checkNumberValid['mobile_number'];
                            }
                            
                            if(@$checkNumberValid['number_status']==0)
                            {
                                $invalidNumber += 1;
                                $creditRevInvalid += $voiceLenghtCredit;
                            }
                            $count++;
                        }
                        executeVoiceQuery($campaignData);
                        if(count($voiceSendData)>0)
                        {
                            $response = sendVoiceSMSApi($voiceSendData, $secondaryRouteInfo->primaryRoute, $voiceSMS);
                            if(!$response || $response==false)
                            {
                                \Log::error('obd_type not matched. campaign ID is: '.$voiceSMS->id);
                                $voiceSMS->status = 'Stop';
                                $voiceSMS->save();
                                return response()->json(prepareResult(true, [], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                            }
                            $voiceSMS->campaign_id = $response['CAMPG_ID'];
                            $voiceSMS->transection_id = @$response['TRANS_ID'];
                            $voiceSMS->save();
                        }
                        $campaignData = [];
                        $voiceSendData = [];
                        usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                    }

                    //black list number import
                    if(count($blackListNumber)>0)
                    {
                        foreach ($blackListNumber as $key => $blacklist) {
                            $unique_key = uniqueKey();
                            $campaignData[] = [
                                'voice_sms_id' => $voiceSMS->id,
                                'primary_route_id' => $secondaryRouteInfo->primary_route_id,
                                'unique_key' => $unique_key,
                                'mobile' => $blacklist,
                                'voice_id' => $voice_id,
                                'use_credit' => $voiceLenghtCredit,
                                'is_auto' => 0,
                                'status' => 'Completed',
                                'stat' => 'BLACK',
                                'err' => 'XX1',
                                'submit_date' => Carbon::now()->toDateTimeString(),
                                'created_at' => Carbon::now()->toDateTimeString(),
                                'updated_at' => Carbon::now()->toDateTimeString(),
                            ];
                        }
                        executeVoiceQuery($campaignData);
                        usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                    }

                    
                    //finally update status
                    if($countBlankRow>0)
                    {
                        $voiceSMS->total_contacts = $voiceSMS->total_contacts - $countBlankRow;
                    }

                    $voiceSMS->total_block_number = count($blackListNumber);
                    $voiceSMS->total_invalid_number = $invalidNumber;

                    $actual_total_credit_used = ($total_contacts * $voiceLenghtCredit);

                    $total_invalid_black_blank = $creditRevInvalid + (count($blackListNumber) * $voiceLenghtCredit) + ($countBlankRow * $voiceLenghtCredit);

                    $totalCreditBack = (($voiceSMS->total_credit_deduct + ($total_invalid_black_blank)) - $actual_total_credit_used);

                    $voiceSMS->total_credit_deduct = $actual_total_credit_used - $total_invalid_black_blank;
                    $voiceSMS->status = 'Ready-to-complete';
                    $voiceSMS->save();

                    //Credit Back
                    if($totalCreditBack>0)
                    {
                        $log_type = ($voiceSMS->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                        creditLog($voiceSMS->user_id, 1, $route_type, 1, $totalCreditBack, null, $log_type, 'Black list and Invalid numbers credit reversed');

                        creditAdd($voiceSMS->user, $route_type, $totalCreditBack);
                    }
                    elseif($totalCreditBack<0)
                    {
                        $log_type = ($voiceSMS->campaign==env('DEFAULT_API_CAMPAIGN_NAME', 'API')) ? 1 : 2;
                        creditLog($voiceSMS->user_id, 1, $route_type, 2, abs($totalCreditBack), null, $log_type, 'Black list and Invalid numbers credit reversed and balance adjust according to your message size.');

                        creditDeduct($voiceSMS->user, $route_type, abs($totalCreditBack));
                    }
                }
            }
        }        
        return true;
    }
}
