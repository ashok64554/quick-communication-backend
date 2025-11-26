<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrimaryRoute;
use App\Models\SecondaryRoute;
use App\Models\PrimaryRouteAssociated;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Jobs\CheckConnectionSMPP;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Illuminate\Support\Facades\Http;

class PrimaryRouteController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:primary-route-list');
        $this->middleware('permission:primary-route-create', ['only' => ['store']]);
        $this->middleware('permission:primary-route-edit', ['only' => ['update']]);
        $this->middleware('permission:primary-route-view', ['only' => ['show']]);
        $this->middleware('permission:primary-route-delete', ['only' => ['destroy']]);
        $this->middleware('permission:primary-route-action', ['only' => ['primaryRouteAction']]);
        $this->middleware('permission:check-primary-route-connection', ['only' => ['checkPrimaryRouteConnection']]);
        //$this->middleware('permission:check-voice-balance', ['only' => ['checkVoiceBalance']]);
    }

    public function index(Request $request)
    {
        try {
            $query = PrimaryRoute::orderBy('id', 'DESC');

            if(!empty($request->gateway_type))
            {
                $query->where('gateway_type', $request->gateway_type);
            }

            if(!empty($request->route_name))
            {
                $query->where('route_name', 'LIKE', '%'.$request->route_name.'%');
            }

            if(!empty($request->smsc_id))
            {
                $query->where('smsc_id', 'LIKE', '%'.$request->smsc_id.'%');
            }

            if(!empty($request->ip_address))
            {
                $query->where('ip_address', 'LIKE', '%'.$request->ip_address.'%');
            }

            if(!empty($request->port))
            {
                $query->where('port', 'LIKE', '%'.$request->port.'%');
            }

            if(!empty($request->smsc_username))
            {
                $query->where('smsc_username', 'LIKE', '%'.$request->smsc_username.'%');
            }

            if(!empty($request->status) && $request->status=='no')
            {
                $query->where('status', 0);
            }
            elseif(!empty($request->status))
            {
                $query->where('status', $request->status);
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
            'gateway_type'    => 'required|in:1,2,4',
            'route_name'    => 'required',
            'smpp_credit'   => 'required|numeric|min:1',
            'smsc_id'       => 'required|unique:primary_routes,smsc_id',
            'ip_address'    => 'required|ip',
            'port'          => 'required|numeric',
            'smsc_username' => 'required|string',
            'smsc_password' => 'required|min:6',
            'sec_route_name' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $primaryRoute = new PrimaryRoute;
            $primaryRoute->created_by = auth()->id();
            $primaryRoute->gateway_type = $request->gateway_type;
            $primaryRoute->route_name = $request->route_name;
            $primaryRoute->smpp_credit = $request->smpp_credit;
            //$primaryRoute->coverage = $request->coverage;
            $primaryRoute->smsc_id = $request->smsc_id;
            $primaryRoute->api_url_for_voice = $request->api_url_for_voice;
            $primaryRoute->ip_address = $request->ip_address;
            $primaryRoute->port = $request->port;
            if($request->transceiver_mode==0)
            {
                $primaryRoute->receiver_port = $request->receiver_port;
            }
            else
            {
                $primaryRoute->receiver_port = null;
            }
            $primaryRoute->smsc_username = $request->smsc_username;
            $primaryRoute->smsc_password = $request->smsc_password;
            $primaryRoute->system_type = $request->system_type;
            $primaryRoute->throughput = $request->throughput;
            $primaryRoute->reconnect_delay = $request->reconnect_delay;
            $primaryRoute->enquire_link_interval = $request->enquire_link_interval;
            $primaryRoute->max_pending_submits = $request->max_pending_submits;
            $primaryRoute->transceiver_mode = $request->transceiver_mode;
            $primaryRoute->source_addr_ton = $request->source_addr_ton;
            $primaryRoute->source_addr_npi = $request->source_addr_npi;
            $primaryRoute->dest_addr_ton = $request->dest_addr_ton;
            $primaryRoute->dest_addr_npi = $request->dest_addr_npi;
            $primaryRoute->log_file = $request->log_file;
            $primaryRoute->log_level = $request->log_level;
            $primaryRoute->instances = $request->instances;
            $primaryRoute->voice = $request->voice;
            $primaryRoute->save();
            if($primaryRoute) 
            {
                //default assigned same route
                $primaryRouteAssociated = new PrimaryRouteAssociated;
                $primaryRouteAssociated->primary_route_id = $primaryRoute->id;
                $primaryRouteAssociated->associted_primary_route = $primaryRoute->id;
                $primaryRouteAssociated->save();

                //assigned other routes
                if(is_array($request->associated_routes))
                {
                    foreach ($request->associated_routes as $key => $associated_route) 
                    {
                        $primaryRouteAssociated = new PrimaryRouteAssociated;
                        $primaryRouteAssociated->primary_route_id = $primaryRoute->id;
                        $primaryRouteAssociated->associted_primary_route = $associated_route;
                        $primaryRouteAssociated->save();

                    }
                }

                //create secondary route
                $secondaryRoute = new SecondaryRoute;
                $secondaryRoute->primary_route_id = $primaryRoute->id;
                $secondaryRoute->created_by = auth()->id();
                $secondaryRoute->sec_route_name = $request->sec_route_name;
                $secondaryRoute->save();

                //added code in smsc file
                addKannelRoute($request);
            }
            $primaryRoute['secondary_route'] = $primaryRoute->secondaryRoute;
            DB::commit();
            return response()->json(prepareResult(false, $primaryRoute, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(PrimaryRoute $primaryRoute)
    {
        try {
            $connectedRoutes = DB::table('primary_route_associateds')
                ->where('primary_route_id', $primaryRoute->id)
                ->where('associted_primary_route', '!=', $primaryRoute->id)
                ->pluck('associted_primary_route');
            
            $primaryRoute['associted_routes'] = $connectedRoutes;
            return response()->json(prepareResult(false, $primaryRoute, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, PrimaryRoute $primaryRoute)
    {
        $validation = \Validator::make($request->all(), [
            'gateway_type'    => 'required|in:1,2,4',
            'route_name'    => 'required',
            'smpp_credit'   => 'required|numeric|min:1',
            'smsc_id'       => 'required|unique:primary_routes,smsc_id,'.$primaryRoute->id,
            'ip_address'    => 'required|ip',
            'port'          => 'required|numeric',
            'smsc_username' => 'required|string',
            'smsc_password' => 'required|min:6'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $old_smsc_id = $primaryRoute->smsc_id;
            $primaryRoute->gateway_type = $request->gateway_type;
            $primaryRoute->route_name = $request->route_name;
            $primaryRoute->smpp_credit = $request->smpp_credit;
            //$primaryRoute->coverage = $request->coverage;
            $primaryRoute->smsc_id = $request->smsc_id;
            $primaryRoute->api_url_for_voice = $request->api_url_for_voice;
            $primaryRoute->ip_address = $request->ip_address;
            $primaryRoute->port = $request->port;
            if($request->transceiver_mode==0)
            {
                $primaryRoute->receiver_port = $request->receiver_port;
            }
            else
            {
                $primaryRoute->receiver_port = null;
            }
            $primaryRoute->smsc_username = $request->smsc_username;
            $primaryRoute->smsc_password = $request->smsc_password;
            $primaryRoute->system_type = $request->system_type;
            $primaryRoute->throughput = $request->throughput;
            $primaryRoute->reconnect_delay = $request->reconnect_delay;
            $primaryRoute->enquire_link_interval = $request->enquire_link_interval;
            $primaryRoute->max_pending_submits = $request->max_pending_submits;
            $primaryRoute->transceiver_mode = $request->transceiver_mode;
            $primaryRoute->source_addr_ton = $request->source_addr_ton;
            $primaryRoute->source_addr_npi = $request->source_addr_npi;
            $primaryRoute->dest_addr_ton = $request->dest_addr_ton;
            $primaryRoute->dest_addr_npi = $request->dest_addr_npi;
            $primaryRoute->log_file = $request->log_file;
            $primaryRoute->log_level = $request->log_level;
            $primaryRoute->instances = $request->instances;
            $primaryRoute->voice = $request->voice;
            $primaryRoute->save();
            if($primaryRoute) 
            {
                //update kannel file
                kannelRouteUpdate($request, $old_smsc_id);

                //update associated route
                //romoved already associted route first
                \DB::table('primary_route_associateds')
                    ->where('primary_route_id', $primaryRoute->id)
                    ->delete();

                // reassign associated routes
                //default assigned same route
                $primaryRouteAssociated = new PrimaryRouteAssociated;
                $primaryRouteAssociated->primary_route_id = $primaryRoute->id;
                $primaryRouteAssociated->associted_primary_route = $primaryRoute->id;
                $primaryRouteAssociated->save();
                
                //assigned other routes
                if(is_array($request->associated_routes))
                {
                    foreach ($request->associated_routes as $key => $associated_route) 
                    {
                        $primaryRouteAssociated = new PrimaryRouteAssociated;
                        $primaryRouteAssociated->primary_route_id = $primaryRoute->id;
                        $primaryRouteAssociated->associted_primary_route = $associated_route;
                        $primaryRouteAssociated->save();

                    }
                }
            }
            $primaryRoute['secondary_route'] = $primaryRoute->secondaryRoute;
            DB::commit();
            return response()->json(prepareResult(false, $primaryRoute, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(PrimaryRoute $primaryRoute)
    {
        try {
            $primaryRouteId = $primaryRoute->id;
            $secondaryRouteIds = SecondaryRoute::select('id')
                ->where('primary_route_id', $primaryRoute->id)
                ->get();
            $checkRouteAssign = User::select('id')
                ->where(function($q) use ($secondaryRouteIds) {
                    $q->whereIn('promotional_route', $secondaryRouteIds)
                        ->orWhereIn('transaction_route', $secondaryRouteIds)
                        ->orWhereIn('two_waysms_route', $secondaryRouteIds)
                        ->orWhereIn('voice_sms_route', $secondaryRouteIds);
                })
                ->first();
            if($checkRouteAssign)
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_delete_route_assigned_to_user'), $this->intime), config('httpcodes.internal_server_error'));
            }
            kannelRouteDelete($primaryRoute->smsc_id);
            $primaryRoute->delete();
            if($primaryRoute)
            {
                //romoved already associted route first
                \DB::table('primary_route_associateds')
                    ->where('primary_route_id', $primaryRoute->id)
                    ->where('associted_primary_route', '!=', $primaryRoute->id)
                    ->delete();

                SecondaryRoute::where('primary_route_id', $primaryRouteId)->delete();
            }
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function primaryRouteAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'status'      => 'required|in:0,1',
            'primary_routes_id'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->status==0)
        {
            $secondaryRouteIds = SecondaryRoute::select('id')
                ->whereIn('primary_route_id', $request->primary_routes_id)
                ->get();
            $checkRouteAssign = User::select('id')
                ->where(function($q) use ($secondaryRouteIds) {
                    $q->whereIn('promotional_route', $secondaryRouteIds)
                        ->orWhereIn('transaction_route', $secondaryRouteIds)
                        ->orWhereIn('two_waysms_route', $secondaryRouteIds)
                        ->orWhereIn('voice_sms_route', $secondaryRouteIds);
                })
                ->first();
            if($checkRouteAssign)
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_deactivate_route_assigned_to_user'), $this->intime), config('httpcodes.internal_server_error'));
            }
        }

        try {
            $primaryRoute = PrimaryRoute::whereIn('id', $request->primary_routes_id)
                ->update([
                    'status' => $request->status
                ]);
            if($primaryRoute)
            {
                SecondaryRoute::whereIn('primary_route_id', $request->primary_routes_id)
                ->update([
                    'status' => $request->status
                ]);
            }
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

    public function checkPrimaryRouteConnection(Request $request, $primary_route_id)
    {
        try {
            $primaryRoute = PrimaryRoute::select('id','smsc_id','ip_address','port','smsc_username','smsc_password')->find($primary_route_id);
            
            if($primaryRoute->gateway_type==4)
            {
                return response()->json(prepareResult(true, [], trans('translate.voice_route_not_allowed_to_check_connection'), $this->intime), config('httpcodes.internal_server_error'));
            }
            if($primaryRoute)
            {
                dispatch(new CheckConnectionSMPP($primaryRoute));
                $primaryRoute = PrimaryRoute::find($primary_route_id);
                return response()->json(prepareResult(false, $primaryRoute, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function checkVoiceBalance($primary_route_id)
    {
        try {
            $primaryRoute = PrimaryRoute::select('id', 'api_url_for_voice', 'ip_address', 'smsc_username','smsc_password','smpp_credit', 'route_name', 'smsc_id')
                ->find($primary_route_id);
            $response = null;
            if($primaryRoute->smsc_id=='videocon')
            {
                $requestUrl = $primaryRoute->api_url_for_voice."/CHECK_ACC_BAL?UserName=".$primaryRoute->smsc_username."&Password=".$primaryRoute->smsc_password."&ChildUserName=";
                $response = Http::get($requestUrl);
                $response = $response->json();
                
                $primaryRoute->smpp_credit = preg_replace("/[^0-9]/", "", $response['ERR_DESC']);
                $primaryRoute->save();
                $response = $response['ERR_DESC'];
            }

            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
