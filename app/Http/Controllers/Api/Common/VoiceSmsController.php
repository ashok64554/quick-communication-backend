<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VoiceUpload;
use App\Models\VoiceUploadSentGateway;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\VoiceSmsHistory;
use App\Models\User;
use App\Models\PrimaryRoute;
use App\Models\SecondaryRoute;
use App\Models\ContactNumber;
use App\Models\Blacklist;
use App\Models\InvalidSeries;
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


class VoiceSmsController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:voice-sms');
    }

    public function index(Request $request)
    {
        try
        {
            $query =  VoiceSms::select('id','uuid','user_id', 'campaign', 'campaign_id', 'obd_type', 'dtmf', 'call_patch_number','voice_id', 'voice_file_path', 'country_id', 'campaign_send_date_time','is_campaign_scheduled', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status')
            ->with('voiceUpload:id,title,file_time_duration')
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
                }
            }
            else
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('campaign', 'LIKE', '%' . $search. '%')
                    ->orWhere('campaign_send_date_time', 'LIKE', '%' . $search. '%')
                    ->orWhere('voice_id', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->campaign))
            {
                $query->where('campaign', 'LIKE', '%'.$request->campaign.'%');
            }

            if(!empty($request->voice_id))
            {
                $query->where('voice_id', $request->voice_id);
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
            'voice_upload_id' => 'required|exists:voice_uploads,id',
            'contact_group_ids'     => 'array',
            'dtmf' => 'required_with:call_patch_number|numeric|nullable',
            'call_patch_number' => 'numeric|nullable',
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

        if(!empty($request->file_path))
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

        $route_type = 4; // voice sms
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

        $obd_type = checkObdType($request->dtmf, $request->call_patch_number);

        $timeCheck = checkPromotionalHours($campaign_send_date_time);
        if(!$timeCheck)
        {
            return response()->json(prepareResult(true, [], trans('translate.can_not_send_promotional_activity_selected_time'), $this->intime), config('httpcodes.internal_server_error'));
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

        $voiceUpload = VoiceUpload::where('user_id', $user_id)
            ->find($request->voice_upload_id);

        if(!$voiceUpload)
        {
            return response()->json(prepareResult(true, [], trans('translate.voice_id_not_assigned_to_you'), $this->intime), config('httpcodes.internal_server_error'));
        }

        $getVoiceId = VoiceUploadSentGateway::select('voice_id', 'file_status')
            ->where('voice_upload_id', $voiceUpload->id)
            ->where('primary_route_id', $secondaryRouteInfo->primary_route_id)
            ->whereNotNull('voice_id')
            ->first();
        if(!$getVoiceId)
        {
            return response()->json(prepareResult(true, [], trans('translate.voice_file_not_verified_to_selected_route'), $this->intime), config('httpcodes.internal_server_error'));
        }
        $voice_id = $getVoiceId->voice_id;
        $voice_file_path = $voiceUpload->file_location;

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

        $voiceLenghtCredit = voiceLenghtCredit($voiceUpload->file_time_duration);
        $priority = $voiceUpload->priority;

        $getRatio = getRatio($userInfo->id, $route_type);
        
        $ratio_percent_set = !empty($request->ratio_set) ? $request->ratio_set : $getRatio['speedRatio'];

        $blacklist = Blacklist::select('mobile_number')->where('user_id', $userInfo->id)->pluck('mobile_number')->toArray();
        $file_numbers = [];
        if(!empty($request->file_mobile_field_name)) {
            $selected_column_name = preg_replace('/[^A-Za-z0-9\_ ]/', '', $request->file_mobile_field_name);
            $selected_column_name = strtolower(preg_replace('/\s+/', '_', $selected_column_name));
        } else {
            $selected_column_name = 'mobile';
        }
        
        try {
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

            if($total_contacts>env('MAXIMUM_NUMBERS_SENT', 25000))
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_sent_more_then_DIFINE_number_numbers'), $this->intime), config('httpcodes.internal_server_error'));
            }

            if($obd_type==4 && $total_contacts>1)
            {
                return response()->json(prepareResult(true, [], trans('translate.in_OBD_type_OTP_cannot_add_more_than_one_number'), $this->intime), config('httpcodes.internal_server_error'));
            }

            $actualNumberForSendCount = count($actualNumberForSend);
            $total_credit_used = $actualNumberForSendCount * $voiceLenghtCredit;
            $total_block_number = count($blackListNumber);
            if(env('RATIO_FREE', 100) >= $actualNumberForSendCount)
            {
                $ratio_percent_set = 0;
            }
            $failed_ratio = ($ratio_percent_set > 0) ? $request->failed_ratio : null;
            //Check current balance
            if($getRouteCreditInfo['current_credit'] < $total_credit_used)
            {
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }

            //create campaign
            $voiceSMS = voiceCampaignCreate($userInfo->parent_id, $userInfo->id, $request->campaign, $obd_type, $secondary_route_id, $request->voice_upload_id, $voice_id, $voice_file_path, $selected_column_name, $campaign_send_date_time, $priority, $voiceLenghtCredit, $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, $failed_ratio, $request->schedule, $request->dtmf, $request->call_patch_number);

            //Create log first
            $creditLog = creditLog($userInfo->id, auth()->id(), $route_type, 2, $total_credit_used, null, 2);

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            
            if(!$creditDeduct)
            {
                \Log::error('Error during creditDeduct, campaign stopped now. please check info: userID/RouteType/TotalCreditUsed: '. $userInfo->id.'/'.$route_type.'/'.$total_credit_used);
                $voiceSMS->status = 'Stop';
                $voiceSMS->save();
                return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.internal_server_error'));
            }

            if((env('VOICE_INSTANT_NUM_OF_MSG_SEND', 5000) >= $total_contacts) && (time()>=strtotime($campaign_send_date_time)) && $route_type != 3)
            {
                //if instant send then filter list
                //blacklist compare
                $actualNumberForSend = array_diff($all_numbers, $blacklist);
                $blackListNumber = array_intersect($all_numbers, $blacklist);

                //Invalid Series
                $invalidSeries = InvalidSeries::pluck('start_with')->toArray();

                //instant send message
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
                                'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Process',
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
                        $response = sendVoiceSMSApi($voiceSendData, $secondaryRouteInfo->primaryRoute, $voiceSMS, $request->otp);
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
                $totalCreditBack = ($creditRevInvalid + (count($blackListNumber) * $voiceLenghtCredit) + ($countBlankRow * $voiceLenghtCredit));
                $voiceSMS->total_credit_deduct = $voiceSMS->total_credit_deduct - ($totalCreditBack);
                $voiceSMS->status = 'Ready-to-complete';
                $voiceSMS->save();

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
                    $destinationPath    = 'csv/voice/';
                    $file_path = $destinationPath.$voiceSMS->id.'.csv';
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
                $voiceSMS->file_path = $file_path;
                $voiceSMS->is_read_file_path = 0;
                $voiceSMS->status = 'Pending';
                $voiceSMS->save();

                setCampaignExecuter($voiceSMS->id, $campaign_send_date_time, $route_type);
            }
                    
            return response()->json(prepareResult(false, $voiceSMS->makeHidden('secondary_route_id','parent_id','user_id','voice_file_path','file_path','file_mobile_field_name','priority','is_read_file_path','total_block_number','transection_id','id'), trans('translate.campaign_successfully_processed'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        try {
            $voiceSms = VoiceSms::select('id', 'voice_upload_id','user_id', 'campaign', 'obd_type', 'dtmf', 'call_patch_number', 'country_id', 'secondary_route_id', 'voice_file_path', 'country_id', 'campaign_send_date_time', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status', 'created_at')
            ->with('voiceUpload:id,title')
            ->find($id);

            if($voiceSms)
            {

                return response()->json(prepareResult(false, $voiceSms, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getVoiceCampaignInfo(Request $request, $id)
    {
        try {
            $campaign = VoiceSms::select('id', 'secondary_route_id','created_at')->withCount('voiceSmsQueues')->find($id);

            if(!$campaign)
            {
                return response()->json(prepareResult(true, trans('translate.record_not_found'), trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }

            if(@$campaign->secondaryRoute==null)
            {
                \Log::error("secondaryRoute not found. Please check this campaign: ". $id);
                return response()->json(prepareResult(true, trans('translate.something_went_wrong'), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
            
            $search = $request->search;

            if(in_array(loggedInUserType(), [0,3]))
            {
                if($campaign->voice_sms_queues_count>0)
                {
                    $queue = \DB::table('voice_sms_queues')
                    ->select('voice_sms_queues.id', 'voice_sms_queues.mobile', 'voice_sms_queues.voice_id', 'voice_sms_queues.use_credit', 'voice_sms_queues.stat', 'voice_sms_queues.status', 'voice_sms_queues.err', 'voice_sms_queues.submit_date', 'voice_sms_queues.done_date', 'voice_sms_queues.start_time', 'voice_sms_queues.end_time', 'voice_sms_queues.duration', 'voice_sms_queues.dtmf', 'voice_sms_queues.response_token', 'voice_sms_queues.is_auto')
                        ->where('voice_sms_queues.voice_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('voice_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_queues.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_queues.err', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = \DB::table('voice_sms_histories')
                    ->select('voice_sms_histories.id', 'voice_sms_histories.mobile', 'voice_sms_histories.message', 'voice_sms_histories.use_credit', 'voice_sms_histories.stat', 'voice_sms_histories.status', 'voice_sms_histories.err', 'voice_sms_histories.submit_date', 'voice_sms_histories.done_date', 'voice_sms_histories.start_time', 'voice_sms_histories.end_time', 'voice_sms_histories.duration', 'voice_sms_histories.dtmf', 'voice_sms_histories.response_token', 'voice_sms_histories.is_auto')
                    ->where('voice_sms_histories.voice_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('voice_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_histories.status', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_histories.err', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $history;
                }                
            }
            else
            {
                if($campaign->voice_sms_queues_count>0)
                {
                    $queue = \DB::table('voice_sms_queues')
                    ->select('voice_sms_queues.id', 'voice_sms_queues.mobile', 'voice_sms_queues.voice_id', 'voice_sms_queues.use_credit', 'voice_sms_queues.stat', 'voice_sms_queues.status', 'voice_sms_queues.err', 'voice_sms_queues.submit_date', 'voice_sms_queues.done_date', 'voice_sms_queues.start_time', 'voice_sms_queues.end_time', 'voice_sms_queues.duration', 'voice_sms_queues.dtmf')
                    ->where('voice_sms_queues.voice_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $queue->where(function($q) use ($search) {
                            $q->where('voice_sms_queues.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_queues.status', 'LIKE', '%'.$search.'%');
                        });
                    }
                    $query = $queue;
                }
                else
                {
                    $history = \DB::table('voice_sms_histories')
                    ->select('voice_sms_histories.id', 'voice_sms_histories.mobile', 'voice_sms_histories.voice_id', 'voice_sms_histories.use_credit', 'voice_sms_histories.stat', 'voice_sms_histories.status', 'voice_sms_histories.err', 'voice_sms_histories.submit_date', 'voice_sms_histories.done_date', 'voice_sms_histories.start_time', 'voice_sms_histories.end_time', 'voice_sms_histories.duration', 'voice_sms_histories.dtmf')
                    ->where('voice_sms_histories.voice_sms_id', $id);

                    if(!empty($request->search))
                    {
                        $history->where(function($q) use ($search) {
                            $q->where('voice_sms_histories.mobile', 'LIKE', '%'.$search.'%')
                            ->orWhere('voice_sms_histories.status', 'LIKE', '%'.$search.'%');
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

    public function changeVoiceCampaignStatusToStop(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'voice_sms_id' => 'required|exists:voice_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            if(in_array(loggedInUserType(), [1,2]))
            {
                $voiceSms = VoiceSms::select('id','user_id','status', 'total_credit_deduct')
                    ->where('user_id', auth()->id())
                    ->where('status', 'Pending')
                    ->find($request->voice_sms_id);
            }
            else
            {
                $voiceSms = VoiceSms::select('id','user_id','status', 'total_credit_deduct')
                    ->where('status', 'Pending')
                    ->find($request->voice_sms_id);
            }
            if($voiceSms)
            {
                $voiceSms->status = 'Stop';
                $voiceSms->save();
                if($voiceSms)
                {
                    $route_type = 4;
                    //Credit back stopped campaign
                    $creditLog = creditLog($voiceSms->user_id, 1, $route_type, 1, $voiceSms->total_credit_deduct, null, 2, "voice campaign stopped, credit reversed");

                    //Credit Back
                    $userInfo = User::find($voiceSms->user_id);
                    creditAdd($userInfo, $route_type, $voiceSms->total_credit_deduct);
                }
                return response()->json(prepareResult(false, VoiceSms::find($request->voice_sms_id), trans('translate.campaign_stopped_and_credit_reversed'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.campaign_is_not_in_changeable_state'), $this->intime), config('httpcodes.bad_request'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function resendThisVoiceSms(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'voice_sms_queue_id'    => 'required|exists:voice_sms_queues,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [1,2]))
        {
            return response()->json(prepareResult(true, [], trans('translate.unauthorized_to_perform_operation'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $findVoiceSMS = VoiceSmsQueue::find($request->voice_sms_queue_id);
            $findVoiceSMS->is_auto = 0;
            $findVoiceSMS->save();
            
            $smsInfo = $findVoiceSMS->voiceSms;
            $voiceUpload = $findVoiceSMS->voiceSms->voiceUpload;
            
            //Voice Api
            $mobileNumber[] = $findVoiceSMS->mobile;
            $response = sendVoiceSMSApi($mobileNumber, $findVoiceSMS->primaryRoute, $findVoiceSMS->voiceSms, $request->otp);
            if(!$response || $response==false)
            {
                return response()->json(prepareResult(true, [], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }

            return response()->json(prepareResult(false, [], trans('translate.message_successfully_submitted'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getVoiceCampaignCurrentStatus($voice_sms_id)
    {
        try {
            $checkQueueData = \DB::table('voice_sms_queues')
                ->select('id')
                ->where('voice_sms_id', $voice_sms_id)
                ->limit(1)
                ->count();

            if($checkQueueData>0)
            {
                \DB::statement("UPDATE `send_sms` SET `total_delivered` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `send_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'DELIVRD'), 
                `total_failed` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `send_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),
                `total_block_number` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `send_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'BLACK'),
                `total_invalid_number` = (SELECT COUNT(*) FROM `voice_sms_queues` WHERE `send_sms`.`id` = `voice_sms_queues`.`voice_sms_id` AND `stat` = 'Invalid'),
                `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
                WHERE `id` = '".$voice_sms_id."'");
            }
            else
            {
                \DB::statement("UPDATE `send_sms` SET `total_delivered` = (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `send_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'DELIVRD'), 
                `total_failed` = (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `send_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),
                `total_block_number` = (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `send_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'BLACK'),
                `total_invalid_number` = (SELECT COUNT(*) FROM `voice_sms_histories` WHERE `send_sms`.`id` = `voice_sms_histories`.`voice_sms_id` AND `stat` = 'Invalid'),
                `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
                WHERE `id` = '".$voice_sms_id."'");
            }

            return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getVoiceCampaignSummeryServer($voice_sms_id)
    {
        try {
            $voiceSms = VoiceSms::select('id', 'secondary_route_id', 'campaign_id')
                ->find($voice_sms_id);

            if($voiceSms && !empty($voiceSms->campaign_id))
            {
                $response = getVoiceCampaignSummeryServer($voiceSms->campaign_id, $voiceSms->secondaryRoute->primaryRoute);
                return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getVoiceCampaignCallingDetailServer($voice_sms_id)
    {
        try {
            $voiceSms = VoiceSms::select('id', 'secondary_route_id', 'campaign_id')
                ->find($voice_sms_id);

            if($voiceSms && !empty($voiceSms->campaign_id))
            {
                $response = getVoiceCampaignCallingDetailServer($voiceSms->campaign_id, $voiceSms->secondaryRoute->primaryRoute);
                return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }


}
