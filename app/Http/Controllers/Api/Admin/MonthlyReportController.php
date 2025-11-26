<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use App\Models\UserWiseMonthlyReport;
use Auth;
use DB;
use Exception;

class MonthlyReportController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:admin-manage-campaign-status');
    }

    public function index(Request $request)
    {
        try
        {
            $query =  UserWiseMonthlyReport::orderBy('id','DESC')->with('user:id,name');

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('group_name', 'LIKE', '%' . $search. '%')
                        ->orWhere('month', 'LIKE', '%' . $search. '%')
                        ->orWhere('year', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->group_name))
            {
                $query->where('group_name', $request->group_name);
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
            }

            if(!empty($request->month))
            {
                $query->where('month', $request->month);
            }

            if(!empty($request->year))
            {
                $query->where('year', $request->year);
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
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'month'    => 'required|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'year'    => 'required|numeric|digits:4',
            'submissions' => 'required|array|min:1',
            'submissions.*.mobile_count_submission'    => 'required|numeric|min:0',
            'submissions.*.sms_count_delivered'    => 'required|numeric|min:0',
            'submissions.*.sms_count_failed'    => 'required|numeric|min:0',
            'submissions.*.sms_count_rejected'    => 'required|numeric|min:0',
            'submissions.*.sms_count_invalid'    => 'required|numeric|min:0',

            'submissions.*.mobile_count_submission'    => 'required|numeric|min:0',
            'submissions.*.mobile_count_delivered'    => 'required|numeric|min:0',
            'submissions.*.mobile_count_failed'    => 'required|numeric|min:0',
            'submissions.*.mobile_count_rejected'    => 'required|numeric|min:0',
            'submissions.*.mobile_count_invalid'    => 'required|numeric|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        DB::beginTransaction();
        try {
            foreach ($request->submissions as $key => $submission) 
            {
                $checkDuplicate = UserWiseMonthlyReport::where('user_id', $request->user_id)
                ->where('group_name', $submission['group_name'])
                ->where('month', $request->month)
                ->where('year', $request->year)
                ->first();
                if($checkDuplicate)
                {
                    $userWiseMontlyReport = $checkDuplicate;
                }
                else
                {
                    $userWiseMontlyReport = new UserWiseMonthlyReport;
                }

                $userWiseMontlyReport->user_id = $request->user_id;
                $userWiseMontlyReport->group_name = $submission['group_name'];
                $userWiseMontlyReport->month = $request->month;
                $userWiseMontlyReport->year = $request->year;
                $userWiseMontlyReport->sms_count_submission = $submission['sms_count_submission'];
                $userWiseMontlyReport->sms_count_delivered = $submission['sms_count_delivered'];
                $userWiseMontlyReport->sms_count_failed = $submission['sms_count_failed'];
                $userWiseMontlyReport->sms_count_rejected = $submission['sms_count_rejected'];
                $userWiseMontlyReport->sms_count_invalid = $submission['sms_count_invalid'];

                $userWiseMontlyReport->mobile_count_submission = $submission['mobile_count_submission'];
                $userWiseMontlyReport->mobile_count_delivered = $submission['mobile_count_delivered'];
                $userWiseMontlyReport->mobile_count_failed = $submission['mobile_count_failed'];
                $userWiseMontlyReport->mobile_count_rejected = $submission['mobile_count_rejected'];
                $userWiseMontlyReport->mobile_count_invalid = $submission['mobile_count_invalid'];
                $userWiseMontlyReport->save();
            }
            DB::commit();
            
            return response()->json(prepareResult(false, [], trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, $id)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'month'    => 'required|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'year'    => 'required|numeric|digits:4',
            'sms_count_submission'    => 'required|numeric|min:0',
            'sms_count_delivered'    => 'required|numeric|min:0',
            'sms_count_failed'    => 'required|numeric|min:0',
            'sms_count_rejected'    => 'required|numeric|min:0',
            'sms_count_invalid'    => 'required|numeric|min:0',
            'mobile_count_submission'    => 'required|numeric|min:0',
            'mobile_count_delivered'    => 'required|numeric|min:0',
            'mobile_count_failed'    => 'required|numeric|min:0',
            'mobile_count_rejected'    => 'required|numeric|min:0',
            'mobile_count_invalid'    => 'required|numeric|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = UserWiseMonthlyReport::where('user_id', $request->user_id)
            ->where('group_name', $request->group_name)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->where('id', '!=', $id)
            ->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $userWiseMontlyReport = UserWiseMonthlyReport::find($id);
            $userWiseMontlyReport->user_id = $request->user_id;
            $userWiseMontlyReport->group_name = $request->group_name;
            $userWiseMontlyReport->month = $request->month;
            $userWiseMontlyReport->year = $request->year;
            $userWiseMontlyReport->sms_count_submission = $request->sms_count_submission;
            $userWiseMontlyReport->sms_count_delivered = $request->sms_count_delivered;
            $userWiseMontlyReport->sms_count_failed = $request->sms_count_failed;
            $userWiseMontlyReport->sms_count_rejected = $request->sms_count_rejected;
            $userWiseMontlyReport->sms_count_invalid = $request->sms_count_invalid;

            $userWiseMontlyReport->mobile_count_submission = $request->mobile_count_submission;
            $userWiseMontlyReport->mobile_count_delivered = $request->mobile_count_delivered;
            $userWiseMontlyReport->mobile_count_failed = $request->mobile_count_failed;
            $userWiseMontlyReport->mobile_count_rejected = $request->mobile_count_rejected;
            $userWiseMontlyReport->mobile_count_invalid = $request->mobile_count_invalid;
            $userWiseMontlyReport->save();
            DB::commit();
            $userWiseMontlyReport['user'] = $userWiseMontlyReport->user()->select('id', 'name')->first();
            return response()->json(prepareResult(false, $userWiseMontlyReport, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
