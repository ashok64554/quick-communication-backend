<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use Illuminate\Http\Request;
use App\Imports\BlacklistImport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Excel;

class BlacklistController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:blacklist');
    }

    public function index(Request $request)
    {
        try {
            $query = Blacklist::select('id','mobile_number')
                ->orderBy('id', 'DESC');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $query->where('mobile_number', 'LIKE', '%'.$request->search.'%');
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
            'mobile_numbers' => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            foreach($request->mobile_numbers as $key => $number)
            {
                if(!empty($number))
                {
                    $checkDuplicate = Blacklist::where('mobile_number', $number)->first();
                    if(!$checkDuplicate)
                    {
                        $blacklist = new Blacklist;
                        if(in_array(loggedInUserType(), [0,3]))
                        {
                            $blacklist->parent_id  = 1;
                        }
                        $blacklist->user_id = auth()->id();
                        $blacklist->mobile_number = $number;
                        $blacklist->save();
                    }
                }
            }
            DB::commit();

            $request = new Request();
            $request->per_page_record = env('DEFAULT_PAGING', 25);
            $request->other_function = true;
            $data = $this->index($request);

            return response()->json(prepareResult(false, $data, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(Blacklist $blacklist)
    {
        try {
            $blacklist->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function blacklistAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'blacklists_id'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $blacklist = Blacklist::whereIn('id', $request->blacklists_id)->delete();

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

    public function blacklistsImport(Request $request)
    {
        $validation = \Validator::make($request->all(), [
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
            $numbers = Excel::toArray(new BlacklistImport, $request->file_path);
            foreach ($numbers[0] as $key => $number) 
            {
                if(!empty($number['number']))
                {
                    $checkDuplicate = Blacklist::where('mobile_number', $number['number'])->first();
                    if(!$checkDuplicate)
                    {
                        $contactNumber = new Blacklist;
                        if(in_array(loggedInUserType(), [0,3]))
                        {
                            $contactNumber->parent_id  = 1;
                        }
                        $contactNumber->user_id = auth()->id();
                        $contactNumber->mobile_number = $number['number'];
                        $contactNumber->save();
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
