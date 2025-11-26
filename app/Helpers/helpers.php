<?php
use App\Models\User;
use App\Models\Appsetting;
use App\Models\CreditLog;
use App\Events\EventNotification;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\SendSms;
use App\Models\CampaignExecuter;
use App\Models\SendSmsQueue;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\IpWhiteListForApi;
use App\Models\OauthAccessTokens;
use App\Models\VoiceUploadSentGateway;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateButton;
use App\Models\ShortLink;
use App\Models\UserDevice;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppCharge;
use App\Mail\CommonMail;
use Carbon\Carbon;
use Edujugon\PushNotification\PushNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

function prepareResult($error, $data, $msg, $intime=null)
{
    return [
        'error' => $error,
        'intime' => $intime,
        'outtime' => Carbon::now(), 
        'message' => $msg,
        'data' => $data
    ];
}

function apiPrepareResult($status, $data, $msg, $errors, $intime=null, $error_sys_code=null)
{
    return [
        'status' => $status, 
        'intime' => $intime,
        'outtime' => Carbon::now(), 
        'message' => $msg,
        'data' => (object) $data,
        'errors' => (object) $errors,
        'error_code' => $error_sys_code
    ];
}

function timeDiff($time)
{
    return strtotime($time) - time();
}

function strReplaceAssoc(array $replace, $subject) 
{ 
    return str_replace(array_keys($replace), array_values($replace), $subject);
}

function getRandomSingleArray($array)
{
    $key = array_rand($array);
    $value = $array[$key];
    $return = [
        'key' => $key,
        'value' => $value,
    ];
    return $return;
}

