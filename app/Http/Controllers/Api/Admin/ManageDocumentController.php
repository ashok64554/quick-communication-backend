<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class ManageDocumentController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:app-setting');
    }

    public function index(Request $request)
    {
        try {
            $query = Document::orderBy('id', 'DESC');

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code_lang', 'LIKE', '%' . $search. '%')
                        ->orWhere('title', 'LIKE', '%'.$search.'%');
                });
            }

            if(!empty($request->code_lang))
            {
                $query->where('code_lang', 'LIKE', '%'.$request->code_lang.'%');
            }

            if(!empty($request->title))
            {
                $query->where('title', 'LIKE', '%'.$request->title.'%');
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
            'code_lang'    => 'required',
            'title'    => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $document = new Document;
            $document->code_lang  = $request->code_lang;
            $document->title  = $request->title;
            $document->slug  = strtolower(Str::slug($request->title));
            $document->api_information  = $request->api_information;
            $document->api_code  = $request->api_code;
            $document->response_description  = $request->response_description;
            $document->api_response  = $request->api_response;
            $document->video_link  = $request->video_link;
            $document->image  = $request->image;
            $document->save();
            DB::commit();

            return response()->json(prepareResult(false, $document, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(Document $document)
    {
        try {
            return response()->json(prepareResult(false, $document, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, Document $document)
    {
        $validation = \Validator::make($request->all(), [
            'code_lang'    => 'required',
            'title'    => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            
            $document->code_lang  = $request->code_lang;
            $document->title  = $request->title;
            $document->slug  = strtolower(Str::slug($request->title));
            $document->api_information  = $request->api_information;
            $document->api_code  = $request->api_code;
            $document->response_description  = $request->response_description;
            $document->api_response  = $request->api_response;
            $document->video_link  = $request->video_link;
            $document->image  = $request->image;
            $document->save();
            DB::commit();

            return response()->json(prepareResult(false, $document, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(Document $document)
    {
        try {
            $document->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
