<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DltTemplate;
use App\Models\ManageSenderId;
use App\Imports\DltTemplateImport;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;

class DltTemplateController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:dlt-template-list');
        $this->middleware('permission:dlt-template-create', ['only' => ['store', 'dltTemplatesImport']]);
        $this->middleware('permission:dlt-template-edit', ['only' => ['update']]);
        $this->middleware('permission:dlt-template-view', ['only' => ['show']]);
        $this->middleware('permission:dlt-template-delete', ['only' => ['destroy']]);
        $this->middleware('permission:dlt-template-action', ['only' => ['dltTemplatesAssignToUsers']]);
    }

    public function index(Request $request)
    {
        try {
            $sender_id_type = !empty($request->sender_id_type) ? array($request->sender_id_type) : array(1,2);
            $query = \DB::table('dlt_templates')
            ->select('dlt_templates.*','users.id as user_id', 'users.name')
            ->orderBy('dlt_templates.id', 'DESC')
            ->join('users', 'dlt_templates.user_id', 'users.id')
            ->join('manage_sender_ids', 'dlt_templates.manage_sender_id', 'manage_sender_ids.id')
            ->whereNull('users.deleted_at')
            ->whereIn('manage_sender_ids.sender_id_type', $sender_id_type);

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('dlt_templates.user_id', auth()->id());
            }

            /*if(in_array(loggedInUserType(), [1]))
            {
                $query->where('dlt_templates.parent_id', auth()->user()->parent_id);
            }*/

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('dlt_templates.template_name', 'LIKE', '%' . $search. '%')
                    ->orWhere('dlt_templates.dlt_template_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('dlt_templates.dlt_message', 'LIKE', '%' . $search. '%')
                    ->orWhere('dlt_templates.sender_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('users.name', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('dlt_templates.user_id', $request->user_id);
            }

            if(!empty($request->dlt_template_group_id))
            {
                $query->where('dlt_templates.dlt_template_group_id', $request->dlt_template_group_id);
            }

            if(!empty($request->manage_sender_id))
            {
                $query->where('dlt_templates.manage_sender_id', $request->manage_sender_id);
            }

            if(!empty($request->template_name))
            {
                $query->where('dlt_templates.template_name', 'LIKE', '%'.$request->template_name.'%');
            }

            if(!empty($request->dlt_template_id))
            {
                $query->where('dlt_templates.dlt_template_id', 'LIKE', '%'.$request->dlt_template_id.'%');
            }

            if(!empty($request->sender_id))
            {
                $query->where('dlt_templates.sender_id', 'LIKE', '%'.$request->sender_id.'%');
            }

            if(!empty($request->header_id))
            {
                $query->where('dlt_templates.header_id', 'LIKE', '%'.$request->header_id.'%');
            }

            if(!empty($request->priority) && $request->priority!="0")
            {
                $query->where('dlt_templates.priority', $request->priority);
            }
            elseif($request->priority=="0")
            {
                $query->where('dlt_templates.priority', '0');
            }

            if(!empty($request->is_unicode) && $request->is_unicode=='no')
            {
                $query->where('dlt_templates.is_unicode', 0);
            }
            elseif(!empty($request->is_unicode))
            {
                $query->where('dlt_templates.is_unicode', $request->is_unicode);
            }

            if(!empty($request->dlt_message))
            {
                $query->where('dlt_templates.dlt_message', 'LIKE', '%'.$request->dlt_message.'%');
            }

            if(!empty($request->status) && $request->status=='no')
            {
                $query->where('dlt_templates.status', 0);
            }
            elseif(!empty($request->status))
            {
                $query->where('dlt_templates.status', $request->status);
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                if(in_array(auth()->user()->userType, [1,2]))
                {
                    $result = $result;
                }

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
            'user_id'    => 'required|exists:users,id',
            'manage_sender_id'    => 'required|exists:manage_sender_ids,id',
            'template_name'    => 'required',
            'dlt_template_id'    => 'required',
            'entity_id'    => 'required',
            'sender_id'    => 'required|min:6',
            'header_id'    => 'required',
            'is_unicode'   => 'required|in:0,1',
            'dlt_message'  => 'required',
            'status'    => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = DltTemplate::where('user_id', $request->user_id)
            ->where('dlt_template_id', $request->dlt_template_id)
            ->where('manage_sender_id', $request->manage_sender_id)
            ->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $dlt_template = new DltTemplate;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $dlt_template->parent_id  = User::find($request->user_id)->parent_id;
            }

            $dlt_template->user_id  = $request->user_id;
            $dlt_template->manage_sender_id  = $request->manage_sender_id;
            $dlt_template->dlt_template_group_id  = $request->dlt_template_group_id;
            $dlt_template->template_name  = $request->template_name;
            $dlt_template->dlt_template_id  = $request->dlt_template_id;
            $dlt_template->entity_id  = $request->entity_id;
            $dlt_template->sender_id  = $request->sender_id;
            $dlt_template->header_id  = $request->header_id;
            $dlt_template->is_unicode  = $request->is_unicode;
            $dlt_template->dlt_message  = $request->dlt_message;
            $dlt_template->status  = $request->status;
            $dlt_template->save();
            DB::commit();

            $userInfo = $dlt_template->user()->select('id', 'name')->first();
            $dlt_template['name'] = $userInfo->name;
            $dlt_template['user_id'] = $userInfo->id;

            return response()->json(prepareResult(false, $dlt_template, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(DltTemplate $dltTemplate)
    {
        try {
            $dltTemplate['user'] = $dltTemplate->user()->select('id', 'name')->first();
            return response()->json(prepareResult(false, $dltTemplate, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, DltTemplate $dltTemplate)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'manage_sender_id'    => 'required|exists:manage_sender_ids,id',
            'template_name'    => 'required',
            'dlt_template_id'    => 'required',
            'entity_id'    => 'required',
            'sender_id'    => 'required|min:6',
            'header_id'    => 'required',
            'is_unicode'   => 'required|in:0,1',
            'dlt_message'  => 'required',
            'status'    => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $dltTemplate->user_id  = $request->user_id;
            $dltTemplate->manage_sender_id  = $request->manage_sender_id;
            $dltTemplate->dlt_template_group_id  = $request->dlt_template_group_id;
            $dltTemplate->template_name  = $request->template_name;
            $dltTemplate->dlt_template_id  = $request->dlt_template_id;
            $dltTemplate->entity_id  = $request->entity_id;
            $dltTemplate->sender_id  = $request->sender_id;
            $dltTemplate->header_id  = $request->header_id;
            $dltTemplate->is_unicode  = $request->is_unicode;
            $dltTemplate->dlt_message  = $request->dlt_message;
            $dltTemplate->status  = $request->status;
            $dltTemplate->save();
            DB::commit();

            $userInfo = $dltTemplate->user()->select('id', 'name')->first();
            $dltTemplate['name'] = $userInfo->name;
            $dltTemplate['user_id'] = $userInfo->id;
            return response()->json(prepareResult(false, $dltTemplate, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(DltTemplate $dltTemplate)
    {
        try {
            $dltTemplate->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function dltTemplatesImport(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'file_path'     => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [0,3]))
        {
            $validation = \Validator::make($request->all(), [
                'user_id'     => 'required|exists:users,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        if(!file_exists($request->file_path)) {
            return response()->json(prepareResult(true, [], trans('translate.file_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        DB::beginTransaction();

        try {
            $dlt_templates = Excel::toArray(new DltTemplateImport, $request->file_path);
            $message = '';
            foreach ($dlt_templates[0] as $key => $dlt_template) 
            {
                if(empty($dlt_template['entity_id']) ||
                empty($dlt_template['header_id']) ||
                empty($dlt_template['sender_id']) ||
                empty($dlt_template['template_name']) ||
                empty($dlt_template['dlt_message']) ||
                empty($dlt_template['dlt_template_id']))
                {
                    $message .= 'Validation Error. Some column are empty.'.'<br>';
                    continue;
                }

                if(strlen($dlt_template['sender_id'])==6)
                {
                    $checkSenderExist = ManageSenderId::where('user_id', auth()->id())
                        ->where('entity_id', preg_replace("/[^0-9]/","",$dlt_template['entity_id']))
                        ->where('header_id', preg_replace("/[^0-9]/","",$dlt_template['header_id']))
                        ->where('sender_id', strtoupper($dlt_template['sender_id']))
                        ->first();
                    if($checkSenderExist)
                    {
                        $sender_id = $checkSenderExist->id;
                    }
                    else
                    {
                        $createSender = new ManageSenderId;
                        if(in_array(loggedInUserType(), [0,3]))
                        {
                            $userInfo = User::find($request->user_id);
                            $createSender->parent_id  = $userInfo->parent_id;
                            $createSender->user_id   = $userInfo->id;
                            $createSender->company_name = $userInfo->companyName;
                        }
                        else
                        {
                            $createSender->user_id   = auth()->id();
                            $createSender->company_name = auth()->user()->companyName;
                        }
                        
                        $createSender->entity_id = preg_replace("/[^0-9]/","",$dlt_template['entity_id']);
                        $createSender->header_id = preg_replace("/[^0-9]/","",$dlt_template['header_id']);
                        $createSender->sender_id = strtoupper($dlt_template['sender_id']);
                        $createSender->sender_id_type = ($dlt_template['sender_id_type']=='Transactional') ? 1 : 2;
                        $createSender->status = '1';
                        $createSender->save();
                        $sender_id = $createSender->id;
                    }
                    if(!empty($sender_id))
                    {
                        $checkDLTTempExist = DltTemplate::where('user_id', auth()->id())
                        ->where('dlt_template_id', preg_replace("/[^0-9]/","",$dlt_template['dlt_template_id']))
                        ->where('sender_id', strtoupper($dlt_template['sender_id']))
                        ->count();
                        if($checkDLTTempExist<1)
                        {
                            $dltTemp = new DltTemplate;

                            if(in_array(loggedInUserType(), [0,3]))
                            {
                                $dltTemp->parent_id  = $userInfo->parent_id;
                                $dltTemp->user_id   = $userInfo->id;
                            }
                            else
                            {
                                $dltTemp->user_id  = auth()->id();
                            }

                            $dltTemp->manage_sender_id  = $sender_id;
                            $dltTemp->template_name = trim($dlt_template['template_name']);
                            $dltTemp->entity_id         = preg_replace("/[^0-9]/","",$dlt_template['entity_id']);
                            $dltTemp->sender_id         = strtoupper($dlt_template['sender_id']);
                            $dltTemp->header_id         = preg_replace("/[^0-9]/","",$dlt_template['header_id']);
                            $dltTemp->is_unicode        = $dlt_template['is_unicode'];
                            $dltTemp->dlt_message       = str_replace('x000D', "", trim($dlt_template['dlt_message']));
                            $dltTemp->dlt_template_id   = preg_replace("/[^0-9]/","",$dlt_template['dlt_template_id']);
                            $dltTemp->status            = 1;
                            $dltTemp->save();
                        }
                        else
                        {
                            $message .= 'DLT template already registered. DLT Template ID: <strong>'. $dlt_template['dlt_template_id'].'</strong><br>';
                        }
                    }
                }
                else
                {
                    $message .= 'Incorrect sender Id: <strong>'. $dlt_template['sender_id'].'</strong><br>';
                }
            }
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);
            $data['message'] = $message;

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function dltTemplatesAssignToUsers(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_ids'    => 'required|array|exists:users,id|min:1',
            'dlt_template_ids'    => 'required|array|exists:dlt_templates,id|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            foreach ($request->user_ids as $key => $user_id) 
            {
                foreach ($request->dlt_template_ids as $keyN => $dlt_template_id) 
                {
                    $dltTemplate = DltTemplate::find($dlt_template_id);
                    if(DltTemplate::where('user_id', $user_id)->where('dlt_template_id', $dltTemplate->dlt_template_id)->count()<1)
                    {
                        $assign = $dltTemplate->replicate();
                        $assign->user_id = $user_id;
                        $assign->save();
                    }
                }
            }
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.assigned'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function dltTemplatesAssignToGroup(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'dlt_template_group_id'    => 'required|exists:dlt_template_groups,id',
            'dlt_template_ids'    => 'required|array|exists:dlt_templates,id|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $getDltTemplates = DltTemplate::whereIn('id', $request->dlt_template_ids)->update([
                'dlt_template_group_id' => $request->dlt_template_group_id
            ]);
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.assigned'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