function generateStrongPassword($length = 12)
{
    $upper    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower    = 'abcdefghijklmnopqrstuvwxyz';
    $numbers  = '0123456789';
    $symbols  = '!@#$%^&*()-_=+[]{}<>?/';

    // Ensure at least one of each character type
    $password = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $numbers[random_int(0, strlen($numbers) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    // Fill the rest with random characters from all sets
    $all = $upper . $lower . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }

    // Shuffle to avoid predictable character placement
    shuffle($password);

    return implode('', $password);
}

function sanitizeFileName($fileName) {
    // Remove any special characters and replace spaces with hyphens
    $sanitizedFileName = preg_replace('/[^A-Za-z0-9-]/', '', str_replace(' ', '-', $fileName));
    
    // Remove multiple consecutive hyphens
    $sanitizedFileName = preg_replace('/-{2,}/', '-', $sanitizedFileName);
    
    // Lowercase the file name
    $sanitizedFileName = strtolower($sanitizedFileName);

    return $sanitizedFileName;

    /*
    $fileName = $file->getClientOriginalName();
    $sanitizedFileName = sanitizeFileName($fileName);
    */
}

function uniqueKey()
{
    return time().rand(10,99).rand(1,9999999);
}

function generateToken($len=28, $type='number')
{
    if($type=='number')
    {
        $token = '19'.time().str_shuffle("1234567");
        return $token;
    }
    $shuffle = str_shuffle("FAKE");
    $token = strtoupper(Str::random($len)).$shuffle;
    return str_replace('-', '', $token);
}

function number_format_ind($number)
{     
    $decimal = (string)($number - floor($number));
    $money = floor($number);
    $length = strlen($money);
    $delimiter = '';
    $money = strrev($money);

    for($i=0;$i<$length;$i++){
        if(( $i==3 || ($i>3 && ($i-1)%2==0) )&& $i!=$length){
            $delimiter .=',';
        }
        $delimiter .=$money[$i];
    }

    $result = strrev($delimiter);
    $decimal = preg_replace("/0\./i", ".", $decimal);
    $decimal = substr($decimal, 0, 3);

    if( $decimal != '0'){
        $result = $result.$decimal;
    }

    return $result;
}

function errorCode()
{  
    $errCode = array('1','160','172','173','12','36','40','60');
    shuffle($errCode);
    return sprintf('%03d', $errCode[1]);
}

function loggedInUserType()
{
    return auth()->user()->userType;
}

function userInfo($user_id=null)
{
    $userId = !empty($user_id) ? $user_id : auth()->id();
    return User::find($userId);
}

function sumByKey($data) 
{
    $groups = array();
    foreach ($data as $item) 
    {
        $key = $item['difference_in_seconds'];
        if($key>=0)
        {
            if (!array_key_exists($key, $groups)) {
                $groups[$key] = array(
                    'difference_in_seconds' => $item['difference_in_seconds'],
                    'total_delivered' => $item['total_delivered'],
                );
            } else {
                $groups[$key]['total_delivered'] = $groups[$key]['total_delivered'] + $item['total_delivered'];
            }
        }
    }
    return $groups;
}

function matchContentPercentage($templateMessage, $messageContent)
{
    $similar = similar_text($templateMessage, $messageContent, $percentage);
    return round($percentage, 2);
}

function getRatio($user_id, $sms_type)
{
    $obj = [
       'speedRatio' => 0,
       'speedFRatio' => 0
    ];
    $getrecord = \DB::table('speed_ratios')->where('user_id', $user_id)->first();
    if(!$getrecord)
    {
        return $obj;
    }

    //1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms, 5:WhatsApp
    switch ($sms_type) {
        case '1':
            $speedRatio = $getrecord->trans_text_sms;
            $speedFRatio = $getrecord->trans_text_f_sms;
            break;
        case '2':
            $speedRatio = $getrecord->promo_text_sms;
            $speedFRatio = $getrecord->promo_text_f_sms;
            break;
        case '3':
            $speedRatio = $getrecord->two_way_sms;
            $speedFRatio = $getrecord->two_way_f_sms;
            break;
        case '4':
            $speedRatio = $getrecord->voice_sms;
            $speedFRatio = $getrecord->voice_f_sms;
            break;
        case '5':
            $speedRatio = $getrecord->whatsapp_sms;
            $speedFRatio = $getrecord->whatsapp_f_sms;
            break;
        default:
            $speedRatio = 0;
            $speedFRatio = 0;
            break;
    }
    $obj = [
       'speedRatio' => $speedRatio,
       'speedFRatio' => $speedFRatio
    ];
    return $obj;
}

function userChildAccounts(User $user)
{
    $allAccounts = [];
    if ($user->grandchildren->count() > 0) {
        foreach ($user->grandchildren as $child) {
            $allAccounts[] = $child->id;
            $allAccounts = array_merge($allAccounts,is_array(userChildAccounts($child)) ? userChildAccounts($child) : []);
        }
    }
    return array_keys(array_flip($allAccounts));
}

function dateList($from_date=null, $to_date=null)
{
    $dateList = array();
    $diff = 7;
    $today      = new \DateTime();
    $earlier    = $today->sub(new \DateInterval('P'.$diff.'D'));
    $later      = new \DateTime(date('Y-m-d'));
    //List Date Wise
    if(!empty($from_date) && !empty($to_date))
    {
        $earlier    = new \DateTime($from_date);
        $later      = new \DateTime($to_date);
    } elseif(!empty($from_date) && empty($to_date)) {
        $earlier    = new \DateTime($from_date);
        $later      = new \DateTime(date('Y-m-d'));
    }

    $end       = $later->modify('+1 day');
    $interval  = new \DateInterval('P1D');
    $period = new \DatePeriod($earlier, $interval, $end);
    $dateList = array();
    foreach ($period as $key => $value) {
        $dateList[] = $value->format("Y-m-d");
    }
    return $dateList;
}

function getNotificationTemplate($notification_for)
{
    $template = NotificationTemplate::where('notification_for', $notification_for)->first();
    if($template)
    {
        return $template;
    }
    return false;
}

function notification($template_name, $user, $variable_data, $extra_info=null, $sender_id=null, $data_id=null, $is_send_email=true, $notification_subject=null, $notification_body=null)
{
    $notification_template = NotificationTemplate::where('notification_for', $template_name)->first();
    if(!$notification_template)
    {
        \Log::error('template not found: please check template:: '.$template_name);
        return false;
    }

    $save_to_database = $notification_template->save_to_database;
    $status_code = $notification_template->status_code;

    //send notification
    $notification_subject = (!empty($notification_subject) ? $notification_subject : $notification_template->notification_subject);
    if(!empty($notification_subject))
    {
        $notification_subject = $notification_template->notification_subject;
        $notification_body = (!empty($notification_body) ? $notification_body : strReplaceAssoc($variable_data, $notification_template->notification_body));

        $route_path = $notification_template->route_path;

        $notification = new Notification;
        $notification->user_id          = $user->id;
        $notification->status_code      = $status_code;
        $notification->title            = $notification_subject;
        $notification->message          = $notification_body;
        $notification->data_id          = $data_id;
        $notification->extra_info       = $extra_info;
        $notification->save();

        //\broadcast(new EventNotification($notification, $user->id, $user->uuid));

        if($save_to_database != true)
        {
            $notification->delete();
        } 

        if(env('IS_FB_NOTIFICATION_ENABLE')== true && !empty(env('FIREBASE_KEY')))
        {
            if($template_name=='kannel-disconnected')
            {
                $userDeviceInfos = UserDevice::where('user_id',$user->id)
                ->whereNotNull('device_token')
                ->orderBy('created_at', 'DESC')
                ->limit(3)
                ->get();
            }
            else
            {
                $userDeviceInfos = UserDevice::where('user_id',$user->id)
                ->whereNotNull('device_token')
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->get();
            }
            

            if($userDeviceInfos->count() > 0)
            { 
                foreach($userDeviceInfos as $key => $userDeviceInfo)
                {
                    $push = new PushNotification('fcm');
                    $push->setMessage([
                        "notification"=>[
                            'title' => $notification_subject,
                            'body'  => $notification_body,
                            'sound' => 'default',
                            'android_channel_id' => '1',
                            //'timestamp' => date('Y-m-d G:i:s')
                        ],
                        'data'=>[
                            'user_id'   => $user->id
                        ]                        
                    ])
                    ->setApiKey(env('FIREBASE_KEY'))
                    ->setDevicesToken($userDeviceInfo->device_token)
                    ->send();
                }
            }
        }
    }
      
    if(env('IS_MAIL_SEND_ENABLE', false) && $is_send_email==true)
    {
        //send mail
        $mail_subject = $notification_template->mail_subject;
        if(!empty($mail_subject))
        {
            $mail_body = strReplaceAssoc($variable_data, $notification_template->mail_body);

            $mailObj = [
                'template_name' => $template_name,
                'mail_subject'  => $mail_subject,
                'mail_body'     => $mail_body,
                'other_info'    => null,
            ];

            try {
                Mail::to($user->email)->send(new CommonMail($mailObj));
                //\Log::info("No errors, mail sent successfully!");
            } catch (Exception $e) {
                //\Log::error("mail sending failed.");
            }

            //notification
            /*if(empty($notification_subject) && $save_to_database == true)
            {
                $notification = new Notification;
                $notification->user_id          = $user->id;
                $notification->status_code      = $status_code;
                $notification->title            = $mail_subject;
                $notification->message          = $mail_body;
                $notification->data_id          = $data_id;
                $notification->extra_info       = $extra_info;
                $notification->save();

                //\broadcast(new EventNotification($notification, $user->id, $user->uuid));
            }*/
        }
    }

    return true;
}

function addKannelRoute($request)
{
    if($request->gateway_type==4)
    {
        return true;
    }
    //Kannel file read/write
    /***********************
    group = smsc
    smsc = smpp
    smsc-id = NEW
    allowed-smsc-id = NEW
    preferred-smsc-id = NEW
    host = 65.1.88.32
    port = 15023
    smsc-username = user013T
    smsc-password = SxQaCPMO
    system-type = smpp
    throughput = 50
    reconnect-delay = 60
    enquire-link-interval = 30
    max-pending-submits = 100
    #alt-charset = "ISO-8859-1"
    source-addr-autodetect = yes
    transceiver-mode = true
    source-addr-ton = 5
    source-addr-npi = 1
    dest-addr-ton = 1
    dest-addr-npi = 1
    log-file = /var/log/kannel/new.log
    log-level = 1
    instances = 5
    ***********************/
    $smsc_file = env('SMSC_FILE', '/etc/kannel/smsc.conf');
    $file   = fopen($smsc_file, 'a+');
    $line   = "\n";
    $line .= "smsc-id=".$request->smsc_id."\n";
    $line .= "group=smsc\n";
    $line .= "smsc=smpp\n";
    $line .= "allowed-smsc-id=".$request->smsc_id."\n";
    $line .= "preferred-smsc-id=".$request->smsc_id."\n";
    $line .= "host=".$request->ip_address."\n";
    $line .= "port=".$request->port."\n";
    $line .= "smsc-username=".$request->smsc_username."\n";
    $line .= "smsc-password=".$request->smsc_password."\n";
    $line .= "system-type=".$request->system_type."\n";
    $line .= "throughput=".$request->throughput."\n";

    $comment = '#';
    if(!empty($request->reconnect_delay))
    {
        $comment = null;
    }
    $line .= $comment."reconnect-delay=".$request->reconnect_delay."\n";

    $comment = '#';
    if(!empty($request->enquire_link_interval))
    {
        $comment = null;
    }
    $line .= $comment."enquire-link-interval=".$request->enquire_link_interval."\n";

    $comment = '#';
    if(!empty($request->max_pending_submits))
    {
        $comment = null;
    }
    $line .= $comment."max-pending-submits=".$request->max_pending_submits."\n";

    $line .= "transceiver-mode=".(($request->transceiver_mode==1) ? 1 : 0)."\n";
    $line .= "source-addr-ton=".$request->source_addr_ton."\n";
    $line .= "source-addr-npi=".$request->source_addr_npi."\n";
    $line .= "dest-addr-ton=".$request->dest_addr_ton."\n";
    $line .= "dest-addr-npi=".$request->dest_addr_npi."\n";
    $line .= "flow-control=0\n";
    $line .= "window=10\n";
    $line .= "wait-ack=600\n";

    $comment = '#';
    if($request->log_file==1)
    {
        $comment = null;
    }
    $line .= $comment."log-file=/var/log/kannel/".strtolower(Str::slug($request->smsc_id)).".log\n";
    $line .= $comment."log-level=".$request->log_level."\n";

    $line .= "instances=".$request->instances."\n";
    
    fwrite($file, $line);
    fclose($file);

    if($request->transceiver_mode==0)
    {
        $smsc_file = env('SMSC_FILE', '/etc/kannel/smsc.conf');
        $file   = fopen($smsc_file, 'a+');
        $line   = "\n";
        $line .= "smsc-id=".$request->smsc_id."\n";
        $line .= "group=smsc\n";
        $line .= "smsc=smpp\n";
        $line .= "allowed-smsc-id=".$request->smsc_id."\n";
        $line .= "preferred-smsc-id=".$request->smsc_id."\n";
        $line .= "host=".$request->ip_address."\n";
        $line .= "receive-port=".$request->receiver_port."\n";
        $line .= "smsc-username=".$request->smsc_username."\n";
        $line .= "smsc-password=".$request->smsc_password."\n";
        $line .= "system-type=".$request->system_type."\n";
        $line .= "throughput=".$request->throughput."\n";

        $comment = '#';
        if(!empty($request->reconnect_delay))
        {
            $comment = null;
        }
        $line .= $comment."reconnect-delay=".$request->reconnect_delay."\n";

        $comment = '#';
        if(!empty($request->enquire_link_interval))
        {
            $comment = null;
        }
        $line .= $comment."enquire-link-interval=".$request->enquire_link_interval."\n";

        $comment = '#';
        if(!empty($request->max_pending_submits))
        {
            $comment = null;
        }
        $line .= $comment."max-pending-submits=".$request->max_pending_submits."\n";

        $line .= "transceiver-mode=".(($request->transceiver_mode==1) ? 1 : 0)."\n";
        $line .= "source-addr-ton=".$request->source_addr_ton."\n";
        $line .= "source-addr-npi=".$request->source_addr_npi."\n";
        $line .= "dest-addr-ton=".$request->dest_addr_ton."\n";
        $line .= "dest-addr-npi=".$request->dest_addr_npi."\n";

        $comment = '#';
        if($request->log_file==1)
        {
            $comment = null;
        }
        $line .= $comment."log-file=/var/log/kannel/".strtolower(Str::slug($request->smsc_id)).".log\n";
        $line .= $comment."log-level=".$request->log_level."\n";

        $line .= "instances=".$request->instances."\n";
        
        fwrite($file, $line);
        fclose($file);
    }

    //Now ADD THE SMSC With HTTPS Admininstration Command
    $kannel_ip = env('KANNEL_IP');
    $kannel_admin_pass = env('KANNEL_ADMIN_PASS');
    $kannel_admin_port = env('KANNEL_ADMIN_PORT');

    if(env('APP_ENV','local')==='production')
    {
        $response = file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/add-smsc?password='.$kannel_admin_pass.'&smsc='.$request->smsc_id);
    }
    return true;
}

function kannelRouteUpdate($request, $old_smsc_id)
{
    if($request->gateway_type==4)
    {
        return true;
    }
    $smsc1 = env('SMSC_FILE', '/etc/kannel/smsc.conf');
    $smsc2 = env('COPY_SMSC_FILE', '/etc/kannel/smsc2.conf');

    if(!file_exists($smsc2))
    {
        $fp = fopen($smsc2, 'w');
        fwrite($fp, null);
        fclose($fp);
        chmod($smsc2, 0777); 
    }
    copy($smsc1,$smsc2);

    $smsc2file = fopen($smsc2, "r");
    $smsc1file = fopen($smsc1, "w+");
    
    //Output lines until EOF is reached
    $found = false;
    while(! feof($smsc2file)) 
    {
        $line   = fgets($smsc2file);
        $key    = explode("=", $line);
        if($key[0] == 'smsc-id')
        {
            if(trim($key[1]) == $old_smsc_id) {
                $found = true;
            } else {
                $found = false;
            }
        }
        if($found == false){
            fwrite($smsc1file,$line);
        }         
    }
    fclose($smsc2file);
    fclose($smsc1file);

    //clean file after deleting the record
    file_put_contents($smsc2, "");
    //unlink($smsc2);

    //Kannel delete start
    $kannel_ip = env("KANNEL_IP");
    $kannel_admin_pass = env("KANNEL_ADMIN_PASS");
    $kannel_admin_port = env("KANNEL_ADMIN_PORT");
   
    //Before Deleting the SMSC Configuration We must Stop and Remove the SMSC from running Instance
    //Stop SMSC
    if(env('APP_ENV','local')==='production')
    {
        file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/stop-smsc?password='.$kannel_admin_pass.'&smsc='.$old_smsc_id);
    }

    //Remove SMSC
    if(env('APP_ENV','local')==='production')
    {
        file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/remove-smsc?password='.$kannel_admin_pass.'&smsc='.$old_smsc_id);
    }

    //add new SMSC in smsc.conf file
    addKannelRoute($request);

    //Now ADD THE SMSC With HTTPS Admininstration Command
    if(env('APP_ENV','local')==='production')
    {
        file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/add-smsc?password='.$kannel_admin_pass.'&smsc='.$request->smsc_id);
    }
    return true;
}

function kannelRouteDelete($smsc_id)
{
    $smsc1 = env('SMSC_FILE', '/etc/kannel/smsc.conf');
    $smsc2 = env('COPY_SMSC_FILE', '/etc/kannel/smsc2.conf');
    if(!file_exists($smsc2))
    {
        $fp = fopen($smsc2, 'w');
        fwrite($fp, null);
        fclose($fp);
        chmod($smsc2, 0777); 
    }
    copy($smsc1,$smsc2);

    $smsc2file = fopen($smsc2, "r");
    $smsc1file = fopen($smsc1, "w+");
    
    //Output lines until EOF is reached
    $found = false;
    while(! feof($smsc2file)) 
    {
        $line   = fgets($smsc2file);
        $key    = explode("=", $line);
        if($key[0] == 'smsc-id')
        {
            if(trim($key[1]) == $smsc_id) {
                $found = true;
            } else {
                $found = false;
            }
        }
        if($found == false){
            fwrite($smsc1file,$line);
        }         
    }
    fclose($smsc2file);
    fclose($smsc1file);

    //clean file after deleting the record
    file_put_contents($smsc2, "");
    //unlink($smsc2);

    //Kannel delete start
    $kannel_ip = env("KANNEL_IP");
    $kannel_admin_pass = env("KANNEL_ADMIN_PASS");
    $kannel_admin_port = env("KANNEL_ADMIN_PORT");
   
    //Before Deleting the SMSC Configuration We must Stop and Remove the SMSC from running Instance
    //Stop SMSC
    if(env('APP_ENV','local')==='production')
    {
        file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/stop-smsc?password='.$kannel_admin_pass.'&smsc='.$old_smsc_id);
    }

    //Remove SMSC
    if(env('APP_ENV','local')==='production')
    {
        file_get_contents('http://'.$kannel_ip.':'.$kannel_admin_port.'/remove-smsc?password='.$kannel_admin_pass.'&smsc='.$old_smsc_id);
    }
    return true;
}

function kannelParameter($is_flash, $is_unicode)
{
    if($is_flash==1)
    {
        $mclass = 0;
        $coding = ($is_unicode==1) ? 2 : 0;
        $dlr_mask = 31;
        //$dlr_mask = 3;
    }
    else
    {
        $mclass = null;
        $coding = ($is_unicode==1) ? 2 : 0;
        $dlr_mask = 31;
    }
    
    $values = [
        'mclass' => $mclass,
        'coding' => $coding,
        'dlr_mask' => $dlr_mask,
    ];
    return $values;
}

function balanceInfo($action_for, $user_id)
{
    $findUser = User::select('id','name','userType','parent_id','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit', 'whatsapp_credit')->withoutGlobalScope('parent_id')->find($user_id);

    //1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms
    switch ($action_for) {
        case '1':
            $current_balance = $findUser->transaction_credit;
            break;
        case '2':
            $current_balance = $findUser->promotional_credit;
            break;
        case '3':
            $current_balance = $findUser->two_waysms_credit;
            break;
        case '4':
            $current_balance = $findUser->voice_sms_credit;
            break;
        case '5':
            $current_balance = $findUser->whatsapp_credit;
            break;
        default:
            $current_balance = 0;
            break;
    }
    $returnObj = [
        'action_for' => $action_for,
        'user_id' => $user_id,
        'name' => $findUser->name,
        'userType' => $findUser->userType,
        'parent_id' => $findUser->parent_id,
        'promotional_credit' => $findUser->promotional_credit,
        'transaction_credit' => $findUser->transaction_credit,
        'two_waysms_credit' => $findUser->two_waysms_credit,
        'voice_sms_credit' => $findUser->voice_sms_credit,
        'whatsapp_credit' => $findUser->whatsapp_credit,
        'current_balance' => $current_balance,
    ];
    return $returnObj;
}

function creditDebit($balanceInfo, $creditedUserId, $balance, $credit_type, $rate, $senderUserId, $comment=null)
{
    $user_id = $balanceInfo['user_id'];
    $userType = $balanceInfo['userType'];
    $action_for = $balanceInfo['action_for'];
    $current_balance = $balanceInfo['current_balance'];
    $senderUser = null;
    if(in_array($userType, [1,2]))
    {
        //first credited to other user account
        $user = User::select('id','userType','parent_id','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit', 'whatsapp_credit')->find($creditedUserId);
        $sign = -1; // deduct
        if($credit_type==1)
        {
            $sign = 1; //Add
        }
        switch ($action_for) {
            case '1':
                $user->transaction_credit = $user->transaction_credit + ($balance * $sign);
                break;
            case '2':
                $user->promotional_credit = $user->promotional_credit + ($balance * $sign);
                break;
            case '3':
                $user->two_waysms_credit = $user->two_waysms_credit + ($balance * $sign);
                break;
            case '4':
                $user->voice_sms_credit = $user->voice_sms_credit + ($balance * $sign);
                break;
            case '5':
                $user->whatsapp_credit = $user->whatsapp_credit + ($balance * $sign);
                break;
            default:
                break;
        }

        //generate credit log
        creditLog($creditedUserId, $senderUserId, $action_for, $credit_type, $balance, $rate, null, $comment);

        $user->save();
        

        //second deduct balance from sender user account
        $senderUser = User::select('id','userType','parent_id','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit', 'whatsapp_credit')->find($senderUserId);
        $sign = 1; // add
        if($credit_type==1) {
            $sign = -1; //deduct
            $credit_type = 2;
        } else {
            $credit_type = 1;
        }
        switch ($action_for) {
            case '1':
                $senderUser->transaction_credit = $senderUser->transaction_credit + ($balance * $sign);
                break;
            case '2':
                $senderUser->promotional_credit = $senderUser->promotional_credit + ($balance * $sign);
                break;
            case '3':
                $senderUser->two_waysms_credit = $senderUser->two_waysms_credit + ($balance * $sign);
                break;
            case '4':
                $senderUser->voice_sms_credit = $senderUser->voice_sms_credit + ($balance * $sign);
                break;
            case '5':
                $senderUser->whatsapp_credit = $senderUser->whatsapp_credit + ($balance * $sign);
                break;
            default:
                break;
        }

        //generate credit log
        creditLog($senderUserId, null, $action_for, $credit_type, $balance, null, null, $comment);

        $senderUser->save();
        return $user;
    }
    return false;
}

function creditLog($user_id, $created_by, $action_for, $credit_type, $balance_difference, $rate=null, $log_type=null, $comment=null, $scurrbing_sms_adjustment=0)
{
    $findUser = \DB::table('users')->select('id','parent_id','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit', 'whatsapp_credit')->find($user_id);
    if(!$findUser)
    {
        \Log::error('User not found'. $user_id);
    }

    $sign = -1; // deduct
    if($credit_type==1)
    {
        $sign = 1; //Add
    }
    //1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms, 5: whats_app
    switch ($action_for) {
        case '1':
            $old_balance = $findUser->transaction_credit;
            break;
        case '2':
            $old_balance = $findUser->promotional_credit;
            break;
        case '3':
            $old_balance = $findUser->two_waysms_credit;
            break;
        case '4':
            $old_balance = $findUser->voice_sms_credit;
            break;
        case '5':
            $old_balance = $findUser->whatsapp_credit;
            break;
        default:
            $old_balance = 0;
            break;
    }
    //calculation
    $balance_difference = $balance_difference;
    $current_balance = $old_balance + ($balance_difference*$sign);

    $creditLog = new CreditLog;
    $creditLog->parent_id = $findUser->parent_id;
    $creditLog->user_id = $user_id;
    $creditLog->created_by = $created_by;
    $creditLog->log_type = $log_type;
    $creditLog->action_for = $action_for;
    $creditLog->credit_type = $credit_type;
    $creditLog->old_balance = $old_balance;
    $creditLog->balance_difference = $balance_difference;
    $creditLog->current_balance = $current_balance;
    $creditLog->rate = $rate;
    $creditLog->comment = $comment;
    $creditLog->scurrbing_sms_adjustment = $scurrbing_sms_adjustment;
    $creditLog->save();
    return $creditLog;
}

function creditApiLog($user_id, $created_by, $action_for, $credit_type, $balance_difference, $rate=null, $log_type=null, $comment=null)
{
    $findUser = \DB::table('users')->select('id','parent_id','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit', 'whatsapp_credit')->find($user_id);
    $sign = -1; // deduct
    if($credit_type==1)
    {
        $sign = 1; //Add
    }
    //1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms
    switch ($action_for) {
        case '1':
            $old_balance = $findUser->transaction_credit + $balance_difference;
            break;
        case '2':
            $old_balance = $findUser->promotional_credit + $balance_difference;
            break;
        case '3':
            $old_balance = $findUser->two_waysms_credit + $balance_difference;
            break;
        case '4':
            $old_balance = $findUser->voice_sms_credit + $balance_difference;
            break;
        case '5':
            $old_balance = $findUser->whatsapp_credit + $balance_difference;
            break;
        default:
            $old_balance = 0;
            break;
    }
    //calculation
    $balance_difference = $balance_difference;
    $current_balance = $old_balance + ($balance_difference*$sign);

    $creditLog = new CreditLog;
    $creditLog->parent_id = $findUser->parent_id;
    $creditLog->user_id = $user_id;
    $creditLog->created_by = $created_by;
    $creditLog->log_type = $log_type;
    $creditLog->action_for = $action_for;
    $creditLog->credit_type = $credit_type;
    $creditLog->old_balance = $old_balance;
    $creditLog->balance_difference = $balance_difference;
    $creditLog->current_balance = $current_balance;
    $creditLog->rate = $rate;
    $creditLog->comment = $comment;
    $creditLog->save();
    return $creditLog;
}

function creditDeduct($user, $route_type, $total_credit_used)
{
    switch ($route_type) {
        case '1':
            if($user->transaction_credit<$total_credit_used)
            {
                return false;
                break;
            }
            $user->transaction_credit = $user->transaction_credit - $total_credit_used;
            $user->save();
            return $user;
            break;
        case '2':
            if($user->promotional_credit<$total_credit_used)
            {
                return false;
                break;
            }
            $user->promotional_credit = $user->promotional_credit - $total_credit_used;
            $user->save();
            return $user;
            break;
        case '3':
            if($user->two_waysms_credit<$total_credit_used)
            {
                return false;
                break;
            }
            $user->two_waysms_credit = $user->two_waysms_credit - $total_credit_used;
            $user->save();
            return $user;
            break;
        case '4':
            if($user->voice_sms_credit<$total_credit_used)
            {
                return false;
                break;
            }
            $user->voice_sms_credit = $user->voice_sms_credit - $total_credit_used;
            $user->save();
            return $user;
            break;
        case '5':
            if($user->whatsapp_credit<$total_credit_used)
            {
                return false;
                break;
            }
            $user->whatsapp_credit = $user->whatsapp_credit - $total_credit_used;
            $user->save();
            return $user;
            break;
        default:
            return false;
            break;
    }
    return false;
}

function creditAdd($user, $route_type, $total_credit_add)
{
    switch ($route_type) {
        case '1':
            $user->transaction_credit = $user->transaction_credit + $total_credit_add;
            $user->save();
            break;
        case '2':
            $user->promotional_credit = $user->promotional_credit + $total_credit_add;
            $user->save();
            break;
        case '3':
            $user->two_waysms_credit = $user->two_waysms_credit + $total_credit_add;
            $user->save();
            break;
        case '4':
            $user->voice_sms_credit = $user->voice_sms_credit + $total_credit_add;
            $user->save();
            break;
        case '5':
            $user->whatsapp_credit = $user->whatsapp_credit + $total_credit_add;
            $user->save();
            break;
        default:
            break;
    }
    return $user;
}

function getUserRouteCreditInfo($route_type, $user)
{
    //1:transaction, 2:promotional, 3:two_waysms, 4:voice_sms, 5:wahatsapp
    switch ($route_type) {
        case '1':
            $secondary_route_id = $user->transaction_route;
            $current_credit = $user->transaction_credit;
            break;
        case '2':
            $secondary_route_id = $user->promotional_route;
            $current_credit = $user->promotional_credit;
            break;
        case '3':
            $secondary_route_id = $user->two_waysms_route;
            $current_credit = $user->two_waysms_credit;
            break;
        case '4':
            $secondary_route_id = $user->voice_sms_route;
            $current_credit = $user->voice_sms_credit;
            break;
        case '5':
            $secondary_route_id = 'WA';
            $current_credit = $user->whatsapp_credit;
            break;
        default:
            $secondary_route_id = null;
            $current_credit = 0;
            break;
    }
    return [
        'secondary_route_id' => $secondary_route_id,
        'current_credit' => ($user->id!=1) ? $current_credit : 999999999
    ];
}

function checkPromotionalHours($campaign_send_date_time=null) 
{
    $campaign_send_date_time = !empty($campaign_send_date_time) ? $campaign_send_date_time : Carbon::now()->toDateTimeString();
    $getTime = date('H:i', strtotime($campaign_send_date_time));
    $startTime = "09:00";
    $endTime = "21:00";
    //dd(strtotime($startTime), strtotime($getTime), strtotime($endTime));
    if(strtotime($getTime)<strtotime($startTime))
    {
        return false;
    }
    elseif(strtotime($getTime)>=strtotime($startTime) && strtotime($getTime)<=strtotime($endTime))
    {
        return true;
    }
    return false;
}

function checkSpacialChar($message, $spacial_char="@")
{
    if (strpos($message, $spacial_char) !== false) {
        return true;
    }
    return false;
}

function messgeLenght($message_type, $message) 
{
    if($message_type==1)
    {
        //$message_character_count = strlen(preg_replace('/\s+/', ' ', trim($message)));
        $message_character_count = strlen($message);
        if ((int)($message_character_count) <= 306) {
            $message_credit_size = (int)((int)($message_character_count) / 160);
            if ((int)($message_character_count) % 160 != 0)
            {
                $message_credit_size = $message_credit_size + 1;
            }
        } else {
          $message_credit_size = (int)((int)($message_character_count-1) / 153);
          if ((int)($message_credit_size) % 153 != 0)
          {
              $message_credit_size = $message_credit_size + 1;
          }
        }
    }
    else
    {
        //$message_character_count = mb_strlen(preg_replace('/\s+/', ' ', trim($message)), 'UTF-8');
        $message_character_count = mb_strlen($message, 'UTF-8');
        if ((int) ($message_character_count) <= 70)
        {
          $message_credit_size = 1;
        }
        else 
        {
            $message_credit_size = (int) ((int) ($message_character_count-1) / 67);
            if ((int) ($message_credit_size) % 67 != 0)
            {
                $message_credit_size = $message_credit_size + 1;
            }
        }
    }
    $messageSizeInfo = [
        'message_credit_size'      => $message_credit_size,
        'message_character_count'  => $message_character_count
    ];
    return $messageSizeInfo;
}

function campaignCreate($parent_id, $user_id, $campaign, $secondary_route_id, $dlt_template_id, $sender_id, $route_type, $sms_type, $message, $message_type, $is_flash, $file_path, $file_mobile_field_name, $campaign_send_date_time, $priority, $message_count, $message_credit_size, $total_contacts, $total_block_number, $total_credit_deduct, $ratio_percent_set, $status, $is_read_file_path=1, $reschedule_send_sms_id=null, $reschedule_type=null, $failed_ratio=null, $is_campaign_scheduled=null, $dlt_template_group_id=null)
{
    \DB::beginTransaction();
    try {
        $sendSMS = new SendSms;
        $sendSMS->parent_id = !empty($parent_id) ? $parent_id : 1;
        $sendSMS->user_id = $user_id;
        $sendSMS->campaign = $campaign;
        $sendSMS->secondary_route_id = $secondary_route_id;
        $sendSMS->dlt_template_id = $dlt_template_id;
        $sendSMS->dlt_template_group_id = $dlt_template_group_id;
        $sendSMS->sender_id = $sender_id;
        $sendSMS->route_type = $route_type;
        $sendSMS->sms_type = $sms_type;
        $sendSMS->message = $message;
        $sendSMS->message_type = $message_type;
        $sendSMS->is_flash = $is_flash;
        $sendSMS->file_path = $file_path;
        $sendSMS->file_mobile_field_name = $file_mobile_field_name;
        $sendSMS->campaign_send_date_time = !empty($campaign_send_date_time) ? $campaign_send_date_time : Carbon::now()->toDateTimeString();
        $sendSMS->priority = $priority;
        $sendSMS->is_campaign_scheduled = !empty($is_campaign_scheduled) ? $is_campaign_scheduled : 0;
        $sendSMS->message_count = $message_count;
        $sendSMS->message_credit_size = $message_credit_size;
        $sendSMS->total_contacts = $total_contacts;
        $sendSMS->total_block_number = $total_block_number;
        $sendSMS->total_credit_deduct = $total_credit_deduct;
        $sendSMS->ratio_percent_set = ($ratio_percent_set==null) ? 0 : $ratio_percent_set;
        $sendSMS->failed_ratio = $failed_ratio;
        $sendSMS->is_update_auto_status = (($ratio_percent_set > 0) || ($failed_ratio > 0) ? 0 : 1);
        $sendSMS->is_read_file_path = $is_read_file_path;
        $sendSMS->status = $status;
        $sendSMS->reschedule_send_sms_id = $reschedule_send_sms_id;
        $sendSMS->reschedule_type = $reschedule_type;
        $sendSMS->save();
        DB::commit();
        return $sendSMS;
    } catch (\Throwable $e) {
        \Log::error($e);
        \DB::rollback();
        return false;
    }
}

function applyRatio($setRatio, $totalNumbers)
{
    $calPercentage = (($totalNumbers*$setRatio)/100);
    $random_numbers = [];
    while(count($random_numbers) < $calPercentage){
        do  {
            $random_mob_number = mt_rand(1, $totalNumbers);    
        } while (in_array($random_mob_number, $random_numbers));
            $random_numbers[] = $random_mob_number;
    }
    sort($random_numbers);
    return $random_numbers;
}

function checkNumberValid($mobile_number, $isRatio, $invalidSeries=null) 
{
    $isValid = 0; //Invalid number
    $actualMobile = $mobile_number;
    if(is_numeric($actualMobile))
    {
        if(strlen($mobile_number) < env('COUNTRY_MOBILE_MAX', 12) && strlen($mobile_number) > env('COUNTRY_MOBILE_MIN', 10))
        {
            $mobile_number = preg_replace("/^0/", env('COUNTRY_CODE', 91), $mobile_number);
            $isValid = 1; //valid number
        }
        elseif(strlen($mobile_number) == env('COUNTRY_MOBILE_MIN', 10))
        {
            $mobile_number = env('COUNTRY_CODE', 91).$mobile_number;
            $isValid = 1; //valid number
        }
        elseif(strlen($mobile_number) == env('COUNTRY_MOBILE_MAX', 12))
        {
            $mobile_number = $mobile_number;
            $isValid = 1; //valid number
        }
    }

    if($isValid==1)
    {
        $checkSeries = checkInvalidSeries($actualMobile, $invalidSeries);
        $isValid = ($checkSeries==0) ? $isValid : 0;
    }
    
    /*********** ratio apply ************/
    $is_auto = false;
    if($isValid==1 && $isRatio==1)
    {
        $is_auto = true;
    }

    $returnData = [
        'mobile_number' => $mobile_number,
        'is_auto'       => $is_auto,
        'number_status' => $isValid
    ];
    //\Log::info($returnData);
    return $returnData;
}

function checkInvalidSeries($mobile_number, array $invalidSeries) 
{
    foreach ($invalidSeries as $needle) {
        if (0 === strpos($mobile_number, $needle))
            return true;
    }
    return false;
}

function executeQuery($data)
{
    \DB::table('send_sms_queues')->insert($data);
    return true;
}

function executeKannelQuery($data)
{
    if(env('KANNEL_LIVE', true)==true)
    {
        $rand = rand(env('RAND_NUM_DB_RANGE_MIN', 2), env('RAND_NUM_DB_RANGE_MAX', 2));
        \DB::connection('mysql'.$rand)->table('send_sms')->insert($data);
        return true;

        // Later use
        /*
        if(checkKannelQueueStatus() && ($data->count()>env('CHUNK_SIZE', 1000)))
        {
            $rand = rand(env('RAND_NUM_DB_RANGE_MIN', 2), env('RAND_NUM_DB_RANGE_MAX', 2));
            \DB::connection('mysql'.$rand)->table('send_sms')->insert($data);
            return true;
        }
        */
    }
}

function kannelSmsbox()
{
    $smsbox = 'smsbox'.rand(1, env('KANNEL_SMSBOX_CONNECTED', 1));
    return $smsbox;
}

function updateRecord($table, $array, $id)
{
    // not using this function anywhere
    $record = DB::table($table)
            ->where('id', $id)
            ->update($array);
    return $record;
}

function checkCurrentStatus($send_sms_id, $totalBunch=50000)
{
    // this function is not using anywhere
    $checkStatus = SendSms::select('id', 'is_update_auto_status')->withCount([
        'sendSmsQueues as total_queues_count',
        'sendSmsHistories as total_history_count',

        'sendSmsQueues as delivered_queue_count' => function ($query) {
          $query->where('stat', 'DELIVRD');
        },
        'sendSmsHistories as delivered_history_count' => function ($query) {
          $query->where('stat', 'DELIVRD');
        },
        
        'sendSmsQueues as invalid_queue_count' => function ($query) {
          $query->where('stat', 'Invalid');
        },
        'sendSmsHistories as invalid_history_count' => function ($query) {
          $query->where('stat', 'Invalid');
        },

        'sendSmsQueues as blacklist_queue_count' => function ($query) {
          $query->where('stat', 'BLACK');
        },
        'sendSmsHistories as blacklist_history_count' => function ($query) {
          $query->where('stat', 'BLACK');
        },

        'sendSmsQueues as failed_queue_count' => function ($query) {
          $query->whereNotIn('stat', ['Pending','Accepted','Invalid','BLACK','DELIVRD']);
        },
        'sendSmsHistories as failed_history_count' => function ($query) {
          $query->whereNotIn('stat', ['Pending','Accepted','Invalid','BLACK','DELIVRD']);
        }
    ])
    ->find($send_sms_id);

    if($checkStatus->is_update_auto_status==0)
    {
        updateReportAuto($send_sms_id, $totalBunch);
        $checkStatus->is_update_auto_status = 1;
        $checkStatus->save();
    }
    return $checkStatus;
}

function updateReportAuto($send_sms_id, $totalBunch=50000)
{
    //Delivered
    \DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
    `stat` = 'DELIVRD',
    `err` = '000',
    `sub` = '001',
    `dlvrd` = '001',
    `status` = 'Completed'
    WHERE `is_auto`= 1 AND `stat` = 'Pending' AND `send_sms_id` = '".$send_sms_id."' LIMIT ".$totalBunch.";");

    \DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
    `stat` = 'DELIVRD',
    `err` = '000',
    `sub` = '000',
    `dlvrd` = '001',
    `status` = 'Completed'
    WHERE `is_auto`= 1 AND `stat` = 'Pending' AND `send_sms_id` = '".$send_sms_id."' LIMIT ".$totalBunch.";");

    //failed
    \DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
    `stat`='FAILED', 
    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
    `sub` = '001',
    `dlvrd` = '001',
    `status` = 'Completed'
    WHERE `is_auto`= 2 AND `stat` = 'Pending' AND `send_sms_id` = '".$send_sms_id."' LIMIT ".$totalBunch.";");

    \DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
    `stat`='FAILED', 
    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
    `sub` = '001',
    `dlvrd` = '001',
    `status` = 'Completed'
    WHERE `is_auto`= 2 AND `stat` = 'Pending' AND `send_sms_id` = '".$send_sms_id."' LIMIT ".$totalBunch.";");
    return true;
}

function reUpdatePending($send_sms_id, $totalBunch=25000)
{
    updateReportAuto($send_sms_id, $totalBunch);
    sleep(1);
    $checkAllUpdateQueue = \DB::table('send_sms_queues')
        ->select('id')
        ->where('is_auto', '!=', 0)
        ->where('stat', 'Pending')
        ->where('send_sms_id', $send_sms_id)
        ->count();
    $checkAllUpdateHistory = \DB::table('send_sms_histories')
        ->select('id')
        ->where('is_auto', '!=', 0)
        ->where('stat', 'Pending')
        ->where('send_sms_id', $send_sms_id)
        ->count();
    $totalRecords = ($checkAllUpdateQueue + $checkAllUpdateHistory);
    $checkTotalPage = ceil($totalRecords / $totalBunch);
    if($totalRecords > 0)
    {
        for ($i=1; $i <= $checkTotalPage ; $i++) 
        { 
            usleep(500);
            updateReportAuto($send_sms_id, $totalBunch);
        }

        \DB::table('send_sms')
        ->where('id', $send_sms_id)
        ->update(['is_update_auto_status' => 1]);
    }
    
    return true;
}

function updateAcceptedToStatusWise($send_sms_id, $operation='queue')
{
    // First we update all the records if status is accepted and done date is not null according to there status (Stat & err value)
    // 000 -> Delivered
    // XX1 -> Invalid 
    // other -> Failed
    // ****************Delivered************
    // UPDATE `send_sms_histories` SET `stat` = 'DELIVRD'  WHERE stat="accepted" AND err='000' AND done_date IS NOT NULL; 

    // ****************Failed************
    // UPDATE `send_sms_histories` SET `stat` = 'FAILED'  WHERE stat="accepted" AND err NOT IN ('000', 'XX1') AND done_date IS NOT NULL; 
    
    if($operation=='history')
    {
        \DB::statement("UPDATE `send_sms_histories` SET `stat` = 'DELIVRD' WHERE stat='ACCEPTED' AND err='000' AND done_date IS NOT NULL AND send_sms_id = '".$send_sms_id."';");
        sleep(2);

        \DB::statement("UPDATE `send_sms_histories` SET `stat` = 'FAILED'  WHERE stat='ACCEPTED' AND err NOT IN ('000', 'XX1') AND done_date IS NOT NULL AND send_sms_id = '".$send_sms_id."';");
        sleep(2);
    }
    else
    {
        \DB::statement("UPDATE `send_sms_queues` SET `stat` = 'DELIVRD' WHERE stat='ACCEPTED' AND err='000' AND done_date IS NOT NULL AND send_sms_id = '".$send_sms_id."';");
        sleep(2);

        \DB::statement("UPDATE `send_sms_queues` SET `stat` = 'FAILED'  WHERE stat='ACCEPTED' AND err NOT IN ('000', 'XX1') AND done_date IS NOT NULL AND send_sms_id = '".$send_sms_id."';");
        sleep(2);
    }
    //\Log::info('updateAcceptedToStatusWise function triggered');
    return true;
}

function checkApiCampaignComplete()
{
    $yesterday = date("Y-m-d", strtotime('-1 days', time()));
    $campaigns = DB::table('send_sms')
        ->select('id')
        ->where('status', 'Ready-to-complete')
        ->whereDate('campaign_send_date_time', '=', $yesterday)
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

        DB::statement("UPDATE `send_sms` SET 

        `total_delivered` = $delivrd, 

        `total_failed` = $failed,

        `total_block_number` = $black,

        `total_invalid_number` = $invalid,

        `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
        
        WHERE `id` = $campaign->id;");
    }
    return;
}

function checkWaApiCampaignComplete()
{
    $yesterday = date("Y-m-d", strtotime('-1 days', time()));
    $campaigns = DB::table('whats_app_send_sms')
        ->select('id')
        ->where('status', 'Ready-to-complete')
        ->whereDate('campaign_send_date_time', '=', $yesterday)
        ->where('campaign', 'API')
        ->get();
    foreach ($campaigns as $key => $campaign) 
    {
        $accepted = 0;
        $invalid = 0;
        $block = 0;
        $sent = 0;
        $delivered = 0;
        $failed = 0;
        $read = 0;
        $other = 0;

        $totalRecords = \DB::table('whats_app_send_sms_queues')
            ->select('stat', \DB::raw('COUNT(stat) as stat_counts'))
            ->where('whats_app_send_sms_id', $campaign->id)
            ->groupBy('stat')
            ->get();
        foreach ($totalRecords as $key => $value) 
        {
            switch (strtolower($value->stat)) 
            {
                case strtolower('pending'):
                    $pending += $value->stat_counts;
                    break;
                case strtolower('accepted'):
                    $accepted += $value->stat_counts;
                    break;
                case strtolower('invalid'):
                    $invalid += $value->stat_counts;
                    break;
                case strtolower('block'):
                    $block += $value->stat_counts;
                    break;
                case strtolower('sent'):
                    $sent += $value->stat_counts;
                    break;
                case strtolower('delivered'):
                    $delivered += $value->stat_counts;
                    break;
                case strtolower('failed'):
                    $failed += $value->stat_counts;
                    break;
                case strtolower('read'):
                    $read += $value->stat_counts;
                    break;
                default:
                    $other += $value->stat_counts;
                    break;
            }
        }

        DB::statement("UPDATE `whats_app_send_sms` SET 

        `total_block_number` = $block, 

        `total_invalid_number` = $invalid, 

        `total_sent` = $sent, 

        `total_delivered` = $delivered,

        `total_read` = $read,

        `total_failed` = $failed + $invalid,

        `total_other` = $other,

        `status` = CASE WHEN `total_contacts` <= (`total_sent` + `total_delivered` + `total_read` + `total_failed` + `total_block_number` + `total_invalid_number` + `total_other`) THEN 'Completed' ELSE `status` END
        
        WHERE `id` = $campaign->id;");
    }
    return;
}

function reUpdateWAPending($whats_app_send_sms_id, $totalBunch=5000)
{
    updateWAReportAuto($whats_app_send_sms_id, $totalBunch);
    sleep(1);
    $checkAllUpdateQueue = \DB::table('whats_app_send_sms_queues')
        ->select('id')
        ->where('is_auto', '!=', 0)
        ->where('stat', 'Pending')
        ->where('whats_app_send_sms_id', $whats_app_send_sms_id)
        ->count();

    $totalRecords = $checkAllUpdateQueue;
    $checkTotalPage = ceil($totalRecords / $totalBunch);
    if($totalRecords > 0)
    {
        for ($i=1; $i <= $checkTotalPage ; $i++) 
        { 
            usleep(500);
            updateWAReportAuto($whats_app_send_sms_id, $totalBunch);
        }

        \DB::table('whats_app_send_sms')
        ->where('id', $whats_app_send_sms_id)
        ->update(['is_update_auto_status' => 1]);
    }
    
    return true;
}

function updateWAReportAuto($whats_app_send_sms_id, $totalBunch=5000)
{
    $errorPayload = [
        'code' => 131049,
        'title' => 'This message was not delivered to maintain healthy ecosystem engagement.","message":"This message was not delivered to maintain healthy ecosystem engagement.',
        'error_data' => [
            'details' => 'In order to maintain a healthy ecosystem engagement, the message failed to be delivered.',
            'href' => 'https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/'
        ]
    ];

    $error_info = json_encode($errorPayload);

    //Delivered
    \DB::statement("UPDATE `whats_app_send_sms_queues` SET `response_token`= COALESCE(response_token, CONCAT('wamid.', TO_BASE64(CONCAT('HBgM', mobile, SUBSTRING(MD5(RAND()), 1, 10))))), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `expiration_timestamp`= submit_date, 
    `sent_date_time`= COALESCE(sent_date_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 2) + 5) second)),
    `delivered_date_time`= COALESCE(delivered_date_time, DATE_ADD(sent_date_time, INTERVAL FLOOR((RAND() * 5) + 13) second)),
    `read_date_time`= COALESCE(read_date_time, DATE_ADD(delivered_date_time, INTERVAL FLOOR((RAND() * 13) + 120) second)),
    `stat` = 'read',
    `error_info` = null,
    `conversation_id` = LOWER(HEX(SUBSTRING(MD5(RAND()), 1, 16))),
    `sent` = 1,
    `delivered` = 1,
    `read` = 1,
    `status` = 'Completed'
    WHERE `is_auto`= 1 AND `stat` = 'Pending' AND `whats_app_send_sms_id` = '".$whats_app_send_sms_id."' LIMIT ".$totalBunch.";");

    //failed
    \DB::statement("UPDATE `whats_app_send_sms_queues` SET `response_token`= COALESCE(response_token, CONCAT('wamid.', TO_BASE64(CONCAT('HBgM', mobile, SUBSTRING(MD5(RAND()), 1, 10))))), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `expiration_timestamp`= submit_date, 
    `sent_date_time`= COALESCE(sent_date_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 2) + 5) second)),
    `stat` = 'failed',
    `error_info` = '".$error_info."',
    `sent` = 1,
    `status` = 'Completed'
    WHERE `is_auto`= 2 AND `stat` = 'Pending' AND `whats_app_send_sms_id` = '".$whats_app_send_sms_id."' LIMIT ".$totalBunch.";");

    return true;
}

//websocket
function checkUserToken($token) 
{
    // break up the token_name(token)en into its three parts
    $token_parts = explode('.', $token);
    if (is_array($token_parts) && array_key_exists('1', $token_parts)) {
       $token_header =  $token_parts[1];
    } else {
        $token_header = null;
    }

    // base64 decode to get a json string
    $token_header_json = base64_decode($token_header);

    // then convert the json to an array
    $token_header_array = json_decode($token_header_json, true);

    $user_token = (is_array($token_header_array) && array_key_exists('jti', $token_header_array)) ? $token_header_array['jti'] : null;

    // find the user ID from the oauth access token table
    // based on the token we just got
    if($user_token) {
        $userAccessToken = OauthAccessTokens::find($user_token);
        $result  = [
            "user_token"=> $user_token,
            "user_id"   => $userAccessToken->user_id,
        ];
        return $result;
    } 
    return false;
}

// Api function and messages
function matchToken($app_key, $app_secret) 
{
    $checkUser = \DB::table('users')->select('id as user_number','name','email','mobile','status','transaction_credit as transaction_balance','promotional_credit as promotional_balance','two_waysms_credit as two_waysms_balance','voice_sms_credit as voice_sms_balance','whatsapp_credit as whatsapp_credit', 'country', 'is_enabled_api_ip_security','userType as ut')
        ->whereRaw("BINARY `app_key`= ?",[$app_key])
        ->whereRaw("BINARY `app_secret`= ?",[$app_secret])
        ->first();
    return ($checkUser) ? $checkUser : false;
}

function checkUserBalance($current_balance)
{
    return ($current_balance > 0) ? true : false;
}

function checkAccountStatus($status)
{
    return ($status==1) ? true : false;
}

function isEnabledApiIpSecurity($user_id, $is_enabled_api_ip_security, $requestIp)
{
    if($is_enabled_api_ip_security==1)
    {
        $checkIpInList = \DB::table('ip_white_list_for_apis')
        ->where('user_id', $user_id)
        ->where('ip_address', $requestIp)
        ->count();
        return ($checkIpInList > 0) ? true : false;
    }
    return true;
}

function invalidApiKeyOrSecretKey($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.invalid_api_key_or_secret_key'), ['invalid_account_keys' => trans('translate.invalid_api_key_or_secret_key')], $intime, config('httpcodes.invalid_account_keys') ), config('httpcodes.un_authorized'));
}

function validationFailed($error, $intime)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.validation_failed'), $error->messages(), $intime, config('httpcodes.validation_error')), config('httpcodes.bad_request'));
}

function accountDeactivated($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.account_is_temporarily_deactivated'),['account_inactive' => trans('translate.account_is_temporarily_deactivated')], $intime, config('httpcodes.account_inactive')), config('httpcodes.un_authorized'));
}

function ipAddressNotValid($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.ip_address_not_whitelisted').' IP:'.request()->ip(), ['ip_address_not_allowed' => trans('translate.ip_address_not_whitelisted').' IP:'.request()->ip()], $intime, config('httpcodes.ip_address_not_allowed')), config('httpcodes.un_authorized'));
}

function invalidDateTimeFormat($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.schedule_date_and_time_is_incorrect'), ['invalid_schedule_date_time' => trans('translate.schedule_date_and_time_is_incorrect')], $intime, config('httpcodes.invalid_schedule_date_time')), config('httpcodes.unprocessable_entity'));
}

function dateTimeNotAllowed($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.schedule_date_time_not_allowed'),['date_and_time_not_allowed'], $intime, config('httpcodes.promotional_message_not_allowed_selected_time')), config('httpcodes.unprocessable_entity'));
}

function promotionalDateTimeNotAllowed($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.can_not_send_promotional_activity_selected_time'), ['date_and_time_not_allowed' => trans('translate.can_not_send_promotional_activity_selected_time')], $intime, config('httpcodes.promotional_message_not_allowed_selected_time')), config('httpcodes.unprocessable_entity'));
}

function dltTemplateNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.dlt_template_not_found'), ['dlt_template_not_associated' => trans('translate.dlt_template_not_found')], $intime, config('httpcodes.dlt_template_not_associated')), config('httpcodes.unprocessable_entity'));
}

function notHaveSufficientBalance($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.you_dont_have_sufficient_balance'), ['insufficient_balance' => trans('translate.you_dont_have_sufficient_balance')], $intime, config('httpcodes.insufficient_balance')), config('httpcodes.unprocessable_entity'));
}

function getewayNotWorking($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.gateway_not_working_contact_to_admin'), ['gateway_not_working' => trans('translate.gateway_not_working_contact_to_admin')], $intime, config('httpcodes.gateway_not_working')), config('httpcodes.unprocessable_entity'));
}

function catchException($e, $intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.something_went_wrong'), ['exception_occur' => $e->getMessage()], $intime, config('httpcodes.exception_occur')), config('httpcodes.internal_server_error'));
}

function responseTokenEmpty($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.response_token_empty'), ['campaign_token_not_found' => trans('translate.response_token_empty')], $intime, config('httpcodes.campaign_token_not_found')), config('httpcodes.unprocessable_entity'));
}

function recordNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.record_not_found'), ['campaign_not_found' => trans('translate.record_not_found')], $intime, config('httpcodes.campaign_not_found')), config('httpcodes.not_found'));
}

function mobileNumberEmptyORInvalid($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.mobile_number_invalid'), ['invalid_mobile_number' => trans('translate.mobile_number_invalid')], $intime, config('httpcodes.invalid_mobile_number')), config('httpcodes.not_found'));
}

// voice
function voiceTemplateNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.voice_template_not_found'), ['voice_template_not_found' => trans('translate.voice_template_not_found')], $intime, config('httpcodes.voice_template_not_found')), config('httpcodes.unprocessable_entity'));
}

function voiceFileNotVerifiedYet($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.voice_file_not_verified_yet'), ['voice_file_not_verified' => trans('translate.voice_file_not_verified_yet')], $intime, config('httpcodes.voice_file_not_verified')), config('httpcodes.unprocessable_entity'));
}

function cannotSentSmsMoreThenSetLimit($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.cannot_sent_more_then_DIFINE_number_numbers'), ['mobile_number_limit_exceeded' => trans('translate.cannot_sent_more_then_DIFINE_number_numbers')], $intime, config('httpcodes.mobile_number_limit_exceeded')), config('httpcodes.unprocessable_entity'));
}

function voiceObdTypeNotMatched($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.voice_obd_type_not_matched'), ['voice_obd_type_not_matched' => trans('translate.voice_obd_type_not_matched')], $intime, config('httpcodes.voice_obd_type_not_matched')), config('httpcodes.unprocessable_entity'));
}

// Whatsapp
function waTemplateNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.wa_template_not_found'), ['whats_app_template_not_found' => trans('translate.wa_template_not_found')], $intime, config('httpcodes.wa_template_not_associated')), config('httpcodes.unprocessable_entity'));
}

function invalidMediaTemplatePayload($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.wa_invalid_media_template_payload'), ['wa_invalid_media_template_payload' => trans('translate.wa_invalid_media_template_payload')], $intime, config('httpcodes.invalid_payload')), config('httpcodes.unprocessable_entity'));
}

function countryDetailNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.country_not_found'), ['country_not_found' => trans('translate.country_not_found')], $intime, config('httpcodes.country_detail_not_found')), config('httpcodes.unprocessable_entity'));
}

function waChargesNotDefine($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.whats_app_charges_not_define_in_your_account_contact_to_admin'), ['whats_app_charges_not_define_in_your_account' => trans('translate.whats_app_charges_not_define_in_your_account_contact_to_admin')], $intime, config('httpcodes.wa_charges_not_found')), config('httpcodes.unprocessable_entity'));
}

function waConfigurationNotFound($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.whats_app_configurations_not_found'), ['whats_app_configurations_not_found' => trans('translate.whats_app_configurations_not_found')], $intime, config('httpcodes.wa_configurations_not_found')), config('httpcodes.unprocessable_entity'));
}

function mobileNumberLenghtInvalid($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.mobile_number_length_invalid'), ['mobile_number_length_invalid' => trans('translate.mobile_number_length_invalid')], $intime, config('httpcodes.wa_configurations_not_found')), config('httpcodes.unprocessable_entity'));
}

function countryCodeInvalid($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.country_code_not_matched'), ['country_code_not_matched' => trans('translate.country_code_not_matched')], $intime, config('httpcodes.wa_configurations_not_found')), config('httpcodes.unprocessable_entity'));
}

function countryCodeNotAllowed($intime=null)
{
    return response()->json(apiPrepareResult(false, [], trans('translate.only_india_country_code_allowed'), ['only_india_country_code_allowed' => trans('translate.only_india_country_code_allowed')], $intime, config('httpcodes.wa_configurations_not_found')), config('httpcodes.unprocessable_entity'));
}

function sendSmsThroughApi($token, $secret, $dlt_template_id, $messages, $mobile_numbers)
{
    $url = env('APP_URL', 'http://localhost:8000')."/api/v1/send-message?app_key=$token&app_secret=$secret&dlt_template_id=$dlt_template_id&mobile_numbers=$mobile_numbers&message=".urlencode($messages);
    
    if(env('APP_URL', 'http://localhost:8000') == 'http://localhost:8000' || env('APP_URL', 'http://localhost:8000') == 'http://localhost:8001')
    {
        return; //temp
    }

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $resp = curl_exec($curl);
    curl_close($curl);
    return json_decode($resp, true);
}

function getStringBetweenTwoWord($str, $starting_word, $ending_word) 
{ 
    $checkStrLength = strlen($str);
    $subtring_start = strpos($str, $starting_word);
    if($checkStrLength<14)
    {
        $subtring_start += $checkStrLength;
    }
    else
    {
        $subtring_start += strlen($starting_word);
    }

    $size = strpos($str, $ending_word, $subtring_start) - $subtring_start;
    return substr($str, $subtring_start, $size);   
} 

function createUniqueLink($two_way_comm_id, $link_expired, $mobile_num, $send_sms_id, $parent_id)
{
    $twoWayContent = \DB::table(env('DB_DATABASE2W').'.two_way_comms')->find($two_way_comm_id);
    $domain     = env('TWOWAY_SMS_DOAMIN', 'http://localhost:8000'); // domain name without slash in the end
    $frontDomain     = env('FRONT_URL', 'http://localhost:3000'); // domain name without slash in the end
    $sub_part   = Str::random(1);
    $token      = Str::random(5);
    $createLink = new ShortLink();
    $createLink->parent_id    = $parent_id;
    $createLink->two_way_comm_id    = $two_way_comm_id;
    $createLink->send_sms_id        = $send_sms_id;
    $createLink->code               = $domain.'/'.$sub_part.'/'.$token;
    $createLink->sub_part           = $sub_part;
    $createLink->token              = $token;
    $createLink->mobile_num         = $mobile_num;
    if($twoWayContent->is_web_temp==1)
    {
        $createLink->link           = $frontDomain.'/2w/'.$sub_part.'/'.$token.'/'.$mobile_num;
    }
    else
    {
        $createLink->link           = $twoWayContent->redirect_url;
    }
    $createLink->link_expired       = $link_expired;
    $createLink->save();
    return $createLink;
}

/*
// Old Function where we also checked the history table
function updateAllTypeStatusReport($send_sms_id)
{
    \DB::statement("UPDATE `send_sms` SET 
    `total_delivered` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'DELIVRD') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'DELIVRD'), 

    `total_failed` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')) + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),

    `total_block_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'BLACK') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'BLACK'),

    `total_invalid_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'Invalid') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'Invalid'),

    `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
    
    WHERE `id` = $send_sms_id AND `status`='Ready-to-complete';");

    return true;
}
*/

