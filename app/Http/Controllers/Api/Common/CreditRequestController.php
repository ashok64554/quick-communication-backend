<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\CreditRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;

class CreditRequestController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:credit-request');
    }

    public function index(Request $request)
    {
        try {
            $query = CreditRequest::with('user:id,name','createdBy:id,name')
                ->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhere('user_id', auth()->id());
                })
                ->orderBy('id', 'DESC')
                ->withoutGlobalScope('parent_id');

            if(!empty($request->credit_request))
            {
                $query->where('credit_request', $request->credit_request);
            }

            if(!empty($request->route_type))
            {
                $query->where('route_type', $request->route_type);
            }

            if(!empty($request->comment))
            {
                $query->where('comment', 'LIKE', '%'.$request->comment.'%');
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
        $validation = \Validator::make($request->all(), [
            'credit_request'    => 'required|min:0|max:2500000',
            'route_type'    => 'required|in:1,2,3,4',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $creditRequest = new CreditRequest;
            $creditRequest->user_id  = (auth()->user()->current_parent_id==auth()->id() || empty(auth()->user()->current_parent_id)) ? 1 : auth()->user()->current_parent_id;
            $creditRequest->created_by  = auth()->id();
            $creditRequest->credit_request  = $request->credit_request;
            $creditRequest->route_type  = $request->route_type;
            $creditRequest->comment  = $request->comment;
            $creditRequest->save();
            DB::commit();

            $creditRequest['user'] = $creditRequest->user()->select('id', 'name')->withTrashed()->withoutGlobalScope('parent_id')->first();
            $creditRequest['created_by'] = $creditRequest->createdBy()->select('id', 'name')->withTrashed()->withoutGlobalScope('parent_id')->first();

            ////////notification and mail//////////
            $user = User::withoutGlobalScope('parent_id')->withTrashed()->find($creditRequest->user_id);
            $variable_data = [
                '{{name}}' => $user->name,
                '{{no_of_credit}}' => $request->credit_request,
            ];
            notification('credit-requested-by-user', $user, $variable_data);
            /////////////////////////////////////

            return response()->json(prepareResult(false, $creditRequest, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function creditRequestReply(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'credit_request_id'    => 'required|exists:credit_requests,id',
            'request_reply'    => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $creditRequest = CreditRequest::where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhere('user_id', auth()->id());
                })
                ->withoutGlobalScope('parent_id')
                ->find($request->credit_request_id);
            $creditRequest->request_reply  = $request->request_reply;
            $creditRequest->reply_date  = date('Y-m-d');
            $creditRequest->save();
            DB::commit();

            $creditRequest['user'] = $creditRequest->user()->select('id', 'name')->withoutGlobalScope('parent_id')->first();

            $creditRequest['created_by'] = $creditRequest->createdBy()->select('id', 'name')->withTrashed()->withoutGlobalScope('parent_id')->first();

            return response()->json(prepareResult(false, $creditRequest, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
    
}
