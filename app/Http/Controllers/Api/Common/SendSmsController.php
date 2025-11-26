<?php
namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\SendSms;
use App\Models\SendSmsQueue;
use App\Models\SendSmsHistory;
use App\Models\User;
use App\Models\PrimaryRoute;
use App\Models\SecondaryRoute;
use App\Models\DltTemplate;
use App\Models\ContactNumber;
use App\Models\Blacklist;
use App\Models\DlrcodeVender;
use App\Models\InvalidSeries;
use Illuminate\Http\Request;
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

class SendSmsController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:send-sms');
    }

    public function index(Request $request)
    {
        try
        {
            $query =  SendSms::select('id','uuid','user_id', 'campaign', 'dlt_template_id', 'sender_id', 'route_type', 'country_id', 'sms_type', 'message', 'message_type', 'is_flash', 'campaign_send_date_time','is_campaign_scheduled', 'message_count', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status')
            ->with('dltTemplate:id,dlt_template_id,template_name')
            ->orderBy('id','DESC');
            if(in_array(loggedInUserType(), [0,3]))
            {
                $query->withoutGlobalScope('parent_id');
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
            }
            elseif(in_array(loggedInUserType(), [1]))
            {
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
                else
                {
                    $query->where('user_id', auth()->id());
                    $query->withoutGlobalScope('parent_id');
                }
            }
            else
            {
                $query->where('user_id', auth()->id());
                $query->withoutGlobalScope('parent_id');
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('campaign', 'LIKE', '%' . $search. '%')
                    ->orWhere('campaign_send_date_time', 'LIKE', '%' . $search. '%')
                    ->orWhere('sender_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('message', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->campaign))
            {
                $query->where('campaign', 'LIKE', '%'.$request->campaign.'%');
            }

            if(!empty($request->only_campaign) && $request->only_campaign=='yes')
            {
                $query->where('campaign', '!=', 'API');
            }

            if(!empty($request->dlt_template_id))
            {
                $query->where('dlt_template_id', $request->dlt_template_id);
            }

            if(!empty($request->sender_id))
            {
                $query->where('sender_id', 'LIKE', '%'.$request->sender_id.'%');
            }

            if(!empty($request->campaign_send_date_time))
            {
                $query->whereDate('campaign_send_date_time', $request->campaign_send_date_time);
            }

            if(!empty($request->is_campaign_scheduled) && $request->is_campaign_scheduled == 1)
            {
                $query->where('is_campaign_scheduled', 1);
            }
            elseif(!empty($request->is_campaign_scheduled) && $request->is_campaign_scheduled == 'no')
            {
                $query->where('is_campaign_scheduled', 0);
            }

            if(!empty($request->route_type))
            {
                $query->where('route_type', $request->route_type);
            }

            if(!empty($request->sms_type))
            {
                $query->where('sms_type', $request->sms_type);
            }

            if(!empty($request->message_type))
            {
                $query->where('message_type', $request->message_type);
            }

            if(!empty($request->status))
            {
                $query->whereDate('status', $request->status);
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
            }
            else
            {
                $query = $query->get();
            }
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'campaign' => 'required|string',
            'dlt_template_id' => 'required|exists:dlt_templates,id',
            'route_type'    => 'required|in:1,2,3,4',
            'sms_type'      => 'required|in:1,2',
            'contact_group_ids'     => 'array'
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

        if($request->sms_type==2)
        {
            $validation = \Validator::make($request->all(), [
                'file_path' => 'required',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.file_required_when_send_custom_sms'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        if(empty($request->mobile_numbers) && count($request->contact_group_ids)<1 && empty($request->file_path))
        {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.add_atleast_one_mobile_number'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!empty($request->file_path) && empty($request->file_mobile_field_name))
        {
            return response()->json(prepareResult(true, [], trans('translate.define_file_column_name_for_mobile'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!empty($request->file_path) && !file_exists($request->file_path))
        {
            return response()->json(prepareResult(true, [], trans('translate.file_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!empty($request->file_path) && $request->sms_type==1)
        {
            $fp = fopen($request->file_path, 'r');
            $csvHeader = fgetcsv($fp);
            if(count($csvHeader)>1)
            {
                return response()->json(prepareResult(true, [], trans('translate.only_one_column_allowed_in_csv_file'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        if(!empty($request->file_path) && !empty($request->file_mobile_field_name))
        {
            $selected_column_name = preg_replace('/[^A-Za-z0-9\_ ]/', '', $request->file_mobile_field_name);
            $selected_column_name = strtolower(preg_replace('/\s+/', '_', $selected_column_name));

            $fp = fopen($request->file_path, 'r');
            $csvHeader = fgetcsv($fp);
            $isHeaderMatched = false;
            foreach ($csvHeader as $key => $header) {
                if($header==$selected_column_name)
                {
                    $isHeaderMatched = true;
                    break;
                }
            }
            if(!$isHeaderMatched)
            {
                return response()->json(prepareResult(true, [], trans('translate.csv_column_not_matched_for_mobile_number'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        $user_id = !empty($request->user_id) ? $request->user_id : auth()->id();
        $userInfo = User::find($user_id);

        $route_type = $request->route_type;
        $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);
        if(!$userInfo || @$userInfo->status!='1')
        {
            return response()->json(prepareResult(true, [], trans('translate.user_not_found_or_inactive_status'), $this->intime), config('httpcodes.internal_server_error'));
        }

        // webhook only for api request now so we comment this line and pass null value.
        // $wh_url = $userInfo->webhook_callback_url;
        $wh_url = null;

        $campaign_send_date_time = !empty($request->campaign_send_date_time) ? $request->campaign_send_date_time : Carbon::now()->toDateTimeString();

        if(!empty($request->campaign_send_date_time) && strtotime($request->campaign_send_date_time)<1)
        {
            return response()->json(prepareResult(true, [], trans('translate.campaign_schedule_date_and_time_invalid'), $this->intime), config('httpcodes.bad_request'));
        }

        if($route_type==2)
        {
            $timeCheck = checkPromotionalHours($campaign_send_date_time);
            if(!$timeCheck)
            {
                return response()->json(prepareResult(true, [], trans('translate.can_not_send_promotional_activity_selected_time'), $this->intime), config('httpcodes.internal_server_error'));
            }
        }

        $secondary_route_id = !empty($request->secondary_route_id) ? $request->secondary_route_id : $getRouteCreditInfo['secondary_route_id'];
        if(empty($secondary_route_id))
        {
            return response()->json(prepareResult(true, [], trans('translate.route_not_assigned_please_contact_to_admin'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->find($secondary_route_id);
        if(!$secondaryRouteInfo || @$secondaryRouteInfo->primaryRoute==null)
        {
            return response()->json(prepareResult(true, [], trans('translate.route_not_found'), $this->intime), config('httpcodes.internal_server_error'));
        }

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

        $dltTemplate = DltTemplate::where('user_id', $user_id)
            ->find($request->dlt_template_id);
        if(!$dltTemplate)
        {
            return response()->json(prepareResult(true, [], trans('translate.dlt_template_not_assigned_to_you'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $sending_type = ($route_type==2) ? [2] : [1,3];
        if($dltTemplate->manageSenderId && !in_array($dltTemplate->manageSenderId->sender_id_type, $sending_type))
        {
            return response()->json(prepareResult(true, [], trans('translate.sender_id_not_matched_with_message_type'), $this->intime), config('httpcodes.bad_request'));
        }


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

        //parameters
        $message_type = ($dltTemplate->is_unicode==1) ? 2 : 1;
        $message = ($request->same_as_template) ? $request->message : preg_replace('/\s+/', ' ', trim($request->message));
        $sender_id = $dltTemplate->sender_id;
        if(empty($request->message))
        {
            $message = ($request->same_as_template) ? $dltTemplate->dlt_message : preg_replace('/\s+/', ' ', trim($dltTemplate->dlt_message));
        }
        $messageSizeInfo = messgeLenght($message_type, $message);
        $priority = $dltTemplate->priority;

        $getRatio = getRatio($userInfo->id, $route_type);

        $ratio_percent_set = !empty($request->ratio_set) ? $request->ratio_set : $getRatio['speedRatio'];
        $is_flash = ($request->is_flash==1) ? 1 : 0;

        // Start Blacklist merge and set condition for india only (91)
        $blacklist = Blacklist::select('mobile_number')->where('user_id', $userInfo->id)->pluck('mobile_number')->toArray();

        $withoutCountryCode = array_map(function($num) {
            return (substr($num, 0, 2) === "91") ? substr($num, 2) : $num;
        }, $blacklist);

        $blacklist = collect([$blacklist,$withoutCountryCode]);
        $blacklist = $blacklist->flatten()->toArray();

        // End Blacklist merge and set condition for india only (91)

        $file_numbers = [];
        if(!empty($request->file_mobile_field_name)) {
            $selected_column_name = preg_replace('/[^A-Za-z0-9\_ ]/', '', $request->file_mobile_field_name);
            $selected_column_name = strtolower(preg_replace('/\s+/', '_', $selected_column_name));
        } else {
            $selected_column_name = 'mobile';
        }
        
        try {
            //normal SMS campaign
            if($request->sms_type==1)
            {
                //file number
                if(!empty($request->file_path))
                {
                    $csvFileData = Excel::toArray(new CampaignImport(), $request->file_path);
                    $file_numbers = $csvFileData[0];
                }

                //combine array
                $numberFlatten = collect([$total_input_number,
                $total_group_number, $file_numbers]);
                $all_numbers = $numberFlatten->flatten()->toArray();

                //Removed duplicate numbers
                $all_numbers = array_unique(preg_replace('/\s+/', '', $all_numbers), SORT_REGULAR);

                //blacklist compare set default if message not send instant
                $actualNumberForSend = $all_numbers;
                $blackListNumber = [];

                $total_contacts = count($all_numbers);
                $actualNumberForSendCount = count($actualNumberForSend);
                $total_credit_used = $actualNumberForSendCount * $messageSizeInfo['message_credit_size'];
                $total_block_number = count($blackListNumber);
                if(env('RATIO_FREE', 100) >= $actualNumberForSendCount)
                {
                    $ratio_percent_set = 0;
                }

                $getFailedRatio = ($request->failed_ratio>0) ? $request->failed_ratio : $getRatio['speedFRatio'];

                $failed_ratio = ($ratio_percent_set > 0) ? $getFailedRatio : null;
                //Check current balance
                if($getRouteCreditInfo['current_credit'] < $total_credit_used)
                {
                    return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
                }

                //create campaign
                $sendSMS = campaignCreate($userInfo->parent_id, $userInfo->id, $request->campaign, $secondary_route_id, $request->dlt_template_id, $sender_id, $route_type, $request->sms_type, $message, $message_type, $is_flash, null, $selected_column_name, $campaign_send_date_time, $priority, $messageSizeInfo['message_character_count'], $messageSizeInfo['message_credit_size'], $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, null, null, $failed_ratio, $request->schedule, $dltTemplate->dlt_template_group_id);

                //Create log first
                $creditLog = creditLog($userInfo->id, auth()->id(), $route_type, 2, $total_credit_used, null, 2);

                //credit deduct
                $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

                
                if(!$creditDeduct)
                {
                    \Log::error('Error during creditDeduct, campaign stopped now. please check info: userID/RouteType/TotalCreditUsed: '. $userInfo->id.'/'.$route_type.'/'.$total_credit_used);
                    $sendSMS->status = 'Stop';
                    $sendSMS->save();
                    DB::rollback();
                    return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
                }

                if((env('INSTANT_NUM_OF_MSG_SEND', 5000) >= $total_contacts) && (time()>=strtotime($campaign_send_date_time)) && $route_type != 3)
                {
                    //if instant send then filter list
                    //blacklist compare
                    $actualNumberForSend = array_diff($all_numbers, $blacklist);
                    $blackListNumber = array_intersect($all_numbers, $blacklist);

                    //Invalid Series
                    $invalidSeries = InvalidSeries::pluck('start_with')->toArray();

                    //instant send message
                    $actualNumberForSend = collect($actualNumberForSend);
                    $chunks = $actualNumberForSend->chunk(env('CHUNK_SIZE', 1000));
                    $invalidNumber = 0;
                    $creditRevInvalid = 0;
                    $countBlankRow = 0;
                    /*******************************************
                     ******************************************/
                    //kannel paramter
                    $kannelPara = kannelParameter($request->is_flash, $dltTemplate->is_unicode);
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
                    //$smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;
                    /*******************************************
                     ******************************************/

                    foreach ($chunks->toArray() as $chunk) 
                    {
                        //\Log::info($chunk);
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

                                $campaignData[] = [
                                    'send_sms_id' => $sendSMS->id,
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
                                
                            if(@$checkNumberValid['is_auto']==0 && @$checkNumberValid['number_status']==1)
                            {
                                //\Log::info($message);
                                //\Log::info(urlencode($message));
                                $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                                $kannelData[] = [
                                    'momt' => 'MT',
                                    'sender' => $sender_id,
                                    'receiver' => @$checkNumberValid['mobile_number'],
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
                            
                            if(@$checkNumberValid['number_status']==0)
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
                                'send_sms_id' => $sendSMS->id,
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
                        $sendSMS->total_contacts = $sendSMS->total_contacts - $countBlankRow;
                    }

                    $sendSMS->total_block_number = count($blackListNumber);
                    $sendSMS->total_invalid_number = $invalidNumber;
                    $totalCreditBack = ($creditRevInvalid + (count($blackListNumber) * $messageSizeInfo['message_credit_size']) + ($countBlankRow * $messageSizeInfo['message_credit_size']));
                    $sendSMS->total_credit_deduct = $sendSMS->total_credit_deduct - ($totalCreditBack);
                    $sendSMS->status = 'Ready-to-complete';
                    $sendSMS->save();

                    //Credit Back
                    creditAdd($userInfo, $route_type, $totalCreditBack);
                }
                else
                {
                    //file system implement
                    $csv_header = $selected_column_name."\n";
                    if(!empty($request->file_path))
                    {
                        $fp = fopen($request->file_path, 'r');
                        $file_path = $request->file_path;
                    }
                    else
                    {
                        $destinationPath    = 'csv/campaign/';
                        $file_path = $destinationPath.$sendSMS->id.'.csv';
                        $fileDestination    = fopen ($file_path, "w");
                        
                        fputs($fileDestination, $csv_header);
                        fclose($fileDestination);

                        $fp = fopen($file_path, 'r');
                    }
                    $inputAndGrpNumFlatten = collect([$total_input_number,
                    $total_group_number]);
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
                                $csv_write_data[] = ($csvColumnName == $selected_column_name) ? $number : null;
                                
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

                    //update file name and change status to pending
                    $sendSMS->file_path = $file_path;
                    $sendSMS->is_read_file_path = 0;
                    $sendSMS->status = 'Pending';
                    $sendSMS->save();

                    setCampaignExecuter($sendSMS->id, $campaign_send_date_time, $route_type);
                }
            }
            else
            {
                //custom SMS campaign
                $inputAndGrpNumFlatten = collect([$total_input_number,
                $total_group_number]);
                $inputAndGrpNum = $inputAndGrpNumFlatten->flatten();
                //added input number and group number in the selected file
                if(count($inputAndGrpNum)>0)
                {
                    $fp = fopen($request->file_path, 'r');
                    $csvHeader = fgetcsv($fp);
                    $csv_write_data = [];
                    $final_data = [];
                    foreach ($inputAndGrpNum as $key => $number) 
                    {
                        foreach ($csvHeader as $key => $columnMatch) 
                        {
                            $csvColumnName = preg_replace('/[^A-Za-z0-9\_ ]/', '', $columnMatch);
                            $csvColumnName = strtolower(preg_replace('/\s+/', '_', $csvColumnName));
                            $csv_write_data[] = ($csvColumnName == $selected_column_name) ? $number : null;
                            
                        }
                        $final_data[] = $csv_write_data;
                        $csv_write_data = [];
                        usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                    }
                    $handle = fopen($request->file_path, 'a+');
                    foreach ($final_data as $key => $data) {
                        fputcsv($handle, $data);
                    }
                    
                    fclose($handle);
                }

                $csvFileData = Excel::toArray(new CampaignImport(), $request->file_path);
                $mobileNums = $csvFileData[0];
                $total_contacts = count($mobileNums);
                $total_block_number = 0;
                $total_credit_used = 0;
                if(env('RATIO_FREE', 100) >= $total_contacts)
                {
                    $ratio_percent_set = 0;
                }

                $getFailedRatio = ($request->failed_ratio>0) ? $request->failed_ratio : $getRatio['speedFRatio'];

                $failed_ratio = ($ratio_percent_set > 0) ? $getFailedRatio : null;

                //create campaign
                $sendSMS = campaignCreate($userInfo->parent_id, $userInfo->id, $request->campaign, $secondary_route_id, $request->dlt_template_id, $sender_id, $route_type, $request->sms_type, $message, $message_type, $is_flash, null, $selected_column_name, $campaign_send_date_time, $priority, $messageSizeInfo['message_character_count'], $messageSizeInfo['message_credit_size'], $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, null, null, $failed_ratio, $request->schedule, $dltTemplate->dlt_template_group_id);

                //readFile
                foreach ($mobileNums as $key => $chunk) 
                {
                    $excalRow = $mobileNums[$key];
                    $changeArray    = [];
                    $newKeyword     = [];
                    if(isset($changeArray) && is_array($changeArray) && count($changeArray) < 1)
                    {
                        foreach ($excalRow as $word => $addCurlyBraces) {
                            $newKeyword['{{'.$word.'}}'] = $addCurlyBraces;
                        }
                    }
                    $msg = strReplaceAssoc($newKeyword, $message);
                    $messgeLenghtCount = messgeLenght($message_type, $msg);                                   
                    $total_credit_used  += $messgeLenghtCount['message_credit_size'];
                }

                //Check current balance
                if($getRouteCreditInfo['current_credit'] < $total_credit_used)
                {
                    if($sendSMS)
                    {
                        $sendSMS->delete();
                    }
                    return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
                }

                //Create log first
                $creditLog = creditLog($userInfo->id, auth()->id(), $route_type, 2, $total_credit_used, null, 2);

                //credit deduct
                $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);
                if(!$creditDeduct)
                {
                    \Log::error('Error during creditDeduct, campaign stopped now. please check info: userID/RouteType/TotalCreditUsed: '. $userInfo->id.'/'.$route_type.'/'.$total_credit_used);
                    $sendSMS->status = 'Stop';
                    $sendSMS->save();
                    DB::rollback();
                    return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
                }
                

                //update file name and change status to pending
                $sendSMS->total_credit_deduct = $total_credit_used;
                $sendSMS->is_read_file_path = 0;
                $sendSMS->file_path = $request->file_path;
                $sendSMS->status = 'Pending';
                $sendSMS->save();

                setCampaignExecuter($sendSMS->id, $campaign_send_date_time, $route_type);
            }
            
            return response()->json(prepareResult(false, $sendSMS->makeHidden('secondary_route_id','parent_id','user_id','dlt_template_id','message_type','file_path','file_mobile_field_name','priority','is_read_file_path','reschedule_send_sms_id','reschedule_type','total_block_number','id'), trans('translate.campaign_successfully_processed'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            /*
            if(@$sendSMS) 
            {
                $sendSMS->delete();
            }
            */
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        try {
            /********************
            DB::statement("UPDATE `send_sms` SET 

            `total_delivered` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'DELIVRD') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'DELIVRD'), 

            `total_failed` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')) + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),

            `total_block_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'BLACK') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'BLACK'),

            `total_invalid_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'Invalid') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'Invalid'),

            `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
            
            WHERE `id` = $id AND `status`='Ready-to-complete';");
            *********************/
            $sendSms = SendSms::select('id','user_id', 'campaign', 'dlt_template_id', 'sender_id', 'route_type', 'country_id', 'sms_type', 'message', 'message_type', 'is_flash', 'campaign_send_date_time', 'message_count', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status', 'created_at')
            ->with('dltTemplate:id,dlt_template_id,template_name')
            ->find($id);


            if($sendSms)
            {
                $timeDifference = time() - strtotime($sendSms->created_at);
                if(($sendSms->status=='Ready-to-complete') && ($timeDifference > 300))
                {
                    /*$getCamInfo = SendSms::select('id','is_update_auto_status', 'ratio_percent_set', 'failed_ratio')
                        ->where('is_update_auto_status', 0)
                        ->find($sendSms->id);
                    if($getCamInfo && ($getCamInfo->is_update_auto_status==0) && (($getCamInfo->ratio_percent_set > 0) || ($getCamInfo->failed_ratio > 0)))
                    {
                        reUpdatePending($id);
                    }*/
                }

                if($sendSms->route_type==3)
                {
                    $sendSms['total_click_logs'] = DB::table(env('DB_DATABASE2W').'.short_links')->where('send_sms_id', $id)->sum('total_click');
                    $sendSms['total_response_feedbacks'] = DB::table(env('DB_DATABASE2W').'.two_way_comm_feedbacks')->where('send_sms_id', $id)->count();
                    $sendSms['total_response_interests'] = DB::table(env('DB_DATABASE2W').'.two_way_comm_interests')->where('send_sms_id', $id)->count();
                    $sendSms['total_response_ratings'] = DB::table(env('DB_DATABASE2W').'.two_way_comm_ratings')->where('send_sms_id', $id)->count();
                }

                return response()->json(prepareResult(false, $sendSms, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function changeCampaignStatusToStop(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'send_sms_id' => 'required|exists:send_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {

            $sendSms = SendSms::select('id','uuid','user_id', 'campaign', 'dlt_template_id', 'sender_id', 'route_type', 'country_id', 'sms_type', 'message', 'message_type', 'is_flash', 'campaign_send_date_time','is_campaign_scheduled', 'message_count', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status')
                    ->with('dltTemplate:id,dlt_template_id,template_name')
                ->where('status', 'Pending');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $sendSms->where('user_id', auth()->id());
            }
            else
            {
                $sendSms = $sendSms->find($request->send_sms_id);
            }
            
            if($sendSms)
            {
                $sendSms->status = 'Stop';
                $sendSms->save();
                if($sendSms)
                {
                    //Credit back stopped campaign
                    $creditLog = creditLog($sendSms->user_id, 1, $sendSms->route_type, 1, $sendSms->total_credit_deduct, null, 2, "campaign stopped, credit reversed");

                    //Credit Back
                    $userInfo = User::find($sendSms->user_id);
                    creditAdd($userInfo, $sendSms->route_type, $sendSms->total_credit_deduct);
                }
                return response()->json(prepareResult(false, $sendSms, trans('translate.campaign_stopped_and_credit_reversed'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.campaign_is_not_in_changeable_state'), $this->intime), config('httpcodes.bad_request'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function repushCampaign(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'send_sms_id' => 'required|exists:send_sms,id',
            'reschedule_type' => 'required|in:ALL,Pending,FAILED,Accepted,DELIVRD',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        try {
            $sendSMS = SendSms::find($request->send_sms_id);
            $campaign_send_date_time = !empty($request->campaign_send_date_time) ? $request->campaign_send_date_time : Carbon::now()->toDateTimeString();

            if(!empty($request->campaign_send_date_time) && strtotime($request->campaign_send_date_time)<1)
            {
                return response()->json(prepareResult(true, [], trans('translate.campaign_schedule_date_and_time_invalid'), $this->intime), config('httpcodes.bad_request'));
            }

            $is_campaign_scheduled = (strtotime($campaign_send_date_time) > time()) ? 1 : 0;
            if($sendSMS->route_type==2)
            {
                $timeCheck = checkPromotionalHours($campaign_send_date_time);
                if(!$timeCheck)
                {
                    return response()->json(prepareResult(true, [], trans('translate.can_not_send_promotional_activity_selected_time'), $this->intime), config('httpcodes.internal_server_error'));
                }
            }

            $queue = $sendSMS->sendSmsQueues()->select(DB::raw('COUNT(id) as total_queue_number'), DB::raw('SUM(use_credit) as total_queue_credit_used'));

            if($request->reschedule_type!='ALL')
            {
                $queue->where('stat', $request->reschedule_type);
            }
            else
            {
                $queue->whereNotIn('stat', ['Black','invalid']);
            }

            $queue = $queue->first();
            $total_queue_number = $queue->total_queue_number;
            $total_queue_credit_used = $queue->total_queue_credit_used;

            $history = $sendSMS->sendSmsHistories()->select(DB::raw('COUNT(id) as total_history_number'), DB::raw('SUM(use_credit) as total_history_credit_used'));
            if($request->reschedule_type!='ALL')
            {
                $history->where('stat', $request->reschedule_type);
            }
            else
            {
                $history->whereNotIn('stat', ['Black','invalid']);
            }

            $history = $history->first();
            $total_history_number = $history->total_history_number;
            $total_history_credit_used = $history->total_history_credit_used;

            $total_contacts = $total_queue_number + $total_history_number;
            $total_credit_used = $total_queue_credit_used + $total_history_credit_used;
            $total_block_number = 0;

            if($total_contacts<1)
            {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.no_mobile_number_found_in_selected_operation'), $this->intime), config('httpcodes.bad_request'));
            }

            $userInfo = User::find($sendSMS->user_id);

            $route_type = $sendSMS->route_type;
            $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);
            if(!$userInfo || @$userInfo->status!='1')
            {
                return response()->json(prepareResult(true, [], trans('translate.user_not_found_or_inactive_status'), $this->intime), config('httpcodes.internal_server_error'));
            }

            // webhook only for api request now so we comment this line and pass null value.
            // $wh_url = $userInfo->webhook_callback_url;
            $wh_url = null;

            $secondary_route_id = $getRouteCreditInfo['secondary_route_id'];
            $secondaryRouteInfo = SecondaryRoute::find($secondary_route_id);
            if(!$secondaryRouteInfo || @$secondaryRouteInfo->primaryRoute==null)
            {
                return response()->json(prepareResult(true, [], trans('translate.route_not_found'), $this->intime), config('httpcodes.internal_server_error'));
            }

            $dltTemplate = DltTemplate::find($sendSMS->dlt_template_id);
            if($userInfo->id!=@$dltTemplate->user_id)
            {
                return response()->json(prepareResult(true, [], trans('translate.dlt_template_not_assigned_to_you'), $this->intime), config('httpcodes.internal_server_error'));
            }

            //Check current balance
            if($getRouteCreditInfo['current_credit'] < $total_credit_used)
            {
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }
            
            $is_read_file_path = (time()>=strtotime($campaign_send_date_time)) ? 1 : 0;
            $status = (time()>=strtotime($campaign_send_date_time)) ? 'Ready-to-complete' : 'Pending';
            $reschedule_send_sms_id = $request->send_sms_id;
            $reschedule_type = $request->reschedule_type;

            $getRatio = getRatio($userInfo->id, $route_type);            

            $ratio_percent_set = !empty($request->ratio_set) ? $request->ratio_set : $getRatio['speedRatio'];
            if(env('RATIO_FREE', 100) >= $total_contacts)
            {
                $ratio_percent_set = 0;
            }

            $getFailedRatio = ($request->failed_ratio>0) ? $request->failed_ratio : $getRatio['speedFRatio'];

            $failed_ratio = ($ratio_percent_set > 0) ? $getFailedRatio : null;

            $repushSendSMS = campaignCreate($sendSMS->parent_id, $sendSMS->user_id, $sendSMS->campaign, $secondary_route_id, $sendSMS->dlt_template_id, $sendSMS->sender_id, $sendSMS->route_type, $sendSMS->sms_type, $sendSMS->message, $sendSMS->message_type, $sendSMS->is_flash, null, $sendSMS->selected_column_name, $campaign_send_date_time, $sendSMS->priority, $sendSMS->message_count, $sendSMS->message_credit_size, $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, $status, $is_read_file_path, $reschedule_send_sms_id, $reschedule_type, $failed_ratio, $is_campaign_scheduled, $dltTemplate->dlt_template_group_id);

            //Create log first
            $creditLog = creditLog($userInfo->id, auth()->id(), $route_type, 2, $total_credit_used, null, 2);

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);
            if(!$creditDeduct)
            {
                \Log::error('Error during creditDeduct, please check info: userID/RouteType/TotalCreditUsed: '. $userInfo->id.'/'.$route_type.'/'.$total_credit_used);
                $sendSMS->status = 'Stop';
                $sendSMS->save();
                DB::rollback();
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }

            if($is_read_file_path == 0)
            {
                setCampaignExecuter($repushSendSMS->id, $campaign_send_date_time, $route_type);
                return response()->json(prepareResult(false, $repushSendSMS->makeHidden('secondary_route_id','parent_id','user_id','dlt_template_id','message_type','file_path','file_mobile_field_name','priority','is_read_file_path','reschedule_send_sms_id','reschedule_type','total_block_number','id'), trans('translate.campaign_successfully_scheduled'), $this->intime), config('httpcodes.created'));
            }

            //now copy records
            if($request->reschedule_type!='ALL')
            {
                DB::statement("INSERT send_sms_queues
                SELECT null, $repushSendSMS->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_histories WHERE `send_sms_id` = $request->send_sms_id AND `stat` = '".$request->reschedule_type."';");

                DB::statement("INSERT send_sms_queues
                SELECT null, $repushSendSMS->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_queues WHERE `send_sms_id` = $request->send_sms_id AND `stat` = '".$request->reschedule_type."';");
            }
            else
            {
                DB::statement("INSERT send_sms_queues
                SELECT null, $repushSendSMS->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_histories WHERE `send_sms_id` = $request->send_sms_id AND `stat` NOT IN ('Black','invalid');");

                DB::statement("INSERT send_sms_queues
                SELECT null, $repushSendSMS->id, $secondaryRouteInfo->primary_route_id, CONCAT(UNIX_TIMESTAMP(), LPAD(FLOOR( RAND() * 100000000), 7, 0)), `mobile`, `message`, `use_credit`, 0, 'Pending', null, 'Completed', null, null, null, null, null, NOW(), NOW() FROM send_sms_queues WHERE `send_sms_id` = $request->send_sms_id AND `stat` NOT IN ('Black','invalid');");
            }
            
            //now insert kannel table
            //kannel paramter
            $priority = $repushSendSMS->priority;
            $sender_id = $repushSendSMS->sender_id;
            $kannelPara = kannelParameter($repushSendSMS->is_flash, $dltTemplate->is_unicode);
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
            $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;
            /*******************************************
             ******************************************/
            $queue = SendSmsQueue::where('send_sms_id', $repushSendSMS->id)
            ->chunk(env('CHUNK_SIZE', 1000), function ($records) use ($kannelPara, $meta_data, $kannel_ip, $node_port, $smsc_id, $priority, $sender_id, $ratio_percent_set, $failed_ratio,$wh_url)
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

                    if($isNotInRatio==true)
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
            
            //Final response
            return response()->json(prepareResult(false, $repushSendSMS->makeHidden('secondary_route_id','parent_id','user_id','dlt_template_id','message_type','file_path','file_mobile_field_name','priority','is_read_file_path','reschedule_send_sms_id','reschedule_type','total_block_number','id'), trans('translate.campaign_successfully_processed'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getCampaignInfo(Request $request, $id)
    {
        try {
            $campaign = SendSms::select('id', 'secondary_route_id','created_at')->withCount('sendSmsQueues')->find($id);

            if(!$campaign)
            {
                return response()->json(prepareResult(true, trans('translate.record_not_found'), trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }

            if(@$campaign->secondaryRoute==null)
            {
                \Log::error("secondaryRoute not found. Please check this campaign: ". $id);
                return response()->json(prepareResult(true, trans('translate.something_went_wrong'), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
            
            //$primary_route_id = $campaign->secondaryRoute->primary_route_id;
            $search = $request->search;

            
            $timeDifference = time() - strtotime($campaign->created_at);
            if(($campaign->status=='Ready-to-complete') && ($timeDifference > 300))
            {
                /*$getCamInfo = SendSms::select('id','is_update_auto_status', 'ratio_percent_set', 'failed_ratio')
                    ->where('is_update_auto_status', 0)
                    ->find($campaign->id);
                if($getCamInfo && ($getCamInfo->is_update_auto_status==0) && (($getCamInfo->ratio_percent_set > 0) || ($getCamInfo->failed_ratio > 0)))
                {
                    reUpdatePending($id);
                }*/
            }

            if(in_array(loggedInUserType(), [0,3]))
            {
                if($campaign->send_sms_queues_count>0)
                {
                    $queue = \DB::table('send_sms_queues')
                    ->select('send_sms_queues.id', 'send_sms_queues.mobile', 'send_sms_queues.message', 'send_sms_queues.use_credit', 'send_sms_queues.stat', 'send_sms_queues.status', 'send_sms_queues.err', 'send_sms_queues.submit_date', 'send_sms_queues.done_date', 'send_sms_queues.response_token', 'send_sms_queues.is_auto')
                        /*->with(["dlrInfo" => function($q) use ($primary_route_id) {
                            $q->select('dlr_code','description')
                                ->where('primary_route_id', '=', $primary_route_id);
                        }])*/
                        ->where('send_sms_queues.send_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('send_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_queues.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_queues.err', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = \DB::table('send_sms_histories')
                    ->select('send_sms_histories.id', 'send_sms_histories.mobile', 'send_sms_histories.message', 'send_sms_histories.use_credit', 'send_sms_histories.stat', 'send_sms_histories.status', 'send_sms_histories.err', 'send_sms_histories.submit_date', 'send_sms_histories.done_date', 'send_sms_histories.response_token', 'send_sms_histories.is_auto')
                        /*->with(["dlrInfo" => function($q) use ($primary_route_id) {
                            $q->select('dlr_code','description')
                                ->where('primary_route_id', '=', $primary_route_id);
                        }])*/
                    ->where('send_sms_histories.send_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('send_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_histories.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_histories.err', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $history;
                }                
            }
            else
            {
                if($campaign->send_sms_queues_count>0)
                {
                    $queue = \DB::table('send_sms_queues')
                    ->select('send_sms_queues.id', 'send_sms_queues.mobile', 'send_sms_queues.message', 'send_sms_queues.use_credit', 'send_sms_queues.stat', 'send_sms_queues.status', 'send_sms_queues.err', 'send_sms_queues.submit_date', 'send_sms_queues.done_date')
                    ->where('send_sms_queues.send_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('send_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_queues.status', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = \DB::table('send_sms_histories')
                    ->select('send_sms_histories.id', 'send_sms_histories.mobile', 'send_sms_histories.message', 'send_sms_histories.use_credit', 'send_sms_histories.stat', 'send_sms_histories.status', 'send_sms_histories.err', 'send_sms_histories.submit_date', 'send_sms_histories.done_date')
                    ->where('send_sms_histories.send_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('send_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('send_sms_histories.status', 'LIKE', '%'.$search.'%');
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

    public function resendThisSms(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'sms_queue_id'    => 'required|exists:send_sms_queues,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [1,2]))
        {
            return response()->json(prepareResult(true, [], trans('translate.unauthorized_to_perform_operation'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $findSMS = SendSmsQueue::find($request->sms_queue_id);
            $findSMS->is_auto = 0;
            $findSMS->save();
            
            $smsInfo = $findSMS->sendSms;
            $dltTemplate = $findSMS->sendSms->dltTemplate;
            $is_unicode = ($smsInfo->message_type==1) ? 0 : 1;

            //kannel paramter
            $kannelPara = kannelParameter($smsInfo->is_flash, $is_unicode);
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
            $smsc_id = $findSMS->primaryRoute->smsc_id;
            $priority = $smsInfo->priority;

            $wh_url = null;

            $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$findSMS->unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
            $kannelData[] = [
                'momt' => 'MT',
                'sender' => $smsInfo->sender_id,
                'receiver' => $findSMS->mobile,
                'msgdata' => urlencode($findSMS->message),
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
                'binfo' => $findSMS->unique_key,
            ];

            executeKannelQuery($kannelData);

            return response()->json(prepareResult(false, [], trans('translate.message_successfully_submitted'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getCampaignCurrentStatus($send_sms_id)
    {
        try {
            $delivrd = 0;
            $pending = 0;
            $accepted = 0;
            $invalid = 0;
            $black = 0;
            $failed = 0;

            $checkQueueData = \DB::table('send_sms_queues')
                ->select('id')
                ->where('send_sms_id', $send_sms_id)
                ->limit(1)
                ->count();

            if($checkQueueData>0)
            {
                $totalRecords = \DB::table('send_sms_queues');
            }
            else
            {
                $totalRecords = \DB::table('send_sms_histories');
            }
            $totalRecords = $totalRecords->select('stat', \DB::raw('COUNT(stat) as stat_counts'))
                ->where('send_sms_id', $send_sms_id)
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

            \DB::statement("UPDATE `send_sms` SET 

            `total_delivered` = $delivrd, 

            `total_failed` = $failed,

            `total_block_number` = $black,

            `total_invalid_number` = $invalid,

            `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
            
            WHERE `id` = $send_sms_id;");

            return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getDlrExplanation($dlr_code)
    {
        try {
            $dlrcodeVender = DlrcodeVender::select('dlr_code', 'description')
                ->where('dlr_code', $dlr_code)
                ->get();
            if(!$dlrcodeVender)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            return response()->json(prepareResult(false, $dlrcodeVender, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
