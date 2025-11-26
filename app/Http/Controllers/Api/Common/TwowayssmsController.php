<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\TwoWayComm;
use App\Models\ShortLink;
use App\Models\LinkClickLog;
use App\Models\User;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;
use Carbon\Carbon;
use Log;

class TwowayssmsController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:two-ways-sms');
        $this->middleware('permission:two-ways-sms-create', ['only' => ['store']]);
        $this->middleware('permission:two-ways-sms-edit', ['only' => ['update']]);
        $this->middleware('permission:two-ways-sms-view', ['only' => ['show']]);
        $this->middleware('permission:two-ways-sms-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = DB::table(env('DB_DATABASE2W').'.two_way_comms')
                ->join(env('DB_DATABASE').'.users', 'two_way_comms.created_by', 'users.id')
                ->whereNull('users.deleted_at')
                ->orderBy('id', 'DESC');

            if($request->is_web_temp==2)
            {
                $query->where('two_way_comms.is_web_temp', 2)
                    ->select('two_way_comms.id','two_way_comms.is_web_temp','two_way_comms.redirect_url','two_way_comms.title','two_way_comms.content_expired', 'users.name as created_by_name');
            }
            elseif($request->is_web_temp==1)
            {
                $query->where('two_way_comms.is_web_temp', 1)
                    ->select('two_way_comms.*', 'users.name as created_by_name');
            }
            else
            {
                $query->select('two_way_comms.id','two_way_comms.title');
            }

            if($request->with_expired=='no')
            {
                $query->where('two_way_comms.content_expired', '>=', date('Y-m-d'));
            }

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('two_way_comms.created_by', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('two_way_comms.title', 'LIKE', '%' . $search. '%')
                    ->orWhere('two_way_comms.content', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->created_by))
            {
                $query->where('two_way_comms.created_by', $request->created_by);
            }

            if(!empty($request->title))
            {
                $query->where('two_way_comms.title', $request->title);
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
        $is_web_temp = (empty($request->is_web_temp) ? 1 : $request->is_web_temp);
        if($is_web_temp==1)
        {
            $validation = \Validator::make($request->all(), [
                'title'    => 'required',
                'content'    => 'required',
                'content_expired' => 'required|date'
            ]);
        }
        else
        {
            $validation = \Validator::make($request->all(), [
                'title'    => 'required',
                'redirect_url'    => 'required|url',
                'content_expired' => 'required|date'
            ]);
        }
        
        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $two_way_comms = new TwoWayComm;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $two_way_comms->parent_id  = User::find($request->user_id)->parent_id;
                $two_way_comms->created_by  = $request->user_id;
            }
            else
            {
                $two_way_comms->created_by  = auth()->id();
            }

            
            $two_way_comms->is_web_temp  = $is_web_temp;
            $two_way_comms->redirect_url  = $request->redirect_url;
            $two_way_comms->title  = $request->title;
            $two_way_comms->content  = $request->content;
            $two_way_comms->bg_color  = $request->bg_color;
            $two_way_comms->content_expired  = $request->content_expired;
            $two_way_comms->take_response  = $request->take_response;
            $two_way_comms->response_mob_num  = $request->response_mob_num;
            $two_way_comms->save();
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $request->is_web_temp = $two_way_comms->is_web_temp;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        //createUniqueLink(1, '21541', 45646, null, 1);
        try {
            $query = DB::table(env('DB_DATABASE2W').'.two_way_comms')
                ->select('two_way_comms.*', 'users.name as created_by_name')
                ->join(env('DB_DATABASE').'.users', 'two_way_comms.created_by', 'users.id')
                ->where('two_way_comms.id', $id)
                ->whereNull('users.deleted_at');
            
            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('two_way_comms.created_by', auth()->id());
            }

            $query = $query->first();
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, $id)
    {
        $two_way_comms = TwoWayComm::find($id);
        $is_web_temp = $two_way_comms->is_web_temp;
        if($is_web_temp==1)
        {
            $validation = \Validator::make($request->all(), [
                'created_by'    => 'required|exists:users,id',
                'title'    => 'required',
                'content'    => 'required',
            ]);
        }
        else
        {
            $validation = \Validator::make($request->all(), [
                'created_by'    => 'required|exists:users,id',
                'title'    => 'required',
                'redirect_url'    => 'required|url',
            ]);
        }

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            
            $two_way_comms->redirect_url  = $request->redirect_url;
            $two_way_comms->title  = $request->title;
            $two_way_comms->content  = $request->content;
            $two_way_comms->bg_color  = $request->bg_color;
            $two_way_comms->content_expired  = $request->content_expired;
            $two_way_comms->take_response  = $request->take_response;
            $two_way_comms->response_mob_num  = $request->response_mob_num;
            $two_way_comms->save();
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $request->is_web_temp = $two_way_comms->is_web_temp;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            TwoWayComm::find($id)->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
