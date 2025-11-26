<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DltTemplate;
use App\Models\DailySubmissionLog;
use App\Models\SpeedRatio;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class AdminActionController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:admin-operation');
        $this->middleware('permission:assign-route-to-user', ['only' => ['assignRouteToUser']]);
        $this->middleware('permission:change-dlt-template-priority', ['only' => ['changeDltTemplatePriority']]);
        $this->middleware('permission:set-user-ratio', ['only' => ['setUserRatio']]);
    }

    public function assignRouteToUser(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'          => 'required|exists:users,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = User::find($request->user_id);
            if($user)
            {
                $user->otp_route = $request->otp_route;
                $user->promotional_route = $request->promotional_route;
                $user->transaction_route = $request->transaction_route;
                $user->two_waysms_route = $request->two_waysms_route;
                $user->voice_sms_route = $request->voice_sms_route;
                $user->save();

                //update child routes
                $user->children()->update([
                    'otp_route' => $request->otp_route,
                    'promotional_route' => $request->promotional_route,
                    'transaction_route' => $request->transaction_route,
                    'two_waysms_route' => $request->two_waysms_route,
                    'voice_sms_route' => $request->voice_sms_route
                ]);

                DB::commit();
                return response()->json(prepareResult(false, $user, trans('translate.success'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function changeDltTemplatePriority(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'dlt_template_ids' => 'required|array|min:1',
            'priority'  => 'required|in:0,1,2,3',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $dlt_template = DltTemplate::whereIn('dlt_template_id', $request->dlt_template_ids)
                ->update([
                    'priority' => strval($request->priority)
                ]);
            return response()->json(prepareResult(false, [], trans('translate.success'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function setUserRatio(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'   => 'required|exists:users,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = User::find($request->user_id);
            if($user)
            {
                $speedRatio = SpeedRatio::updateOrCreate(
                    ['user_id' => $request->user_id],
                    [
                        'user_id' => $request->user_id,
                        'trans_text_sms' => $request->trans_text_sms,
                        'promo_text_sms' => $request->promo_text_sms,
                        'two_way_sms' => $request->two_way_sms,
                        'voice_sms' => $request->voice_sms,
                        'whatsapp_sms' => $request->whatsapp_sms,
                        'trans_text_f_sms' => $request->trans_text_f_sms,
                        'promo_text_f_sms' => $request->promo_text_f_sms,
                        'two_way_f_sms' => $request->two_way_f_sms,
                        'voice_f_sms' => $request->voice_f_sms,
                        'whatsapp_f_sms' => $request->whatsapp_f_sms,
                    ]
                );

                DB::commit();
                $user['speed_ratio'] = $user->speedRatio;
                return response()->json(prepareResult(false, $user, trans('translate.success'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function userWiseCreditInfo(Request $request)
    {
        try {
            $query = \DB::table('users')
            ->select('id', 'name', 'email', 'mobile', 'city', 'companyName', 'authority_type', 'promotional_credit', 'transaction_credit', 'two_waysms_credit', 'voice_sms_credit')
            ->where('userType', '!=', 0)
            ->where('status', '!=', '2')
            ->whereNull('deleted_at');

            if(!empty($request->route_type))
            {
                if($request->route_type==2)
                {
                    $query->orderBy('promotional_credit', 'ASC');
                }
                elseif($request->route_type==3)
                {
                    $query->orderBy('two_waysms_credit', 'ASC');
                }
                elseif($request->route_type==4)
                {
                    $query->orderBy('voice_sms_credit', 'ASC');
                }
                else
                {
                    $query->orderBy('transaction_credit', 'ASC');
                }
            }

            if(!empty($request->less_than_balance))
            {
                if($request->route_type==2)
                {
                    $query->where('promotional_credit', '<=', $request->less_than_balance);
                }
                elseif($request->route_type==3)
                {
                    $query->where('two_waysms_credit', '<=', $request->less_than_balance);
                }
                elseif($request->route_type==4)
                {
                    $query->where('voice_sms_credit', '<=', $request->less_than_balance);
                }
                else
                {
                    $query->where('transaction_credit', '<=', $request->less_than_balance);
                }
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.username', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.email', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.mobile', 'LIKE', '%' . $search. '%');
                });
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
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getDailyReport(Request $request)
    {
        try {
            if(in_array(loggedInUserType(), [0]))
            {
                $query = DailySubmissionLog::orderBy('id', 'DESC')->with('primaryRoute:id,route_name');

                if(!empty($request->submission_date))
                {
                    $query->whereDate('submission_date', $request->submission_date);
                }

                if(!empty($request->sms_gateway))
                {
                    $query->where('sms_gateway', $request->sms_gateway);
                }

                $route_name = \DB::table('primary_routes')->select('route_name')->find($request->sms_gateway);
                if(!empty($request->per_page_record))
                {
                    $perPage = $request->per_page_record;
                    $page = $request->input('page', 1);
                    $total = $query->count();
                    $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                    $pagination =  [
                        'route' => $route_name,
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
            }
            return response()->json(prepareResult(false, null, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function serverCommands(Request $request)
    {
        try {
            if(in_array(loggedInUserType(), [0]))
            {
                $start_time = microtime(TRUE);
                $run = shell_exec($request->command);
                $response = explode("\n", $run);
                return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, null, trans('translate.unauthorized'), $this->intime), config('httpcodes.unauthorized'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

}
