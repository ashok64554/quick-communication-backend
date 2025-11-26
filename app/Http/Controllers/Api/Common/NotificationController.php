<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class NotificationController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }
    
    public function index(Request $request)
    {
        try
        {
            $query =  Notification::where('user_id', Auth::id())->orderBy('id','DESC');
            if($request->mark_all_as_read == 1)
            {
                Notification::where('user_id',Auth::id())->update(['read_at' => date('Y-m-d H:i:s')]);
            }
            if($request->is_readed)
            {
                $query->whereNotNull('read_at');
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

    public function show(Notification $notification)
    {
        try {
            return response()->json(prepareResult(false, $notification, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(Notification $notification)
    {
        try {
            $notification->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function read($id)
    {
        try
        {
            $notification = Notification::find($id);
            $notification->update(['read_at' => date('Y-m-d H:i:s')]);
            return response()->json(prepareResult(false, [], trans('translate.success'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e)
        {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function userNotificationReadAll()
    {
        try
        {
            Notification::where('user_id', Auth::id())->update(['read_at' => date('Y-m-d H:i:s')]);
            return response()->json(prepareResult(false, [], trans('translate.success'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e)
        {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function userNotificationDelete()
    {
        try
        {
            Notification::where('user_id', Auth::id())->delete();
            return response()->json(prepareResult(false, [], trans('translate.delete'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e)
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function unreadNotificationsCount()
    {
        try
        {
            $data['notifications'] = Notification::where('user_id', Auth::id())->whereNull('read_at')->limit(10)->orderBy('id', 'DESC')->get();
            $data['counts'] = Notification::where('user_id', Auth::id())->whereNull('read_at')->count();
            return response()->json(prepareResult(false, $data, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e)
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
