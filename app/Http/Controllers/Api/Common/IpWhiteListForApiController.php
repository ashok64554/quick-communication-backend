<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\IpWhiteListForApi;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class IpWhiteListForApiController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:ip-white-list-for-api');
    }

    public function index(Request $request)
    {
        try {
            $query = IpWhiteListForApi::orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
            }
            
            if(!empty($request->ip_address))
            {
                $query->where('ip_address', 'LIKE', '%'.$request->ip_address.'%');
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
            'ip_address'    => 'required|ip',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = IpWhiteListForApi::where('ip_address', $request->ip_address)->where('user_id', auth()->id())->withoutGlobalScope('parent_id')->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $ip_address = new IpWhiteListForApi;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $ip_address->parent_id  = 1;
            }
            $ip_address->user_id  = auth()->id();
            $ip_address->ip_address = $request->ip_address;
            $ip_address->save();
            DB::commit();
            return response()->json(prepareResult(false, $ip_address, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(IpWhiteListForApi $ipWhiteListForApi)
    {
        try {
            $ipWhiteListForApi->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
