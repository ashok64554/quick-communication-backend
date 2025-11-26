<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecondaryRoute;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class SecondaryRouteController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:users-list');
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update']]);
        $this->middleware('permission:user-view', ['only' => ['show']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        $this->middleware('permission:user-action', ['only' => ['secondaryRouteAction']]);
    }
    
    public function index(Request $request)
    {
        try {
            $query = SecondaryRoute::select('secondary_routes.*')->with('primaryRoute:id,route_name,online_from', 'createdBy:id,name')
                ->join('primary_routes', 'secondary_routes.primary_route_id', 'primary_routes.id')
                ->orderBy('id', 'DESC');

            if(!empty($request->gateway_type))
            {
                $query->where('primary_routes.gateway_type', $request->gateway_type);
            }

            if(!empty($request->primary_route_id))
            {
                $query->where('secondary_routes.primary_route_id', $request->primary_route_id);
            }

            if(!empty($request->sec_route_name))
            {
                $query->where('secondary_routes.sec_route_name', 'LIKE', '%'.$request->sec_route_name.'%');
            }

            if(!empty($request->status) && $request->status=='no')
            {
                $query->where('secondary_routes.status', 0);
            }
            elseif(!empty($request->status))
            {
                $query->where('secondary_routes.status', $request->status);
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
            'sec_route_name'    => 'required',
            'primary_route_id'  => 'required|exists:primary_routes,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $secondaryRoute = new SecondaryRoute;
            $secondaryRoute->created_by = auth()->id();
            $secondaryRoute->primary_route_id = $request->primary_route_id;
            $secondaryRoute->sec_route_name = $request->sec_route_name;
            $secondaryRoute->status = 1;
            $secondaryRoute->save();
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($secondaryRoute, $request);

            return response()->json(prepareResult(false, $data, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(SecondaryRoute $secondaryRoute, Request $request)
    {
        try {
            $secondaryRoute['primary_route'] = $secondaryRoute->primaryRoute()->select('id','route_name','online_from')->first();
            $secondaryRoute['created_by'] = $secondaryRoute->createdBy()->select('id','name')->first();
            if($request->other_function)
            {
                return $secondaryRoute;
            }
            return response()->json(prepareResult(false, $secondaryRoute, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, SecondaryRoute $secondaryRoute)
    {
        $validation = \Validator::make($request->all(), [
            'sec_route_name'    => 'required',
            'primary_route_id'  => 'required|exists:primary_routes,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $secondaryRoute->primary_route_id = $request->primary_route_id;
            $secondaryRoute->sec_route_name = $request->sec_route_name;
            $secondaryRoute->save();
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($secondaryRoute, $request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(SecondaryRoute $secondaryRoute)
    {
        try {
            $secondaryRouteId = $secondaryRoute->id;
            $checkRouteAssign = User::select('id')
                ->where(function($q) use ($secondaryRouteId) {
                    $q->where('promotional_route', $secondaryRouteId)
                        ->orWhere('transaction_route', $secondaryRouteId)
                        ->orWhere('two_waysms_route', $secondaryRouteId)
                        ->orWhere('voice_sms_route', $secondaryRouteId);
                })
                ->first();
            if($checkRouteAssign)
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_delete_route_assigned_to_user'), $this->intime), config('httpcodes.internal_server_error'));
            }
            $secondaryRoute->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function secondaryRouteAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'status'      => 'required|in:0,1',
            'secondary_routes_id'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->status==0)
        {
            $secondaryRouteId = $request->secondary_routes_id;
            $checkRouteAssign = User::select('id')
                ->where(function($q) use ($secondaryRouteId) {
                    $q->whereIn('promotional_route', $secondaryRouteId)
                        ->orWhereIn('transaction_route', $secondaryRouteId)
                        ->orWhereIn('two_waysms_route', $secondaryRouteId)
                        ->orWhereIn('voice_sms_route', $secondaryRouteId);
                })
                ->first();
            if($checkRouteAssign)
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_deactivate_route_assigned_to_user'), $this->intime), config('httpcodes.internal_server_error'));
            }
        }

        try {
            $secondaryRoutes = SecondaryRoute::whereIn('id', $request->secondary_routes_id)
                ->update([
                    'status' => $request->status
                ]);

            $request = new Request();
            $request->other_function = true;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);
            return response()->json(prepareResult(false, $data, trans('translate.success'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
