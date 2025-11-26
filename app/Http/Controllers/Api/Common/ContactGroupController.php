<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class ContactGroupController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:phone-book-list');
        $this->middleware('permission:phone-book-edit', ['only' => ['update']]);
        $this->middleware('permission:phone-book-view', ['only' => ['show']]);
        $this->middleware('permission:phone-book-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = ContactGroup::select('id','user_id','group_name','description')
                ->with('user:id,name')
                ->withCount('contactNumbers')
                ->orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
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

    public function show(ContactGroup $contactGroup, Request $request)
    {
        try {
            $contactGroup = ContactGroup::select('id','user_id','group_name','description')
            ->with('user:id,name')
            ->withCount('contactNumbers')
            ->find($contactGroup->id);
            $contactGroup['contact_numbers'] = $contactGroup->contactNumbers()->select('id', 'number')->get();
            $contactGroup['contact_numbers_count'] = count($contactGroup['contact_numbers']);
            if($request->other_function)
            {
                return $contactGroup;
            }
            return response()->json(prepareResult(false, $contactGroup, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, ContactGroup $contactGroup)
    {
        $validation = \Validator::make($request->all(), [
            'group_name'      => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            $contactGroup->group_name = $request->group_name;
            $contactGroup->description = $request->description;
            $contactGroup->save();

            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($contactGroup, $request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(ContactGroup $contactGroup)
    {
        try {
            $contactGroup->contactNumbers->each->delete();
            $contactGroup->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
