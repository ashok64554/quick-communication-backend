<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppChatBot;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Auth;
use DB;
use Exception;

class WAChatBotController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-chatbots');
        $this->middleware('permission:whatsapp-chatbot-create', ['only' => ['store']]);
        $this->middleware('permission:whatsapp-chatbot-edit', ['only' => ['update']]);
        $this->middleware('permission:whatsapp-chatbot-view', ['only' => ['show']]);
        $this->middleware('permission:whatsapp-chatbot-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = WhatsAppChatBot::select('whats_app_chat_bots.*','users.id as user_id', 'users.name')
            ->orderBy('whats_app_chat_bots.id', 'DESC')
            ->join('users', 'whats_app_chat_bots.user_id', 'users.id')
            ->whereNull('users.deleted_at');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_chat_bots.user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_chat_bots.chatbot_name', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_chat_bots.user_id', $request->user_id);
            }

            if(!empty($request->whats_app_configuration_id))
            {
                $query->where('whats_app_chat_bots.whats_app_configuration_id', $request->whats_app_configuration_id);
            }

            if(!empty($request->whats_app_template_id))
            {
                $query->where('whats_app_chat_bots.whats_app_template_id', $request->whats_app_template_id);
            }

            if(!empty($request->matching_criteria))
            {
                $query->where('whats_app_chat_bots.matching_criteria', $request->matching_criteria);
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
            'user_id' => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',
            'whats_app_template_id' => 'nullable|exists:whats_app_templates,id',
            'chatbot_name' => 'required|string',
            'matching_criteria' => 'required|in:exact,contain',
            'start_with' => 'required|string',
            'request_payload' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {

            $user_id = $request->user_id;
            if(in_array(loggedInUserType(), [1,2]))
            {
                $user_id =  auth()->id();
            }

            $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
            if(!$wa_config)
            {
                return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
            }

            if(!empty($request->whats_app_template_id))
            {
                $wa_template = \DB::table('whats_app_templates')
                    ->where('whats_app_configuration_id', $request->configuration_id)
                    ->find($request->whats_app_template_id);
                if(!$wa_template)
                {
                    return response()->json(prepareResult(true, [], trans('translate.whatsapp_template_not_assigned_to_you'), $this->intime), config('httpcodes.bad_request'));
                }
            }

            $checkExist = \DB::table('whats_app_chat_bots')
                ->where('whats_app_configuration_id', $request->configuration_id)
                ->where('start_with', 'LIKE', '%'.$request->start_with.'%')
                ->first();
            if($checkExist)
            {
                return response()->json(prepareResult(true, [], trans('translate.chatbot_keyword_already_found'), $this->intime), config('httpcodes.bad_request'));
            }

            $automation_flow = generateAutomationFlow($request->request_payload);
            if(!$automation_flow)
            {
                return response()->json(prepareResult(true, [], trans('translate.invalid_request_payload'), $this->intime), config('httpcodes.bad_request'));
            }

            $wa_chatbot = new WhatsAppChatBot;
            $wa_chatbot->user_id  = $user_id;
            $wa_chatbot->whats_app_configuration_id  = $wa_config->id;
            $wa_chatbot->display_phone_number_req  = $wa_config->display_phone_number_req;
            $wa_chatbot->matching_criteria  = $request->matching_criteria;
            $wa_chatbot->chatbot_name  = $request->chatbot_name;
            $wa_chatbot->start_with  = $request->start_with;
            $wa_chatbot->request_payload  = $request->request_payload;
            $wa_chatbot->automation_flow  = $automation_flow;
            $wa_chatbot->save();
            DB::commit();

            return response()->json(prepareResult(false, $wa_chatbot, trans('translate.created'), $this->intime), config('httpcodes.created'));

        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }

    }

    public function show($id)
    {
        try {
            $wa_chatbot = WhatsAppChatBot::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $wa_chatbot->where('user_id', auth()->id());
            }
            $wa_chatbot = $wa_chatbot->where('id', $id)->first();
            if($wa_chatbot)
            {
                $userInfo = $wa_chatbot->user()->select('id', 'name')->first();
                $wa_chatbot['name'] = $userInfo->name;
                $wa_chatbot['user_id'] = $userInfo->id;

                return response()->json(prepareResult(false, $wa_chatbot, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, $id)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',
            'chatbot_name'    => 'required|string',
            'matching_criteria' => 'required|in:exact,contain',
            'start_with'    => 'required|string',
            'request_payload'    => 'required',                                                                  
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $user_id = $request->user_id;
        if(in_array(loggedInUserType(), [1,2]))
        {
            $user_id =  auth()->id();
        }

        $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
        if(!$wa_config)
        {
            return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        $wa_chatbot = WhatsAppChatBot::where('id', $id)->where('user_id', $user_id)->first();

        if(!$wa_chatbot)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        $checkExist = \DB::table('whats_app_chat_bots')
            ->where('whats_app_configuration_id', $request->configuration_id)
            ->where('start_with', 'LIKE', '%'.$request->start_with.'%')
            ->where('id', '!=', $id)
            ->first();
        if($checkExist)
        {
            return response()->json(prepareResult(true, [], trans('translate.chatbot_keyword_already_found'), $this->intime), config('httpcodes.bad_request'));
        }

        $automation_flow = generateAutomationFlow($request->request_payload);
        if(!$automation_flow)
        {
            return response()->json(prepareResult(true, [], trans('translate.invalid_request_payload'), $this->intime), config('httpcodes.bad_request'));
        }

        // return $automation_flow;
        $user_id = $request->user_id;
        if(in_array(loggedInUserType(), [1,2]))
        {
            $user_id =  auth()->id();
        }

        DB::beginTransaction();
        try {
            $wa_chatbot->user_id  = $user_id;
            $wa_chatbot->whats_app_configuration_id  = $wa_config->id;
            $wa_chatbot->display_phone_number_req  = $wa_config->display_phone_number_req;
            $wa_chatbot->chatbot_name  = $request->chatbot_name;
            $wa_chatbot->matching_criteria  = $request->matching_criteria;
            $wa_chatbot->start_with  = $request->start_with;
            $wa_chatbot->request_payload  = $request->request_payload;
            $wa_chatbot->automation_flow  = $automation_flow;
            $wa_chatbot->save();
            DB::commit();

            $userInfo = $wa_chatbot->user()->select('id', 'name')->first();
            $wa_chatbot['name'] = $userInfo->name;
            $wa_chatbot['user_id'] = $userInfo->id;
            return response()->json(prepareResult(false, $wa_chatbot, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $wa_chatbot = WhatsAppChatBot::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $wa_chatbot->where('user_id', auth()->id());
            }
            $wa_chatbot = $wa_chatbot->first();
            if($wa_chatbot)
            { 
                $wa_chatbot->delete();
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
