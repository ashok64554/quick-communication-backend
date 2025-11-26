<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\UserLog;
use Illuminate\Support\Str;
use Mail;
use Auth;
use DB;
use Exception;

class UserLoginLogController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:view-login-log');
    }

    public function viewLoginLog(Request $request)
    {
        try {
            $query = UserLog::orderBy('id', 'DESC')->with('user:id,name');

            if(in_array(auth()->user()->userType, [0,3]))
            {
                $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());
                $query->where('user_id', $user_id);
            }
            else
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where('complete_info', 'LIKE', '%' . $search. '%');
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
}
