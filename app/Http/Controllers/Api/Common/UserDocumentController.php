<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class UserDocumentController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:upload-documents');
    }

    public function index(Request $request)
    {
        try {
            $query = UserDocument::with('user:id,name')->orderBy('id', 'DESC');
            $query->where(function($q) {
                $q->where('is_share_to_parent', 1)
                ->orWhere('user_id', auth()->id());
            });
            if(!empty($request->title))
            {
                $query->where('title', 'LIKE', '%'.$request->title.'%');
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
            'title'    => 'required',
            'file_path' => 'required',
            'is_share_to_parent' => 'required:in:0,1'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $userDocument = new UserDocument;
            $userDocument->user_id  = auth()->id();
            $userDocument->title  = $request->title;
            $userDocument->document  = $request->file_path;
            $userDocument->is_share_to_parent  = $request->is_share_to_parent;
            $userDocument->save();
            DB::commit();
            $userDocument['user'] = $userDocument->user()->select('id', 'name')->first();
            return response()->json(prepareResult(false, $userDocument, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(UserDocument $userDocument)
    {
        try {
            if(file_exists($userDocument->document))
            {
                unlink($userDocument->document);
            }
            $userDocument->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
