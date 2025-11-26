<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appsetting;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Illuminate\Support\Facades\Http;

class AppsettingController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:app-setting', ['except' => ['index','publicAppSetting','getCurruentKannelStatus']]);
        $this->middleware('permission:kannel-status-page', ['except' => ['index','publicAppSetting','addUpdate']]);
    }

    public function index()
    {
        try {
            $query = Appsetting::query();
            if(auth()->guard('api')->check() && in_array(@auth()->user()->userType, [0,3]))
            {
                $query->select('*');
            }
            else
            {
                $query->select('app_name','app_logo','contact_email','contact_address','contact_number');
            }
            $query = $query->first();
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function publicAppSetting()
    {
        try {
            $query = Appsetting::select('app_name','app_logo','contact_email','contact_address','contact_number', 'g_key', 'g_secret','privacy_policy','terms_and_conditions','cookies_protection','cookies_disclaimer')->first();
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function addUpdate(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'app_name'    => 'required',
            'app_logo' => 'required',
            'contact_email'  => 'required|email',
            'contact_address'  => 'required',
            'tax_percentage'  => 'required|numeric',

        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            if(Appsetting::count()>0)
            {
                $app_setting = Appsetting::first();
            }
            else
            {
                $app_setting = new Appsetting;
            }
            
            $app_setting->app_name = $request->app_name;
            $app_setting->app_logo = $request->app_logo;
            $app_setting->contact_email = $request->contact_email;
            $app_setting->contact_address = $request->contact_address;
            $app_setting->contact_number = $request->contact_number;
            $app_setting->tax_percentage = $request->tax_percentage;
            $app_setting->privacy_policy = $request->privacy_policy;
            $app_setting->terms_and_conditions = $request->terms_and_conditions;
            $app_setting->cookies_protection = $request->cookies_protection;
            $app_setting->cookies_disclaimer = $request->cookies_disclaimer;
            $app_setting->save();
            DB::commit();
            return response()->json(prepareResult(false, $app_setting, trans('translate.created'), $this->intime),config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getCurrentKannelStatus()
    {
        try {
            $password = env('KANNEL_ADMIN_PASS', 'nrt_inc_2010');
            $kannel_ip = env('KANNEL_IP', '68.178.162.45');
            $response = Http::get("$kannel_ip:13000/status?password=$password");
            $response = $response->body();
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime),config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function contactUs(Request $request)
    {
        try {
            $query = ContactUs::orderBy('id', 'DESC')->whereNotNull('name');

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search. '%')
                        ->orWhere('email', 'LIKE', '%' . $search. '%')
                        ->orWhere('mobile', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->name))
            {
                $query->where('name', 'LIKE', '%'.$request->name.'%');
            }

            if(!empty($request->email))
            {
                $query->where('email', 'LIKE', '%'.$request->email.'%');
            }

            if(!empty($request->mobile))
            {
                $query->where('mobile', 'LIKE', '%'.$request->mobile.'%');
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
