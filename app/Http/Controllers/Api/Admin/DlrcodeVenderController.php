<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DlrcodeVender;
use App\Imports\DlrcodeVenderImport;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;

class DlrcodeVenderController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:dlrcode-list');
        $this->middleware('permission:dlrcode-create', ['only' => ['store', 'dlrcodeVenderImport']]);
        $this->middleware('permission:dlrcode-edit', ['only' => ['update']]);
        $this->middleware('permission:dlrcode-view', ['only' => ['show']]);
        $this->middleware('permission:dlrcode-delete', ['only' => ['destroy']]);
        $this->middleware('permission:dlrcode-action', ['only' => ['dlrcodeAction']]);
    }

    public function index(Request $request)
    {
        try {
            $query = DlrcodeVender::with('primaryRoute:id,route_name')
                ->orderBy('id', 'DESC');

            if(!empty($request->search))
            {
                $search = $request->search;
                /*$query->where(function ($q) use ($search) {
                    $q->where('dlr_code', 'LIKE', '%' . $search. '%');
                });*/
                $query->where('dlr_code', 'LIKE', '%'.$request->search.'%');
            }

            if(!empty($request->primary_route_id))
            {
                $query->where('primary_route_id', $request->primary_route_id);
            }

            if(!empty($request->dlr_code))
            {
                $query->where('dlr_code', 'LIKE', '%'.$request->dlr_code.'%');
            }

            if(!empty($request->dlr_code_exact))
            {
                $query->where('dlr_code', $request->dlr_code);
            }

            if(!empty($request->is_refund_applicable) && $request->is_refund_applicable=='no')
            {
                $query->where('is_refund_applicable', 0);
            }
            elseif(!empty($request->is_refund_applicable))
            {
                $query->where('is_refund_applicable', $request->is_refund_applicable);
            }

            if(!empty($request->is_retry_applicable) && $request->is_retry_applicable=='no')
            {
                $query->where('is_retry_applicable', 0);
            }
            elseif(!empty($request->is_retry_applicable))
            {
                $query->where('is_retry_applicable', $request->is_retry_applicable);
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
            'dlr_code'    => 'required',
            'description' => 'required',
            'primary_route_id'  => 'required|exists:primary_routes,id',
            'is_refund_applicable'  => 'required|in:0,1',
            'is_retry_applicable'  => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = DlrcodeVender::where('primary_route_id', $request->primary_route_id)->where('dlr_code', $request->dlr_code)->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $dlrcodeVender = new DlrcodeVender;
            $dlrcodeVender->primary_route_id = $request->primary_route_id;
            $dlrcodeVender->dlr_code = $request->dlr_code;
            $dlrcodeVender->description = $request->description;
            $dlrcodeVender->is_refund_applicable = $request->is_refund_applicable;
            $dlrcodeVender->is_retry_applicable = $request->is_retry_applicable;
            $dlrcodeVender->save();
            DB::commit();
            $dlrcodeVender['primary_route'] = $dlrcodeVender->primaryRoute()->select('id', 'route_name')->first();
            return response()->json(prepareResult(false, $dlrcodeVender, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id, Request $request)
    {
        try {
            $dlrcodeVender = DlrcodeVender::with('primaryRoute:id,route_name')->find($id);
            if(!$dlrcodeVender)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            return response()->json(prepareResult(false, $dlrcodeVender, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, $id)
    {
        $validation = \Validator::make($request->all(), [
            'dlr_code'    => 'required',
            'description' => 'required',
            'primary_route_id'  => 'required|exists:primary_routes,id',
            'is_refund_applicable'  => 'required|in:0,1',
            'is_retry_applicable'  => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $dlrcodeVender = DlrcodeVender::find($id);
            if(!$dlrcodeVender)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            $dlrcodeVender->primary_route_id = $request->primary_route_id;
            $dlrcodeVender->dlr_code = $request->dlr_code;
            $dlrcodeVender->description = $request->description;
            $dlrcodeVender->is_refund_applicable = $request->is_refund_applicable;
            $dlrcodeVender->is_retry_applicable = $request->is_retry_applicable;
            $dlrcodeVender->save();
            DB::commit();

            $dlrcodeVender['primary_route'] = $dlrcodeVender->primaryRoute()->select('id', 'route_name')->first();

            return response()->json(prepareResult(false, $dlrcodeVender, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $dlrcodeVender = DlrcodeVender::find($id);
            if(!$dlrcodeVender)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            $dlrcodeVender->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function dlrcodeAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'status'     => 'required|in:0,1',
            'dlrcode_vender_ids'     => 'required|array|min:1',
            'action'     => 'required|in:refund_applicable,retry_applicable',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            if($request->action=='refund_applicable')
            {
                $dlrcodeVender = DlrcodeVender::whereIn('id', $request->dlrcode_vender_ids)
                    ->update([
                        'is_refund_applicable' => $request->status
                    ]);
            }
            elseif($request->action=='retry_applicable')
            {
                $dlrcodeVender = DlrcodeVender::whereIn('id', $request->dlrcode_vender_ids)
                    ->update([
                        'is_retry_applicable' => $request->status
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

    public function dlrcodeVenderImport(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'primary_route_id'  => 'required|exists:primary_routes,id',
            'file_path'     => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(!file_exists($request->file_path)) {
            return response()->json(prepareResult(true, [], trans('translate.file_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            $dlrcodes = Excel::toArray(new DlrcodeVenderImport, $request->file_path);
            foreach ($dlrcodes[0] as $key => $dlrcode) 
            {
                if(!empty($dlrcode['code']))
                {
                    $code = (strlen($dlrcode['code'])<3) ? sprintf('%03d', $dlrcode['code']) : $dlrcode['code'];
                    $checkDuplicate = DlrcodeVender::where('primary_route_id', $request->primary_route_id)->where('dlr_code', $code)->first();
                    if(!$checkDuplicate)
                    {
                        $dlrcodeVender = new DlrcodeVender;
                        $dlrcodeVender->primary_route_id = $request->primary_route_id;
                        $dlrcodeVender->dlr_code = $code;
                        $dlrcodeVender->description = $dlrcode['description'];
                        $dlrcodeVender->is_refund_applicable = $dlrcode['is_refund_applicable'];
                        $dlrcodeVender->is_retry_applicable = $dlrcode['is_retry_applicable'];
                        $dlrcodeVender->save();
                    }
                }
            }
            DB::commit();

            $request = new Request();
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $request->other_function = true;
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
