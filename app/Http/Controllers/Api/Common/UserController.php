<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Str;
use Mail;
use Auth;
use DB;
use Exception;

class UserController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:users-list', ['except' => ['usersForDdl']]);
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update']]);
        $this->middleware('permission:user-view', ['only' => ['show', 'viewLoginLog']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        $this->middleware('permission:user-action', ['only' => ['userAction']]);
        $this->middleware('permission:user-ddl-list', ['only' => ['usersForDdl']]);
    }

    public function index(Request $request)
    {
        try {
            $query = User::where('userType', '!=', 0)
            ->where('status', '!=', '2')
            ->with('roles:id,name')
            
            ->orderBy('users.id', 'DESC');

            if(in_array(auth()->user()->userType, [0,3]))
            {
                $query->with('promotionalRouteInfo:id,sec_route_name','transactionRouteInfo:id,sec_route_name','twoWaysmsRouteInfo:id,sec_route_name','voiceSmsRouteInfo:id,sec_route_name', 'speedRatio','WhatsAppCharges:user_id,wa_marketing_charge as wa_marketing_charge,wa_utility_charge,wa_service_charge');
            }
            else
            {
                $query->whereIn('userType', [1,2]);
                if(auth()->user()->userType==1)
                {
                    $query->where('current_parent_id', auth()->id());
                }
                else
                {
                    $query->where('id', auth()->id());
                }

                $query->with('WhatsAppCharges:user_id,wa_marketing_charge,wa_utility_charge,wa_service_charge');
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.username', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.email', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.mobile', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->email))
            {
                $query->where('email', 'LIKE', '%'.$request->email.'%');
            }

            if(!empty($request->name))
            {
                $query->where('name', 'LIKE', '%'.$request->name.'%');
            }

            if(!empty($request->mobile))
            {
                $query->where('mobile', 'LIKE', '%'.$request->mobile.'%');
            }

            if(!empty($request->username))
            {
                $query->where('username', 'LIKE', '%'.$request->username.'%');
            }

            if(!empty($request->userType))
            {
                $query->where('userType', $request->userType);
            }

            if(!empty($request->status) && $request->status=='no')
            {
                $query->where('status', '0');
            }
            elseif(!empty($request->status))
            {
                $query->where('status', (string) $request->status);
            }


            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $result = $result->makeHidden(['otp_route','promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
                }

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
                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $query = $query->makeHidden(['otp_route','promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
                }
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
            'name'      => 'required|regex:/^[a-zA-Z0-9-_ ]+$/',
            'email'     => 'required|email|unique:users,email',
            'username'  => 'required|min:6|max:20|alpha_dash|unique:users,username',
            'password'  => 'required|string|min:6',
            'mobile'    => 'required|min:10|max:12',
            'address'   => "required|string|regex:/(^([A-Za-z0-9'\.\-\s\,]+)(\d+)?$)/u",
        ],
        [
            'address' =>  'The address format is invalid. allowed only these ,.- special characters.',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkExistingUser = \DB::table('users')
            ->where('email', $request->email)
            ->orWhere('username', $request->email)
            ->orWhere('email', $request->username)
            ->orWhere('username', $request->username)
            ->first();
        if ($checkExistingUser) {
            return response()->json(prepareResult(true, [], trans('translate.user_already_exist_with_this_email_or_username'), $this->intime), config('httpcodes.bad_request'));
        }

        if ($request->userType==0) {
            return response()->json(prepareResult(true, [], trans('translate.invalid_request'), $this->intime), config('httpcodes.bad_request'));
        }

        if ($request->userType==3) {
            $validation = \Validator::make($request->all(), [
                'role_id'     => 'required|exists:roles,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        DB::beginTransaction();
        try {
            $user = new User;
            $user->userType = $request->userType;
            $user->name = $request->name;
            $user->email  = $request->email;
            $user->username  = $request->username;
            $user->password = bcrypt($request->password);
            $user->mobile = $request->mobile;
            $user->address = $request->address;
            $user->city = $request->city;
            $user->zipCode = $request->zipCode;
            $user->companyName = $request->companyName;
            $user->country = (!empty($request->country_id) ? $request->country_id : 76);
            $user->status = '1';
            $user->is_visible_dlt_template_group = $request->is_visible_dlt_template_group;

            $user->allow_to_add_webhook = $request->allow_to_add_webhook;

            if(!empty($request->companyLogo))
            {
                $user->companyLogo = $request->companyLogo;
            }
            $user->websiteUrl = $request->websiteUrl;
            $user->designation = $request->designation;
            $user->authority_type = !empty($request->authority_type) ? $request->authority_type : '2';
            $user->locktimeout = $request->locktimeout;
            $user->created_by = auth()->id();
            $user->current_parent_id = auth()->id();

            //route assign
            $user->otp_route = auth()->user()->otp_route;
            $user->promotional_route = auth()->user()->promotional_route;
            $user->transaction_route = auth()->user()->transaction_route;
            $user->two_waysms_route = auth()->user()->two_waysms_route;
            $user->voice_sms_route = auth()->user()->voice_sms_route;

            $user->save();
            if($user) 
            {
                if(in_array($request->userType, [0,3]))
                {
                    $user->account_type = (!empty($request->account_type) ? $request->account_type : 1);
                    $user->save();
                }
                else
                {
                    $user->account_type = auth()->user()->promotional_route;
                    $user->save();
                }

                if($request->userType==1)
                {
                    $user->parent_id = $user->id;
                    $user->current_parent_id = auth()->user()->parent_id;
                    $user->save();
                }
                elseif($request->userType==2)
                {
                    $user->parent_id = (empty(auth()->user()->parent_id) ? 1 : auth()->user()->parent_id);
                    $user->current_parent_id = auth()->user()->parent_id;
                    $user->save();
                }
                elseif(in_array($request->userType, [3]))
                {
                    $user->parent_id = 1;
                    $user->current_parent_id = null;
                    $user->save();
                }
                //Role and permission sync
                if($request->userType==1)
                {
                    $roleName = 'reseller';
                }
                elseif($request->userType==2)
                {
                    $roleName = 'client';
                }
                else
                {
                    $findRole = Role::find($request->role_id);
                    $roleName = $findRole->name;
                }
                $role = Role::where('name', $roleName)->first();
                $permissions = $role->permissions->pluck('name');
                
                $user->assignRole($role->name);
                foreach ($permissions as $key => $permission) {
                    $user->givePermissionTo($permission);
                }
            }
            
            DB::commit();
            return response()->json(prepareResult(false, $user, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(User $user)
    {
        try {
            if($user->parent_id==auth()->id() || in_array(auth()->user()->userType, [0,3]))
            {
                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $user = $user->makeHidden(['otp_route','promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
                }
                $user['whats_app_charges'] = $user->WhatsAppCharges()->select('wa_marketing_charge','wa_utility_charge','wa_service_charge')->first();
                return response()->json(prepareResult(false, $user, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, User $user)
    {
        $validation = \Validator::make($request->all(), [
            'name'      => 'required',
            'email'     => 'required|email|unique:users,email,'.$user->id,
            'username'  => 'required|min:6|max:20|alpha_dash|unique:users,username,'.$user->id,
            'mobile'    => 'required|min:10|max:12',
            'address'   => 'required|string',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        if($user->parent_id==auth()->id() || in_array(auth()->user()->userType, [0,3]))
        {
            // nothing heppen
        }
        else
        {
            return response()->json(prepareResult(true, [], trans('translate.invalid_request'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $user->name = $request->name;
            $user->email  = $request->email;
            $user->username  = $request->username;
            $user->mobile = $request->mobile;
            $user->address = $request->address;
            $user->city = $request->city;
            $user->zipCode = $request->zipCode;
            $user->companyName = $request->companyName;
            if(!empty($request->companyLogo))
            {
                $user->companyLogo = $request->companyLogo;
            }
            $user->websiteUrl = $request->websiteUrl;
            $user->designation = $request->designation;
            $user->authority_type = !empty($request->authority_type) ? $request->authority_type : $user->authority_type;
            $user->country = (!empty($request->country_id) ? $request->country_id : $user->country);
            $user->locktimeout = $request->locktimeout;
            $user->status = $user->status;
            
            if(!empty($request->is_visible_dlt_template_group))
            {
                $user->is_visible_dlt_template_group = $request->is_visible_dlt_template_group;
            }

            $user->allow_to_add_webhook = $request->allow_to_add_webhook;
            if($request->allow_to_add_webhook!=1)
            {
                $user->webhook_callback_url = null;
                $user->webhook_signing_key = null;
            }
            
            $user->save(); 

            if(in_array($request->userType, [0,3]))
            {
                $user->account_type = (!empty($request->account_type) ? $request->account_type : $user->account_type);
                $user->save();
            }
            else
            {
                $user->account_type = auth()->user()->account_type;
                $user->save();
            }

            if($user->userType==3)
            {
                //Role and permission sync
                $role = Role::find($request->role_id);
                $roleName = $role->name;

                // check current Role
                $currentRole = $user->roles[0]->name;
                if($currentRole!=$roleName)
                {
                    //delete role and permissions
                    \DB::table('model_has_roles')->where('model_id', $user->id)->delete();
                    \DB::table('model_has_permissions')->where('model_id', $user->id)->delete();
                    
                    $permissions = $role->permissions->pluck('name');
                    $user->assignRole($role->name);
                    foreach ($permissions as $key => $permission) {
                        $user->givePermissionTo($permission);
                    }
                }
            }           
            DB::commit();

            if(in_array(auth()->user()->userType, [1,2]))
            {
                $user = $user->makeHidden(['otp_route','promotional_route','transaction_route','two_waysms_route','voice_sms_route']);
            }

            return response()->json(prepareResult(false, $user, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(User $user)
    {
        try {
            if($user->userType==0)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            if($user->id==auth()->id())
            {
                return response()->json(prepareResult(true, [], trans('translate.you_cant_delete_self_account'), $this->intime), config('httpcodes.not_found'));
            }

            $user->email = $user->email.'#deleted-'.time();
            $user->status = '2';
            $user->save();
            if($user && in_array($user->userType, [1,2]))
            {
                $childs = userChildAccounts($user);
                $childAccountDisabled = User::whereIn('id', $childs)
                ->update([
                    'status' => '3'
                ]);
                $user->delete();
            }
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function usersForDdl(Request $request)
    {
        try {
            $search = $request->search;
            $query = User::select('id', 'name', 'email', 'username', 'mobile', 'promotional_credit', 'transaction_credit', 'two_waysms_credit', 'is_visible_dlt_template_group', 'voice_sms_credit','whatsapp_credit as wa_amount', \DB::raw("concat(username,' | ',name) as username_name"))
            ->whereIn('userType',[1,2])
            ->where('status', '1')
            ->orderBy('users.name', 'ASC')
            ->where(function ($query) use ($search) {
                $query->where('email', 'LIKE', '%' . $search. '%')
                ->orWhere('name', 'LIKE', '%' . $search. '%')
                ->orWhere('mobile', 'LIKE', '%' . $search. '%')
                ->orWhere('username', 'LIKE', '%' . $search. '%');
            });

            if(in_array(auth()->user()->userType, [1,2]))
            {
                $query->where('id', auth()->id());
            }

            if(!empty($request->is_visible_dlt_template_group))
            {
                $query->where('is_visible_dlt_template_group', $request->is_visible_dlt_template_group);
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

    public function userAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'status'      => 'required|in:0,1,2',
            'user_ids'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $users = User::whereIn('id', $request->user_ids)
                ->where('userType', '!=', 0)
                ->get();
            foreach ($users as $key => $user) {
                if($user->id!=auth()->id())
                {
                    $user->status = (string) $request->status;
                    $user->save();
                    if($request->status!='1')
                    {
                        if($request->status=='2' && in_array($user->userType, [1,2]))
                        {
                            //update child status
                            $user->children()->update([
                                'status' => '3'
                            ]);
                        }
                        else
                        {
                            //update child status
                            $user->children()->update([
                                'status' => (string) $request->status
                            ]);
                        }
                    }
                }
            }

            return response()->json(prepareResult(false, [], trans('translate.updated'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getWebhookInfo($user_id)
    {
        try {
            if(in_array(auth()->user()->userType, [1,2]))
            {
                $user_id = auth()->id();
            }
            $user = User::select('id','name','webhook_callback_url','webhook_signing_key')->with('subscribeWebhookEvents:id,user_id,webhook_event_name')->find($user_id);
            if($user)
            {
                return response()->json(prepareResult(false, $user, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }

            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
