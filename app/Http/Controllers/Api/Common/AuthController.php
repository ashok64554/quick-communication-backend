<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserLog;
use App\Models\WebhookEvent;
use App\Models\SubscribeWebhookEvent;
use App\Models\TFA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Auth;
use DB;
use Exception;
use Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }

    public function unauthorized(Request $request)
    {
        return response()->json(prepareResult(true, [], trans('translate.unauthorized_login'), $this->intime), config('httpcodes.unauthorized'));
    }

    public function throttleKey($data)
    {
        return \Str::lower($data);
    }
    
    public function login(Request $request)
    {
        if(env('IS_THROTTLE_ON', false))
        {
            if (RateLimiter::tooManyAttempts(request()->ip(), env('THROTTLE_ALLOW_ATTEMPTS', 5))) 
            {
                $seconds = RateLimiter::availableIn($this->throttleKey(request()->ip()));

                $returnError  = [
                    "account_locked"=> true, 
                    "time" => $seconds
                ];
                return response()->json(prepareResult(true, $returnError, trans('translate.too_many_fail_login_attempt'), $this->intime), config('httpcodes.unauthorized'));
            }
        }
        

        $validation = \Validator::make($request->all(),[ 
            'email'     => 'required',
            'password'  => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $email = $request->email;
            $user = User::select('*', 'is_show_ratio as sp_operation')
            ->where(function($q) use ($email) {
                $q->where('email', $email)
                    ->orWhere('username', $email);
            })
            ->withoutGlobalScope('parent_id')
            ->first();
            if (!$user)  {
                if(env('IS_THROTTLE_ON', false))
                {
                    RateLimiter::hit(request()->ip(), env('THROTTLE_RATE_LIMIT', 600));
                }
                return response()->json(prepareResult(true, [], trans('translate.user_not_exist'), $this->intime), config('httpcodes.not_found'));
            }
            $user = $user->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);

            $user['whats_app_charges'] = $user->WhatsAppCharges()->select('wa_marketing_charge','wa_utility_charge','wa_service_charge')->first();
            if(in_array($user->status, ['0','3'])) {
                return response()->json(prepareResult(true, [], trans('translate.account_is_inactive'), $this->intime), config('httpcodes.unauthorized'));
            }

            if(Hash::check($request->password, $user->password)) {

                if(env('IS_2FA_ENABLED', false))
                {
                    $checkTFA = DB::table('t_f_a_s')
                    ->where('user_id', $user->id)
                    ->where('ip_address', request()->ip())
                    ->whereDate('expires_at', '>=', date('Y-m-d'))
                    ->first();
                    if(!$checkTFA)
                    {
                        $deleteOldOtps = DB::table('t_f_a_s')
                            ->where('user_id', $user->id)
                            ->where('ip_address', request()->ip())
                            ->where(function($q) {
                                $q->whereDate('expires_at', '<', date('Y-m-d'))
                                    ->orWhereNull('expires_at');
                            })
                            ->delete();

                        $createTfa = new TFA;
                        $createTfa->user_id = $user->id;
                        $createTfa->ip_address = request()->ip();
                        $createTfa->login_otp = rand(100000, 999999);
                        $createTfa->expires_at = null;
                        $createTfa->save();

                        //send mail or message
                        $variable_data = [
                            '{{name}}' => $user->name,
                            '{{otp}}' => $createTfa->login_otp
                        ];
                        notification('send-otp-for-login', $user, $variable_data, null, null, null, true);

                        $ask_for_otp = [
                            'otp' => $createTfa->login_otp,
                            'ask_for_otp' => true,
                            'token_id' => Str::random(15).'@'.base64_encode($user->id).'@'.Str::random(7)
                        ];
                        return response()->json(prepareResult(false, $ask_for_otp, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.success'));
                    }
                }
                

                $user['ask_for_otp'] = false;
                $tokenResult = $user->createToken('authToken');
                $accessToken = $tokenResult->accessToken;
                $user['access_token'] = $accessToken;
                $user['expires_at'] = $tokenResult->token->expires_at->toDateTimeString();
                $user['permissions'] = $user->permissions()->select('id','name')->orderBy('permission_id', 'ASC')->get();
                if(($user->is_visible_dlt_template_group==1) || in_array($user->userType, [0,3]))
                {
                    $user['permissions'][] = [
                        'id' => count($user['permissions']) + 1,
                        'name' => 'is-visible-dlt-template-group'
                    ];
                }

                $user['parent_key'] = null;

                //create device info
                if(!empty($request->device_token))
                {
                    $checkDevice = UserDevice::where('user_id', $user->id)
                        ->where('device_token', $request->device_token)
                        ->first();
                    if(!$checkDevice)
                    {
                        $userDevice = new UserDevice;
                        $userDevice->user_id = $user->id;
                        $userDevice->device_token = $request->device_token;
                        $userDevice->ip_address = request()->ip();
                        $userDevice->save();
                    }
                }

                $currentIP = ($request->ip()=='127.0.0.1') ? '122.173.39.109' : $request->ip();
                $info = \Location::get($currentIP);
                $userLog = new UserLog;
                $userLog->user_id = $user->id;
                $userLog->ip_address = $request->ip();
                $userLog->country_name = @$info->countryName;
                $userLog->complete_info = $info;
                $userLog->save();

                return response()->json(prepareResult(false, $user, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.success'));
            } else {
                if(env('IS_THROTTLE_ON', false))
                {
                    RateLimiter::hit(request()->ip(), env('THROTTLE_RATE_LIMIT', 600));
                }
                return response()->json(prepareResult(true, [], trans('translate.invalid_username_and_password'), $this->intime), config('httpcodes.unauthorized'));
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function resentOtp(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'token_id'     => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $token_id = $request->token_id;
        $getUserId = explode('@', $token_id);
        $user_id = base64_decode($getUserId[1]);

        try {

            if (env('IS_THROTTLE_ON', false) && RateLimiter::tooManyAttempts($user_id.'|'.request()->ip(), env('THROTTLE_ALLOW_ATTEMPTS', 3))) 
            {
                $seconds = RateLimiter::availableIn($this->throttleKey($user_id.'|'.request()->ip()));

                $returnError  = [
                    "account_locked"=> true, 
                    "time" => $seconds
                ];
                return response()->json(prepareResult(true, $returnError, trans('translate.too_many_otp_resent_attempt'), $this->intime), config('httpcodes.unauthorized'));
            }

            $checkTFA = TFA::where('user_id', $user_id)
                ->where('ip_address', request()->ip())
                ->first();
            if(!$checkTFA)
            {
                $createTfa = new TFA;
                $createTfa->user_id = $user_id;
                $createTfa->ip_address = request()->ip();
                $createTfa->login_otp = rand(100000, 999999);
                $createTfa->expires_at = null;
                $createTfa->save();

                $checkTFA = $createTfa;
            }
            $user = \DB::table('users')
                ->select('id', 'uuid','name')
                ->where('id', $user_id)
                ->first();
            //send mail or message
            $variable_data = [
                '{{name}}' => $user->name,
                '{{otp}}' => $checkTFA->login_otp
            ];
            notification('send-otp-for-login', $user, $variable_data, null, null, null, true);

            $ask_for_otp = [
                'otp' => $checkTFA->login_otp,
                'ask_for_otp' => true,
                'token_id' => Str::random(15).'@'.base64_encode($user->id).'@'.Str::random(7)
            ];

            if(env('IS_THROTTLE_ON', false))
            {
                RateLimiter::hit($user_id.'|'.request()->ip(), env('THROTTLE_RATE_LIMIT', 600));
            }

            return response()->json(prepareResult(false, $ask_for_otp, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function verifyOtp(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'token_id'     => 'required',
            'otp'  => 'required|min:6',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $token_id = $request->token_id;
        $getUserId = explode('@', $token_id);
        $user_id = base64_decode($getUserId[1]);

        if (env('IS_THROTTLE_ON', false) && RateLimiter::tooManyAttempts($user_id.'|otp_verification|'.request()->ip(), env('THROTTLE_ALLOW_ATTEMPTS', 3))) 
            {
                $seconds = RateLimiter::availableIn($this->throttleKey($user_id.'|otp_verification|'.request()->ip()));

                $returnError  = [
                    "account_locked"=> true, 
                    "time" => $seconds
                ];
                return response()->json(prepareResult(true, $returnError, trans('translate.too_many_invalid_otp_attempt'), $this->intime), config('httpcodes.unauthorized'));
            }

        try {
            $checkTFA = TFA::where('user_id', $user_id)
                ->where('ip_address', request()->ip())
                ->where('login_otp', $request->otp)
                ->first();
            if($checkTFA)
            {
                if($request->remember_this_device_for_15_days)
                {
                    $checkTFA->update([
                        'expires_at' => date('Y-m-d', strtotime('15 days', time()))
                    ]);
                }
                else
                {
                    $checkTFA->delete();
                }
                $user = User::select('*', 'is_show_ratio as sp_operation')
                ->where('id', $user_id)
                ->withoutGlobalScope('parent_id')
                ->first();
                $user = $user->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);

                $user['ask_for_otp'] = false;
                $tokenResult = $user->createToken('authToken');
                $accessToken = $tokenResult->accessToken;
                $user['access_token'] = $accessToken;
                $user['expires_at'] = $tokenResult->token->expires_at->toDateTimeString();
                $user['permissions'] = $user->permissions()->select('id','name')->orderBy('permission_id', 'ASC')->get();

                $user['whats_app_charges'] = $user->WhatsAppCharges()->select('wa_marketing_charge','wa_utility_charge','wa_service_charge')->first();

                if(($user->is_visible_dlt_template_group==1) || in_array($user->userType, [0,3]))
                {
                    $user['permissions'][] = [
                        'id' => count($user['permissions']) + 1,
                        'name' => 'is-visible-dlt-template-group'
                    ];
                }
                
                $user['parent_key'] = null;

                //create device info
                if(!empty($request->device_token))
                {
                    $checkDevice = UserDevice::where('user_id', $user->id)
                        ->where('device_token', $request->device_token)
                        ->first();
                    if(!$checkDevice)
                    {
                        $userDevice = new UserDevice;
                        $userDevice->user_id = $user->id;
                        $userDevice->device_token = $request->device_token;
                        $userDevice->ip_address = request()->ip();
                        $userDevice->save();
                    }
                }

                $currentIP = ($request->ip()=='127.0.0.1') ? '122.173.39.109' : $request->ip();
                $info = \Location::get($currentIP);
                $userLog = new UserLog;
                $userLog->user_id = $user->id;
                $userLog->ip_address = $request->ip();
                $userLog->country_name = @$info->countryName;
                $userLog->complete_info = $info;
                $userLog->save();

                return response()->json(prepareResult(false, $user, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.success'));
            }
            else
            {
                if(env('IS_THROTTLE_ON', false))
                {
                    RateLimiter::hit($user_id.'|otp_verification|'.request()->ip(), env('THROTTLE_RATE_LIMIT', 600));
                }
                return response()->json(prepareResult(true, trans('translate.invalid_otp'), trans('translate.invalid_otp'), $this->intime), config('httpcodes.unauthorized'));
            }

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function logout(Request $request)
    {
        if (Auth::check()) 
        {
            try
            {
                $token = Auth::user()->token();
                $token->revoke();
                auth('api')->user()->tokens->each(function ($token, $key) {
                    $token->where('revoked', 1)->delete();
                });
                return response()->json(prepareResult(false, [], trans('translate.logout_message'), $this->intime), config('httpcodes.success'));
            }
            catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
        }
        return response()->json(prepareResult(true, [], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
    }

    public function forgotPassword(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'email'     => 'required|email'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $user = User::where('email',$request->email)->withoutGlobalScope('parent_id')->first();
            if (!$user) {
                return response()->json(prepareResult(true, [], trans('translate.user_not_exist'), $this->intime), config('httpcodes.not_found'));
            }

            if(in_array($user->status, ['0','3'])) {
                return response()->json(prepareResult(true, [], trans('translate.account_is_inactive'), $this->intime), config('httpcodes.unauthorized'));
            }

            //Delete if entry exists
            DB::table('password_resets')->where('email', $request->email)->delete();

            $token = Str::random(64);
            DB::table('password_resets')->insert([
              'email' => $request->email, 
              'token' => $token, 
              'created_at' => Carbon::now()->toDateTimeString()
            ]);

            ////////notification and mail//////////
            $variable_data = [
                '{{name}}' => $user->name,
                '{{link}}' => env('FRONT_URL').'/reset-password/'.$token
            ];
            notification('forgot-password', $user, $variable_data);
            /////////////////////////////////////

            return response()->json(prepareResult(false, $request->email, trans('translate.password_reset_link_send_to_your_mail'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function updatePassword(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'password'  => 'required|string|min:6',
            'token'     => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $tokenExist = DB::table('password_resets')
                ->where('token', $request->token)
                ->first();
            if (!$tokenExist) {
                return response()->json(prepareResult(true, [], trans('translate.token_expired_or_not_found'), $this->intime), config('httpcodes.unauthorized'));
            }

            $user = User::where('email',$tokenExist->email)->withoutGlobalScope('parent_id')->first();
            if (!$user) {
                return response()->json(prepareResult(true, [], trans('translate.user_not_exist'), $this->intime), config('httpcodes.not_found'));
            }

            if(in_array($user->status, ['0','3'])) {
                return response()->json(prepareResult(true, [], trans('translate.account_is_inactive'), $this->intime), config('httpcodes.unauthorized'));
            }

            $userUpdate = User::where('email', $tokenExist->email)
                    ->withoutGlobalScope('parent_id')
                    ->update(['password' => Hash::make($request->password)]);
 
            DB::table('password_resets')->where(['email'=> $tokenExist->email])->delete();

            ////////notification and mail//////////
            $variable_data = [
                '{{name}}' => $user->name
            ];
            notification('password-changed', $user, $variable_data);
            /////////////////////////////////////


            return response()->json(prepareResult(false, $tokenExist->email, trans('translate.password_changed'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function changePassword(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'old_password'  => 'required|string|min:6',
            'password'      => 'required|string|min:6'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            
            $user = User::where('email', Auth::user()->email)->withoutGlobalScope('parent_id')->first();
            
            if(in_array($user->status, ['0','3'])) {
                return response()->json(prepareResult(true, [], trans('translate.account_is_inactive'), $this->intime), config('httpcodes.unauthorized'));
            }
            if(Hash::check($request->old_password, $user->password)) {
                $userUpdate = User::where('email', Auth::user()->email)
                    ->withoutGlobalScope('parent_id')
                    ->update(['password' => Hash::make($request->password)]);

                ////////notification and mail//////////
                $variable_data = [
                    '{{name}}' => $user->name
                ];
                notification('password-changed', $user, $variable_data);
                /////////////////////////////////////
            }
            else
            {
                return response()->json(prepareResult(true, [], trans('translate.old_password_not_matched'), $this->intime), config('httpcodes.unauthorized'));
            }
            
            return response()->json(prepareResult(false, $request->email, trans('translate.password_changed'), $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function childLogin(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'account_uuid'      => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $parent_key = null;
            if(empty($request->is_back_to_self_account))
            {
                $parent_key = base64_encode(auth()->user()->uuid);
            }

            $user = User::select('*', 'is_show_ratio as sp_operation')
            ->where('uuid', base64_decode($request->account_uuid))
            ->withoutGlobalScope('parent_id')
            ->first();
            if (!$user)  {
                return response()->json(prepareResult(true, [], trans('translate.user_not_exist'), $this->intime), config('httpcodes.not_found'));
            }
            $user = $user->makeHidden(['promotional_route','transaction_route','two_waysms_route','voice_sms_route']);

            if(in_array($user->status, ['0','3'])) {
                return response()->json(prepareResult(true, [], trans('translate.account_is_inactive'), $this->intime), config('httpcodes.unauthorized'));
            }
            $tokenResult = $user->createToken('authToken');
            $accessToken = $tokenResult->accessToken;
            $user['access_token'] = $accessToken;
            $user['expires_at'] = $tokenResult->token->expires_at->toDateTimeString();
            $user['permissions'] = $user->permissions()->select('id','name')->orderBy('permission_id', 'ASC')->get();
            if(($user->is_visible_dlt_template_group==1) || in_array($user->userType, [0,3]))
            {
                $user['permissions'][] = [
                    'id' => count($user['permissions']) + 1,
                    'name' => 'is-visible-dlt-template-group'
                ];
            }
            $user['parent_key'] = $parent_key;
            return response()->json(prepareResult(false, $user, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function signUp(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'name'      => 'required|regex:/(^([a-zA-Z ]+)(\d+)?$)/u',
            'email'     => 'required|unique:users,email',
            'username'  => 'required|unique:users,username|min:6|max:20',
            'password'  => 'required|min:6|max:20',
            'mobile'  => 'required|numeric|digits:10',
            'address'  => 'required|regex:/(^([a-zA-Z ]+)(\d+)?$)/u',
            'companyName'  => 'required|regex:/(^([a-zA-Z ]+)(\d+)?$)/u',
            'city'  => 'required|regex:/(^([a-zA-Z ]+)(\d+)?$)/u',
            'zipCode'  => 'required|digits:6',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $user = new User;
            $user->userType = 2;
            $user->name = $request->name;
            $user->email  = $request->email;
            $user->username  = $request->username;
            $user->password = bcrypt($request->password);
            $user->mobile = $request->mobile;
            $user->address = $request->address;
            $user->city = $request->city;
            $user->zipCode = $request->zipCode;
            $user->companyName = $request->companyName;
            $user->authority_type = 1;
            $user->locktimeout = 10;
            $user->parent_id = 2;
            $user->current_parent_id = 2;

            //for other info
            $parent = User::select('id','promotional_route','transaction_route','two_waysms_route','voice_sms_route')->find(2);
            if($parent)
            {
                //route assign
                $user->promotional_route = $parent->promotional_route;
                $user->transaction_route = $parent->transaction_route;
                $user->two_waysms_route = $parent->two_waysms_route;
                $user->voice_sms_route = $parent->voice_sms_route;
            }

            //default credit added
            $user->promotional_credit = 10;

            $user->save();
            if($user) 
            {
                //Role and permission sync
                $role = Role::where('name', 'client')->first();
                $permissions = $role->permissions->pluck('name');
                $user->assignRole('client');
                foreach ($permissions as $key => $permission) {
                    $user->givePermissionTo($permission);
                }
            }
            
            DB::commit();
            return response()->json(prepareResult(false, User::select('name', 'email', 'username', 'password', 'mobile', 'address', 'city', 'zipCode', 'companyName', 'promotional_credit')->find($user->id), trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getWebhookEvents()
    {
        try {
            $query = WebhookEvent::orderBy('event_name', 'ASC')->get();
            $records['data'] = $query;
            return response()->json(prepareResult(false, $records, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function addWebhookUrl(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'webhook_url' => [
                'required',
                'url',
                'active_url', // Checks if the URL is reachable
                function ($attribute, $value, $fail) {
                    if (!str_starts_with($value, 'https://')) {
                        $fail("The {$attribute} must use HTTPS.");
                    }
                },
            ]
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if (!testWebhookUrl($request->webhook_url)) {
            return response()->json(prepareResult(true, ['webhook_url' => "The {$request->webhook_url} is not reachable or does not accept POST requests."], trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {

            $user_id = auth()->id();
            if(in_array(loggedInUserType(), [0,3]))
            {
                $user_id = $request->user_id;
            }

            $user = User::select('id', 'webhook_callback_url','webhook_signing_key')->find($user_id);
            if(sizeof($request->events) > 0)
            {
                $user->webhook_callback_url = $request->webhook_url;
                //$user->webhook_signing_key = (string) \Uuid::generate();
                $user->webhook_signing_key = $request->signing_key;
            }
            else
            {
                $user->webhook_callback_url = null;
                $user->webhook_signing_key = null;
            }
            $user->save();
            
            if($user)
            {
                SubscribeWebhookEvent::where('user_id', $user_id)->delete();
                foreach ($request->events as $key => $event) 
                {
                    $eventSubs = new SubscribeWebhookEvent;
                    $eventSubs->user_id = $user_id;
                    $eventSubs->webhook_event_name = $event;
                    $eventSubs->save();
                }
            }
            $user['subscribe_webhook_events'] = $user->subscribeWebhookEvents();
            
            DB::commit();
            return response()->json(prepareResult(false, $user, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getAllTimezones()
    {
        $tzlist = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        return response()->json(prepareResult(false, $tzlist, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
    }
}
