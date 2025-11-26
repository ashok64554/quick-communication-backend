<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class RoleController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:roles-list');
        $this->middleware('permission:role-create', ['only' => ['store']]);
        $this->middleware('permission:role-edit', ['only' => ['update']]);
        $this->middleware('permission:role-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = Role::orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [0,3]))
            {
                $query->where(function ($q) {
                    $q->whereNull('parent_id')
                        ->orWhere('parent_id', 1);
                });
            }
            else
            {
                $query->where('parent_id', auth()->user()->parent_id);
            }

            if(!empty($request->name))
            {
                $query->where('name', 'LIKE', '%'.$request->name.'%');
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

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'name'    => 'required|unique:roles,id',
            'permissions' => 'required|array|min:1'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $roleName = '1-'.Str::slug(substr($request->name, 0, 20));
        }
        else
        {
            $roleName = auth()->user()->parent_id.'-'.Str::slug(substr($request->name, 0, 20));
        }
        $checkDuplicate = Role::where('name', $roleName)->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $role = new Role;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $role->parent_id  = 1;
            }

            $role->name  = $roleName;
            $role->actual_name  = $request->name;
            $role->guard_name  = 'api';
            $role->save();
            DB::commit();
            if($role) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json(prepareResult(false, $role, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(Role $role)
    {
        try {
            $query = Role::where('id', $role->id)->with('permissions');

            if(in_array(loggedInUserType(), [0,3]))
            {
                $query->where(function ($q) {
                    $q->whereNull('parent_id')
                        ->orWhere('parent_id', 1);
                });
            }
            else
            {
                $query->where('parent_id', auth()->user()->parent_id);
            }
            $query = $query->first();
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, Role $role)
    {
        $validation = \Validator::make($request->all(), [
            'name'    => 'required',
            'permissions' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $roleName = '1-'.Str::slug(substr($request->name, 0, 20));
        }
        else
        {
            $roleName = auth()->user()->parent_id.'-'.Str::slug(substr($request->name, 0, 20));
        }
        $checkDuplicate = Role::where('name', $roleName)
            ->where('id', '!', $role->id)
            ->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            if(!in_array($role->id, [1,2,3]))
            {
                $role->name  = $roleName;
            }
            $role->actual_name  = $request->name;
            $role->save();
            DB::commit();
            if($role) {
                $role->syncPermissions($request->permissions);

                $roleUsers = DB::table('model_has_roles')
                    ->where('role_id',$role->id)
                    ->get();
                foreach ($roleUsers as $key => $value) 
                {
                    $user = User::find($value->model_id);
                    if(!empty($user))
                    {
                        $user->syncPermissions($request->permissions);
                    }
                }
            }
            return response()->json(prepareResult(false, $role, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(Role $role)
    {
        try {
            $checkRoleAllot = \DB::table('model_has_roles')->where('role_id', $role->id)->count();
            if($checkRoleAllot>0)
            {
                return response()->json(prepareResult(true, [], trans('translate.cannot_delete_this_role_bcoz_some_user_has_assigned'), $this->intime), config('httpcodes.internal_server_error'));
            }
            $role->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getPermissions()
    {
        try {
            if(in_array(loggedInUserType(), [0,3]))
            {
                $query = \DB::table('model_has_roles')->where('model_id', 1)->first();
            }
            else
            {
                $query = \DB::table('model_has_roles')->where('model_id', auth()->id())->first();
            }
            $role = Role::with('permissions')->find($query->role_id);

            return response()->json(prepareResult(false, $role, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getAllPermissions()
    {
        try {
            $role = Permission::get();

            return response()->json(prepareResult(false, $role, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