function updateAllTypeStatusReport($send_sms_id)
{
    \DB::statement("UPDATE `send_sms` SET 
    `total_delivered` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'DELIVRD'), 

    `total_failed` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),

    `total_block_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'BLACK'),

    `total_invalid_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'Invalid'),

    `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
    
    WHERE `id` = $send_sms_id AND `status`='Ready-to-complete';");

    return true;
}

function updateAllTypeStatusWAReport($whats_app_send_sms_id)
{
    \DB::statement("UPDATE `whats_app_send_sms` SET 
    `total_sent` = (SELECT COUNT(*) FROM `whats_app_send_sms_queues` WHERE `whats_app_send_sms`.`id` = `whats_app_send_sms_queues`.`whats_app_send_sms_id` AND `stat` = 'SENT'),

    `total_delivered` = (SELECT COUNT(*) FROM `whats_app_send_sms_queues` WHERE `whats_app_send_sms`.`id` = `whats_app_send_sms_queues`.`whats_app_send_sms_id` AND `stat` = 'DELIVRD'), 

    `total_failed` = (SELECT COUNT(*) FROM `whats_app_send_sms_queues` WHERE `whats_app_send_sms`.`id` = `whats_app_send_sms_queues`.`whats_app_send_sms_id` AND `stat` = 'FAILED'),

    `total_block_number` = (SELECT COUNT(*) FROM `whats_app_send_sms_queues` WHERE `whats_app_send_sms`.`id` = `whats_app_send_sms_queues`.`whats_app_send_sms_id` AND `stat` = 'BLACK'),

    `total_invalid_number` = (SELECT COUNT(*) FROM `whats_app_send_sms_queues` WHERE `whats_app_send_sms`.`id` = `whats_app_send_sms_queues`.`whats_app_send_sms_id` AND `stat` = 'Invalid'),

    `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
    
    WHERE `id` = $whats_app_send_sms_id AND `status`='Ready-to-complete';");

    return true;
}

