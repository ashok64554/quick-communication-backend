<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppSendSmsHistory;
use App\Models\WhatsAppReplyThread;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;
use Carbon\Carbon;
use App\Imports\CampaignImport;
use Log;

class WASendMessageController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-sms');
    }

    public function index(Request $request)
    {
        try {
            $query = WhatsAppSendSms::select('whats_app_send_sms.*','users.id as user_id', 'users.name')
            ->join('users', 'whats_app_send_sms.user_id', 'users.id')
            ->join('whats_app_templates', 'whats_app_send_sms.whats_app_template_id', 'whats_app_templates.id')
            ->whereNull('users.deleted_at')
            ->orderBy('whats_app_send_sms.id', 'DESC')
            ->with('country:id,name,iso3,currency_code,currency_symbol')
            ->withCount('WhatsAppReplyThreads');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_send_sms.user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_send_sms.campaign', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.template_name', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.category', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.template_language', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_send_sms.user_id', $request->user_id);
            }

            if(!empty($request->template_type))
            {
                $query->where('whats_app_templates.template_type', $request->template_type);
            }

            if(!empty($request->template_name))
            {
                $query->where('whats_app_templates.template_name', $request->template_name);
            }

            if(!empty($request->template_language))
            {
                $query->where('whats_app_templates.template_language', 'LIKE', '%'.$request->template_language.'%');
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $pagination =  [
                    'data' => $result,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage)
                ];
                $query = $pagination;
                if($request->other_function)
                {
                    return $pagination;
                }
            }
            else
            {
                $query = $query->get();
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'country_id' => 'required|exists:countries,id',
            'campaign' => 'required|string',
            'whats_app_configuration_id' => 'required|exists:whats_app_configurations,id',
            'whats_app_template_id' => 'required|exists:whats_app_templates,id',
            'contact_group_ids'     => 'array',
            'sms_type'      => 'required|in:1,2',
            'file_path'      => 'required',
            'file_mobile_field_name' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $validation = \Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        $file_path = $request->file_path;

        if(empty($request->mobile_numbers) && count($request->contact_group_ids)<1 && empty($file_path))
        {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.add_atleast_one_mobile_number'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!empty($file_path) && !file_exists($file_path))
        {
            return response()->json(prepareResult(true, [], trans('translate.file_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->file_mobile_field_name!='mobile_numbers')
        {
            return response()->json(prepareResult(true, [], trans('translate.file_mobile_field_name_is_incorrect'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!empty($file_path))
        {
            $fp = fopen($file_path, 'r');
            $csvHeader = fgetcsv($fp);
            if($csvHeader[0]!='mobile_numbers')
            {
                return response()->json(prepareResult(true, [], trans('translate.first_column_name_must_be_mobile_numbers'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        $user_id = !empty($request->user_id) ? $request->user_id : auth()->id();
        $userInfo = User::find($user_id);

        $route_type = 5; // for whatsapp
        $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);

        if(!$userInfo || @$userInfo->status!='1')
        {
            return response()->json(prepareResult(true, [], trans('translate.user_not_found_or_inactive_status'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $campaign_send_date_time = !empty($request->campaign_send_date_time) ? $request->campaign_send_date_time : Carbon::now()->toDateTimeString();

        if(!empty($request->campaign_send_date_time) && strtotime($request->campaign_send_date_time)<1)
        {
            return response()->json(prepareResult(true, [], trans('translate.campaign_schedule_date_and_time_invalid'), $this->intime), config('httpcodes.bad_request'));
        }

        $whatsAppTemplate = WhatsAppTemplate::where('user_id', $user_id)
            ->find($request->whats_app_template_id);
        if(!$whatsAppTemplate)
        {
            return response()->json(prepareResult(true, [], trans('translate.whatsapp_template_not_assigned_to_you'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $whatsappButtons = $whatsAppTemplate->whatsAppTemplateButtons;

        //whatsapp paramter format
        $parameter_format = $whatsAppTemplate->parameter_format;
        $header_text = $whatsAppTemplate->header_text;
        preg_match_all('/\{\{(.*?)\}\}/', $header_text, $match_header);

        $body_text = $whatsAppTemplate->message;
        preg_match_all('/\{\{(.*?)\}\}/', $body_text, $match_body);

        $footer_text = $whatsAppTemplate->footer_text;
        preg_match_all('/\{\{(.*?)\}\}/', $footer_text, $match_footer);

        //check template Type
        $template_type = $whatsAppTemplate->template_type;

        $chargesPerMsg = getWACharges($whatsAppTemplate->category, $userInfo->id, $request->country_id);
        if(empty($chargesPerMsg) || $chargesPerMsg<=0)
        {
            return response()->json(prepareResult(true, [], trans('translate.whats_app_charges_not_define_in_your_account_contact_to_admin'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $total_numbers_for_deductions = 0;

        $whatsAppConfiguration = WhatsAppConfiguration::where('user_id', $user_id)
            ->find($request->whats_app_configuration_id);
        if(!$whatsAppConfiguration)
        {
            return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.internal_server_error'));
        }

        if($request->sms_type==1)
        {
            $fp = fopen($request->file_path, 'r');
            $csvHeader = fgetcsv($fp);
            if(count($csvHeader)>1)
            {
                return response()->json(prepareResult(true, [], trans('translate.only_one_column_allowed_in_csv_file'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        if($request->sms_type==2)
        {
            $header_variable = (!empty($whatsAppTemplate->header_variable) ? json_decode($whatsAppTemplate->header_variable) : []);
            $body_variable = (!empty($whatsAppTemplate->message_variable) ? json_decode($whatsAppTemplate->message_variable) : []);

            // button variables
            $WhatsAppTemplateButtons = $whatsAppTemplate->whatsAppTemplateButtons;
            $i = 0;
            foreach ($WhatsAppTemplateButtons as $key => $button) 
            {
                $sub_type = $button->button_type;
                if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG','FLOW']))
                {
                    if($sub_type=='URL' && str_contains($button->button_value, '{{1}}'))
                    {
                        $i++;
                    }
                    elseif($sub_type=='COPY_CODE')
                    {
                        $i++;
                    }
                    elseif($sub_type=='CATALOG')
                    {
                        $i++;
                    }
                    elseif($sub_type=='FLOW')
                    {
                        $i++;
                    }
                }
            }

            $media_var_count = 0;
            if($template_type=='MEDIA')
            {
                if($whatsAppTemplate->media_type=='LOCATION')
                {
                    $media_var_count = 4;
                }
                else
                {
                    $media_var_count = 2;
                }
            }

            $all_variables = collect([$header_variable, $body_variable])->flatten()->toArray();
            $usedVariables = count($all_variables) + $i + $media_var_count;

            $fp = fopen($request->file_path, 'r');
            $csvHeader = fgetcsv($fp);
            if(count($csvHeader) != ($usedVariables+1))
            {
                return response()->json(prepareResult(true, [], trans('translate.template_variable_count_mismatched'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        try {
            
            $message = $request->message;
            $country = \DB::table('countries')->find($request->country_id);

            //input number
            $total_input_number = [];
            if(!empty($request->mobile_numbers)) {
                foreach (preg_split ("/\s+/", $request->mobile_numbers) as $space => $woSpace) {
                    foreach (preg_split ("/\,/", $woSpace) as $key => $mobile) {
                        if(!empty($mobile))
                        {
                            $total_input_number[] = (int) $mobile;
                        }
                    }
                }
            }

            //group number
            $total_group_number = [];
            if(is_array($request->contact_group_ids) && count($request->contact_group_ids)>0) {
                $total_group_number = ContactNumber::select('number')
                    ->whereIn('contact_group_id', $request->contact_group_ids)
                    ->pluck('number');
            }

            // file number
            $file_numbers = [];
            $csvFileData = Excel::toArray(new CampaignImport(), $file_path);
            $file_numbers = $csvFileData[0];
            $totalFileNumbers = count($file_numbers);
            
            // 1 = normal, 2 = custom
            if($request->sms_type==1)
            {
                //combine array
                $numberFlatten = collect([$total_input_number, $total_group_number, $file_numbers]);
                $all_numbers = $numberFlatten->flatten()->toArray();

                $all_numbers = collect($all_numbers);
                
                //total numbers
                $total_contacts = $total_credit_used = count($all_numbers);
            }
            else
            {
                //combine array
                $numberFlatten = collect([$total_input_number, $total_group_number]);
                $all_numbers = $numberFlatten->flatten()->toArray();

                $total_contacts = $total_credit_used = count($all_numbers) + $totalFileNumbers;

                $all_numbers = collect($file_numbers);
            }
            
            
            //Check current balance
            if($getRouteCreditInfo['current_credit'] < ($total_credit_used * $chargesPerMsg))
            {
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }

            $file_mobile_field_name = (!empty($request->file_mobile_field_name) ? $request->file_mobile_field_name : 'mobile_numbers');

            // if campaign date & time is bigger than current time OR number of send message is greater than define instant message.
            $status = 'Ready-to-complete';
            $is_campaign_scheduled = $scheduled = null;
            if((strtotime($campaign_send_date_time) > time()))
            {
                $status = 'Pending';
                $is_campaign_scheduled = $scheduled = true;
                if(!empty($file_path))
                {
                    $file_path = $file_path;
                }
                else
                {
                    $newFile = time().'_'.rand(1,9999);
                    $destinationPath    = 'csv/whatsapp/';
                    $file_path = $destinationPath.$newFile.'.csv';
                    $fileDestination    = fopen ($file_path, "w");
                    
                    $csv_header = $file_mobile_field_name."\n";
                    fputs($fileDestination, $csv_header);
                    fclose($fileDestination);
                }

                $fp = fopen($file_path, 'r');

                $inputAndGrpNumFlatten = collect([$total_input_number, $total_group_number]);
                $inputAndGrpNum = $inputAndGrpNumFlatten->flatten();
                //added input number and group number in the selected file
                if(count($inputAndGrpNum)>0)
                {
                    $csvHeader = fgetcsv($fp);
                    $csv_write_data = [];
                    $final_data = [];
                    foreach ($inputAndGrpNum as $key => $number) 
                    {
                        foreach ($csvHeader as $key => $columnMatch) 
                        {
                            $csvColumnName = preg_replace('/[^A-Za-z0-9\_ ]/', '', $columnMatch);
                            $csvColumnName = strtolower(preg_replace('/\s+/', '_', $csvColumnName));
                            $csv_write_data[] = ($csvColumnName == $file_mobile_field_name) ? $number : null;
                            
                        }
                        $final_data[] = $csv_write_data;
                        $csv_write_data = [];
                        usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                    }
                    $handle = fopen($file_path, 'a+');
                    foreach ($final_data as $key => $data) {
                        fputcsv($handle, $data);
                    }

                    fclose($handle);
                    usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                }
            }           

            // ratio

            // Ratio function disabled for now
            /*
            $getRatio = getRatio($userInfo->id, $route_type);
            $ratio_percent_set = !empty($request->ratio_set) ? $request->ratio_set : $getRatio['speedRatio'];
            $getFailedRatio = ($request->failed_ratio>0) ? $request->failed_ratio : $getRatio['speedFRatio'];
            $failed_ratio = ($ratio_percent_set > 0) ? $getFailedRatio : null;
            */

            //default ratio set to 0%
            $ratio_percent_set = 0;
            $failed_ratio = 0;
            

            $total_block_number = 0;

            // set 0, once campaign is done then we ll update actual credit used (IN amount)
            $total_credit_used = 0;
            $waSendSMS = createWACampaign($user_id, $request->campaign, $whatsAppConfiguration->id, $request->whats_app_template_id, $request->country_id, $whatsAppConfiguration->sender_number, $message, $file_path, $file_mobile_field_name, $campaign_send_date_time, $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, $status, $whatsAppTemplate->category, $chargesPerMsg, $is_read_file_path=1, $reschedule_whats_app_send_sms_id=null, $reschedule_type=null, $failed_ratio=null, $is_campaign_scheduled=null);
            
            // campaign send                
            
            $media_type = strtolower($whatsAppTemplate->media_type);

            $chunks = $all_numbers->chunk(env('WA_CHUNK_SIZE', 2000));
            $invalidNumber = 0;
            $countBlankRow = 0;

            $batchArr = [];
            if($scheduled)
            {
                $batchTimeStart = Carbon::parse($campaign_send_date_time)->toDateTimeString();
            }
            else
            {
                $batchTimeStart = Carbon::now()->addMinutes(0)->toDateTimeString();
            }
            
            foreach ($chunks->toArray() as $nkey => $chunk) 
            {
                $campaignData = [];
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
                $batch_id = \Uuid::generate(4);

                $batchTimeStart = ($nkey==0) ? Carbon::parse($batchTimeStart)->addMinutes(0)->toDateTimeString() : Carbon::parse($batchTimeStart)->addMinutes(2)->toDateTimeString();

                $batchArr[] = [
                    'user_id' => $userInfo->id,
                    'whats_app_send_sms_id' => $waSendSMS->id,
                    'batch' => $batch_id,
                    'priority' => (!empty($whatsAppTemplate->priority) ? $whatsAppTemplate->priority : 0),
                    'execute_time' => $batchTimeStart
                ];
                foreach ($chunk as $nkey => $number) 
                {
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

                        if($request->sms_type==1)
                        {
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
                                    $latitude = $request->latitude;
                                    $longitude = $request->longitude;
                                    $location_name = $request->location_name;
                                    $location_address = $request->location_address;
                                }
                                $titleOrFileName = $request->file_caption;
                                $header = prapareWAComponent('header', $request->upload_wa_file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
                            }
                            else
                            {
                                $header = prapareWAComponent('header', $request->header_variables, $parameter_format, $match_header[1]);
                            }
                            
                            $body = prapareWAComponent('body', $request->body_variables, $parameter_format, $match_body[1]);
                            $footer = prapareWAComponent('footer', $request->footer_variables, $parameter_format, $match_footer[0]);

                            // Button code needs to implement
                            $urlArray = $request->url_variable_array;
                            $coupon_code = $request->coupon_code_variable;
                            $catalog_code[] = $request->catalog_code_variable;
                            $flow_code[] = $request->flow_code_variable;
                            $buttons = prapareWAButtonComponent($whatsappButtons,$urlArray,$coupon_code,$catalog_code,$flow_code);

                            $obj = array_merge($header, $body, $footer, $buttons);

                            $messagePayload = waMsgPayload($checkNumberValid['mobile_number'], $whatsAppTemplate->template_name, $whatsAppTemplate->template_language, $obj);
                        }
                        else
                        {
                            // custom WA sms
                            $checkNumberValid = waCheckNumberValid($number[$file_mobile_field_name], $country->phonecode, $country->min, $country->max);
                            $unique_key = uniqueKey();
                            $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);

                            //variables
                            //header_var_
                            //body_var_
                            //footer_var_
                            //button_url_var_
                            //button_coupon_var_
                            //media_var_
                            $messagePayload = $number;
                            $waVariables = [
                                'header',
                                'body',
                                'footer'
                            ];

                            $named_head = 'no_var';
                            $named_body = 'no_var';
                            $named_foot = 'no_var';

                            if($parameter_format=='NAMED')
                            {
                                $prepareArr = [];
                                if(sizeof($match_header[1])>0) 
                                { 
                                    $named_head = $match_header[1][0];
                                }

                                if($template_type=='MEDIA')
                                {

                                    $latitude = null;
                                    $longitude = null;
                                    $location_name = null;
                                    $location_address = null;
                                    if($media_type=='location')
                                    {
                                        $latitude = $number['latitude_var_1'];
                                        $longitude = $number['longitude_var_1'];
                                        $location_name = $number['location_name_var_1'];
                                        $location_address = $number['location_address_var_1'];
                                    }

                                    if(!empty($number['media_var_1']))
                                    {
                                        $titleOrFileName = $number['caption_var_1'];
                                        $header = prapareWAComponent('header', $number['media_var_1'], $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
                                    }
                                    else
                                    {
                                        $titleOrFileName = $request->file_caption;
                                        $header = prapareWAComponent('header', $request->upload_wa_file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
                                    }
                                }
                                else
                                {
                                    $prepareArr[] = [
                                        "text" => $number[$named_head]
                                    ];
                                    $header = prapareWAComponent('header', $prepareArr, $parameter_format, $match_header[1]);
                                }

                                $prepareArr = [];
                                foreach ($match_body[1] as $key => $value) 
                                { 
                                    $prepareArr[] = [
                                        "text" => $number[$value]
                                    ];
                                }
                                $body = prapareWAComponent('body', $prepareArr, $parameter_format, $match_body[1]);
                                

                                $prepareArr = [];
                                if(sizeof($match_footer[1])>0) 
                                { 
                                    $prepareArr[] = $number[$match_footer[1][0]];
                                }
                                $footer = prapareWAComponent('footer', $prepareArr, $parameter_format, $match_footer[1]);
                            }
                            else
                            {
                                foreach ($waVariables as $key => $waVariable) 
                                {
                                    $preparePayload = findVariableCounts($csvHeader, $waVariable);
                                    $prepareArr = [];
                                    for ($i=1; $i <= $preparePayload ; $i++) 
                                    { 
                                        $prepareArr[] = $number[$waVariable.'_var_'.($i)];
                                    }
                                    if($waVariable=='header')
                                    {
                                        if($template_type=='MEDIA')
                                        {
                                            $latitude = null;
                                            $longitude = null;
                                            $location_name = null;
                                            $location_address = null;
                                            if($media_type=='location')
                                            {
                                                $latitude = $number['latitude_var_1'];
                                                $longitude = $number['longitude_var_1'];
                                                $location_name = $number['location_name_var_1'];
                                                $location_address = $number['location_address_var_1'];
                                            }
                                            
                                            if(!empty($number['media_var_1']))
                                            {
                                                $titleOrFileName = $number['caption_var_1'];
                                                $header = prapareWAComponent($waVariable, $number['media_var_1'], $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
                                            }
                                            else
                                            {
                                                $titleOrFileName = $request->file_caption;
                                                $header = prapareWAComponent($waVariable, $request->upload_wa_file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
                                            }
                                        }
                                        else
                                        {
                                            $header = prapareWAComponent($waVariable, $prepareArr, $parameter_format, $match_header[1]);
                                        }
                                    }

                                    if($waVariable=='body')
                                    {
                                        $body = prapareWAComponent($waVariable, $prepareArr, $parameter_format, $match_body[1]);
                                    }
                                    if($waVariable=='footer')
                                    {
                                        $footer = prapareWAComponent($waVariable, $prepareArr, $parameter_format, $match_footer[1]);
                                    }
                                }
                            }

                            

                            // Button code needs to implement
                            $waButtonVariables = [
                                'button_url',
                                'button_coupon',
                                'product_catalog_id',
                                'flow_token'
                            ];
                            $urlArray = $coupon_code = $catalog_code = $flow_code = null;
                            foreach ($waButtonVariables as $key => $waButtonVariable) 
                            {
                                $preparePayload = findVariableCounts($csvHeader, $waButtonVariable);
                                $prepareArr = [];
                                for ($i=1; $i <= $preparePayload ; $i++) 
                                { 
                                    $prepareArr[] = $number[$waButtonVariable.'_var_'.($i)];
                                }

                                if($waButtonVariable=='button_url')
                                {
                                    $urlArray = $prepareArr;
                                }
                                
                                if($waButtonVariable=='button_coupon')
                                {
                                    $coupon_code = implode(',', $prepareArr);
                                }

                                if($waButtonVariable=='product_catalog_id')
                                {
                                    $catalog_code[] = implode(',', $prepareArr);
                                }

                                if($waButtonVariable=='flow_token')
                                {
                                    $flow_code[] = implode(',', $prepareArr);
                                }
                            }

                            $buttons = prapareWAButtonComponent($whatsappButtons,$urlArray,$coupon_code,$catalog_code,$flow_code);

                            $obj = array_merge($header,$body, $footer, $buttons);
                            $messagePayload = waMsgPayload($checkNumberValid['mobile_number'], $whatsAppTemplate->template_name, $whatsAppTemplate->template_language, $obj);

                        }

                        if($checkNumberValid['number_status']!=0)
                        {
                            $isAllowedToDeductCredit = checkWaCreditDeductOrNot($user_id, $checkNumberValid['mobile_number'], $whatsAppTemplate->category);
                            if($isAllowedToDeductCredit)
                            {
                                $total_numbers_for_deductions++;
                            } 
                        }

                        $campaignData[] = [
                            'batch_id' => $batch_id,
                            'whats_app_send_sms_id' => $waSendSMS->id,
                            'user_id' => $userInfo->id,
                            'unique_key' => $unique_key,
                            'sender_number' => $whatsAppConfiguration->sender_number,
                            'mobile' => $checkNumberValid['mobile_number'],
                            'template_category' => $whatsAppTemplate->category,
                            'message' => json_encode($messagePayload),
                            'use_credit' => ($checkNumberValid['number_status']==0) ? 0 : $chargesPerMsg,
                            'is_auto' => $is_auto,
                            'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                            'error_info' => $checkNumberValid['erro_info'],
                            'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                            'created_at' => Carbon::now()->toDateTimeString(),
                            'updated_at' => Carbon::now()->toDateTimeString(),
                        ];
                    }
                    else
                    {
                        $countBlankRow++;
                    }

                    if(@$checkNumberValid['number_status']==0)
                    {
                        $invalidNumber += 1;
                    }
                    $count++;
                }

                executeWAQuery($campaignData);
                $campaignData = [];
                usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
            }

            // credit deduct 
            // Create log first
            $total_credit_used = $total_numbers_for_deductions * $chargesPerMsg;
            $waSendSMS->total_credit_deduct = $total_credit_used;
            $waSendSMS->save();

            $creditLog = creditLog($userInfo->id, auth()->id(), $route_type, 2, $total_credit_used, null, 2);

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            $waSendSMS->total_invalid_number = $invalidNumber;
            $waSendSMS->save();

            if(!$creditDeduct)
            {
                \Log::error('Error during creditDeduct, campaign stopped now. please check info: userID/RouteType/TotalCreditUsed: '. $userInfo->id.'/'.$route_type.'/'.$total_credit_used);
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }
            executeBatchQuery($batchArr);

            // run automation
            if(env('APP_ENV', 'local') == 'production' && date('s') >= 5)
            {
                \Artisan::call('wafile:process');
            }

            return response()->json(prepareResult(false, $waSendSMS->makeHidden('whats_app_configuration_id','whats_app_template_id','user_id','sender_number','file_path','file_mobile_field_name','is_read_file_path','reschedule_whats_app_send_sms_id','reschedule_type','total_block_number','id','ratio_percent_set','failed_ratio'), trans('translate.campaign_successfully_processed'), $this->intime), config('httpcodes.created'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        try {
            $sendSms = WhatsAppSendSms::select('id','user_id', 'campaign', 'whats_app_template_id', 'sender_number', 'message', 'campaign_send_date_time', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_sent', 'total_delivered', 'total_read', 'total_failed', 'total_other', 'is_credit_back', 'credit_back_date', 'status', 'created_at')
            ->with('whatsAppTemplate:id,category,template_language,template_name,template_type')
            ->find($id);

            if($sendSms)
            {
                return response()->json(prepareResult(false, $sendSms, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waGetCampaignInfo(Request $request, $id)
    {
        try {
            $campaign = WhatsAppSendSms::select('id', 'user_id', 'whats_app_template_id','created_at')->with('whatsAppTemplate:id,category,template_language,template_type,header_text,media_type,message,footer_text')->withCount('whatsAppSendSmsQueues')->find($id);

            if(!$campaign)
            {
                return response()->json(prepareResult(true, trans('translate.record_not_found'), trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            
            $search = $request->search;

            if(in_array(loggedInUserType(), [0,3]))
            {
                if($campaign->whats_app_send_sms_queues_count>0)
                {
                    $queue = WhatsAppSendSmsQueue::select('whats_app_send_sms_queues.id','whats_app_send_sms_queues.unique_key', 'whats_app_send_sms_queues.mobile', 'whats_app_send_sms_queues.use_credit', 'whats_app_send_sms_queues.stat', 'whats_app_send_sms_queues.status', 'whats_app_send_sms_queues.error_info', 'whats_app_send_sms_queues.submit_date', 'whats_app_send_sms_queues.response_token', 'whats_app_send_sms_queues.conversation_id', 'whats_app_send_sms_queues.expiration_timestamp', 'whats_app_send_sms_queues.sent', 'whats_app_send_sms_queues.sent_date_time', 'whats_app_send_sms_queues.delivered', 'whats_app_send_sms_queues.delivered_date_time', 'whats_app_send_sms_queues.read', 'whats_app_send_sms_queues.read_date_time', 'whats_app_send_sms_queues.delivered', 'whats_app_send_sms_queues.is_auto')
                        ->where('whats_app_send_sms_queues.whats_app_send_sms_id', $id)

                        ->with('WhatsAppReplyThreads:id,queue_history_unique_key,profile_name,phone_number_id,message,received_date');

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('whats_app_send_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_queues.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_queues.error_info', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = WhatsAppSendSmsHistory::select('whats_app_send_sms_histories.id','whats_app_send_sms_histories.unique_key', 'whats_app_send_sms_histories.mobile', 'whats_app_send_sms_histories.use_credit', 'whats_app_send_sms_histories.stat', 'whats_app_send_sms_histories.status', 'whats_app_send_sms_histories.error_info', 'whats_app_send_sms_histories.submit_date', 'whats_app_send_sms_histories.response_token', 'whats_app_send_sms_histories.conversation_id', 'whats_app_send_sms_histories.expiration_timestamp', 'whats_app_send_sms_histories.sent', 'whats_app_send_sms_histories.sent_date_time', 'whats_app_send_sms_histories.delivered', 'whats_app_send_sms_histories.delivered_date_time', 'whats_app_send_sms_histories.read', 'whats_app_send_sms_histories.read_date_time', 'whats_app_send_sms_histories.delivered', 'whats_app_send_sms_histories.is_auto')
                    ->where('whats_app_send_sms_histories.whats_app_send_sms_id', $id)
                    ->with('WhatsAppReplyThreads:id,queue_history_unique_key,profile_name,phone_number_id,message,received_date');

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('whats_app_send_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_histories.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_histories.error_info', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $history; 
                }          
            }
            else
            {
                if($campaign->whats_app_send_sms_queues_count>0)
                {
                    $queue = WhatsAppSendSmsQueue::select('whats_app_send_sms_queues.id','whats_app_send_sms_queues.unique_key', 'whats_app_send_sms_queues.mobile', 'whats_app_send_sms_queues.use_credit', 'whats_app_send_sms_queues.stat', 'whats_app_send_sms_queues.status', 'whats_app_send_sms_queues.error_info', 'whats_app_send_sms_queues.submit_date', 'whats_app_send_sms_queues.response_token', 'whats_app_send_sms_queues.conversation_id', 'whats_app_send_sms_queues.expiration_timestamp', 'whats_app_send_sms_queues.sent', 'whats_app_send_sms_queues.sent_date_time', 'whats_app_send_sms_queues.delivered', 'whats_app_send_sms_queues.delivered_date_time', 'whats_app_send_sms_queues.read', 'whats_app_send_sms_queues.read_date_time', 'whats_app_send_sms_queues.delivered')
                        ->where('whats_app_send_sms_queues.whats_app_send_sms_id', $id)
                        ->with('WhatsAppReplyThreads:id,queue_history_unique_key,profile_name,phone_number_id,message,received_date');

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('whats_app_send_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_queues.status', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = WhatsAppSendSmsHistory::select('whats_app_send_sms_histories.id','whats_app_send_sms_histories.unique_key', 'whats_app_send_sms_histories.mobile', 'whats_app_send_sms_histories.use_credit', 'whats_app_send_sms_histories.stat', 'whats_app_send_sms_histories.status', 'whats_app_send_sms_histories.error_info', 'whats_app_send_sms_histories.submit_date', 'whats_app_send_sms_histories.response_token', 'whats_app_send_sms_histories.conversation_id', 'whats_app_send_sms_histories.expiration_timestamp', 'whats_app_send_sms_histories.sent', 'whats_app_send_sms_histories.sent_date_time', 'whats_app_send_sms_histories.delivered', 'whats_app_send_sms_histories.delivered_date_time', 'whats_app_send_sms_histories.read', 'whats_app_send_sms_histories.read_date_time', 'whats_app_send_sms_histories.delivered')
                        ->where('whats_app_send_sms_histories.whats_app_send_sms_id', $id)
                        ->with('WhatsAppReplyThreads:id,queue_history_unique_key,profile_name,phone_number_id,message,received_date');

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('whats_app_send_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('whats_app_send_sms_histories.status', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $history;
                }
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $pagination =  [
                    'message' => $campaign->whatsAppTemplate,
                    'data' => $result,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage),
                ];
                $query = $pagination;
            }
            else
            {
                $query = $query->get();
            }
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waCampaignStop(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'whats_app_send_sms_id' => 'required|exists:whats_app_send_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $campaign = WhatsAppSendSms::query();

            if(in_array(loggedInUserType(), [1,2]))
            {
                $campaign->where('whats_app_send_sms.user_id', auth()->id());
            }

            $campaign = $campaign->find($request->whats_app_send_sms_id);

            if(!$campaign)
            {
                return response()->json(prepareResult(true, trans('translate.record_not_found'), trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }

            if($campaign->status=='Pending')
            {
                $campaign->status = 'Stop';
                $campaign->save();
                if($campaign)
                {
                    // Campaign queue status also stopped.
                    \DB::table('whats_app_send_sms_queues')
                        ->where('whats_app_send_sms_id', $campaign->id)
                        ->update([
                            'status' => 'Stop'
                        ]);
                    //Credit back stopped campaign
                    $creditLog = creditLog($campaign->user_id, 1, 5, 1, $campaign->total_credit_deduct, null, 2, "campaign stopped, credit reversed");

                    //Credit Back
                    $userInfo = User::find($campaign->user_id);
                    creditAdd($userInfo, 5, $campaign->total_credit_deduct);
                }

                return response()->json(prepareResult(false, $campaign, trans('translate.campaign_stopped_and_credit_reversed'), $this->intime), config('httpcodes.success'));
            }

            return response()->json(prepareResult(true, [], trans('translate.campaign_is_not_in_changeable_state'), $this->intime), config('httpcodes.bad_request'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waGetCampaignCurrentStatus($whats_app_send_sms_id)
    {
        try {
            $total_sent = 0;
            $total_delivered = 0;
            $total_read = 0;
            $total_failed = 0;
            $total_invalid = 0;
            $total_block = 0;
            $total_pending = 0;
            $total_other = 0;

            $totalRecords = \DB::table('whats_app_send_sms_queues')->select('stat', \DB::raw('COUNT(stat) as stat_counts'))
                ->where('whats_app_send_sms_id', $whats_app_send_sms_id)
                ->groupBy('stat')
                ->get();
            if($totalRecords->count()<1)
            {
                $totalRecords = \DB::table('whats_app_send_sms_histories')->select('stat', \DB::raw('COUNT(stat) as stat_counts'))
                ->where('whats_app_send_sms_id', $whats_app_send_sms_id)
                ->groupBy('stat')
                ->get();
            }

            foreach ($totalRecords as $key => $value) 
            {
                switch (strtolower($value->stat)) 
                {
                    case strtolower('accepted'):
                        $total_sent += $value->stat_counts;
                        break;
                    case strtolower('delivered'):
                        $total_delivered += $value->stat_counts;
                        break;
                    case strtolower('read'):
                        $total_read += $value->stat_counts;
                        break;
                    case strtolower('failed'):
                        $total_failed += $value->stat_counts;
                        break;
                    case strtolower('INVALID'):
                        $total_invalid += $value->stat_counts;
                        break;
                    case strtolower('Block'):
                        $total_block += $value->stat_counts;
                        break;
                    case strtolower('Pending'):
                        $total_pending += $value->stat_counts;
                        break;
                    default:
                        $total_other += $value->stat_counts;
                        break;
                }
            }

            \DB::statement("UPDATE `whats_app_send_sms` SET 
            `total_sent` = $total_sent, 
            `total_delivered` = $total_delivered,
            `total_read` = $total_read,
            `total_failed` = $total_failed,
            `total_invalid_number` = $total_invalid,
            `total_block_number` = $total_block,
            `total_other` = $total_other,
            `status` = CASE WHEN `total_contacts` <= (`total_sent` + `total_delivered` + `total_read` + `total_failed` + `total_invalid_number` + `total_block_number` + `total_other`) THEN 'Completed' ELSE `status` END
            
            WHERE `id` = $whats_app_send_sms_id AND status!='Stop';");

            return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waReplyThreadUsers(Request $request)
    {
        /*
        select MAX(`id`), `profile_name`, `phone_number_id`, (select message FROM whats_app_reply_threads wa_table where wa_table.id=MAX(wa_inner.`id`)) as message FROM whats_app_reply_threads wa_inner group by `phone_number_id` order by `id` desc;
        */

        $phone_numbers = \DB::table('whats_app_configurations')
                ->where('user_id', auth()->id())->pluck('display_phone_number_req');

        try {
            $query = \DB::table('whats_app_reply_threads as wa_threads')
                ->select('id','user_id','profile_name','phone_number_id')
                ->selectRaw("(SELECT `message` FROM whats_app_reply_threads getMessage WHERE `getMessage`.`id`=MAX(`wa_threads`.`id`)) as message")
                ->orderBy('id', 'DESC')
                ->groupBy('phone_number_id');


            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
                $query->whereIn('display_phone_number', $phone_numbers);
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
            }

            if(!empty($request->whats_app_send_sms_id))
            {
                $query->where('whats_app_send_sms_id', $request->whats_app_send_sms_id);
            }

            if(!empty($request->queue_history_unique_key))
            {
                $query->where('queue_history_unique_key', $request->queue_history_unique_key);
            }

            if(!empty($request->phone_number))
            {
                $query->where('phone_number_id', 'LIKE', '%'.$request->phone_number.'%');
            }

            $query = $query->get();

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waReplyThreadCaseWise(Request $request)
    {
        try {
            // do not change model query, 
            // if you want to change orderBy then also reorder the read record list.
            $query = WhatsAppReplyThread::orderBy('id', 'DESC')
            ->with('WhatsAppSendSms:id,campaign,sender_number,message,whats_app_configuration_id', 'WhatsAppSendSmsQueue:id,message,sender_number,mobile','WhatsAppSendSmsHistory:id,message,sender_number,mobile');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_reply_threads.user_id', auth()->id());
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_reply_threads.user_id', $request->user_id);
            }

            if(!empty($request->whats_app_send_sms_id))
            {
                $query->where('whats_app_reply_threads.whats_app_send_sms_id', $request->whats_app_send_sms_id);
            }

            if(!empty($request->queue_history_unique_key))
            {
                $query->where('whats_app_reply_threads.queue_history_unique_key', $request->queue_history_unique_key);
            }

            if(!empty($request->phone_number))
            {
                $phone_number = $request->phone_number;
                $query->where(function($q) use ($phone_number) {
                    $q->where('whats_app_reply_threads.phone_number_id', $phone_number)
                        ->orWhere('whats_app_reply_threads.display_phone_number', $phone_number);
                });
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $last_customer_rpl_id = null;
                if(count($result)>0)
                {
                    foreach ($result as $key => $value) 
                    {
                        if($value->is_vendor_reply!=1)
                        {
                            $last_customer_rpl_id = $value->id;
                            $getConfInfo = WhatsAppConfiguration::find(@$value->WhatsAppSendSms->whats_app_configuration_id);
                            if($getConfInfo)
                            {
                                //\Log::info($getConfInfo);
                                $access_token = base64_decode($getConfInfo->access_token);
                                $sender_number = $getConfInfo->sender_number;
                                $appVersion = $getConfInfo->app_version;
                                $response_token = $value->response_token;
                                wAReplyMessageRead($access_token, $sender_number, $appVersion, $response_token);
                            }
                            break;
                        }
                    }
                }

                $pagination =  [
                    'data' => $result->reverse()->values(),
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage),
                    'last_message_id' => $last_customer_rpl_id
                ];
                $query = $pagination;
            }
            else
            {
                $query = $query->get();
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waSendReplyMessage(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'whats_app_configuration_id' => 'required|exists:whats_app_configurations,id',
            'whats_app_reply_thread_id' => 'required|exists:whats_app_reply_threads,id',
            'type' => 'required|in:text,image,video,document'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $validation = \Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }

            $user_id = $request->user_id;
        }
        else
        {
            $user_id = auth()->id();
        }

        $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($request->whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        try {
            $getRecord = WhatsAppReplyThread::where('user_id', $user_id)->with('user:id,name')->find($request->whats_app_reply_thread_id);
            if($getRecord)
            {
                $access_token = base64_decode($getConfInfo->access_token);
                $sender_number = $getConfInfo->sender_number;
                $appVersion = $getConfInfo->app_version;

                $response = waSendReplyMsg($access_token, $sender_number, $appVersion, $getRecord->phone_number_id, $request->type, $request->message, $request->response_token, $request->file_name);

                if($response['error']==false)
                {
                    $response = json_decode($response['response'], true);
                    $wa_reply_threads[] = [
                        'queue_history_unique_key' => $getRecord->queue_history_unique_key,
                        'whats_app_send_sms_id' => $getRecord->whats_app_send_sms_id,
                        'user_id' => $getRecord->user_id,
                        'profile_name' => $getRecord->user->name,
                        'phone_number_id' => $getRecord->display_phone_number,
                        'display_phone_number' => $getRecord->phone_number_id,
                        'user_mobile' => $getRecord->phone_number_id,
                        'message' => $request->message,
                        'context_ref_wa_id' => null,
                        'error_info' => null,
                        'received_date' => date('Y-m-d H:i:s'),
                        'response_token' => @$response['messages'][0]['id'],
                        'use_credit' => null,
                        'is_vendor_reply' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    executeWAReplyThreds($wa_reply_threads);

                    return response()->json(prepareResult(false, $wa_reply_threads, trans('translate.created'), $this->intime), config('httpcodes.created'));
                }
                return response()->json(prepareResult(true, [], $response->messages[0]->message_status, $this->intime), config('httpcodes.internal_server_error'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waDownloadReplyFile(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'whats_app_reply_thread_id' => 'required|exists:whats_app_reply_threads,id',
            'whats_app_configuration_id' => 'required|exists:whats_app_configurations,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $validation = \Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }

            $user_id = $request->user_id;
        }
        else
        {
            $user_id = auth()->id();
        }

        $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($request->whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        try {

            $getRecord = WhatsAppReplyThread::where('user_id', $user_id)->find($request->whats_app_reply_thread_id);
            if($getRecord)
            {
                $access_token = base64_decode($getConfInfo->access_token);
                $mediaId = $getRecord->media_id;
                $appVersion = $getConfInfo->app_version;
                
                $data = getMediaFileFromWA($access_token, $mediaId, $appVersion);

                $getRecord->media_url = $data;
                $getRecord->save();

                return response()->json(prepareResult(false, $getRecord, trans('translate.synced'), $this->intime), config('httpcodes.success'));
            }

            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waRepushCampaign(Request $request, $wa_send_sms_id)
    {
        /*
        $validation = \Validator::make($request->all(), [
            'whats_app_reply_thread_id' => 'required|exists:whats_app_reply_threads,id',
            'whats_app_configuration_id' => 'required|exists:whats_app_configurations,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        */
        if(in_array(loggedInUserType(), [0,3]))
        {
            $validation = \Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }

            $user_id = $request->user_id;
        }
        else
        {
            $user_id = auth()->id();
        }

        $getCampaign = WhatsAppSendSms::where('user_id', $user_id)->find($request->wa_send_sms_id);
        if(!$getCampaign)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        try {

            $submitMsgs = WhatsAppSendSmsQueue::select('whats_app_send_sms_queues.id','whats_app_send_sms_queues.unique_key','whats_app_send_sms_queues.mobile','whats_app_send_sms_queues.submit_date','whats_app_send_sms_queues.whats_app_send_sms_id','whats_app_send_sms_queues.message','whats_app_send_sms.whats_app_configuration_id','whats_app_send_sms.whats_app_template_id','whats_app_send_sms.sender_number','whats_app_configurations.access_token','whats_app_configurations.app_version','whats_app_templates.template_language','whats_app_templates.template_name')
                ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                ->join('whats_app_configurations', 'whats_app_send_sms.whats_app_configuration_id', 'whats_app_configurations.id')
                ->join('whats_app_templates', 'whats_app_send_sms.whats_app_template_id', 'whats_app_templates.id')
                ->where('is_auto', 0)
                ->where('stat', 'failed')
                ->where('whats_app_send_sms_queues.whats_app_send_sms_id', $wa_send_sms_id)
                ->limit(5)
                ->get();

            foreach ($submitMsgs as $key => $submitMsg) 
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

            return response()->json(prepareResult(false, [], trans('translate.synced'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
