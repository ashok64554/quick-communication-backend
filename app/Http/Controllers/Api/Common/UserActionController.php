<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class UserActionController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        //$this->middleware('permission:user-change-api-key', ['only' => ['userChangeApiKey']]);
    }

    public function userChangeApiKey(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = User::select('id','uuid','app_key','app_secret')->find(auth()->id())->makeVisible('app_key','app_secret');
            if($request->change_key==1)
            {
                $user->app_key = Str::random(25);
                $user->app_secret = Str::random(15);
                $user->save();
                DB::commit();

                ////////notification and mail//////////
                $variable_data = [];
                notification('api-key-secret-changed', $user, $variable_data);
                /////////////////////////////////////
                return response()->json(prepareResult(false, $user, trans('translate.created'), $this->intime), config('httpcodes.created'));
            }
            return response()->json(prepareResult(false, $user, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function enableAdditionalSecurity(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'is_enabled_api_ip_security'      => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = User::select('id','uuid','is_enabled_api_ip_security')->find(auth()->id());
            $user->is_enabled_api_ip_security = $request->is_enabled_api_ip_security;
            $user->save();
            DB::commit();

            ////////notification and mail//////////
            $variable_data = [
                '{{action_name}}' => ($request->is_enabled_api_ip_security==1) ? 'Enabled' : 'Disabled',
            ];
            notification('enable-additional-security', $user, $variable_data);
            /////////////////////////////////////

            return response()->json(prepareResult(false, $user, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function resetUserPassword(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'          => 'required|exists:users,id',
            'password'          => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            if(in_array(loggedInUserType(), [0,3]))
            {
                $user = User::select('id','password')->find($request->user_id);
                if($user)
                {
                    $user->password = bcrypt($request->password);
                    $user->save();
                    DB::commit();
                    return response()->json(prepareResult(false, $user, trans('translate.success'), $this->intime), config('httpcodes.success'));
                }
            }
            else
            {
                return response()->json(prepareResult(true, trans('translate.unauthorized_to_perform_operation'), trans('translate.unauthorized_to_perform_operation'), $this->intime), config('httpcodes.bad_request'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function updateProfile(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'name'      => 'required',
            'mobile'    => 'required|min:10|max:12',
            'address'   => 'required|string',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = User::find(auth()->id());
            $user->name = $request->name;
            $user->mobile = $request->mobile;
            $user->address = $request->address;
            $user->city = $request->city;
            $user->zipCode = $request->zipCode;
            $user->companyName = $request->companyName;
            if(!empty($request->companyLogo))
            {
                $user->companyLogo = $request->companyLogo;
            }
            $user->companyName = $request->companyName;
            $user->websiteUrl = $request->websiteUrl;
            $user->designation = $request->designation;
            $user->locktimeout = $request->locktimeout;
            $user->save();
            $user = $user->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);            
            DB::commit();
            return response()->json(prepareResult(false, $user, trans('translate.success'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function updateSelfPassword(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'old_password'      => 'required',
            'password'          => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = User::find(auth()->id());
            if(\Hash::check($request->old_password, $user->password))
            {
                $user->password = bcrypt($request->password);
                $user->save();
                DB::commit();
                $user = $user->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']); 
                return response()->json(prepareResult(false, $user, trans('translate.success'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.incorrect_password'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
