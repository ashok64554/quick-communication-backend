<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Country;
use App\Models\ShortLink;
use App\Models\TwoWayCommInterest;
use App\Models\TwoWayCommFeedback;
use App\Models\TwoWayCommRating;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Validator;
use Log;
use DB;
use DateTime;
use Str;
use Illuminate\Support\Facades\RateLimiter;

class DocumentController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }

    public function index(Request $request)
    {
        try {
            $query = Document::select('code_lang')
                ->groupBy('code_lang')
                ->orderBy('code_lang', 'ASC');

            if(!empty($request->with_content) && $request->with_content == 1)
            {
                $query->with('getLangWise');
            }

            if(!empty($request->code_lang))
            {
                $query->where('code_lang', 'LIKE', '%'.$request->code_lang.'%');
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

    public function countries(Request $request)
    {
        try {
            $query = Country::select('id','iso','name', 'name as nicename','currency_code', 'phonecode', 'min', 'max')
                ->orderBy('name', 'ASC');
                
            if(!empty($request->name))
            {
                $query->where('name', 'LIKE', '%'.$request->name.'%');
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

    public function getWebContent($sub_part, $token, $mobile_num)
    {
        try {
            $query = ShortLink::select('short_links.id as short_link_id',
                'two_way_comms.id as two_way_comm_id',
                'two_way_comms.redirect_url', 
                'two_way_comms.title', 
                'two_way_comms.content', 
                'two_way_comms.bg_color', 
                'two_way_comms.content_expired', 
                'two_way_comms.take_response')
                ->join('two_way_comms', 'short_links.two_way_comm_id', 'two_way_comms.id')
                ->where('short_links.sub_part', $sub_part)
                ->where('short_links.token', $token)
                ->where('short_links.mobile_num', $mobile_num)
                ->first();
            if($query)
            {
                return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function takeResponse(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'two_way_comm_id'    => 'required',
            'short_link_id'    => 'required',
            'mobile_num'    => 'required',
            'sub_part'    => 'required',
            'token'    => 'required',
            'response_from'    => 'required|in:1,2,3',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->response_from==1)
        {
            // Interested form
            $validation = \Validator::make($request->all(), [
                'is_interest'    => 'required|in:0,1',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }
        elseif($request->response_from==2)
        {
            // Feedback form
            $validation = \Validator::make($request->all(), [
                'name'    => 'required',
                'email'   => 'required|email',
                'subject' => 'required',
                'comment' => 'required',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }
        elseif($request->response_from==3)
        {
            // Rating form
            $validation = \Validator::make($request->all(), [
                'rating'    => 'required|integer|between:1,5',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        try {
            $checkRecord = ShortLink::select('id','link_expired', 'send_sms_id')
                ->where('two_way_comm_id', $request->two_way_comm_id)
                ->where('sub_part', $request->sub_part)
                ->where('token', $request->token)
                ->where('id', $request->short_link_id)
                ->where('mobile_num', $request->mobile_num)
                ->first();
            if(!$checkRecord)
            {
                return response()->json(prepareResult(true, trans('translate.no_records_found'), trans('translate.no_records_found'), $this->intime), config('httpcodes.not_found'));
            }

            if(!empty($checkRecord->link_expired) && strtotime($checkRecord->link_expired) < time())
            {
                return response()->json(prepareResult(true, trans('translate.content_link_expired'), trans('translate.content_link_expired'), $this->intime), config('httpcodes.not_found'));
            }

            if($request->response_from==1)
            {
                $checkEntry = \DB::table(env('DB_DATABASE2W').'.two_way_comm_interests')
                    ->where('short_link_id', $request->short_link_id)
                    ->count();
                if($checkEntry>0)
                {
                    return response()->json(prepareResult(true, trans('translate.response_already_submitted'), trans('translate.response_already_submitted'), $this->intime), config('httpcodes.bad_request'));
                }

                // Interest form
                $interest = new TwoWayCommInterest;
                $interest->two_way_comm_id = $request->two_way_comm_id;
                $interest->short_link_id = $request->short_link_id;
                $interest->send_sms_id = $checkRecord->send_sms_id;
                $interest->mobile = $request->mobile_num;
                $interest->is_interest = $request->is_interest;
                $interest->ip = $request->ip();
                $interest->save();
                $data = $interest;
            }
            elseif($request->response_from==2)
            {
                $checkEntry = \DB::table(env('DB_DATABASE2W').'.two_way_comm_feedbacks')
                    ->where('short_link_id', $request->short_link_id)
                    ->count();
                if($checkEntry>0)
                {
                    return response()->json(prepareResult(true, trans('translate.response_already_submitted'), trans('translate.response_already_submitted'), $this->intime), config('httpcodes.bad_request'));
                }

                // Feedback form
                $feedback = new TwoWayCommFeedback;
                $feedback->two_way_comm_id = $request->two_way_comm_id;
                $feedback->short_link_id = $request->short_link_id;
                $feedback->send_sms_id = $checkRecord->send_sms_id;
                $feedback->name = $request->name;
                $feedback->mobile = $request->mobile_num;
                $feedback->email = $request->email;
                $feedback->subject = $request->subject;
                $feedback->comment = $request->comment;
                $feedback->ip = $request->ip();
                $feedback->save();
                $data = $feedback;
            }
            else
            {
                $checkEntry = \DB::table(env('DB_DATABASE2W').'.two_way_comm_ratings')
                    ->where('short_link_id', $request->short_link_id)
                    ->count();
                if($checkEntry>0)
                {
                    return response()->json(prepareResult(true, trans('translate.response_already_submitted'), trans('translate.response_already_submitted'), $this->intime), config('httpcodes.bad_request'));
                }
                
                // Rating form
                $rating = new TwoWayCommRating;
                $rating->two_way_comm_id = $request->two_way_comm_id;
                $rating->short_link_id = $request->short_link_id;
                $rating->send_sms_id = $checkRecord->send_sms_id;
                $rating->rating = $request->rating;
                $rating->mobile = $request->mobile_num;
                $rating->ip = $request->ip();
                $rating->save();
                $data = $rating;
            }
            return response()->json(prepareResult(false, $data, trans('translate.request_successfully_submitted'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function throttleKey($data)
    {
        return \Str::lower($data);
    }

    public function generateVerifyCode(Request $request)
    {
        $removeBlank = ContactUs::whereNull('name')->whereDate('created_at', '<', date('Y-m-d'))->delete();

        if (RateLimiter::tooManyAttempts(request()->ip(), env('THROTTLE_ALLOW_ATTEMPTS', 5))) 
        {
            $seconds = RateLimiter::availableIn($this->throttleKey(request()->ip()));

            $returnError  = [
                "account_locked"=> true, 
                "time" => $seconds
            ];
            return response()->json(prepareResult(true, $returnError, trans('translate.too_many_attempts_system_lock_for_next_3_hours'), $this->intime), config('httpcodes.unauthorized'));
        }

        $validation = \Validator::make($request->all(), [
            'email'  => 'required|exists:users,email',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $contactus = ContactUs::where('ip_address', request()->ip())
                ->whereNull('name')
                ->first();
            if(!$contactus) 
            {
                $contactus = new ContactUs;
                $contactus->verify_code = rand(1000,9999);
                $contactus->email = $request->email;
            }
            $contactus->ip_address = request()->ip();
            $contactus->save();
            DB::commit();
            RateLimiter::hit(request()->ip(), (3600*3));
            return response()->json(prepareResult(false, [], trans('translate.check_mobile_app_or_notification_for_code'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function saveContactUs(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'verify_code'    => 'required|numeric',
            'name' => 'required|string',
            'email'  => 'required|exists:users,email',
            'mobile'  => 'required|numeric',
            'message'  => 'required|string',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $contactus = ContactUs::where('verify_code', $request->verify_code)
            ->where('ip_address', request()->ip())
            ->whereNull('name')
            ->first();
        if(!$contactus) 
        {
            return response()->json(prepareResult(true, [], trans('translate.code_not_matched'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $contactus->name = $request->name;
            $contactus->mobile = $request->mobile;
            $contactus->message = $request->message;
            $contactus->save();
            DB::commit();

            return response()->json(prepareResult(false, $contactus, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
