<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CreditLog;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class UserCreditController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:user-account-log');
        $this->middleware('permission:user-credit-add', ['only' => ['store']]);
    }

    public function index(Request $request)
    {
        try {
            $query = CreditLog::select('id', 'parent_id', 'user_id', 'created_by', 'log_type', 'action_for', 'credit_type', 'rate', 'comment', 'scurrbing_sms_adjustment',
                DB::raw("(CASE WHEN action_for = '5' THEN CONCAT('₹',FORMAT(old_balance,4,'IN')) ELSE CONCAT('',FORMAT(old_balance,0,'IN')) END) AS old_balance"),
                DB::raw("(CASE WHEN action_for = '5' THEN CONCAT('₹',FORMAT(balance_difference,4,'IN')) ELSE CONCAT('',FORMAT(balance_difference,0,'IN')) END) AS balance_difference"),
                DB::raw("(CASE WHEN action_for = '5' THEN CONCAT('₹',FORMAT(current_balance,4,'IN')) ELSE CONCAT('',FORMAT(current_balance,0,'IN')) END) AS current_balance"),
            )
            ->with('user:id,name','createdBy:id,name','parent:id,name')
            ->orderBy('id', 'DESC');

            if(auth()->user()->userType==2)
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
            }

            if(!empty($request->log_type))
            {
                $query->where('log_type', $request->log_type);
            }

            if(!empty($request->action_for))
            {
                $query->where('action_for', $request->action_for);
            }

            if(!empty($request->credit_type))
            {
                $query->where('credit_type ', $request->credit_type);
            }

            if(!empty($request->created_at))
            {
                $query->whereDate('created_at', $request->created_at);
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $result = $result->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
                }

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
                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $query = $query->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
                }
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'      => 'required|exists:users,id',
            'action_for'     => 'required|in:1,2,3,4,5',
            'credit_type'  => 'required|in:1,2',
            'balance'  => 'required|numeric|min:1',
            'rate'  => 'required|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if (auth()->id()==$request->user_id) {
            return response()->json(prepareResult(true, [], trans('translate.you_can_not_transfer_balance_to_self'), $this->intime), config('httpcodes.bad_request'));
        }

        //check balance
        if (in_array(auth()->user()->userType, [1,2])) 
        {
            // if credited balance
            if($request->credit_type==1)
            {
                $balanceInfo = balanceInfo($request->action_for, auth()->id());
                if($request->balance > $balanceInfo['current_balance'])
                {
                    return response()->json(prepareResult(true, [], trans('translate.you_dont_have_sufficient_balance'), $this->intime), config('httpcodes.bad_request'));
                }
            }

            // if debited balance
            if($request->credit_type==2)
            {
                $balanceInfo = balanceInfo($request->action_for, $request->user_id);
                if($request->balance > $balanceInfo['current_balance'])
                {
                    return response()->json(prepareResult(true, [], trans('translate.user_dont_have_sufficient_balance_to_debit'), $this->intime), config('httpcodes.bad_request'));
                }
            }
        }

        DB::beginTransaction();
        try {
            if (in_array(auth()->user()->userType, [1,2])) 
            {
                $creditDebit = creditDebit($balanceInfo, $request->user_id, $request->balance, $request->credit_type, $request->rate, auth()->id());
            }
            else
            {
                $user = User::find($request->user_id);
                if($request->credit_type==1)
                {
                    creditLog($user->id, auth()->id(), $request->action_for, $request->credit_type, $request->balance, $request->rate, null, 'Credit added');
                    $creditDebit = creditAdd($user, $request->action_for, $request->balance);
                }
                else
                {
                    creditLog($user->id, auth()->id(), $request->action_for, $request->credit_type, $request->balance, $request->rate, null, 'Credit deducted');
                    $creditDebit = creditDeduct($user, $request->action_for, $request->balance);
                }
            }
            
            DB::commit();

            if($request->credit_type==1)
            {
                $user = User::select('id','uuid','name','email')->find($request->user_id);
                ////////notification and mail//////////
                $variable_data = [
                    '{{name}}' => $user->name,
                    '{{no_of_credit}}' => $request->balance,
                ];
                notification('credit-added', $user, $variable_data);
                /////////////////////////////////////
            }
            

            return response()->json(prepareResult(false, $creditDebit, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
