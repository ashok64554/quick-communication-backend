<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ManageSenderId;
use App\Models\SendSms;
use App\Models\SendSmsQueue;
use App\Models\DltTemplateGroup;
use App\Models\DltTemplate;
use App\Exports\ReportExportBySenderID;
use Auth;
use DB;
use Edujugon\PushNotification\PushNotification;
use App\Mail\CommonMail;
use Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Excel;
use App\Imports\CampaignImport;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Redis;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use Illuminate\Support\Facades\Http;
use App\Models\DlrcodeVender;
use App\Models\CallbackWebhook;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use ZipArchive;


class FunctionTestController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }
    
    public function testFunction(Request $request)
    {
        $date_format = Carbon::now()->addSeconds(rand(2,4));
        dd($date_format->toDateTimeString(), $date_format->format('ymdHi'));
        $response = [
                "campaign_token" => "64faa610-abc7-11ee-a02b-75ac21154640",
                "mobile_number" => 919713753131,
                "used_credit" => 1,
                "submit_date" => "2024-01-10 18:54:26",
                "done_date" => "2024-01-10 18:54:27",
                "status" => "DELIVRD",
                "status_code" => "000"
            ];
        $data = [
            'message_type' => 1,
            'webhook_url' => 'http://localhost:8001/sms-webhook-response',
            'response' => json_encode($response)
        ];
        \DB::table('callback_webhooks')->insert($data);
        dd('done');
        die;
        
        $templates = ['1307160985513028142', '1307160985542414047', '1307160985550791919', '1307160985557222208', '1307160985563979484', '1307160985571021889', '1307160985576811881', '1307160985582801509', '1307160985591210065', '1307160985597324122', '1307160985606666567', '1307160985614822699', '1307160985620300263', '1307160985727481353', '1307160985751628707', '1307160985767269659', '1307161553426121602', '1307162113974391666', '1307162486679119558', '1307163645754982613', '1307163940047629480', '1307163940086386068', '1307163940116890493', '1307164060508678036', '1307164801494652198', '1307164802076179209', '1307164819523570220', '1307164878551844855', '1307164881016746682', '1307164924766067209', '1307165098402756822', '1307165159514141099', '1307165252902934582', '1307165277795600851', '1307165296221779795', '1307165296158526086', '1307166710257701721', '1307166710351610560', '1307166710437826690', '1307166702044270782', '1307166903534620576', '1307167266176903029', '1307167643770684960', '1307167991199498219', '1307170383221011716'];
        $getDltTemplates = DltTemplate::whereIn('dlt_template_id', $templates)
        ->update(['dlt_template_group_id' => 1]);

        dd('Done');

        $users = DB::table('users')->where('id', 4)->delete();
        dd('done');
        $json = json_encode([48,3]);
        $result = \DB::select('CALL getAdminDateWiseConsumption(?, ?)', ["2023-05-24", $json]);
        dd($result);

        \Artisan::call('check:campaign');
        
        /*---------- Call mysql Function / View / Event / Proceudure /  --------------*/

        /*
        // FUNCTION / VIEW
        //1. create function
        create function p1() returns INTEGER DETERMINISTIC NO SQL return @p1;
        
        //2. create view
        create view userinfo as
            select * from users where id = p1() ;
        */

        //3. call function and view
        $userId = 1;
        //$userinfo = \DB::select("SELECT s.* FROM (SELECT @p1:=$userId p) parm , userinfo s;");
        //return $userinfo;

        // PROCEDURE
        /*
        //1. create procedure
        DELIMITER $$

        CREATE PROCEDURE getUserInfo(
            IN userId INT(11)
        )
        BEGIN
            SELECT * 
            FROM users
            WHERE id = userId;
        END $$

        DELIMITER ;
        */

        //2. call procedure
        //$userinfo = \DB::select('CALL getUserInfo(?)', [$userId]);
        //return $userinfo;

        $result = \DB::select('CALL getDeliveredCount(?)', [1]);
        foreach ($result as $key => $value) {
            return $value->total_delivered;
        }

        //EVENT 
        /*
        //1. create event
        CREATE EVENT IF NOT EXISTS getUsers
        ON SCHEDULE AT CURRENT_TIMESTAMP
        DO
          SELECT * FROM users;
        */



        /*------------Notification------------*/
        $user = User::first();
        $variable_data = [
            '{{name}}' => 'Ashok',
            '{{no_of_credit}}' => '100'
        ];
        return notification('credit-added', $user, $variable_data, 'abcd');
        /*------------------------*/
    }

    public function copyDltTemplates($from_user, $to_user)
    {
        dd('function off');
        $groups = DltTemplateGroup::where('user_id', $from_user)->get();
        foreach ($groups as $key => $group) 
        {
            $findGroup = DltTemplateGroup::where('group_name', $group->group_name)->where('user_id', $to_user)->first();
            if(!$findGroup)
            {
                $createGroup = new DltTemplateGroup;
                $createGroup->user_id = $to_user;
                $createGroup->group_name = $group->group_name;
                $createGroup->save();
                $findGroup = $createGroup;
            }

            $getAllTemplates = DltTemplate::where('user_id', $from_user)
                ->where('dlt_template_group_id', $group->id)
                ->get();
            foreach ($getAllTemplates as $key => $templates) 
            {
                $senderIds = ManageSenderId::where('user_id', $to_user)->first();
                $dlt_template = new DltTemplate;
                $dlt_template->parent_id  = 1;
                $dlt_template->user_id  = $to_user;
                $dlt_template->manage_sender_id  = $senderIds->id;
                $dlt_template->dlt_template_group_id  = (($findGroup) ? $findGroup->id : null);
                $dlt_template->template_name  = $templates->template_name;
                $dlt_template->dlt_template_id  = $templates->dlt_template_id;
                $dlt_template->entity_id  = $templates->entity_id;
                $dlt_template->sender_id  = $templates->sender_id;
                $dlt_template->header_id  = $templates->header_id;
                $dlt_template->is_unicode  = $templates->is_unicode;
                $dlt_template->dlt_message  = $templates->dlt_message;
                $dlt_template->status  = $templates->status;
                $dlt_template->save();

            }
        }
        dd('Done');
    }

    public function createCampaign()
    {
        $user = User::find(2);
        $accessToken = $user->createToken('authToken')->accessToken; 
        return view('create-campaign', compact('accessToken'));
    }

    public function postCampaign(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'file'     => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $file = $request->file;
            $fileArray = array();
            $formatCheck = ['doc','docx','png','jpeg','jpg','gif','pdf','svg','mp4','webp','csv','xlsx'];

            $fileName   = time().'-'.rand(0,99999).'.' . $file->getClientOriginalExtension();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileSize = $file->getSize();
            if(!in_array($extension, $formatCheck))
            {
                return response()->json(prepareResult(true, [], trans('translate.file_not_allowed').'Only allowed : doc, docx, png, jpeg, jpg, gif, pdf, svg, mp4, webp, csv, xlsx', $this->intime), config('httpcodes.internal_server_error'));
            }

            $destinationPath = 'uploads/';
            if(in_array($extension, ['csv','xlsx'])) {
                $destinationPath = 'csv/campaign/';
            }

            $file->move($destinationPath, $fileName);
            $file_location  = $destinationPath.$fileName;

            $fileInfo = [
                'pass_this_file_name'   => $destinationPath.$fileName,
                'file_extension'    => $file->getClientOriginalExtension(),
                'uploading_file_name' => $file->getClientOriginalName(),
            ];
            dd($fileInfo);  
        }
        catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function exportMultiSheet()
    {
        return (new ReportExportBySenderID('CCMPCZ', null, null))->download(time().'.xlsx');
    }

    public function reportUserWise()
    {
        $total_queue_stats = DB::table('send_sms_queues')
            ->select('stat', DB::raw('COUNT(*) as stat_wise_count'), DB::raw('SUM(use_credit) as used_credit'))
            ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
            ->where('send_sms.sender_id', 'CCMPCZ')
            ->where('send_sms.user_id', 2)
            ->groupBy('stat')
            ->orderBy('stat', 'ASC')
            ->get()
            ->toArray();

        $total_history_stats = DB::table('send_sms_histories')
            ->select('stat', DB::raw('COUNT(*) as stat_wise_count'), DB::raw('SUM(use_credit) as used_credit'))
            ->join('send_sms', 'send_sms_histories.send_sms_id', 'send_sms.id')
            ->where('send_sms.sender_id', 'CCMPCZ')
            ->where('send_sms.user_id', 2)
            ->groupBy('stat')
            ->orderBy('stat', 'ASC')
            ->get()
            ->toArray();
        dd($total_queue_stats, $total_history_stats);
    }

    public function testPushNotification($device_token)
    {
        // e8kaWF7sQuOWPMpejBZNJl:APA91bGatdBnozr-hHD6yRzRqoxV6m7npfDKhVQI5aIi8AlvgIspqUOhLpJf8F-CU1wB5qQIFyU28r7MAIzkhmiCVM7NwVb-gF2oyJzhvGm4iQCHCiZsEUgsDg8hObMqGQBut5vyns6V
        $push = new PushNotification('fcm');
        $push->setMessage([
            "notification"=>[
                'title' => 'testing title from SS',
                'body'  => 'Testing Body from SS',
                'sound' => 'default',
                'android_channel_id' => '1',
            ],
            'data'=>[
                'id'  => 'test',
            ]                        
        ])
        ->setApiKey(env('FIREBASE_KEY'))
        ->setDevicesToken($device_token)
        ->send();
        return $push->getFeedback();
    }

    public function checkMail()
    {
        $user = User::where('email', 'ashok@nrt.co.in')->first();
        $token = Str::random(64);
        /*$variable_data = [
                '{{name}}' => $user->name,
                '{{link}}' => env('FRONT_URL').'/reset-password/'.$token
            ];
        notification('forgot-password', $user, $variable_data);

        return $variable_data;*/

        $mailObj = [
            'template_name' => 'forgot-password',
            'mail_subject'  => 'testing mail subject',
            'mail_body'     => 'Dear Ashok Sahu,<br>
Please click below link to reset password.<br>
<br>
<a href="https://app.ok-go.in/reset-password/C8sd1R8YXvvUZSbWATVhjCHDLqVdmCItbRvKx34o2SQvFr5xXo7Qu1M1RJPbPyY6" style="background: #f0ab04; padding: 5px; text-decoration: none; color:#fff">Click here</a>
<br><br>
        <div style="border-bottom: 1px solid #f0ab04;"></div>
        <br>
                    Regards,<br>SMS PORTAL,<br>463 - A, Pacific Business Center, Behind D-Mart Shopping Center, Hoshangabad Rd, Bhopal, Madhya Pradesh 462026 India,<br>9713753131,<br>info@nrtsms.com,<br><br>
<img src="https://ok-go.in/uploads/logo.png" height="40px">',
            'other_info'    => null,
        ];

        try {
            $mail = Mail::to('ashok@nrt.co.in')->send(new CommonMail($mailObj));
            dd($mail);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function pendingSmsResend()
    {
        //Our SQL Box Code
        $kannel_domain          = env("KANNEL_DOMAIN");
        $kannel_ip              = env("KANNEL_IP");
        $kannel_admin_user      = env("KANNEL_ADMIN_USER");
        $kannel_sendsms_pass    = env("KANNEL_SENDSMS_PASS");
        $kannel_sendsms_port    = env("KANNEL_SENDSMS_PORT");
        $node_port              = env("NODE_PORT");

        $dlt_d = '?smpp?PE_ID=1201158039515302745&TEMPLATE_ID=1707167999084285893&TELEMARKETER_ID=1702157571346669272';

        $totalRecords = SendSmsQueue::where('stat', 'Pending')
            ->whereDate('created_at', '2023-05-09')
            ->orderBy('id', 'ASC')
            ->get();
        $chunkedRecords = $totalRecords->chunk(1000);
        foreach ($chunkedRecords as $records) 
        {
            $kannelData = [];
            foreach($records as $pending) 
            {
                $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&msgid='.$pending->unique_key;
                $kannelData[] = [
                    'momt' => 'MT',
                    'sender' => 'CCMPCZ',
                    'receiver' => $pending->mobile,
                    'msgdata' => urlencode($pending->message),
                    'smsc_id' => 'voda_idea',
                    'mclass' => null,
                    'coding' => 2,
                    'dlr_mask' => 31,
                    'dlr_url' => $dlr_url,
                    'charset' => 'UTF-8',
                    'boxc_id' => kannelSmsbox(),
                    'binfo' => $pending->send_sms_id,
                    'meta_data' => $dlt_d,
                    'priority' => 0
                ];
            }
            executeKannelQuery($kannelData);
            $kannelData = [];
            usleep(2000);
        }
        return 'done';
    }

    public function reimportKannelFile($file_name='kannel13.store')
    {
        $file_location = '';
        $file_path = $file_location.$file_name;
        if(file_exists($file_path))
        {
            $handle = fopen($file_path,'r') or die ('File opening failed');
            while (!feof($handle)) 
            {
                $dd = fgets($handle);
                dd($dd);
            }
        }
        else
        {
            return response()->json(prepareResult(true, '', 'File not found, Please check file: '.$file_path, $this->intime), config('httpcodes.not_found'));
        }
    }

    public function checkCache(Request $request)
    {
        $results = Cache::rememberForever('users', function () {
            return User::where('userType', '!=', 0)
                ->where('status', '!=', '2')
                ->with('roles:id,name')
                ->orderBy('users.id', 'DESC')
                ->get();
        });

        if(Cache::has('users'))
        {
            echo 'Cache does exist.';
            return $results;
        }

        return $results;
    }

    public function jsonResponse($unique_key)
    {
        $rand = '-'.rand(1, 10);
        $token = generateToken();

        // Acknowledgement (ACCEPTED)
        $data["1"] = [
            "msgid" => $unique_key,
            "d"     => 8,
            "oo"    => 00,
            "ff"    => $token,
            "s"     => '',
            "ss"    => '',
            "aa"    => "ACK/",
            "finalDateTime" => date('Y-m-d H:i:s')
        ];

        // Final Delivery
        $data["2"] = [
            "msgid" => $unique_key,
            "d"     => 1,
            "oo"    => 00,
            "ff"    => $token,
            "s"     => "sub:001",
            "ss"    => "dlvrd:001",
            "aa"    => "id:".$token." sub:001 dlvrd:001 submit date:".strtotime( $rand."second", time())." done date:".time()." stat:DELIVRD err:000 text:",
            "finalDateTime" => date('Y-m-d H:i:s')
        ];

        // REJECTED 
        $data["3"] = [
            "msgid" => $unique_key,
            "d"     => 16,
            "oo"    => '',
            "ff"    => '',
            "s"     => '',
            "ss"    => '',
            "aa"    => '',
            "finalDateTime" => date('Y-m-d H:i:s')
        ];
        $num = rand(1, 3);
        return $data[$num];
    }

    public function voiceFileProcess(Request $request)
    {
        $client = new Client();
        $file_location = 'voice/sample_file-15s.mp3';
        $smsc_username = 'VDEMO00783_1845';
        $smsc_password = 'pass123';
        $file_time_duration = '30';
        $title = 'sample_file-15s-nrt168496841614.mp3';

        $fileWithUrl = env('APP_URL', 'https://ok-go.in').'/'.$file_location;

        $putFile = 'voice/'.time().'.mp3';
        file_put_contents($putFile, file_get_contents($fileWithUrl));

        $putFileWithUrl = env('APP_URL', 'https://ok-go.in').'/'.$putFile;

        $requestUrl = "http://103.132.146.183/VoxUpload/api/Values/upload";
        $response = $client->post($requestUrl, [
            'UserName' => $smsc_username,
            'Password' => $smsc_password,
            'PlanType' => $file_time_duration,
            'fileName' => $title.'-nrt-'.time(),
            'uploadedFile' => fopen($putFileWithUrl, 'wb')
        ]);

        $response = json_decode($response->getBody(), true);
        dd($response);
    }

    public function excelToCsv(Request $request)
    {
        $file = public_path('file.xlsx');
        $readerExtension = 'Xlsx';
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($readerExtension);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $allSheetsArray = $spreadsheet->getSheetNames();
        $import = $spreadsheet->getSheetByName('Top Sheet');
        $import = $spreadsheet->getSheetByName('Top Sheet');
        $import = $import->getCell('C29')->getCalculatedValue();
        dd($import);


        $csvFileData = Excel::toArray(new CampaignImport(), public_path('file.xlsx'));
        dd($csvFileData);
    }


    public function sendSmsAtTheRate()
    {
        $kannel_domain      = env('KANNEL_DOMAIN');
        $kannel_ip          = env('KANNEL_IP');
        $kannel_admin_user  = env('KANNEL_ADMIN_USER', 'tester');
        $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
        $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
        $node_port = env('NODE_PORT', 8009);
        $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');

        $meta_data = '?smpp?PE_ID=1301157777449098747&TEMPLATE_ID=1307161927617472274&TELEMARKETER_ID='.$telemarketer_id;

        $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&msgid=1706598274949945748';
        $message = 'WLMS- Mobile OTP For WLMS registration is : @- Dept of Food';
        $kannelData[] = [
            'momt' => 'MT',
            'sender' => 'FOODMP',
            'receiver' => '919713753131',
            'msgdata' => $message,
            'smsc_id' => 'voda_idea3',
            'mclass' => null,
            'coding' => 0,
            'dlr_mask' => 31,
            'dlr_url' => $dlr_url,
            'charset' => 'UTF-8',
            'boxc_id' => kannelSmsbox(),
            'binfo' => '1706598274949945748',
            'meta_data' => $meta_data,
            'priority' => 3,
            'alt_dcs' => 1
        ];

        executeKannelQuery($kannelData);
        return $kannelData;
    }

    public function checkApiCampaignCompleted()
    {
        checkApiCampaignComplete();
        return 'Done';
    }

    public function buttonsVariablePayload($templateId=null)
    {
        if(empty($templateId))
        {
            $whatsAppTemplate = WhatsAppTemplate::first();
        }
        else
        {
            $whatsAppTemplate = WhatsAppTemplate::find($templateId);
        }
        
        $whatsappButtons = $whatsAppTemplate->whatsAppTemplateButtons;
        if($whatsappButtons->count()>0)
        {
            $urlArray = ['/contact', ''];
            $coupon_code = 'NRT10';
            $buttons = prapareWAButtonComponent($whatsappButtons,$urlArray,$coupon_code);
            dd($buttons);
        }
        dd('No buttons found.');
    }

    public function testUpdateWADlr()
    {
        try {
            //$redis = Redis::connect('127.0.0.1', 6379);
            $redis = Redis::connection();
        } catch(\Predis\Connection\ConnectionException $e){
            \Log::error('error connection redis');
            die;
        }

        $this->updateWADlr($redis);
    }

    public function qualitySignalReport()
    {
        $appVersion = 'v17.0';
        $phone_number_id = 105736689271434;
        $access_token = 'EAAIgPLInHcsBO7jBWNJNJa6bVSRnJYdLBPxZBPhrPA6MPiO3OAnB1EUDAkzN2ukZAM8222puIlZCgSKb6GPd6NzTpIXpg8nT5ookpOHjobmXQ7jNNmM63CJxAMrP3CF42ZAzZCERX0ikYdDVDR7gzu1LG2wEeZBZAbRVhhVAxbZBoxTKd3Vqdvse8KyuuZAzal5r1ZBG0KMeDaQktSVBZBIuRIZD';
        $url = "https://graph.facebook.com/$appVersion/$phone_number_id";

        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->get($url);
        return $response->body();
    }

    public function updateWADlr($redis)
    {
        $redisConn = $redis;
        $arList = $redis->lrange("whatsapp_key", 0 ,1000);
        if(sizeof($arList)>0)
        {
            $wa_failed_responses = [];
            $wa_sent_responses = [];
            $wa_delivered_responses = [];
            $wa_read_responses = [];
            foreach ($arList as $key => $value) 
            {
                $json_decode = json_decode($value, true);
                $getStatus = $json_decode['entry'][0]['changes'][0]['value'];

                if(array_key_exists('statuses', $getStatus))
                {
                    $getFinalStatus = $getStatus['statuses'][0];
                    switch (strtolower($getFinalStatus['status']))
                    {
                        case 'failed':
                            $wa_failed_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'status' => 'Completed',
                                'sent' => 1,
                                'sent_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                                'error_info' => json_encode($getFinalStatus['errors'])
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'sent':
                            $wa_sent_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'conversation_id' => $getFinalStatus['conversation']['id'],
                                'expiration_timestamp' => date('Y-m-d H:i:s', date($getFinalStatus['conversation']['expiration_timestamp'])),
                                'stat' => $getFinalStatus['status'],
                                'status' => 'Completed',
                                'sent' => 1,
                                'sent_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'delivered':
                            $wa_delivered_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'delivered' => 1,
                                'delivered_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'read':
                            $wa_read_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'read' => 1,
                                'read_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        default:
                            // code...
                            break;
                    }
                }
            }

            if(sizeof($wa_failed_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_failed_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_sent_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_sent_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_delivered_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_delivered_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_read_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_read_responses,
                    uniqueBy: 'response_token'
                );
            }
            
            sleep(2);
            $arList = $redis->lrange("whatsapp_key", 0 ,1);
            if(sizeof($arList)>0)
            {
                $this->updateWADlr($redisConn);
            }
        }
        return true;
    }

    public function codeInfo(Request $request)
    {
        try {
            $codes = DlrcodeVender::with('primaryRoute:id,route_name')
                ->orderBy('dlr_code', 'ASC')
                ->groupBy('dlr_code')
                ->get();
            return view('code-info', compact('codes'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waGeneratePayload($whats_app_template_id)
    {
        $count= 1;
        $user_id = 3;
        $number = '919713753131';
        $country_id = 76;
        $prepareHeader = [];
        $country = \DB::table('countries')->find($country_id);
        $whatsAppTemplate = WhatsAppTemplate::where('user_id', $user_id)->find($whats_app_template_id);
        $currentArrKey = $count;
        $isRatio = false;
        $isFailedRatio = false;
        $upload_wa_file_path = "https://wa-file-path";
        $media_type = strtolower($whatsAppTemplate->media_type);

        $userInfo = User::find($user_id);

        $whatsappButtons = $whatsAppTemplate->whatsAppTemplateButtons;

        $parameter_format = $whatsAppTemplate->parameter_format;
        $header_text = $whatsAppTemplate->header_text;
        preg_match_all('/\{\{(.*?)\}\}/', $header_text, $match_header);
        $header_variables = (!empty($whatsAppTemplate->header_variable) ? json_decode($whatsAppTemplate->header_variable, true) : null);

        $body_text = $whatsAppTemplate->message;
        preg_match_all('/\{\{(.*?)\}\}/', $body_text, $match_body);
        $body_variables = (!empty($whatsAppTemplate->message_variable) ? json_decode($whatsAppTemplate->message_variable, true) : null);

        $footer_text = $whatsAppTemplate->footer_text;
        preg_match_all('/\{\{(.*?)\}\}/', $footer_text, $match_footer);
        $footer_variables = [];

        //check template Type
        $template_type = $whatsAppTemplate->template_type;

        $chargesPerMsg = getWACharges($whatsAppTemplate->category, $userInfo->id, $country_id);

        $checkNumberValid = waCheckNumberValid($number, $country->phonecode, $country->min, $country->max);
        $unique_key = uniqueKey();
        $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);

        if($template_type=='MEDIA')
        {
            $latitude = null;
            $longitude = null;
            $location_name = null;
            $location_address = null;
            if($media_type=='location')
            {
                $latitude = 'Location-Latitude';
                $longitude = 'Location-Longitude';
                $location_name = 'Location-Name';
                $location_address = 'Location-Address';
            }
            $titleOrFileName = 'File-Caption';
            $header = prapareWAComponent('header', $upload_wa_file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
        }
        else
        {
            $header = prapareWAComponent('header', $header_variables, $parameter_format, $match_header[1]);
        }
        
        $body = prapareWAComponent('body', $body_variables, $parameter_format, $match_body[1]);
        $footer = prapareWAComponent('footer', $footer_variables, $parameter_format, $match_footer[0]);

        // Button code needs to implement
        $urlArray = $coupon_code = null;

        // buttons parameter
        $WhatsAppTemplateButtons = $whatsAppTemplate->whatsAppTemplateButtons;

        $i = 1;
        foreach ($WhatsAppTemplateButtons as $key => $button) 
        {
            $sub_type = $button->button_type;
            if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG','FLOW']))
            {
                if($sub_type=='URL' && str_contains($button->button_value, '{{1}}'))
                {
                    $urlArray[] = 'button_url_var_'. $i;
                    $i++;
                }
                elseif($sub_type=='COPY_CODE')
                {
                    $coupon_code[] = 'button_coupon_var_1';
                }
                elseif($sub_type=='CATALOG')
                {
                    $catalog_code[] = 'product_catalog_id_1';
                }
                elseif($sub_type=='FLOW')
                {
                    $flow_code[] = 'flow_token_1';
                }
            }
        }

        $buttons = prapareWAButtonComponent($WhatsAppTemplateButtons, $urlArray, $coupon_code, $catalog_code, $flow_code);
        $obj = array_merge($header,$body, $footer, $buttons);
        
        $messagePayload = waMsgPayload($checkNumberValid['mobile_number'], $whatsAppTemplate->template_name, $whatsAppTemplate->template_language, $obj);
        return $messagePayload;
    }

    public function testWaSendMessage()
    {
        /*
        {
            "message": {
                "content": {
                    "type": "TEMPLATE",
                    "template": {
                        "templateId": "hello_world"
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
        $appKey = '8k098HYWr1waNUeVvrpzsOkGo';
        $appSecret = 'uQuiP411VeQDbm6';
        $url = "https://ok-go.in/api/send-wa-message";
        
        /*
        $payload = [
            "message" => [
                "content" => [
                    "type" => "TEMPLATE",
                    "template" => [
                        "templateId" => "welcome_message",
                    ]
                ],
                "recipient" => [
                    "to" => 919713753131,
                    "recipient_type" => "individual"
                ],
                "sender" => [
                    "from" => 919201169301
                ]
            ]
        ];
        */

        $payload = [
            "message" => [
                "content" => [
                    "type" => "MEDIA_TEMPLATE",
                    "template" => [
                        "templateId" => "chj_order_bill",
                        "bodyParameterValues" => [
                            "var1",
                            "var2",
                            "var3"
                        ],
                        "media" => [
                            "type" => "document",
                            "url" => "https://ok-go.in//whatsapp-file//wa-1742460058-554.pdf",
                            "filename" => "Ashok test File",
                        ]
                    ]
                ],
                "recipient" => [
                    "to" => 919713753131,
                    "recipient_type" => "individual"
                ],
                "sender" => [
                    "from" => 917554139385
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' =>  "Bearer $appKey-$appSecret",
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload);
        return $response->body();
    }

    public function calRemaingDays()
    {
        $startDate = Carbon::parse('2025-06-01');
        $endDate = "2025-06-19";

        $period = CarbonPeriod::create($startDate, $endDate);
        $daysCount = collect($period)->filter(function ($date) {
            return $date->dayOfWeek !== Carbon::SUNDAY;
        })->count();

        echo "Total days (excluding Sundays): $daysCount";
    }

    public function waFileDownload(Request $request)
    {
        $appVersion = 'v21.0';
        $mediaId = 1176891544250578;
        $access_token = 'EAAIgPLInHcsBPLkrRZA5VZA0d5fImXkjuRdllfbdS5O6QYhoHbPRpnPliZADbxuMrDZAeJWa4OZCguMpgeHpsk4mrLjXXCbvPBJeekvPEaSbKMvkVOn94qJRF8zOD10wZB7JydtCtoZBhMcVqiBpZAvjVDxp7EQtHulLZBECAGZBrKTNR151zs0IEVyVAmO69oyhIiVL3v9fRl9D7ZAa2gLUC5WhZCZBwX4fQDJVi6QTrrZCuWwTZAKDQZDZD';
        $data = getMediaFileFromWA($access_token, $mediaId, $appVersion);
        \Log::info('Download File');
        \Log::info($data);
        return $data;
    }

    public function thanks(Request $request)
    {
        \Log::info('Kylas Callback');
        \Log::info($request->all());
        return view('thankyou');
    }

    public function zipDownload()
    {
        $folderName = 'public';
        $sourcePath = storage_path("app/{$folderName}");
        $zipFileName = "{$folderName}.zip";
        $zipFilePath = storage_path("app/{$zipFileName}");

        if (!file_exists($sourcePath)) {
            return response()->json(['error' => 'Source folder not found.'], 404);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'Unable to create ZIP file.'], 500);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = realpath($file);
            $relativePath = substr($filePath, strlen($sourcePath) + 1);

            if (is_dir($filePath)) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    public function testWaRepushCampaign(Request $request)
    {
        $getCampaign = WhatsAppSendSms::where('user_id', '38')->find(11611);
        if(!$getCampaign)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        try {

            $submitMsgs = WhatsAppSendSmsQueue::select('whats_app_send_sms_queues.id','whats_app_send_sms_queues.unique_key','whats_app_send_sms_queues.error_info','whats_app_send_sms_queues.mobile','whats_app_send_sms_queues.submit_date','whats_app_send_sms_queues.whats_app_send_sms_id','whats_app_send_sms_queues.message','whats_app_send_sms.whats_app_configuration_id','whats_app_send_sms.whats_app_template_id','whats_app_send_sms.sender_number','whats_app_configurations.access_token','whats_app_configurations.app_version','whats_app_templates.template_language','whats_app_templates.template_name')
                ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                ->join('whats_app_configurations', 'whats_app_send_sms.whats_app_configuration_id', 'whats_app_configurations.id')
                ->join('whats_app_templates', 'whats_app_send_sms.whats_app_template_id', 'whats_app_templates.id')
                ->where('is_auto', 0)
                ->where('stat', 'failed')
                ->where('whats_app_send_sms_queues.whats_app_send_sms_id', 11611)
                ->whereJsonContains('error_info', ['code' => 131053])
                
                ->get();

            foreach ($submitMsgs as $key => $submitMsg) 
            {   
                $error_info = json_decode($submitMsg->error_info, true);
                if($error_info[0]['code']=='131053')
                {
                    $template_name = $submitMsg->template_name; 
                    $sender_number = $submitMsg->sender_number; 
                    $appVersion = $submitMsg->app_version;
                    $message = json_decode($submitMsg->message);
                    $access_token = base64_decode($submitMsg->access_token); 
                    $response = wAMessageSend($access_token, $sender_number, $appVersion, $template_name, $message);   
                    \Log::info($response);             
                    if($response['error']==false)
                    {
                        $response = json_decode($response['response']);
                        //update response
                        $submitMsg->submit_date = date('Y-m-d H:i:s');
                        $submitMsg->stat = @$response->messages[0]->message_status;
                        $submitMsg->response_token = @$response->messages[0]->id;
                        $submitMsg->error_info = null;
                        $submitMsg->submit_date = date('Y-m-d H:i:s');
                        $submitMsg->stat = null;
                        $submitMsg->save();
                    }
                    else
                    {
                        $submitMsg->error_info = $response;
                        $submitMsg->submit_date = date('Y-m-d H:i:s');
                        $submitMsg->stat = 'Failed';
                        $submitMsg->status = 'Completed';
                        $submitMsg->save();
                    }
                }
            }

            return response()->json(prepareResult(false, [], trans('translate.synced'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
