<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class NotificationTemplateController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:admin-manage-campaign-status');
    }

    public function index(Request $request)
    {
        try {
            $query = NotificationTemplate::orderBy('id', 'DESC');

            if(!empty($request->notification_for))
            {
                $query->where('notification_for', 'LIKE', '%'.$request->notification_for.'%');
            }

            if(!empty($request->mail_subject))
            {
                $query->where('mail_subject', 'LIKE', '%'.$request->mail_subject.'%');
            }

            if(!empty($request->is_deletable) && $request->is_deletable=='no')
            {
                $query->where('is_deletable', 0);
            }
            elseif(!empty($request->is_deletable))
            {
                $query->where('is_deletable', $request->is_deletable);
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
            'notification_for' => 'required|unique:notification_templates,notification_for',
            'mail_subject' => 'required_without:notification_subject',
            'mail_body' => 'required_with:mail_subject',
            'notification_subject' => 'required_without:mail_subject',
            'notification_body' => 'required_with:notification_subject',
            'status_code' => 'required|in:success,info,danger,error,warning',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $notificationTemplate = new NotificationTemplate;
            $notificationTemplate->notification_for = $request->notification_for;
            $notificationTemplate->mail_subject = $request->mail_subject;
            $notificationTemplate->mail_body = $request->mail_body;
            $notificationTemplate->notification_subject = $request->notification_subject;
            $notificationTemplate->notification_body = $request->notification_body;
            $notificationTemplate->custom_attributes = $request->custom_attributes;
            $notificationTemplate->save_to_database = $request->save_to_database;
            $notificationTemplate->status_code = $request->status_code;
            $notificationTemplate->route_path = $request->route_path;
            $notificationTemplate->is_deletable = 1;
            $notificationTemplate->save();
            DB::commit();
            return response()->json(prepareResult(false, $notificationTemplate, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(NotificationTemplate $notificationTemplate, Request $request)
    {
        try {
            if($request->other_function)
            {
                return $notificationTemplate;
            }
            return response()->json(prepareResult(false, $notificationTemplate, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate)
    {
        $validation = \Validator::make($request->all(), [
            'notification_for' => 'required|unique:notification_templates,notification_for,'.$notificationTemplate->id,
            'mail_subject' => 'required_without:notification_subject',
            'mail_body' => 'required_with:mail_subject',
            'notification_subject' => 'required_without:mail_subject',
            'notification_body' => 'required_with:notification_subject',
            'status_code' => 'required|in:success,info,danger,error,warning',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();

        try {
            $notificationTemplate->notification_for = $request->notification_for;
            $notificationTemplate->mail_subject = $request->mail_subject;
            $notificationTemplate->mail_body = $request->mail_body;
            $notificationTemplate->notification_subject = $request->notification_subject;
            $notificationTemplate->notification_body = $request->notification_body;
            $notificationTemplate->custom_attributes = $request->custom_attributes;
            $notificationTemplate->save_to_database = $request->save_to_database;
            $notificationTemplate->status_code = $request->status_code;
            $notificationTemplate->route_path = $request->route_path;
            $notificationTemplate->is_deletable = 1;
            $notificationTemplate->save();
            DB::commit();

            $request = new Request();
            $request->other_function = true;
            $data = $this->show($notificationTemplate, $request);

            return response()->json(prepareResult(false, $data, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $deleted = NotificationTemplate::where('id', $id)
                ->where('is_deletable', 1)
                ->delete();
            if($deleted)
            {
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success')); 
            }
            return response()->json(prepareResult(true, [], trans('translate.you_cant_delete_this_record'), $this->intime), config('httpcodes.unauthorized')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
