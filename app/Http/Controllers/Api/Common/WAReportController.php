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
use Illuminate\Support\Facades\Http;
use Auth;
use DB;
use Exception;
use Excel;
use Carbon\Carbon;
use Log;

class WAReportController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-sms');
    }

    public function getAnalytics(Request $request)
    {
        try {
            $query = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]) || empty($request->user_id))
            {
                $query->where('user_id', auth()->id());
            }
            else
            {
                $query->where('user_id', $request->user_id);
            }
            $getWAConfig = $query->first();

            $waba_id = $getWAConfig->waba_id; 
            $app_version = $getWAConfig->app_version; 
            $access_token = base64_decode($getWAConfig->access_token);
            $from_date = (!empty($request->from_date) ? strtotime($request->from_date) : strtotime(date("Y-m-d", strtotime("-30 days", time()))));
            $to_date = (!empty($request->to_date) ? strtotime($request->to_date) : strtotime(date("Y-m-d")));
            $granularity = (!empty($request->granularity) ? $request->granularity : 'DAY');
            $phone_numbers = (!empty($request->phone_numbers) ? json_encode($request->phone_numbers) : json_encode([]));
            $country_codes = (!empty($request->country_codes) ? json_encode($request->country_codes) : json_encode([]));

            $url = "https://graph.facebook.com/$app_version/$waba_id?fields=analytics.start($from_date).end($to_date).granularity($granularity).phone_numbers($phone_numbers).country_codes($country_codes)";

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)->throw();

            $response = $response->json();
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getConversationAnalytics(Request $request)
    {
        try {
            $query = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]) || empty($request->user_id))
            {
                $query->where('user_id', auth()->id());
            }
            else
            {
                $query->where('user_id', $request->user_id);
            }
            $getWAConfig = $query->first();

            $waba_id = $getWAConfig->waba_id; 
            $app_version = $getWAConfig->app_version; 
            $access_token = base64_decode($getWAConfig->access_token);
            $from_date = (!empty($request->from_date) ? strtotime($request->from_date) : strtotime(date("Y-m-d", strtotime("-30 days", time()))));
            $to_date = (!empty($request->to_date) ? strtotime($request->to_date) : strtotime(date("Y-m-d")));
            $granularity = (!empty($request->granularity) ? $request->granularity : 'DAY');
            $phone_numbers = (!empty($request->phone_numbers) ? json_encode($request->phone_numbers) : json_encode([]));
            $country_codes = (!empty($request->country_codes) ? json_encode($request->country_codes) : json_encode([]));
            $dimensions = (!empty($request->dimensions) ? json_encode($request->dimensions) : json_encode([]));
            $conversation_directions = (!empty($request->conversation_directions) ? json_encode($request->conversation_directions) : json_encode([]));
            $conversation_categories = (!empty($request->conversation_categories) ? json_encode($request->conversation_categories) : json_encode([]));
            $conversation_types = (!empty($request->conversation_types) ? json_encode($request->conversation_types) : json_encode([]));

            $url = "https://graph.facebook.com/$app_version/$waba_id?fields=conversation_analytics.start($from_date).end($to_date).granularity($granularity).phone_numbers($phone_numbers).country_codes($country_codes).dimensions($dimensions).conversation_categories($conversation_categories).conversation_directions($conversation_directions).dimensions($dimensions).conversation_types($conversation_types)";

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)->throw();

            $response = $response->json();
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getTemplateAnalytics(Request $request)
    {
        try {
            $query = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]) || empty($request->user_id))
            {
                $query->where('user_id', auth()->id());
            }
            else
            {
                $query->where('user_id', $request->user_id);
            }
            $getWAConfig = $query->first();
            if(!empty($request->from_date))
            {
                $fromDate = Carbon::createFromFormat('Y-m-d', $request->from_date, 'UTC');
                $from_date = $fromDate->timestamp;
            }
            else
            {
                $from_date = Carbon::now('UTC')->subDays(30)->timestamp;
            }

            if(!empty($request->to_date))
            {
                $toDate = Carbon::createFromFormat('Y-m-d', $request->to_date, 'UTC');
                $to_date = $toDate->timestamp;
            }
            else
            {
                $to_date = Carbon::now('UTC')->subDays(30)->timestamp;
            }
            //dd($from_date, $to_date);

            $granularity = (!empty($request->granularity) ? $request->granularity : 'DAILY');
            $template_ids = (!empty($request->template_ids) ? json_encode($request->template_ids) : json_encode([]));
            $metric_types = (!empty($request->metric_types) ? json_encode($request->metric_types) : json_encode(["COST"]));

            $waba_id = $getWAConfig->waba_id; 
            $app_version = $getWAConfig->app_version; 
            $access_token = base64_decode($getWAConfig->access_token);
            $pageLimit = env('WA_PAGE_LIMIT', 25);
            $after = (!empty($request->after) ? $request->after : 'x');
            $before = (!empty($request->before) ? $request->before : 'x');
            $url = "https://graph.facebook.com/$app_version/$waba_id?fields=template_analytics.start($from_date).end($to_date).granularity($granularity).metric_types($metric_types).template_ids($template_ids).limit($pageLimit).after($after)";

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)->throw();

            $response = $response->json();
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    // Not working properly.
    public function getCreditLines(Request $request)
    {
        try {
            $query = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]) || empty($request->user_id))
            {
                $query->where('user_id', auth()->id());
            }
            else
            {
                $query->where('user_id', $request->user_id);
            }

            if($request->configuration_id)
            {
                $query->where('id', $request->configuration_id);
            }

            $getWAConfig = $query->first();

            $waba_id = $getWAConfig->waba_id; 
            $app_version = $getWAConfig->app_version; 
            $access_token = base64_decode($getWAConfig->access_token);
            
            $url = "https://graph.facebook.com/$app_version/$waba_id/extendedcredits";

            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)->throw();

            $response = $response->json();
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getWaSummery(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'whats_app_send_sms_id'    => 'nullable|exists:whats_app_send_sms,id',
            'user_id'    => 'nullable|exists:users,id',
            'whats_app_template_id'    => 'nullable|exists:whats_app_templates,id',
            'configuration_id'    => 'nullable|exists:whats_app_configurations,id',
            'from_date'    => 'nullable|date',
            'to_date'    => 'nullable|date',
            'message_category' => 'nullable|in:Utility,Marketing,Authentication',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        try {
            $arr = [];
            // Query Table
            $query = WhatsAppSendSmsQueue::select('users.name', 'whats_app_send_sms_queues.template_category', 'whats_app_send_sms_queues.stat',
                    DB::raw('SUM(use_credit) as used_credit'),
                    DB::raw('COUNT(whats_app_send_sms_queues.id) as totals')
                )
                ->join('users', 'whats_app_send_sms_queues.user_id', 'users.id')
                ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                ->groupBy(['whats_app_send_sms_queues.template_category', 'whats_app_send_sms_queues.user_id', 'whats_app_send_sms_queues.stat'])
                ->orderBy('users.name', 'ASC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where("whats_app_send_sms_queues.user_id", auth()->id());
            }
            else
            {
                if(!empty($request->user_id))
                {
                    $query->where("whats_app_send_sms_queues.user_id", $request->user_id);
                }
            }

            if(!empty($request->whats_app_send_sms_id))
            {
                $query->where("whats_app_send_sms_queues.whats_app_send_sms_id", $request->whats_app_send_sms_id);
            }

            if(!empty($request->whats_app_template_id))
            {
                $query->where("whats_app_send_sms.whats_app_template_id", $request->whats_app_template_id);
            }

            if(!empty($request->configuration_id))
            {
                $query->where("whats_app_send_sms.whats_app_configuration_id", $request->configuration_id);
            }

            if(!empty($request->message_category))
            {
                $query->where("whats_app_send_sms_queues.template_category", $request->message_category);
            }

            if(!empty($request->from_date) && empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", $request->from_date);
            }

            if(empty($request->from_date) && !empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", $request->to_date);
            }

            if(!empty($request->from_date) && !empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", ">=", $request->from_date)
                    ->whereDate("whats_app_send_sms.campaign_send_date_time", "<=", $request->to_date);
            }

            $reporDatas = $query->get();
            if($reporDatas->count()>0)
            {
                foreach($reporDatas as $key => $reporData)
                {
                    $arr[] = [
                        'user_name' => $reporData->name,
                        'template_category' => $reporData->template_category,
                        'stat' => $reporData->stat,
                        'totals' => $reporData->totals,
                        'used_credit' => $reporData->used_credit
                    ];
                }
            }

            // History Table
            $query = WhatsAppSendSmsHistory::select('users.name', 'whats_app_send_sms_histories.template_category', 'whats_app_send_sms_histories.stat',
                    DB::raw('SUM(use_credit) as used_credit'),
                    DB::raw('COUNT(whats_app_send_sms_histories.id) as totals')
                )
                ->join('users', 'whats_app_send_sms_histories.user_id', 'users.id')
                ->join('whats_app_send_sms', 'whats_app_send_sms_histories.whats_app_send_sms_id', 'whats_app_send_sms.id')
                ->groupBy(['whats_app_send_sms_histories.template_category', 'whats_app_send_sms_histories.user_id', 'whats_app_send_sms_histories.stat'])
                ->orderBy('users.name', 'ASC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where("whats_app_send_sms_histories.user_id", auth()->id());
            }
            else
            {
                if(!empty($request->user_id))
                {
                    $query->where("whats_app_send_sms_histories.user_id", $request->user_id);
                }
            }

            if(!empty($request->whats_app_send_sms_id))
            {
                $query->where("whats_app_send_sms_histories.whats_app_send_sms_id", $request->whats_app_send_sms_id);
            }

            if(!empty($request->whats_app_template_id))
            {
                $query->where("whats_app_send_sms.whats_app_template_id", $request->whats_app_template_id);
            }

            if(!empty($request->configuration_id))
            {
                $query->where("whats_app_send_sms.whats_app_configuration_id", $request->configuration_id);
            }

            if(!empty($request->message_category))
            {
                $query->where("whats_app_send_sms_histories.template_category", $request->message_category);
            }

            if(!empty($request->from_date) && empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", $request->from_date);
            }

            if(empty($request->from_date) && !empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", $request->to_date);
            }

            if(!empty($request->from_date) && !empty($request->to_date))
            {
                $query->whereDate("whats_app_send_sms.campaign_send_date_time", ">=", $request->from_date)
                    ->whereDate("whats_app_send_sms.campaign_send_date_time", "<=", $request->to_date);
            }

            $reporDatas = $query->get();
            if($reporDatas->count()>0)
            {
                foreach($reporDatas as $key => $reporData)
                {
                    $arr[] = [
                        'user_name' => $reporData->name,
                        'template_category' => $reporData->template_category,
                        'stat' => $reporData->stat,
                        'totals' => $reporData->totals,
                        'used_credit' => $reporData->used_credit
                    ];
                }
            }

            $collection = collect($arr);

            $result = $collection
                ->groupBy(fn($item) => $item['user_name'].'|'.$item['template_category'].'|'.$item['stat'])
                ->map(function ($group) {
                    return [
                        'user_name' => $group->first()['user_name'],
                        'template_category' => $group->first()['template_category'],
                        'stat' => $group->first()['stat'],
                        'totals' => $group->sum('totals'),
                        'used_credit' => $group->sum(fn($item) => (float)$item['used_credit']),
                    ];
                })
                ->values()
                ->toArray();

            /*
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') campaign_send_date_time");
                ->groupBy(DB::raw('DATE(campaign_send_date_time)'))
            */

            return response()->json(prepareResult(false, $result, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
