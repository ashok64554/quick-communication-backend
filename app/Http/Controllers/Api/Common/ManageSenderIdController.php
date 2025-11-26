<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\ManageSenderId;
use App\Models\DltTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class ManageSenderIdController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:sender-id-list');
        $this->middleware('permission:sender-id-create', ['only' => ['store', 'dlrcodeVenderImport']]);
        $this->middleware('permission:sender-id-edit', ['only' => ['update']]);
        $this->middleware('permission:sender-id-delete', ['only' => ['destroy']]);
        $this->middleware('permission:sender-id-action', ['only' => ['senderidAction']]);
    }

    public function index(Request $request)
    {
        try {
            $query = ManageSenderId::with('user:id,name')->orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                if(!empty($request->user_id))
                {
                    $query->where(function ($q) {
                        $q->where('user_id', auth()->id())
                            ->orWhere('parent_id', auth()->id());
                    });
                }
                else
                {
                    $query->where('user_id', auth()->id());
                }
            }
            
            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('manage_sender_ids.company_name', 'LIKE', '%' . $search. '%')
                    ->orWhere('manage_sender_ids.entity_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('manage_sender_ids.sender_id', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->company_name))
            {
                $query->where('company_name', 'LIKE', '%'.$request->company_name.'%');
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
            }

            if(!empty($request->entity_id))
            {
                $query->where('entity_id', 'LIKE', '%'.$request->entity_id.'%');
            }

            if(!empty($request->header_id))
            {
                $query->where('header_id', 'LIKE', '%'.$request->header_id.'%');
            }

            if(!empty($request->sender_id))
            {
                $query->where('sender_id', 'LIKE', '%'.$request->sender_id.'%');
            }

            if(!empty($request->sender_id_type))
            {
                $query->where('sender_id_type', $request->sender_id_type);
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
            'user_id'    => 'required|exists:users,id',
            'company_name'    => 'required',
            'entity_id'    => 'required',
            'header_id'    => 'required',
            'sender_id'    => 'required|min:6',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = ManageSenderId::where('user_id', $request->user_id)->where('sender_id', $request->sender_id)->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $manageSenderId = new ManageSenderId;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $manageSenderId->parent_id  = User::find($request->user_id)->parent_id;
            }
            $manageSenderId->user_id  = $request->user_id;
            $manageSenderId->company_name  = $request->company_name;
            $manageSenderId->entity_id  = $request->entity_id;
            $manageSenderId->header_id  = $request->header_id;
            $manageSenderId->sender_id  = $request->sender_id;
            $manageSenderId->sender_id_type  = !empty($request->sender_id_type) ? $request->sender_id_type : 1;
            $manageSenderId->status  = 1;
            $manageSenderId->save();
            DB::commit();
            $manageSenderId['user'] = $manageSenderId->user()->select('id', 'name')->first();
            return response()->json(prepareResult(false, $manageSenderId, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, ManageSenderId $manageSenderId)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'company_name'    => 'required',
            'entity_id'    => 'required',
            'header_id'    => 'required',
            'sender_id'    => 'required|min:6',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $manageSenderId->user_id  = $request->user_id;
            $manageSenderId->company_name  = $request->company_name;
            $manageSenderId->entity_id  = $request->entity_id;
            $manageSenderId->header_id  = $request->header_id;
            $manageSenderId->sender_id  = $request->sender_id;
            $manageSenderId->sender_id_type  = !empty($request->sender_id_type) ? $request->sender_id_type : $manageSenderId->sender_id_type;
            $manageSenderId->status  = 1;
            $manageSenderId->save();
            DB::commit();

            $manageSenderId['user'] = $manageSenderId->user()->select('id', 'name')->first();
            return response()->json(prepareResult(false, $manageSenderId, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(ManageSenderId $manageSenderId)
    {
        try {
            $checkConnectedTemplate = DltTemplate::where('manage_sender_id', $manageSenderId->id)->count();
            if($checkConnectedTemplate>0)
            {
                return response()->json(prepareResult(true, [], trans('translate.dlt_templates_are_connected_to_this_sender_id_remove_first'), $this->intime), config('httpcodes.bad_request'));
            }
            $manageSenderId->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function senderidAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'status'      => 'required|in:0,1',
            'manage_sender_ids'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $manageSenderId = ManageSenderId::whereIn('id', $request->manage_sender_ids)
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

    public function assignSenderId(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_ids'  => 'required|array|exists:users,id|min:1',
            'sender_ids'=> 'required|array|exists:manage_sender_ids,id|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            foreach ($request->user_ids as $key => $user_id) 
            {
                foreach ($request->sender_ids as $keyN => $sender_id) 
                {
                    $senderId = ManageSenderId::find($sender_id);
                    if(ManageSenderId::where('user_id', $user_id)->where('id', $sender_id)->count()<1)
                    {
                        $assign = $senderId->replicate();
                        $assign->user_id = $user_id;
                        $assign->save();
                    }
                }
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
}