function smsCreditToAmount($credit, $ratePerSms)
{
    return round(($credit * $ratePerSms), 2);
}

function smsCreditReverse($ratePerSms, $failedSMS)
{
    $scurrbing_charge = env('SCRUBBING_CHARGES', 0.02);
    $scurrbingCharges = round(($failedSMS * $scurrbing_charge), 2);
    $converToSMS = round(($scurrbingCharges / $ratePerSms), 0);
    return $converToSMS;
}

// Voice start

function voiceFileUploadToGateway($primaryRoute, $voiceupload)
{
    $response = false;
    if($primaryRoute->smsc_id=='videocon')
    {
        $fileWithUrl = public_path($voiceupload->file_location);
        $requestUrl = "http://103.132.146.183/VoxUpload/api/Values/upload";           
        $uploadedFile = new \CURLFile(realpath($fileWithUrl));
        $post = [
            'UserName' => $primaryRoute->smsc_username,
            'Password' => $primaryRoute->smsc_password,
            'PlanType' => $voiceupload->file_time_duration,
            'fileName' => $voiceupload->title.'-nrt-'.time(),
            'uploadedFile' => $uploadedFile,
        ];  

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");   
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: multipart/form-data'));
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);   
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);  
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $response = curl_exec ($ch);

        if ($response === FALSE) {
            $response = curl_error($ch);
        }
        else
        {
            $voiceUploadSentGateway = new VoiceUploadSentGateway;
            $voiceUploadSentGateway->voice_upload_id = $voiceupload->id;
            $voiceUploadSentGateway->primary_route_id = $primaryRoute->id;
            $voiceUploadSentGateway->file_send_to_smsc_id = $primaryRoute->smsc_id;
            $voiceUploadSentGateway->voice_id = preg_replace("/[^0-9]/", "", $response);
            $voiceUploadSentGateway->file_status = 2; // process
            $voiceUploadSentGateway->save();
            $response = $voiceUploadSentGateway;
        }
        curl_close ($ch);
    }
    return $response;
}

