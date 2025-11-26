<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\ContactNumber;
use App\Imports\ContactNumberImport;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;

class ContactNumberController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:phone-book-list');
        $this->middleware('permission:phone-book-create', ['only' => ['store','contactNumbersImport']]);
        $this->middleware('permission:phone-book-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = ContactNumber::select('id','number')
                ->orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->number))
            {
                $query->where('number', 'LIKE', '%'.$request->number.'%');
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
            'group_name' => 'required',
            'numbers' => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            if(ContactGroup::where('group_name', $request->group_name)->count()>0)
            {
                $contactGroup = ContactGroup::where('group_name', $request->group_name)->first();
            }
            else
            {
                $contactGroup = new ContactGroup;
                if(in_array(loggedInUserType(), [0,3]))
                {
                    $contactGroup->parent_id  = 1;
                }
                $contactGroup->user_id = auth()->id();
                $contactGroup->group_name = $request->group_name;
                $contactGroup->description = $request->description;
                $contactGroup->save();
            }

            foreach($request->numbers as $key => $number)
            {
                $checkDuplicate = ContactNumber::where('contact_group_id', $contactGroup->id)
                    ->where('number', $number)->first();
                if(!$checkDuplicate)
                {
                    $contactNumber = new ContactNumber;
                    if(in_array(loggedInUserType(), [0,3]))
                    {
                        $contactNumber->parent_id  = 1;
                    }
                    $contactNumber->user_id = auth()->id();
                    $contactNumber->contact_group_id = $contactGroup->id;
                    $contactNumber->number = $number;
                    $contactNumber->save();
                }
            }
            DB::commit();
            $contactGroup['contact_numbers'] = $contactGroup->contactNumbers()->select('id', 'number')->get();
            return response()->json(prepareResult(false, $contactGroup, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(ContactNumber $contactNumber)
    {
        try {
            $contactNumber->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function contactNumbersImport(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'group_name'    => 'required',
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
            if(ContactGroup::where('group_name', $request->group_name)->count()>0)
            {
                $contactGroup = ContactGroup::where('group_name', $request->group_name)->first();
            }
            else
            {
                $contactGroup = new ContactGroup;
                if(in_array(loggedInUserType(), [0,3]))
                {
                    $contactGroup->parent_id  = 1;
                }
                $contactGroup->user_id = auth()->id();
                $contactGroup->group_name = $request->group_name;
                $contactGroup->description = $request->description;
                $contactGroup->save();
            }

            $numbers = Excel::toArray(new ContactNumberImport, $request->file_path);
            foreach ($numbers[0] as $key => $number) 
            {
                if(!empty($number['number']))
                {
                    $checkDuplicate = ContactNumber::where('contact_group_id', $contactGroup->id)
                    ->where('number', $number['number'])->first();
                    if(!$checkDuplicate)
                    {
                        $contactNumber = new ContactNumber;
                        if(in_array(loggedInUserType(), [0,3]))
                        {
                            $contactNumber->parent_id  = 1;
                        }
                        $contactNumber->user_id = auth()->id();
                        $contactNumber->contact_group_id = $contactGroup->id;
                        $contactNumber->number = $number['number'];
                        $contactNumber->save();
                    }
                }
            }
            DB::commit();
            $contactGroup['contact_numbers'] = $contactGroup->contactNumbers()->select('id', 'number')->get();
            return response()->json(prepareResult(false, $contactGroup, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
