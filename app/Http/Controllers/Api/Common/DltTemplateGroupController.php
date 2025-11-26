<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\DltTemplateGroup;
use App\Models\DltTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class DltTemplateGroupController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }

    public function index(Request $request)
    {
        try {
            $query = DltTemplateGroup::select('id','user_id','group_name')
                ->with('user:id,name')
                ->withCount('dltTemplates')
                ->orderBy('group_name', 'ASC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $query->where('group_name', 'LIKE', '%'.$request->search.'%');
            }

            if(!empty($request->group_name))
            {
                $query->where('group_name', 'LIKE', '%'.$request->group_name.'%');
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
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
            'user_id'       => 'required|exists:users,id',
            'group_name'    => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkUserHavePermission = User::select('is_visible_dlt_template_group')->find($request->user_id);
        if($checkUserHavePermission && $checkUserHavePermission->is_visible_dlt_template_group!=1)
        {
            return response()->json(prepareResult(true, [], trans('translate.user_dont_have_permission_to_add_dlt_group'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            $dltTemplateGroup = new DltTemplateGroup;
            $dltTemplateGroup->user_id = $request->user_id;
            $dltTemplateGroup->group_name = $request->group_name;
            $dltTemplateGroup->save();

            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($dltTemplateGroup, $request);

            return response()->json(prepareResult(false, $data, trans('translate.created'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(DltTemplateGroup $dltTemplateGroup, Request $request)
    {
        try {
            $dltTempGrpId = $dltTemplateGroup->id;
            $dltTemplateGroup = DltTemplateGroup::select('id','user_id','group_name')
            ->with('user:id,name')
            ->with('dltTemplates:id,user_id,manage_sender_id,dlt_template_group_id,template_name,dlt_template_id,entity_id,sender_id,header_id,is_unicode,dlt_message,status');
            if(in_array(loggedInUserType(), [1,2]))
            {
                $dltTemplateGroup->where('user_id', auth()->id());
            }

            $dltTemplateGroup = $dltTemplateGroup->find($dltTempGrpId);
            if($request->other_function)
            {
                return $dltTemplateGroup;
            }
            return response()->json(prepareResult(false, $dltTemplateGroup, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, DltTemplateGroup $dltTemplateGroup)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'       => 'required|exists:users,id',
            'group_name'    => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            $dltTempGrpId = $dltTemplateGroup->id;
            $dltTemplateGroup = DltTemplateGroup::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $dltTemplateGroup->where('user_id', auth()->id());
            }
            $dltTemplateGroup = $dltTemplateGroup->find($dltTempGrpId);
            if(!$dltTemplateGroup)
            {
                return response()->json(prepareResult(true, [], trans('translate.unauthorized_to_perform_operation'), $this->intime), config('httpcodes.bad_request'));
            }

            $dltTemplateGroup->user_id = $request->user_id;
            $dltTemplateGroup->group_name = $request->group_name;
            $dltTemplateGroup->save();

            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($dltTemplateGroup, $request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(DltTemplateGroup $dltTemplateGroup)
    {
        try {
            $checkConnectedTemplate = DltTemplate::where('dlt_template_group_id', $dltTemplateGroup->id)->count();
            if($checkConnectedTemplate>0)
            {
                return response()->json(prepareResult(true, [], trans('translate.dlt_templates_are_connected_to_this_group_remove_first'), $this->intime), config('httpcodes.bad_request'));
            }

            if(in_array(loggedInUserType(), [1,2]) && $dltTemplateGroup->user_id != auth()->id())
            {
                return response()->json(prepareResult(true, [], trans('translate.unauthorized_to_perform_operation'), $this->intime), config('httpcodes.bad_request'));
            }

            $dltTemplateGroup->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