function checkVoiceNumberValid($mobile_number, $isRatio, $invalidSeries=null, $with_country_code=false) 
{
    $isValid = 0; //Invalid number
    $actualMobile = $mobile_number;
    if(is_numeric($actualMobile))
    {
        if(strlen($mobile_number) < env('COUNTRY_MOBILE_MAX', 12) && strlen($mobile_number) > env('COUNTRY_MOBILE_MIN', 10) && $with_country_code==true)
        {
            $mobile_number = preg_replace("/^0/", env('COUNTRY_CODE', 91), $mobile_number);
            $isValid = 1; //valid number
        }
        elseif(strlen($mobile_number) == env('COUNTRY_MOBILE_MIN', 10))
        {
            $mobile_number = $mobile_number;
            $isValid = 1; //valid number
        }
        elseif(strlen($mobile_number) == env('COUNTRY_MOBILE_MAX', 12))
        {
            $mobile_number = preg_replace('/^\+?91|\|91|\D/', '', ($mobile_number));
            $isValid = 1; //valid number
        }
    }

    if($isValid==1)
    {
        $checkSeries = checkInvalidSeries($actualMobile, $invalidSeries);
        $isValid = ($checkSeries==0) ? $isValid : 0;
    }
    
    /*********** ratio apply ************/
    $is_auto = false;
    if($isValid==1 && $isRatio==1)
    {
        $is_auto = true;
    }

    $returnData = [
        'mobile_number' => $mobile_number,
        'is_auto'       => $is_auto,
        'number_status' => $isValid
    ];
    //\Log::info($returnData);
    return $returnData;
}

function convertFileStatus($stringStatus)
{
    //1:pending, 2:process, 3:approved, 4:rejected
    switch (strtolower($stringStatus)) {
        case 'pending':
            $file_status = 1;
            break;
        case 'process':
            $file_status = 2;
            break;
        case 'approved':
            $file_status = 3;
            break;
        case 'rejected':
            $file_status = 4;
            break;
        default:
            $file_status = 1;
            break;
    }
    return $file_status;
}

function voiceLenghtCredit($duration)
{
    if($duration<=30)
    {
        $credit = 1;
    }
    elseif($duration>30 && $duration<=60)
    {
        $credit = 2;
    }
    elseif($duration>60 && $duration<=90)
    {
        $credit = 3;
    }
    elseif($duration>90 && $duration<=120)
    {
        $credit = 4;
    }
    return $credit;
}

function voiceCampaignCreate($parent_id, $user_id, $campaign, $obd_type, $secondary_route_id, $voice_upload_id, $voice_id, $voice_file_path, $file_mobile_field_name, $campaign_send_date_time, $priority, $message_credit_size, $total_contacts, $total_block_number, $total_credit_deduct, $ratio_percent_set, $status, $is_read_file_path=1, $failed_ratio=null, $is_campaign_scheduled=null, $dtmf=null, $call_patch_number=null)
{
    \DB::beginTransaction();
    try {
        $voiceSMS = new VoiceSMS;
        $voiceSMS->parent_id = !empty($parent_id) ? $parent_id : 1;
        $voiceSMS->user_id = $user_id;
        $voiceSMS->campaign = $campaign;
        $voiceSMS->obd_type = $obd_type;
        $voiceSMS->dtmf = $dtmf;
        $voiceSMS->call_patch_number = $call_patch_number;
        $voiceSMS->secondary_route_id = $secondary_route_id;
        $voiceSMS->voice_upload_id = $voice_upload_id;
        $voiceSMS->voice_id = $voice_id;
        $voiceSMS->voice_file_path = $voice_file_path;
        $voiceSMS->file_path = null;
        $voiceSMS->file_mobile_field_name = $file_mobile_field_name;
        $voiceSMS->campaign_send_date_time = !empty($campaign_send_date_time) ? $campaign_send_date_time : Carbon::now()->toDateTimeString();
        $voiceSMS->priority = $priority;
        $voiceSMS->is_campaign_scheduled = !empty($is_campaign_scheduled) ? $is_campaign_scheduled : 0;
        $voiceSMS->message_credit_size = $message_credit_size;
        $voiceSMS->total_contacts = $total_contacts;
        $voiceSMS->total_block_number = $total_block_number;
        $voiceSMS->total_credit_deduct = $total_credit_deduct;
        $voiceSMS->ratio_percent_set = ($ratio_percent_set==null) ? 0 : $ratio_percent_set;
        $voiceSMS->failed_ratio = $failed_ratio;
        $voiceSMS->is_update_auto_status = (($ratio_percent_set > 0) || ($failed_ratio > 0) ? 0 : 1);
        $voiceSMS->is_read_file_path = $is_read_file_path;
        $voiceSMS->status = $status;
        $voiceSMS->save();
        DB::commit();
        return $voiceSMS;
    } catch (\Throwable $e) {
        \Log::error($e);
        \DB::rollback();
        return false;
    }
}

function executeVoiceQuery($data)
{
    \DB::table('voice_sms_queues')->insert($data);
    return true;
}

function checkObdType($dtmf, $call_patch_number, $otp=null)
{
    if(!empty($otp))
    {
        $obd_type = 4; // OTP
    }
    elseif(!empty($dtmf) && !empty($call_patch_number))
    {
        $obd_type = 3; // CallPatch
    }
    elseif(!empty($dtmf) && empty($call_patch_number))
    {
        $obd_type = 2; // DTMF
    }
    else
    {
        $obd_type = 1; // SINGLE_VOICE
    }
    return $obd_type;
}

function getObdType($obd_type)
{
    switch (strtoupper($obd_type)) {
        case 1:
            $obdType = 'SINGLE_VOICE';
            break;
        case 2:
            $obdType = 'DTMF';
            break;
        case 3:
            $obdType = 'CallPatch';
            break;
        default:
            $obdType = 'OTP';
            break;
    }
    return $obdType;
}

function sendVoiceSMSApi($mobileArray, $primary_route, $voiceSMS, $otp=null,$isRepeat=false)
{
    $count_mobiles = count($mobileArray);
    if($primary_route->smsc_id=='videocon')
    {
        $mobiles = implode(",", $mobileArray);

        // if single number then we just push the single SMS Api
        if(($count_mobiles==1) && in_array($voiceSMS->obd_type, [1, 2]))
        {
            $prepareArray = [
                'UserName' => $primary_route->smsc_username,
                'Password' => $primary_route->smsc_password,
                'TransitionId' => $voiceSMS->id,
                'VoiceId' => $voiceSMS->voice_id,
                'DN' => $mobiles,
                'OBD_TYPE' => getObdType($voiceSMS->obd_type),
                'DTMF' => $voiceSMS->dtmf,
                'CALL_PATCH_NO' => $voiceSMS->call_patch_number,
            ];
            $urlEndPoint = 'SINGLE_CALL';
            if(!empty($urlEndPoint))
            {
                $url = $primary_route->api_url_for_voice.$urlEndPoint;
                $response = Http::get($url, $prepareArray);
                $response = $response->json();
                return $response;
            }

        }

        // For VOICE OTP
        elseif(($count_mobiles==1) && in_array($voiceSMS->obd_type, [4]) && !empty($otp))
        {
            $prepareArray = [
                'UserName' => $primary_route->smsc_username,
                'Password' => $primary_route->smsc_password,
                'VoiceId' => $voiceSMS->voice_id,
                'DN' => $mobiles,
                'OTP' => $otp,
                'OBD_TYPE' => ($isRepeat==false) ? 'OTP_PROCESS' : 'OTP_REPEAT'
            ];
            $urlEndPoint = 'VOICE_OTP';
            if(!empty($urlEndPoint))
            {
                $url = $primary_route->api_url_for_voice.$urlEndPoint;
                $response = Http::post($url, $prepareArray);
                $response = $response->json();
                return $response;
            }
        }

        // Bulk send Voice SMS, at a time 25K numbers
        $prepareArray = [
            'UserName' => $primary_route->smsc_username,
            'Password' => $primary_route->smsc_password,
            'TransitionId' => $voiceSMS->id,
            'VoiceId' => $voiceSMS->voice_id,
            'CampaignData' => $mobiles,
            'OBD_TYPE' => getObdType($voiceSMS->obd_type),
            'DTMF' => $voiceSMS->dtmf,
            'CALL_PATCH_NO' => $voiceSMS->call_patch_number,
        ];
        $urlEndPoint = null;
        if($voiceSMS->obd_type==1)
        {
            // http://103.132.146.183/OBD_REST_API/api/OBD_Rest/Campaign_Creation
            // SINGLE_VOICE
            $urlEndPoint = 'Campaign_Creation';
        }
        elseif($voiceSMS->obd_type==2)
        {
            // http://103.132.146.183/OBD_REST_API/api/OBD_Rest/Campaign_CreationDTMF
            // DTMF
            $urlEndPoint = 'Campaign_CreationDTMF';
        }
        elseif($voiceSMS->obd_type==3)
        {
            //  http://103.132.146.183/OBD_REST_API/api/OBD_Rest/Campaign_CreationCallPatch
            // CallPatch
            $urlEndPoint = 'Campaign_CreationCallPatch';
        }

        if(!empty($urlEndPoint))
        {
            $url = $primary_route->api_url_for_voice.$urlEndPoint;
            $response = Http::post($url, $prepareArray);
            $response = $response->json();
            return $response;
        }
        return false;
    }
}

function setCampaignExecuter($send_sms_id, $campaign_send_date_time, $campaign_type)
{
    $campaignExecuter = new CampaignExecuter;
    $campaignExecuter->send_sms_id = $send_sms_id;
    $campaignExecuter->campaign_send_date_time = (empty($campaign_send_date_time) ? Carbon::now()->toDateTimeString() : $campaign_send_date_time);
    $campaignExecuter->campaign_type = $campaign_type;
    $campaignExecuter->save();
    
    return true; 
}


function getVoiceCampaignSummeryServer($campaign_id, $primary_route)
{
    $response = null;
    if($primary_route->smsc_id=='videocon')
    {
        $prepareArray = [
            'UserName' => $primary_route->smsc_username,
            'Password' => $primary_route->smsc_password,
            'campaignid' => $campaign_id
        ];

        $urlEndPoint = "Campaign_Summary";
        $url = $primary_route->api_url_for_voice.$urlEndPoint;
        $response = Http::get($url, $prepareArray);
        $response = $response->json();
        return $response;
    }

    return $response;
}

function getVoiceCampaignCallingDetailServer($campaign_id, $primary_route)
{
    $response = null;
    if($primary_route->smsc_id=='videocon')
    {
        $prepareArray = [
            'UserName' => $primary_route->smsc_username,
            'Password' => $primary_route->smsc_password,
            'campaignid' => $campaign_id
        ];

        $urlEndPoint = "Campaign_Call_Details";
        $url = $primary_route->api_url_for_voice.$urlEndPoint;
        $response = Http::get($url, $prepareArray);
        $response = $response->json();
        return $response;
    }

    return $response;
}

function updateVoiceReportAuto($voice_sms_id, $totalBunch=50000)
{
    $time = time();

    //Delivered
    \DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
    `stat` = 'Answered',
    `cli` = ".$time.",
    `flag` = null,
    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
    `dtmf` = null,
    `status` = 'Completed'
    WHERE `is_auto`= 1 AND `stat` = 'Pending' AND `voice_sms_id` = '".$voice_sms_id."' LIMIT ".$totalBunch.";");

    \DB::statement("UPDATE `voice_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
    `stat` = 'Answered',
    `cli` = ".$time.",
    `flag` = null,
    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
    `dtmf` = null,
    `status` = 'Completed'
    WHERE `is_auto`= 1 AND `stat` = 'Pending' AND `voice_sms_id` = '".$voice_sms_id."' LIMIT ".$totalBunch.";");

    return true;
}

function voiceReUpdatePending($voice_sms_id, $totalBunch=25000)
{
    updateVoiceReportAuto($voice_sms_id, $totalBunch);
    sleep(1);
    $checkAllUpdateQueue = \DB::table('voice_sms_queues')
        ->select('id')
        ->where('is_auto', '!=', 0)
        ->where('stat', 'Pending')
        ->where('voice_sms_id', $voice_sms_id)
        ->count();
    $checkAllUpdateHistory = \DB::table('voice_sms_histories')
        ->select('id')
        ->where('is_auto', '!=', 0)
        ->where('stat', 'Pending')
        ->where('voice_sms_id', $voice_sms_id)
        ->count();
    $totalRecords = ($checkAllUpdateQueue + $checkAllUpdateHistory);
    $checkTotalPage = ceil($totalRecords / $totalBunch);
    if($totalRecords > 0)
    {
        for ($i=1; $i <= $checkTotalPage ; $i++) 
        { 
            usleep(500);
            updateVoiceReportAuto($voice_sms_id, $totalBunch);
        }

        \DB::table('voice_sms')
        ->where('id', $voice_sms_id)
        ->update(['is_update_auto_status' => 1]);
    }
    
    return true;
}

function updateAllTypeVoiceStatusReport($voice_sms_id)
{
    \DB::statement("UPDATE `voice_sms` SET 
    `total_delivered` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `voice_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'Answered') + (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `voice_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'Answered'), 

    `total_failed` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `voice_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` NOT IN ('Pending','Process','Invalid','BLACK','Answered')) + (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `voice_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` NOT IN ('Pending','Process','Invalid','BLACK','Answered')),

    `total_block_number` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `voice_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'BLACK') + (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `voice_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'BLACK'),

    `total_invalid_number` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `voice_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'Invalid') + (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `voice_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'Invalid'),

    `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
    
    WHERE `id` = $voice_sms_id AND `status`='Ready-to-complete';");

    return true;
}

// Whatsapp
function whatsAppConfiguration($configuration_id, $user_id=null)
{
    $userId = !empty($user_id) ? $user_id : auth()->id();
    return WhatsAppConfiguration::where('user_id',$userId)
        ->find($configuration_id);
}

function checkKannelQueueStatus()
{
    $password = env('KANNEL_ADMIN_PASS', 'nrt_inc_2010');
    $kannel_ip = env('KANNEL_IP', '68.178.162.45');
    $url = "http://$kannel_ip:13000/status.xml?password=$password";
    $xml = simplexml_load_file($url);
    if ($xml === false) {
        \Log::error('Failed loading Kannel Status:');
    }
    else
    {
        if($xml->sms->sent->queued<50000)
        {
            return true;
        }
        else
        {
            sleep(5); // if queue generate wait time
            checkKannelQueueStatus(); // recheck after wait time
        }
    }
    return true;
}


function createWACampaign($user_id, $campaign, $whats_app_configuration_id, $whats_app_template_id, $country_id, $sender_number, $message, $file_path, $file_mobile_field_name, $campaign_send_date_time, $total_contacts, $total_block_number, $total_credit_deduct, $ratio_percent_set, $status, $msg_category, $chargesPerMsg, $is_read_file_path=1, $reschedule_whats_app_send_sms_id=null, $reschedule_type=null, $failed_ratio=null, $is_campaign_scheduled=null)
{
    \DB::beginTransaction();
    try {
        $waSendSMS = new WhatsAppSendSms;
        $waSendSMS->user_id = $user_id;
        $waSendSMS->campaign = $campaign;
        $waSendSMS->whats_app_configuration_id = $whats_app_configuration_id;
        $waSendSMS->whats_app_template_id = $whats_app_template_id;
        $waSendSMS->country_id = $country_id;
        $waSendSMS->sender_number = $sender_number;
        $waSendSMS->message = $message;
        $waSendSMS->file_path = $file_path;
        $waSendSMS->file_mobile_field_name = $file_mobile_field_name;
        $waSendSMS->campaign_send_date_time = !empty($campaign_send_date_time) ? $campaign_send_date_time : Carbon::now()->toDateTimeString();
        $waSendSMS->is_campaign_scheduled = !empty($is_campaign_scheduled) ? $is_campaign_scheduled : 0;
        $waSendSMS->message_category = $msg_category;
        $waSendSMS->charges_per_msg = $chargesPerMsg;
        $waSendSMS->total_contacts = $total_contacts;
        $waSendSMS->total_block_number = $total_block_number;
        $waSendSMS->total_credit_deduct = $total_credit_deduct;
        $waSendSMS->ratio_percent_set = ($ratio_percent_set==null) ? 0 : $ratio_percent_set;
        $waSendSMS->failed_ratio = $failed_ratio;
        $waSendSMS->is_update_auto_status = (($ratio_percent_set > 0) || ($failed_ratio > 0) ? 0 : 1);
        $waSendSMS->is_read_file_path = $is_read_file_path;
        $waSendSMS->status = $status;
        $waSendSMS->reschedule_whats_app_send_sms_id = $reschedule_whats_app_send_sms_id;
        $waSendSMS->reschedule_type = $reschedule_type;
        $waSendSMS->save();
        DB::commit();
        return $waSendSMS;
    } catch (\Throwable $e) {
        \Log::error($e);
        \DB::rollback();
        return false;
    }
}

function executeWAQuery($data)
{
    \DB::table('whats_app_send_sms_queues')->insert($data);
    return true;
}

function executeBatchQuery($batch)
{
    \DB::table('whats_app_batches')->insert($batch);
    return true;
}

function executeWAReplyThreds($data)
{
    \DB::table('whats_app_reply_threads')->insert($data);
    return true;
}

function findVariableCounts($variablesArr, $findVar)
{
    $found = 0;
    foreach ($variablesArr as $variable) 
    {
        if (stripos($variable, $findVar) !== false) 
        {
            $found++;
        }
    }
    return $found;
}

function getWATemplateType($value=null)
{
    switch (strtolower($value)) {
        case strtolower('none'):
            $templateType = 'none';
            break;
        case strtolower('TEXT'):
            $templateType = 'TEXT';
            break;
        case strtolower('Media'):
            $templateType = 'MEDIA';
            break;
        case strtolower('IMAGE'):
            $templateType = 'MEDIA';
            break;
        case strtolower('VIDEO'):
            $templateType = 'MEDIA';
            break;
        case strtolower('DOCUMENT'):
            $templateType = 'MEDIA';
            break;
        case strtolower('LOCATION'):
            $templateType = 'MEDIA';
            break;
        default:
            $templateType = 'none';
            break;
    }
    return $templateType;
}

function prapareWAComponent($type, $variables, $parameter_format, $parameter_names=null, $template_type=null, $media_type=null, $titleOrFileName=null,$latitude=null,$longitude=null,$location_name=null,$location_address=null)
{
    if($template_type=='MEDIA')
    {
        if(preg_match('#^https?://#', $variables))
        {
            $link = $variables;
        }
        else
        {
            $link = env('APP_URL').'/'.$variables;
        }
        
        if($media_type=='document')
        {
            $parameter[] = [
                'type' => $media_type,
                $media_type => [
                    'link' => $link,
                    'filename' => $titleOrFileName
                ]
            ];
        }
        elseif($media_type=='location')
        {
            $parameter[] = [
                'type' => $media_type,
                $media_type => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'name' => $location_name,
                    'address' => $location_address
                ]
            ];
        }
        else
        {
            $parameter[] = [
                'type' => $media_type,
                $media_type => [
                    'link' => $link
                ]
            ];
        }
        
        $componentPrapare = [
            [
                'type' => $type,
                'parameters' => $parameter
            ]
        ];
        return $component = $componentPrapare;
    }
    else
    {
        if(is_array($variables) && count($variables)>0)
        {
            $parameter = [];
            if($parameter_format=='NAMED')
            {
                foreach ($variables as $key => $variableVal) 
                {
                    $parameter[] = [
                        'type' => 'text',
                        'parameter_name' => $parameter_names[$key],
                        'text' => $variableVal['text']
                    ];
                }
            }
            else
            {
                foreach ($variables as $key => $variableVal) 
                {
                    $parameter[] = [
                        'type' => 'text',
                        'text' => $variableVal
                    ];
                }
            }
            

            $componentPrapare = [
                [
                    'type' => $type,
                    'parameters' => $parameter
                ]
            ];
            return $component = $componentPrapare;
        }
    }

    return [];
}

function getWaTempType($sub_type)
{
    switch (strtolower($sub_type)) {
        case 'copy_code':
            $type = 'coupon_code';
            break;

        case 'catalog':
            $type = 'action';
            break;

        case 'flow':
            $type = 'action';
            break;

        case 'url':
            $type = 'text';
            break;
        
        default:
            $type = 'text';
            break;
    }
    return $type;
}

function prapareWAButtonComponent($buttons, $urlArray=null, $coupon_code=null, $catalog_code=null, $flow_code=null)
{
    if(count($buttons)>0)
    {
        $parameter = [];
        $parameter_value = null;
        $parameter_index = 0;
        foreach ($buttons as $key => $button) 
        {
            $sub_type = $button->button_type;
            if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG', 'FLOW']))
            {
                $type = getWaTempType(strtolower($sub_type));

                if($sub_type=='URL' && is_array($urlArray) && count($urlArray)>0 && str_contains($button->button_value, '{{1}}'))
                {
                    $parameter_value = $urlArray[$parameter_index];
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $key, 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => (empty($parameter_value) ? '/' : $parameter_value)
                            ]
                        ]
                    ];
                    $parameter_index++;
                }
                elseif($sub_type=='COPY_CODE' && !empty($coupon_code))
                {
                    $parameter_value = $coupon_code;
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $key, 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => $parameter_value
                            ]
                        ]
                    ];
                }
                elseif($sub_type=='CATALOG' && is_array($catalog_code) && count($catalog_code)>0)
                {
                    $parameter_value = $catalog_code[$parameter_index];
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $key, 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => [
                                    'thumbnail_product_retailer_id' => $parameter_value
                                ] 
                            ]
                        ]
                    ];
                    $parameter_index++;
                }
                elseif($sub_type=='FLOW' && is_array($flow_code) && count($flow_code)>0)
                {
                    $parameter_value = $flow_code[$parameter_index];
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $key, 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => [
                                    'flow_token' => $parameter_value
                                ] 
                            ]
                        ]
                    ];
                    $parameter_index++;
                }
            }
        }
        return $parameter = $parameter;
    }

    return [];
}

function prapareWAButtonComponentForApi($buttons=null)
{
    if(count($buttons)>0)
    {
        $parameter = [];
        foreach ($buttons as $key => $button) 
        {
            $sub_type = strtoupper($button['type']);
            if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG', 'FLOW','QUICK_REPLY']))
            {
                $type = getWaTempType(strtolower($sub_type));

                if($sub_type=='URL')
                {
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $button['index'], 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => (empty($button['payload']) ? '/' : $button['payload'])
                            ]
                        ]
                    ];
                }
                elseif($sub_type=='COPY_CODE')
                {
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $button['index'], 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => $button['payload']
                            ]
                        ]
                    ];
                }
                elseif($sub_type=='CATALOG')
                {
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $button['index'], 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => [
                                    'thumbnail_product_retailer_id' => $button['thumbnail_product_retailer_id']
                                ] 
                            ]
                        ]
                    ];
                }
                elseif($sub_type=='FLOW')
                {
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $button['index'], 
                        'parameters' => [
                            [
                                'type' => $type,
                                $type => [
                                    'flow_token' => $button['flow_token']
                                ] 
                            ]
                        ]
                    ];
                }
                elseif($sub_type=='QUICK_REPLY')
                {
                    $parameter[] = [
                        'type' => 'button',
                        "sub_type" => $sub_type,
                        "index" => $button['index'], 
                        'parameters' => [
                            [
                                'type' => "payload",
                                'payload' => $button['payload']
                            ]
                        ]
                    ];
                }
            }
        }
        return $parameter = $parameter;
    }

    return [];
}


function waMsgPayload($mobile_number, $template_name, $language, $component, $template_type=null)
{
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $mobile_number,
        'type' => 'template',
        'template' => [
            'name' => $template_name,
            'language' => [
                'code' => $language
            ],
            'components' => $component
        ],  
    ];
    return $payload;
}

function prapareWAComponentSample($type, $variables, $parameter_format, $parameter_names=null, $template_type=null, $media_type=null, $titleOrFileName=null,$latitude=null,$longitude=null,$location_name=null,$location_address=null)
{
    if($template_type=='MEDIA')
    {
        if(preg_match('#^https?://#', $variables))
        {
            $link = $variables;
        }
        else
        {
            $link = env('APP_URL').'/'.$variables;
        }
        
        if($media_type=='document')
        {
            $parameter['media'] = [
                'type' => $media_type,
                'url' => $link,
                'filename' => $titleOrFileName
            ];
        }
        elseif($media_type=='location')
        {
            $parameter['media'] = [
                'type' => $media_type,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $location_name,
                'address' => $location_address
            ];
        }
        else
        {
            $parameter['media'] = [
                'type' => $media_type,
                'url' => $link
            ];
        }
        
        $componentPrapare = $parameter;
        return $componentPrapare;
    }
    else
    {
        if(is_array($variables) && count($variables)>0)
        {
            $parameter = [];
            if($parameter_format=='NAMED')
            {
                foreach ($variables as $key => $variableVal) 
                {
                    $parameter[] = [
                        'type' => 'text',
                        'parameter_name' => $parameter_names[$key],
                        'text' => $variableVal
                    ];
                }
                $componentPrapare = [
                    $type => $parameter
                ];
            }
            else
            {
                $componentPrapare = [
                    $type => $variables
                ];
            }
            
            return $componentPrapare;
        }
    }

    return [];
}

function prapareWAButtonComponentSample($buttons, $urlArray=null, $coupon_code=null, $catalog_code=null, $flow_code=null)
{
    if(count($buttons)>0)
    {
        $parameter = [];
        $quickReplies = [];
        $parameter_value = null;
        $parameter_index = 0;
        foreach ($buttons as $key => $button) 
        {
            $sub_type = $button->button_type;
            if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG', 'FLOW', 'QUICK_REPLY']))
            {
                $type = getWaTempType(strtolower($sub_type));

                if($sub_type=='URL' && is_array($urlArray) && count($urlArray)>0 && str_contains($button->button_value, '{{1}}'))
                {
                    $parameter_value = $urlArray[$parameter_index];
                    $parameter[] = [
                        'type' => $type,
                        'index' => $key, 
                        'payload' => (empty($parameter_value) ? '/' : $parameter_value)
                    ];
                    $parameter_index++;
                }
                elseif($sub_type=='COPY_CODE' && !empty($coupon_code))
                {
                    $parameter_value = $coupon_code;
                    $parameter[] = [
                        'type' => $type,
                        'index' => $key, 
                        'payload' => $parameter_value[0]
                    ];
                }
                elseif($sub_type=='CATALOG' && is_array($catalog_code) && count($catalog_code)>0)
                {
                    $parameter_value = $catalog_code[$parameter_index];
                    $parameter[] = [
                        'type' => $type,
                        'index' => $key,
                        'payload' => [
                            'thumbnail_product_retailer_id' => $parameter_value
                        ] 
                    ];
                    $parameter_index++;
                }
                elseif($sub_type=='FLOW' && is_array($flow_code) && count($flow_code)>0)
                {
                    $parameter_value = $flow_code[$parameter_index];
                    $parameter[] = [
                        'type' => $type,
                        'index' => $key,
                        'payload' => [
                            'flow_token' => $parameter_value
                        ]
                    ];
                    $parameter_index++;
                }
                elseif($sub_type=='QUICK_REPLY')
                {
                    $parameter_value = $button->button_text;
                    $quickReplies[] = [
                        'type' => $type,
                        'index' => $key,
                        'payload' => $parameter_value
                    ];
                    $parameter_index++;
                }
            }
        }

        $buttons = [];

        if (!empty($parameter)) {
            $buttons['actions'] = $parameter;
        }

        if (!empty($quickReplies)) {
            $buttons['quickReplies'] = $quickReplies;
        }

        return $parameter = [
            'buttons' => $buttons,
        ];
        
    }

    return [];
}

function waMsgPayloadSample($mobile_number, $template_name, $language, $component, $from_number,$template_type=null)
{
    /*
        {
            "message": {
                "content": {
                    "type": "TEMPLATE",
                    "language": "hi",
                    "template": {
                        "templateId": "promotion_text_only",
                        "headerParameterValue": {
                            "0": "HEAD"
                        },
                        "bodyParameterValues": {
                            "0": "B1",
                            "1": "B2",
                            "2": "B3"
                        },
                        "media": {
                            "type": "image",
                            "url": "https://ok-go.in/whatsapp-file/file_name.jpg"
                        },
                    }
                },
                "recipient": {
                    "to": "919713753131",
                    "recipient_type": "individual"
                },
                "sender": {
                    "from": "15550622381"
                }
            }
        }
    */
    /*
    # we can also use array_merge 
    array_merge(
        ['templateId' => $template_name],
        $component
    )
    */

    $payload = [
        'message' => [
            'content' => [
                'type' => (($template_type=='MEDIA') ? 'MEDIA_TEMPLATE' : 'TEMPLATE'),
                'language' => $language,
                'preview_url' => true,
                'shorten_url' => false,
                'template' => [
                    'templateId' => $template_name,
                    ...$component //spread operator
                ]
            ],
            'recipient' => [
                'to' => $mobile_number,
                'recipient_type' => 'individual'
            ],
            'sender' => [
                'from' => $from_number
            ]
        ]
    ];
    return $payload;
}

function wAMessageSend($access_token, $sender_number, $appVersion, $templateName, $payload)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";
    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload)->throw();
        return [
            'error' => false,
            'response' => $response->body()
        ];
    } catch (\Throwable $e) {
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function SubscribeWaApp($access_token, $waba_id, $appVersion)
{
    try {
        $url = "https://graph.facebook.com/$appVersion/$waba_id/subscribed_apps";
        $payload = [
          "access_token" => $access_token
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload)->throw();
        if($response->successful())
        {
            return [
                'error' => false,
                'response' => json_decode($response->body(), true)
            ];
        }
        else
        {
            \Log::error('FB Flow Subscribe WA App error');
            return [
                'error' => true,
                'response' => $response['error']['message']
            ];
        }
    } catch (\Throwable $e) {
        \Log::error($e);
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function checkQualitySignalReport($access_token, $sender_number, $appVersion)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number";

    $response = Http::withHeaders([
        'Authorization' =>  'Bearer ' . $access_token,
        'Content-Type' => 'application/json' 
    ])
    ->get($url);
    return $response->body();
}

function waPhoneNumberRequestToRegister($access_token, $sender_number, $appVersion, $pin=123456)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/register";
    $payload = [
        'messaging_product' => 'whatsapp',
        'pin' => (int) $pin
    ];

    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload);
        return $response->body();
    } catch(\Throwable $e) {
        \Log::error($e->getMessage());
        return $e->getMessage();
    }
}

function waPhoneNumberRequestToVerify($access_token, $sender_number, $appVersion, $otp_method, $language='en_US')
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/request_code";
    $payload = [
        'code_method' => $otp_method,
        'language' => $language
    ];

    $response = Http::withHeaders([
        'Authorization' =>  'Bearer ' . $access_token,
        'Content-Type' => 'application/json' 
    ])
    ->post($url, $payload);
    return $response->body();
}

function waPhoneNumberVerify($access_token, $sender_number, $appVersion, $code)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/verify_code";
    $payload = [
        'code' => $code
    ];

    $response = Http::withHeaders([
        'Authorization' =>  'Bearer ' . $access_token,
        'Content-Type' => 'application/json' 
    ])
    ->post($url, $payload);
    return $response->body();
}

function wAReplyMessageRead($access_token, $sender_number, $appVersion, $response_token)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";

    $payload = [
        "messaging_product" => "whatsapp",
        "status" => "read",
        "message_id" => $response_token
    ];

    $response = Http::withHeaders([
        'Authorization' =>  'Bearer ' . $access_token,
        'Content-Type' => 'application/json' 
    ])
    ->post($url, $payload);
    return $response->body();
}

function waSendReplyMsg($access_token, $sender_number, $appVersion, $toNumber, $type, $message, $response_token=null, $file_name=null)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";

    $component = [];
    if($type=='text')
    {
        $component = [
            "preview_url" => true,
            "body" => $message
        ];
    }
    elseif($type=='image' || $type=='video')
    {
        $component = [
            "link" => $message
        ];
    }
    elseif($type=='document')
    {
        $component = [
            "link" => $message,
            "filename" => $file_name
        ];
    }

    if(!empty($response_token))
    {
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toNumber,
            "context" => [
                "message_id" => $response_token
            ],
            "type" => $type,
            $type => $component
        ];
    }
    else
    {
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $toNumber,
            "type" => $type,
            $type => $component
        ];
    }
    
    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload);
        //\Log::info($response);
        return [
            'error' => false,
            'response' => $response->body()
        ];
    } catch(\Throwable $e) {
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function recheckCampaignStatusB4Process($send_sms_id)
{
    $campaign = \DB::table('send_sms')->select('id')
        ->where('is_read_file_path', 0)
        ->where('status', 'Pending')
        ->find($send_sms_id);
    return ($campaign ? true : false);
}

function recheckVoiceCampaignStatusB4Process($voice_sms_id)
{
    $campaign = \DB::table('voice_sms')->select('id')
        ->where('is_read_file_path', 0)
        ->where('status', 'Pending')
        ->find($voice_sms_id);
    return ($campaign ? true : false);
}

function validateWAFile($file, $type, $mimeType, $file_size)
{
    $type = strtolower($type);
    $mimeType = strtolower($mimeType);
    $is_RGB_A = true;

    switch ($type) {
        case 'image':
            $allowed_format = ['jpeg','jpg','png'];
            $allowed_mime_type = [
                'image/jpeg', 
                'image/png'
            ];
            $allowed_size = 5*1024*1024;  // 5MB
            //$is_RGB_A = isAlphaImage($file);
            break;
        case 'video':
            $allowed_format = ['mp4','3gp'];
            $allowed_mime_type = [
                'video/mp4', 
                'video/3gp'
            ];
            $allowed_size = 16*1024*1024;  // 16MB
            break;
        case 'audio':
            $allowed_format = ['aac','mp4','mpeg','amr','ogg'];
            $allowed_mime_type = [
                'audio/aac', 
                'audio/mp4', 
                'audio/mpeg', 
                'audio/amr', 
                'audio/ogg'
            ];
            $allowed_size = 16*1024*1024;  // 16MB
            break;
        case 'document':
            $allowed_format = ['txt','pdf','xls','csv','doc','docx','xlsx', 'ppt', 'pptx'];
            $allowed_mime_type = [
                'text/plain', 
                'application/pdf', 
                'application/vnd.ms-powerpoint', 
                'application/msword', 
                'application/vnd.ms-excel', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                'application/vnd.openxmlformats-officedocument.presentationml.presentation', 
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            $allowed_size = 100*1024*1024;  // 100MB
            break;
        case 'sticker':
            $allowed_format = ['webp'];
            $allowed_mime_type = [
                'image/webp'
            ];
            $allowed_size = 100*1024;  // 100KB
            break;
        
        default:
            return false;
            break;
    }

    $is_allowed = (in_array($mimeType, $allowed_mime_type) && $allowed_size>$file_size) ? true : false;

    $obj = [
        'allowed_format' => $allowed_format,
        'allowed_mime_type' => $allowed_mime_type,
        'file_mime_type' => $mimeType,
        'file_size_mb' => round($file_size/(1024*1024), 2),
        'allowed_size_mb' => round($allowed_size/(1024*1024), 2),
        'is_allowed' => $is_allowed,
        'is_RGB_A' => $is_RGB_A
    ];
    return $obj;
}

function isAlphaImage($file)
{
    /* The color type of PNG image is stored at byte offset 25. Possible values of that 25'th byte is:
        * 0 - greyscale
        * 2 - RGB
        * 3 - RGB with palette
        * 4 - greyscale + alpha
        * 6 - RGB + alpha
    */
    return (ord(@file_get_contents($file, NULL, NULL, 25, 1)) == 2 || ord(@file_get_contents($file, NULL, NULL, 25, 1)) == 6);
}

function applyRandRatio($dlt_template_id)
{
    $ratioEnabled = ['1307168787130510484','1307168787117626876','1307166903534620576','1307173149728947156'];
    //$ratioEnabled = [];
    if(in_array($dlt_template_id, $ratioEnabled))
    {
        //$day = date('l', strtotime("Sunday +1 days"));
        $day = date('l');

        switch ($day) {
            case 'Sunday':
                $array = [0,0,0,0,1,0,0,0,0,1]; // 20-22
                break;
            case 'Saturday':
                $array = [0,1,0,0,1,0,1,0,0,0]; // 29-32
                break;
            case 'Friday':
                $array = [0,1,0,0,1,0,1,0,0,1]; // 39-42
                break;
            case 'Thursday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Wednesday':
                $array = [0,1,1,0,1,0,1,0,1,1]; // 59-61
                break;
            case 'Tuesday':
                $array = [0,1,1,0,1,1,1,0,1,1]; // 69-71
                break;
            case 'Monday':
                $array = [1,1,1,0,1,1,1,0,1,1]; // 79-81
                break;
            default:
                $array = [0];
                break;
        }
        

        /*
        // Comment for now
        switch ($day) {
            case 'Sunday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Saturday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Friday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Thursday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Wednesday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Tuesday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            case 'Monday':
                $array = [0,1,0,0,1,0,1,0,1,1]; // 49-51
                break;
            default:
                $array = [0];
                break;
        }
        */

        $status = array_rand($array, 1);
        return $array[$status];
    }
    return false;
}

function checkWaCreditDeductOrNot($user_id, $mobile_number, $message_category, $campaign_date=null)
{
    $isAllowDeduct = true;
    $checkLastMessage = \DB::table('whats_app_send_sms_queues')
        ->where('user_id', $user_id)
        ->where('mobile', $mobile_number)
        ->orderBy('id', 'DESC')
        ->first();
    if($checkLastMessage)
    {
        if($checkLastMessage->template_category == $message_category && !empty($checkLastMessage->expiration_timestamp) && strtotime($checkLastMessage->expiration_timestamp) > time())
        {
            $isAllowDeduct = false;
        }
    }

    return $isAllowDeduct;
}

function dlrGenerator($msg_id, $wh_url, $uuid, $mobile, $used_credit)
{
    try {
        $redis = Redis::connection();
    } catch(\Predis\Connection\ConnectionException $e){
        \Log::error('error connection redis- AutoDLR');
        die;
    }
    $token = generateToken();
    $redis->rpush('dlrkey', json_encode([
            "msgid"=> $msg_id,
            "d"=> '8',
            "oo"=> '00',
            "ff"=> $token,
            "s"=> '',
            "ss"=> '',
            "aa"=> 'ACK/',
            "wh_url"=> $wh_url,
            "uuid"=> $uuid,
            "mobile"=> $mobile,
            "used_credit"=> $used_credit,
            "finalDateTime"=> date('Y-m-d H:i:s')
        ])
    );

    $date_format = Carbon::now()->addSeconds(rand(2,4));


    $redis->rpush('dlrkey', json_encode([
            "msgid"=> $msg_id,
            "d"=> '1',
            "oo"=> '00',
            "ff"=> $token,
            "s"=> 'sub:001',
            "ss"=> 'dlvrd:001',
            "aa"=> 'id:'.$token.' sub:001 dlvrd:001 submit date:'.date('ymdHi').' done date:'.$date_format->format('ymdHi').' stat:DELIVRD err:000 text:',
            "wh_url"=> $wh_url,
            "uuid"=> $uuid,
            "mobile"=> $mobile,
            "used_credit"=> $used_credit,
            "finalDateTime"=> $date_format->toDateTimeString()
        ])
    );
    return true;
}

function getCountryID($iso3)
{
    return \DB::table('countries')
        ->select('id')
        ->where('iso3', $iso3)
        ->first();
}

function getCountryIDByNumber($phonecode)
{
    $country = \DB::table('countries')
        ->select('id')
        ->where('phonecode', $phonecode)
        ->first();
    return (($country) ? $country->id : null);
}

function getWACharges($template_category, $user_id, $country_id)
{
    $getChargeInfo = \DB::table('whats_app_charges')
        ->where('user_id', $user_id)
        ->where('country_id', $country_id)
        ->first();
    if(!$getChargeInfo)
    {
        $chargesPerMsg = null;
    }
    else
    {
        switch (strtolower($template_category)) {
            case 'marketing':
                $chargesPerMsg = $getChargeInfo->wa_marketing_charge;
                break;
            case 'utility':
                $chargesPerMsg = $getChargeInfo->wa_utility_charge;
                break;
            case 'service':
                $chargesPerMsg = $getChargeInfo->wa_service_charge;
                break;
            case 'authentication':
                $chargesPerMsg = $getChargeInfo->wa_authentication_charge;
                break;
            default:
                $chargesPerMsg = null;
                break;
        }
    }
    
    return $chargesPerMsg;
}

function waCheckNumberValid($mobile_number, $country_code, $min_length, $max_length) 
{
    $isValid = 0; //Invalid number
    $actualMobile = $mobile_number;
    $is_auto = 0; // by default off ratio
    $erro_info = null;
    if(is_numeric($actualMobile))
    {
        $lastEight = substr($actualMobile, -8);
        if(strlen($mobile_number) == $min_length && $min_length != $max_length)
        {
            $mobile_number = $country_code.$mobile_number;
            $isValid = 1; //valid number
        }
        elseif(strlen($mobile_number) == $max_length)
        {
            $getInitials = substr($actualMobile, 0, strlen($country_code));
            if($getInitials==$country_code)
            {
                $mobile_number = $mobile_number;
                $isValid = 1; //valid number
            }
        }
        elseif(strlen($mobile_number) < $min_length || strlen($mobile_number) > $max_length)
        {
            $erro_info = json_encode([
                'error' => true,
                'response' => 'Mobile number length not valid'
            ]);
        }
        elseif($lastEight < 1)
        {
            $erro_info = json_encode([
                'error' => true,
                'response' => 'Mobile number not allowed due to last 8 digit is 0'
            ]);
        }
        else
        {
            $erro_info = json_encode([
                'error' => true,
                'response' => 'Mobile number not valid for selected campaign country'
            ]);
        }
    }
    $returnData = [
        'mobile_number' => $mobile_number,
        'is_auto'       => $is_auto,
        'number_status' => $isValid,
        'erro_info'     => $erro_info
    ];

    return $returnData;
}

function genSHA256($tagId, $string)
{
    $hash = hash('sha256', $string);
    return $hash;
}

function testWebhookUrl($url)
{
    try {
        $response = Http::timeout(5)->post($url, ['webhook' => 'Connection established.']);
        if ($response->status() < 200 || $response->status() >= 300) 
        {
            return false;
        }
        return true;
    } catch (\Exception $e) {
        return false;
    }
    return false;
}

function waGenerateQRCode($access_token, $sender_number, $appVersion, $prefilled_message, $generate_qr_image="SVG")
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/message_qrdls";

    $payload = [
        "prefilled_message" => $prefilled_message,
        "generate_qr_image" => $generate_qr_image
    ];

    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload);
        if($response->successful())
        {
            return [
                'error' => false,
                'response' => json_decode($response->body(), true)
            ];
        }
        else
        {
            return [
                'error' => true,
                'response' => $response['error']['message']
            ];
        }
        
    } catch(\Throwable $e) {
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function waDeleteQRCode($access_token, $sender_number, $appVersion, $code)
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/message_qrdls/$code";

    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->delete($url);
        if($response->successful())
        {
            return [
                'error' => false,
                'response' => json_decode($response->body(), true)
            ];
        }
        else
        {
            return [
                'error' => true,
                'response' => $response['error']['message']
            ];
        }
        
    } catch(\Throwable $e) {
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function waQrcodesSync($access_token, $sender_number, $appVersion, $generate_qr_image="SVG")
{
    $url = "https://graph.facebook.com/$appVersion/$sender_number/message_qrdls?fields=code,deep_link_url,prefilled_message,qr_image_url.format($generate_qr_image)";

    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->get($url);
        if($response->successful())
        {
            return [
                'error' => false,
                'response' => json_decode($response->body(), true)
            ];
        }
        else
        {
            return [
                'error' => true,
                'response' => $response['error']['message']
            ];
        }
        
    } catch(\Throwable $e) {
        return [
            'error' => true,
            'response' => $e->getMessage()
        ];
    }
}

function waPullAllTemplatePaging($accessToken, $user_id, $configuration_id, $nextUrl=null, $afterCursor=null)
{
    if(!empty($nextUrl))
    {
        //\Log::info($nextUrl);
        $client = new Client();
        $response = $client->get($nextUrl, [
            'query' => [
                'access_token' => $accessToken,
                'limit' => env('WA_PAGE_LIMIT', 25),
                'after' => $afterCursor,
            ],
        ]);

        // Decode the JSON response
        $allTemplates = json_decode($response->getBody(), true);
        //\Log::info('api trigger');
        //\Log::info($allTemplates['paging']);
        createWaTemplatePull($accessToken, $user_id, $configuration_id, $allTemplates);
    }
    return null;
}

function createWaTemplatePull($accessToken, $user_id, $configuration_id, $data)
{
    foreach (@$data['data'] as $key => $responseData)
    {
        $status = (($responseData['status']=='APPROVED') ? '1' : '0');

        DB::beginTransaction();

        $header_variable = '';
        $header_handle = '';

        if(@$responseData['components']['format']=='TEXT')
        {
            $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_text'][0] : [];
            //$header_variable = (!empty($header_text) ?  implode(', ', $header_text) : NULL);
        } 
        else 
        {
            $header_handle_wa[] = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_handle'][0] : @$responseData['components'][1]['example']['header_handle'][0];

            $header_handle = (!empty($header_handle_wa) ? implode(', ', $header_handle_wa) : NULL);
        }

        $wa_app_template = WhatsAppTemplate::where('user_id', $user_id)->where('wa_template_id', @$responseData['id'])->first();
        if(!$wa_app_template)
        {
            $wa_app_template = new WhatsAppTemplate;
        }

        $headerVariable = null;
        $bodyVariable = null;
        $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['text'] : NULL;
        $totalVariablesInHeader = substr_count($header_text, "{{");
        for ($i=1; $i <= $totalVariablesInHeader; $i++) 
        { 
            $headerVariable[] = '{{'.$i.'}}';
        }

        $body_text = ((@$responseData['components'][0]['type']=='BODY') ? @$responseData['components'][0]['text'] : @$responseData['components'][1]['text']);
        $totalVariablesInBody = substr_count($body_text, "{{");
        for ($i=1; $i <= $totalVariablesInBody; $i++) 
        { 
            $bodyVariable[] = '{{'.$i.'}}';
        }

        $footer_text = ((@$responseData['components'][1]['type']=='FOOTER') ? @$responseData['components'][1]['text'] : ((@$responseData['components'][2]['type']=='FOOTER') ? @$responseData['components'][2]['text'] : NULL));
       
        $wa_app_template->user_id  = $user_id;
        $wa_app_template->whats_app_configuration_id  = $configuration_id;
        $wa_app_template->wa_template_id  = @$responseData['id'];
        $wa_app_template->parameter_format  = $responseData['parameter_format'];
        $wa_app_template->category  = $responseData['category'];
        $wa_app_template->template_language  = $responseData['language'];
        $wa_app_template->template_name  = $responseData['name'];
        $wa_app_template->template_type  = getWATemplateType(@$responseData['components'][0]['format']);
        $wa_app_template->header_text  = $header_text;
        $wa_app_template->header_variable  = (!empty($headerVariable) ? json_encode($headerVariable) : null);
        $wa_app_template->media_type  = (!in_array(@$responseData['components'][0]['format'], [null, '', 'none', 'TEXT']) ? @$responseData['components'][0]['format'] : null);
        $wa_app_template->header_handle  = $header_handle;
        $wa_app_template->message  = $body_text;
        $wa_app_template->message_variable  = (!empty($bodyVariable) ? json_encode($bodyVariable) : null);
        $wa_app_template->footer_text  =  $footer_text;
        $wa_app_template->status  = $status;
        $wa_app_template->wa_status  = @$responseData['status'];
        $wa_app_template->save();

        //delete all old records if exists
        WhatsAppTemplateButton::where('whats_app_template_id', $wa_app_template->id)->delete();
        if(@$responseData['components'][1]['type']=='BUTTONS' || @$responseData['components'][2]['type']=='BUTTONS' || @$responseData['components'][3]['type']=='BUTTONS')
        {
            if(!empty(@$responseData['components'][1]['type']) && @$responseData['components'][1]['type']=='BUTTONS')
            {
                $buttons = @$responseData['components'][1]['buttons'];
            }
            elseif(!empty(@$responseData['components'][2]['type']) && @$responseData['components'][2]['type']=='BUTTONS')
            {
                $buttons = @$responseData['components'][2]['buttons'];
            }
            else
            {
                $buttons = @$responseData['components'][3]['buttons'];
            }
            
            if(is_array(@$buttons) && count(@$buttons) > 0)
            {
                foreach (@$buttons as $key => $button) 
                {
                    $btn_text = @$button['example'];
                    $button_variables = (!empty($btn_text) ?  implode(', ', $btn_text) : NULL);
                     
                    $button_val_name = '';
                    if(!empty(@$button['phone_number']))
                    {
                        $button_val_name='phone_number';
                    }
                    if(!empty(@$button['url']))
                    {
                        $button_val_name='url';
                    }

                    $wa_app_template_btn = new WhatsAppTemplateButton;
                    $wa_app_template_btn->whats_app_template_id  = $wa_app_template->id;
                    $wa_app_template_btn->button_type  = @$button['type'];
                    $wa_app_template_btn->button_text  = @$button['text'];
                    $wa_app_template_btn->button_val_name  = strtolower(@$button['type']);
                    $wa_app_template_btn->button_value  = ($button_val_name !='' )? $button[$button_val_name]: NULL;
                    $wa_app_template_btn->button_variables  = $button_variables;
                    $wa_app_template_btn->flow_id  = @$button['flow_id'];
                    $wa_app_template_btn->flow_action  = @$button['flow_action'];
                    $wa_app_template_btn->navigate_screen  = @$button['navigate_screen'];
                    $wa_app_template_btn->save();
                    if(@$button['type']=='CATALOG')
                    {
                        $wa_app_template->media_type = 'CATALOG';
                        $wa_app_template->save();
                    }
                }
            }
        }
        DB::commit();
    }
    if(array_key_exists('next', $data['paging']))
    {
        waPullAllTemplatePaging($accessToken, $user_id, $configuration_id, $data['paging']['next'], $data['paging']['cursors']['after']);
    }
    return true;
}

function checkMobileNumberCountryCode($mobile)
{
    $numbers = \DB::table('countries')->select('phonecode')->pluck('phonecode')->toArray();
    $onecheck = substr($mobile, 0, 1);
    if($onecheck=='+')
    {
        return false;
    }
    $twocheck = substr($mobile, 0, 2);
    $threecheck = substr($mobile, 0, 3);
    if(!in_array($twocheck, $numbers))
    {
        if(!in_array($threecheck, $numbers))
        {
            return false;
        }        
    }
    return true;
}

function checkMobileNumberIndiaCountryCode($mobile)
{
    $onecheck = substr($mobile, 0, 1);
    if($onecheck=='+')
    {
        return false;
    }
    $twocheck = substr($mobile, 0, 2);
    if($twocheck!=91)
    {
        return false;       
    }
    return true;
}

function justNumber($number)
{
    $onlyNumber = preg_replace('/\s+/', '', trim($number));
    $onlyNumber = preg_replace('/\+/', '', $onlyNumber);
    return $onlyNumber;
}


function getMediaFileFromWA($access_token, $mediaId, $appVersion)
{
    $url = "https://graph.facebook.com/$appVersion/$mediaId";

    try {
        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->get($url);
        \Log::info($url);
        if($response->successful())
        {
            $jsonResponse = json_decode($response, true);
            $url = $jsonResponse['url'];
            $mime_type = $jsonResponse['mime_type'];

            $mimeMap = [
                // Images
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',

                // Video
                'video/mp4' => 'mp4',
                'video/3gpp' => '3gp',

                // Audio
                'audio/mpeg' => 'mp3',
                'audio/ogg' => 'ogg',
                'audio/amr' => 'amr',
                'audio/aac' => 'aac',

                // Documents
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',

                // Others
                'application/zip' => 'zip',
                'application/x-zip-compressed' => 'zip',
                'application/x-rar-compressed' => 'rar',
            ];

            if (!array_key_exists($mime_type, $mimeMap)) 
            {
                return $mime_type. ' Received, This file can\'t be shown on this application due to security reason. Open WhatsApp on your phone to view.';
            }

            $extension = $mimeMap[$mime_type] ?? 'bin';

            $mediaBinary = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)
            ->body();

            $destinationFolder = 'whatsapp-file/';
            $filename = $mediaId . '.' . $extension;

            File::put("{$destinationFolder}{$filename}", $mediaBinary);
            return $destinationFolder.$filename;
        }
        return 'File Received, This file can\'t be shown on this application due to security reason. Open WhatsApp on your phone to view.';
        
    } catch(\Throwable $e) {
        \Log::error('File downloading error from whatsapp');
        \Log::error($e->getMessage());
        return 'File Received, This file can\'t be shown on this application due to security reason. Open WhatsApp on your phone to view.';
    }
}

function arrayFlatten($array)
{
    $flattened = Arr::flatten($array);
    $flattened = array_values(array_unique($flattened));
    return $flattened;
}

function createWhatsappPayloadBot($to, $message, $step_type, $options = []) 
{
    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $to
    ];

    switch ($step_type) {
        case "buttons":
            $buttons = [];
            foreach ($options as $opt) {
                $buttons[] = [
                    "type" => "reply",
                    "reply" => [
                        "id" => $opt['id'],
                        "title" => $opt['title']
                    ]
                ];
            }

            $payload["type"] = "interactive";
            $payload["interactive"] = [
                "type" => "button",
                "body" => ["text" => $message],
                "action" => ["buttons" => $buttons]
            ];
            break;

        case "list":
            $rows = [];
            foreach ($options as $index => $item) {
                $rows[] = [
                    "id" => isset($item['id']) ? $item['id'] : "list_" . ($index + 1),
                    "title" => $item['title'],
                    "description" => $item['description'] ?? ""
                ];
            }

            $payload["type"] = "interactive";
            $payload["interactive"] = [
                "type" => "list",
                "header" => [
                    "type" => "text",
                    "text" => $options['listName'] ?? "Options"
                ],
                "body" => ["text" => $message],
                "footer" => ["text" => "Choose one"],
                "action" => [
                    "button" => $options['buttonName'] ?? "View Options",
                    "sections" => [
                        [
                            "title" => $options['listName'] ?? "Available Choices",
                            "rows" => $rows
                        ]
                    ]
                ]
            ];
            break;

        case "input":
            $payload["type"] = "text";
            $payload["text"] = [
                "preview_url" => false,
                "body" => $message
            ];
            break;

        default: // normal text
            $payload["type"] = "text";
            $payload["text"] = [
                "preview_url" => false,
                "body" => $message
            ];
    }

    return $payload;
}


function generateAutomationFlow(array $payload)
{
    $payload = @$payload['reducedNodes'];
    
    $steps = [];
    $idMap = [];
    $counter = 1;

    // Map original UUIDs to numeric IDs
    foreach ($payload as $block) {
        $idMap[$block['id']] = $counter++;
    }

    foreach ($payload as $block) {
        $step = [
            'id' => $idMap[$block['id']],
            'type' => null,
        ];

        switch ($block['messageType']) {
            case 'session':
                $step['type'] = 'message';
                $step['text'] = "Session Start: " . implode(', ', $block['messageData']['session']['keywords'] ?? []);
                $step['next_step'] = isset($block['next']) && $block['next'] ? $idMap[$block['next']] : null;
                break;

            case 'send-message':
                $step['type'] = 'message';
                $step['text'] = $block['messageData']['body'] ?? '';

                if (!empty($block['messageData']['saveResponseAs'])) {
                    // treat as input step instead of plain message
                    $step['type'] = 'input';
                    $step['field'] = $block['messageData']['saveResponseAs'];
                }

                if (!empty($block['messageData']['media'])) {
                    $step['media'] = [
                        'file_name'      => $block['messageData']['media']['file_name'] ?? null,
                        'file_extension' => $block['messageData']['media']['file_extension'] ?? null,
                    ];
                }

                if (!empty($block['messageData']['buttons'])) {
                    $step['type'] = 'buttons';
                    $options = [];
                    foreach ($block['messageData']['buttons'] as $btn) {
                        $options[] = [
                            'id'        => $btn['id'],
                            'title'     => $btn['title'],
                            'next_step' => isset($btn['next']) && $btn['next'] ? $idMap[$btn['next']] : null,
                        ];
                    }
                    $step['options'] = $options;
                }

                $step['next_step'] = isset($block['next']) && $block['next'] ? $idMap[$block['next']] : null;
                break;

            case 'set-condition':
                $step['type'] = 'condition';
                $cond = $block['messageData']['condition']['conditions'][0] ?? null;

                if ($cond) {
                    $step['variable'] = $cond['variable'];
                    $step['operator'] = $cond['operator'];

                    $cases = [];
                    foreach ($block['messageData']['condition']['results'] as $result) {
                        if ($result['type'] === 'true') {
                            $cases[] = [
                                'when'      => $cond['value'],
                                'next_step' => isset($result['next']) && $result['next'] ? $idMap[$result['next']] : null,
                            ];
                        } elseif ($result['type'] === 'false') {
                            $cases[] = [
                                'when'      => 'else',
                                'next_step' => isset($result['next']) && $result['next'] ? $idMap[$result['next']] : null,
                            ];
                        }
                    }
                    $step['cases'] = $cases;
                }
                break;

            case 'trigger-webhook':
                $step['type'] = 'api_call';
                $webhook = $block['messageData']['webHook'] ?? [];

                $step['method'] = $webhook['method'] ?? 'GET';
                $step['url']    = $webhook['url'] ?? '';

                $headers = [];
                if (!empty($webhook['headers'])) {
                    foreach ($webhook['headers'] as $h) {
                        $headers[$h['key']] = $h['value'];
                    }
                }
                $step['headers'] = $headers;

                $step['payload'] = $webhook['body'] ?? new \stdClass();

                if (!empty($block['messageData']['saveResponseAs'])) {
                    $step['save_response_as'] = $block['messageData']['saveResponseAs'];
                }

                $step['next_step'] = isset($block['next']) && $block['next'] ? $idMap[$block['next']] : null;
                break;

            default:
                $step['type'] = 'end';
                $step['text'] = $block['messageData']['body'] ?? "Session ended.";
                break;
        }

        $steps[] = $step;
    }

    return [
        'initiation' => 'book ticket',
        'steps'      => $steps
    ];
}