<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateButton;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WATemplateController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
       // $this->middleware('permission:whatsapp-template-list');
       // $this->middleware('permission:whatsapp-template-create', ['only' => ['store', 'waPullTemplate','waPullAllTemplate', 'waGeneratePayload']]);
       // $this->middleware('permission:whatsapp-template-view', ['only' => ['show']]);
       // $this->middleware('permission:whatsapp-template-edit', ['only' => ['update']]);
       // $this->middleware('permission:whatsapp-template-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = WhatsAppTemplate::select('whats_app_templates.*','users.id as user_id', 'users.name','whats_app_configurations.verified_name')
            ->orderBy('whats_app_templates.id', 'DESC')
            ->join('users', 'whats_app_templates.user_id', 'users.id')
            ->join('whats_app_configurations', 'whats_app_templates.whats_app_configuration_id', 'whats_app_configurations.id')
            ->with('whatsAppTemplateButtons', 'whatsAppConfiguration:id,display_phone_number_req')
            ->whereNull('users.deleted_at');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_templates.user_id', auth()->id());
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_templates.user_id', $request->user_id);
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_templates.category', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.template_name', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.wa_template_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.template_language', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_templates.tags', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->wa_configuration_id))
            {
                $query->where('whats_app_templates.whats_app_configuration_id', $request->wa_configuration_id);
            }

            if(!empty($request->category))
            {
                $query->where('whats_app_templates.category', $request->category);
            }

            if(!empty($request->template_type))
            {
                $query->where('whats_app_templates.template_type', $request->template_type);
            }

            if(!empty($request->wa_status))
            {
                $query->where('whats_app_templates.wa_status', $request->wa_status);
            }

            if(!empty($request->wa_template_id))
            {
                $query->where('whats_app_templates.wa_template_id', 'LIKE', '%'.$request->wa_template_id.'%');
            }

            if(!empty($request->template_name))
            {
                $query->where('whats_app_templates.template_name',  'LIKE', '%'.$request->template_name.'%');
            }

            if(!empty($request->template_language))
            {
                $query->where('whats_app_templates.template_language', 'LIKE', '%'.$request->template_language.'%');
            }

            if(!empty($request->tags))
            {
                $query->where('whats_app_templates.tags', 'LIKE', '%'.$request->tags.'%');
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
            'user_id'    => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',
            'category'    => 'required|in:Marketing,Utility,Authentication',
            'template_name'    => 'required',
            'template_language'    => 'required',
            'template_type'    => 'required|in:None,Text,Media',
        ]);
        $media_type  ='Text';
        if($request->template_type=='Media'){
            $media_type  = $request->media_type;
            $validation = \Validator::make($request->all(), [
                'media_type'    => 'required|in:IMAGE,DOCUMENT,VIDEO,LOCATION',
            
            ]);
            
        }

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $checkDuplicate = WhatsAppTemplate::where('user_id', $request->user_id)
                ->where('template_name', $request->template_name)
                ->where('category', $request->category)
                ->where('template_language', $request->template_language)
                ->first();
            if($checkDuplicate) 
            {
                return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
            }

            $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());

            $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
            if(!$wa_config)
            {
                return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
            }

            $accessToken = base64_decode($wa_config->access_token);
            $waba_id = $wa_config->waba_id;
            $app_version = $wa_config->app_version;
            $buttons = [];
            $components = [];
            $header_part = [];
            $body_part = [];

            \Log::channel('whatsapp_template')->info('WA template request payload');
            \Log::channel('whatsapp_template')->info(json_encode($request->all()));

            if($request->category == 'Authentication')
            {
                $body[] = [
                    'type' => 'body',
                    'add_security_recommendation' => $request->add_security_recommendation
                ];

                $footer[] = [
                    'type' => 'footer',
                    'code_expiration_minutes' => $request->code_expiration_minutes
                ];

                $buttons[] = [
                    'type' => 'buttons',
                    'buttons' => $request->template_buttons
                ];

                $array_merge = array_merge($body, $footer, $buttons);

                // Remove null values from the components array
                $components = array_filter($array_merge);

                // Prepare the final request data
                $requestData = [
                    "name" => $request->template_name,
                    "language" => $request->template_language,
                    "category" => strtoupper($request->category),
                    "message_send_ttl_seconds" => $request->message_send_ttl_seconds,
                    "components" => $components,
                ];
            }
            else
            {
                // Check if there are buttons and handle accordingly
                if (is_array($request->template_buttons)) 
                {
                    foreach ($request->template_buttons as $btn) 
                    {
                        $buttonData = [
                            "type" => $btn['button_type'],
                            "text" => $btn['button_text'],
                        ];

                        if($request->button_action == '0') 
                        {
                            $button_variables = '';
                            if(!empty(@$btn['button_variables']))
                            {
                                $button_variables = explode(',', @$btn['button_variables']);
                                $button_variables = array_map('trim', @$button_variables);
                            }

                            $button_val_name = (!empty(@$btn['button_val_name']) ? @$btn['button_val_name'] : strtolower(@$btn['button_type']));
                            if($button_val_name=='quick_reply')
                            {
                                $buttonData = [
                                    "type" => $btn['button_type'],
                                    "text" => $btn['button_text']
                                ];
                            }
                            else
                            {
                                $buttonData = [
                                    "type" => $btn['button_type'],
                                    "text" => $btn['button_text'],
                                    $button_val_name => @$btn['button_value']
                                ];
                            }

                            
                           
                            if(!empty($button_variables))
                            {
                                 $buttonData["example"] = $button_variables;
                            }
                        }

                        $buttons[] = $buttonData;
                    }
                }

                // ****************
                // Header
                $header_part = [];
                $headerVariable = [];
                $bodyVariable = [];
                if($request->template_type!='None')
                {
                    if(!empty($request->header_text))
                    {
                        $header_text = $request->header_text;
                        preg_match_all('/{{\s*(\w+)\s*}}/', $header_text, $matches);
                        $headerVariableNames = $matches[1];

                        // compare
                        if(is_array($request->header_variable))
                        {
                            $payloadHeadParams = array_column($request->header_variable, 'param_name');
                            $missingHeader = array_diff($headerVariableNames, $payloadHeadParams);
                            $missingHeadFormatted = array_map(fn($var) => "{{{$var}}}", $missingHeader);
                            $missingHeaderString = implode(', ', $missingHeadFormatted);
                            if(!empty($missingHeadFormatted)) {
                                return response()->json(prepareResult(true, [], trans('translate.wa_variable_is_missing_in_example')." $missingHeaderString", $this->intime), config('httpcodes.bad_request'));
                            }
                        }
                        else
                        {
                            $placeholderCount = count(array_unique($headerVariableNames));
                            $headerVariables = (!empty($request->header_variable) ? array_map('trim', explode(',', $request->header_variable)) : 0 );
                            $headerVariableCount = (!empty($request->header_variable) ? count($headerVariables) : 0);
                            if($headerVariableCount!=$placeholderCount) {
                                return response()->json(prepareResult(true, [], trans('translate.wa_positional_variable_is_missing_in_example'), $this->intime), config('httpcodes.bad_request'));
                            }
                        }

                        $wrappedHeaderVariables = array_map(fn($var) => '{{' . $var . '}}', $headerVariableNames);
                        if(sizeof($wrappedHeaderVariables)>0)
                        {
                            $headerVariable[] = $wrappedHeaderVariables;
                        }
                    }
                    
                    $headerComponent = [
                        "type" => "HEADER",
                        "format" => strtoupper($media_type),
                    ];

                    // Add HEADER component based on template_type
                    if($request->parameter_format=='NAMED')
                    {
                        if($request->template_type == 'Text')
                        {
                            if($media_type == 'Text') 
                            {
                                $headerComponent["text"] = $request->header_text;
                                if(!empty($request->header_variable))
                                {
                                    $headerComponent["example"] = [
                                        "header_text_named_params" => $request->header_variable
                                    ];
                                }
                            }
                        }
                    }
                    else
                    {
                        $header_variable =  explode(',', $request->header_variable);
                        $header_variable = array_map('trim', $header_variable);

                        if($media_type == 'Text') 
                        {
                            $headerComponent["text"] = $request->header_text;
                            if(!empty($request->header_variable))
                            {
                                $headerComponent["example"] = [
                                    "header_text" => $header_variable
                                ];
                            }
                        } 
                    }

                    // Only for media type 
                    if($request->template_type == 'Media') 
                    {
                        //$header_variable =  explode(',', @$request->header_variable);
                        //$header_variable = array_map('trim', $header_variable);

                        if ($media_type == 'IMAGE' || $media_type == 'DOCUMENT' || $media_type == 'VIDEO') 
                        {
                            $headerComponent["example"] = [
                                "header_handle" => [$request->header_handle]
                            ];
                        }
                    }
                    $header_part[] = $headerComponent;
                }

                //return $headerComponent;
                // End Header
                // ****************

                // ****************
                // Body
                $body_text = $request->message;
                preg_match_all('/{{\s*(\w+)\s*}}/', $body_text, $matches);
                $bodyVariableNames = $matches[1];

                if(is_array($request->message_variable))
                {
                    // compare
                    $payloadBodyParams = array_column($request->message_variable, 'param_name');
                    $missingBody = array_diff($bodyVariableNames, $payloadBodyParams);
                    $missingBodyFormatted = array_map(fn($var) => "{{{$var}}}", $missingBody);
                    $missingBodyString = implode(', ', $missingBodyFormatted);
                    if(!empty($missingBodyFormatted)) {
                        return response()->json(prepareResult(true, [], trans('translate.wa_variable_is_missing_in_example')." $missingBodyString", $this->intime), config('httpcodes.bad_request'));
                    }
                }
                else
                {
                    $placeholderCount = count(array_unique($bodyVariableNames));
                    $bodyVariables = (!empty($request->message_variable) ? array_map('trim', explode(',', $request->message_variable)) : 0);
                    $bodyVariableCount = (!empty($request->message_variable) ? count($bodyVariables) : 0);
                    if($bodyVariableCount!=$placeholderCount) {
                        return response()->json(prepareResult(true, [], trans('translate.wa_positional_variable_is_missing_in_example'), $this->intime), config('httpcodes.bad_request'));
                    }
                }
                

                $wrappedBodyVariables = array_map(fn($var) => '{{' . $var . '}}', $bodyVariableNames);
                if(sizeof($wrappedBodyVariables)>0)
                {
                    $bodyVariable[] = $wrappedBodyVariables;
                }

                $bodyComponent = [
                    "type" => "BODY",
                    "text" => $request->message,
                ];
                // Construct the components array
                if($request->parameter_format=='NAMED')
                {
                    if(!empty($request->message_variable))
                    {
                        $bodyComponent["example"] = [
                            "body_text_named_params" => $request->message_variable
                        ];
                    } 
                }
                else
                {
                    if(!empty($request->message_variable))
                    {
                        $message_variable =  explode(',', $request->message_variable);
                        $message_variable = array_map('trim', $message_variable);

                        $bodyComponent["example"] = [
                            "body_text" => [$message_variable]
                        ];
                    }
                }

                // End Body
                // ****************

                $button_part = [];
                $footer_part = [];
                $body_part[] = $bodyComponent;

                if(!empty($request->footer_text))
                {
                    $footer_part = [
                        [
                            "type" => "FOOTER",
                            "text" => $request->footer_text,
                        ]
                    ];
                }

                if(sizeof($buttons)>0)
                {
                    $button_part = [
                        [
                            "type" => "BUTTONS",
                            "buttons" => $buttons,
                        ],
                    ];
                }

                $array_merge = array_merge($header_part, $body_part);
                $array_merge_footer = array_merge($footer_part, $button_part);
                $array_merge_all = array_merge($array_merge, $array_merge_footer);

                // Remove null values from the components array
                $components = array_filter($array_merge_all);

                // Prepare the final request data
                $requestData = [
                    "name" => $request->template_name,
                    "language" => $request->template_language,
                    "category" => strtoupper($request->category),
                    "parameter_format" => strtoupper($request->parameter_format),
                    "components" => $components,
                ];
            }

            \Log::channel('whatsapp_template')->info('Payload ready for submit whatsapp');
            \Log::channel('whatsapp_template')->info(json_encode($requestData));
            //return $requestData;

            // Make the API call
            $client = new Client();
            $response = $client->post("https://graph.facebook.com/{$app_version}/{$waba_id}/message_templates", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $requestData,
            ]);

            $responseData = json_decode($response->getBody(), true);
            \Log::info($responseData);

            if(!empty(@$responseData['status']))
            {
                $status = (($responseData['status']=='APPROVED') ? '1' : '0');

                DB::beginTransaction();

                $headerVariableVal = null;

                if($request->category != 'Authentication')
                {
                    if(sizeof($headerVariable)>0)
                    {
                        $headArr = arrayFlatten($headerVariable);
                        $headerVariableVal = ((sizeof($headArr) > 0) ? json_encode($headArr) : null);
                    }
                }

                $bodyVariableVal = null;
                if($request->category != 'Authentication')
                {
                    if(sizeof($bodyVariable)>0)
                    {
                        $bodyArr = arrayFlatten($bodyVariable);
                        $bodyVariableVal = ((sizeof($bodyArr) > 0) ? json_encode($bodyArr) : null);
                    }
                }


                $wa_app_template = new WhatsAppTemplate;
                $wa_app_template->user_id  = $user_id;
                $wa_app_template->whats_app_configuration_id  = $request->configuration_id;
                $wa_app_template->wa_template_id  = @$responseData['id'];
                if($request->category == 'Authentication')
                {
                    $wa_app_template->parameter_format  = 'POSITIONAL';
                }
                else
                {
                    $wa_app_template->parameter_format  = (!empty($request->parameter_format) ? $request->parameter_format : 'POSITIONAL');
                }
                
                $wa_app_template->category  = ucfirst(strtolower(@$responseData['category']));
                $wa_app_template->marketing_type  = $request->marketing_type;
                $wa_app_template->template_language  = $request->template_language;
                $wa_app_template->template_name  = $request->template_name;
                $wa_app_template->template_type  = $request->template_type;
                $wa_app_template->header_text  = $request->header_text;
                $wa_app_template->header_variable  = $headerVariableVal;
                $wa_app_template->media_type  = $request->media_type;
                $wa_app_template->header_handle  = $request->header_handle;
                $wa_app_template->message  = $request->message;
                $wa_app_template->message_variable  = $bodyVariableVal;
                $wa_app_template->footer_text  = $request->footer_text;
                $wa_app_template->button_action  = $request->button_action;
                $wa_app_template->status  = $status;
                $wa_app_template->wa_status  = @$responseData['status'];
                $wa_app_template->tags  = $request->tags;
                $wa_app_template->save();

                if(is_array(@$request->template_buttons) && count(@$request->template_buttons) > 0 )
                {
                    foreach ($request->template_buttons as $key => $button) 
                    {
                        $wa_app_template_btn = new WhatsAppTemplateButton;
                        $wa_app_template_btn->whats_app_template_id  = $wa_app_template->id;
                        $wa_app_template_btn->url_type  = @$button['url_type'];
                        $wa_app_template_btn->button_val_name  = @$button['button_val_name'];
                        $wa_app_template_btn->button_value  = @$button['button_value'];
                        $wa_app_template_btn->button_variables  = @$button['button_variables'];
                        $wa_app_template_btn->flow_id  = @$button['flow_id'];
                        $wa_app_template_btn->flow_action  = @$button['flow_action'];
                        $wa_app_template_btn->navigate_screen  = @$button['navigate_screen'];

                        if($request->category == 'Authentication')
                        {
                            $wa_app_template_btn->button_type  = strtoupper(@$button['type']);
                            $wa_app_template_btn->button_text  = @$button['text'];
                        }
                        else
                        {
                            $wa_app_template_btn->button_type  = @$button['button_type'];
                            $wa_app_template_btn->button_text  = @$button['button_text'];
                        }
                        $wa_app_template_btn->save();
                    }
                }

                DB::commit();

                /* Get Template information */
                $endoint = "https://graph.facebook.com/{$app_version}/{$waba_id}/message_templates";
                $response = $client->get($endoint, [
                    'query' => [
                        'access_token' => $accessToken,
                        'name' => $request->wa_template_name
                    ],
                ]);
                $responseData = json_decode($response->getBody(), true);
                if(sizeof($responseData['data'])>0)
                {
                    $responseData = $responseData['data'][0];
                    if($request->category == 'Authentication')
                    {
                        $wa_app_template->footer_text  = @$responseData['data'][0]['components'][1]['text'];
                    }
                }

                $wa_app_template->json_response  = json_encode(@$responseData);

                if($request->category == 'Authentication')
                {
                    $wa_app_template->message_variable  = '["{{1}}"]';
                    
                }
                
                $wa_app_template->save();
                /* Get Template information */

                $userInfo = $wa_app_template->user()->select('id', 'name')->first();

                $wa_app_template['name'] = $userInfo->name;
                $wa_app_template['user_id'] = $userInfo->id;

                return response()->json(prepareResult(false, $wa_app_template, trans('translate.created'), $this->intime), config('httpcodes.created'));

            }
            return response()->json(prepareResult(true, [], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
        } catch (RequestException $e) {
            // Handle the error response
            if ($e->hasResponse()) 
            {
                $response = $e->getResponse();
                $errorBody = json_decode($response->getBody(), true);

                // Log the error details
                \Log::error('Guzzle Error: ' . $e->getMessage(), [
                    'status_code' => $response->getStatusCode(),
                    'error_body' => $errorBody,
                ]);

                return response(prepareResult(false, $errorBody,trans('translate.something_went_wrong')), config('httpcodes.internal_server_error'));  
            } 
            else 
            {
                // Handle non-HTTP exception (e.g., connection error)
                \Log::error('Guzzle Error: ' . $e->getMessage());
                return response(prepareResult(false, $e->getMessage(),trans('translate.something_went_wrong')), config('httpcodes.internal_server_error'));  
            } 
        }
    }

    public function waPullTemplate(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',
            'wa_template_id'=> 'required_without:wa_template_name',
            'wa_template_name'=> 'required_without:wa_template_id',
    
        ]);
        
        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        try {

            $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());

            $checkDuplicate = WhatsAppTemplate::where('user_id', $user_id)
                ->where('wa_template_id', $request->wa_template_id)
                ->first();
            
            $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
            if(!$wa_config)
            {
                return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
            }

            $accessToken = base64_decode($wa_config->access_token);
            $waba_id = $wa_config->waba_id;
            $apiVersion = (!empty($wa_config->app_version) ? $wa_config->app_version : env('FB_APP_VERSION')); 
            $wa_template_id = $request->wa_template_id;

            $client = new Client();
            
            if(!empty($wa_template_id))
            {
                $endoint = "https://graph.facebook.com/{$apiVersion}/{$wa_template_id}";
                $response = $client->get($endoint, [
                    'query' => [
                        'access_token' => $accessToken,
                    ],
                ]);

                // Decode the JSON response
                $responseData = json_decode($response->getBody(), true);
            }
            else
            {
                $endoint = "https://graph.facebook.com/{$apiVersion}/{$waba_id}/message_templates";
                $response = $client->get($endoint, [
                    'query' => [
                        'access_token' => $accessToken,
                        'name' => $request->wa_template_name
                    ],
                ]);

                $responseData = json_decode($response->getBody(), true);

                //return $responseData;
                if(sizeof($responseData['data'])>0)
                {
                    $responseData = $responseData['data'][0];
                }
                else
                {
                    return response(prepareResult(false, trans('translate.no_records_found'),trans('translate.no_records_found')), config('httpcodes.not_found'));  
                }
            }

            $status = (($responseData['status']=='APPROVED') ? '1' : '0');

            DB::beginTransaction();

            $header_variable = null;
            $header_handle = null;

            $wa_template_id = @$responseData['id'];

            if(@$responseData['components']['format']=='TEXT')
            {
                $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_text'][0] : [];
                //$header_variable = (!empty($header_text) ?  implode(', ', $header_text) : NULL);

            } else{
                $header_handle_wa[] = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_handle'][0] : @$responseData['components'][1]['example']['header_handle'][0];

                $header_handle = (!empty($header_handle_wa) ? implode(', ', $header_handle_wa) : NULL);
            }

            $wa_app_template = WhatsAppTemplate::where('user_id', $user_id)
                ->where('wa_template_id', $wa_template_id)
                ->where('whats_app_configuration_id', $request->configuration_id)
                ->first();
            if(!$wa_app_template)
            {
                $wa_app_template = new WhatsAppTemplate;
            }

            $headerVariable = [];
            $bodyVariable = [];
            $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['text'] : NULL;

            $template_type = getWATemplateType(@$responseData['components'][0]['format']);

            /* Header Variable */
            $headerVariable = [];
            if($template_type!='none' && !empty($header_text))
            {
                preg_match_all('/{{\s*(\w+)\s*}}/', $header_text, $matches);
                $headerVariableNames = $matches[1];

                $wrappedHeaderVariables = array_map(fn($var) => '{{' . $var . '}}', $headerVariableNames);
                if(sizeof($wrappedHeaderVariables)>0)
                {
                    $headerVariable = $wrappedHeaderVariables;
                }
            }
            /* End Header Variable */

            $body_text = ((@$responseData['components'][0]['type']=='BODY') ? @$responseData['components'][0]['text'] : @$responseData['components'][1]['text']);

            /* Body Variables */
            preg_match_all('/{{\s*(\w+)\s*}}/', $body_text, $matches);
            $bodyVariableNames = $matches[1];

            $wrappedBodyVariables = array_map(fn($var) => '{{' . $var . '}}', $bodyVariableNames);
            $bodyVariable = $wrappedBodyVariables;
            /* End Body Variables */

            $footer_text = ((@$responseData['components'][1]['type']=='FOOTER') ? @$responseData['components'][1]['text'] : ((@$responseData['components'][2]['type']=='FOOTER') ? @$responseData['components'][2]['text'] : NULL));
           
            $wa_app_template->user_id  = $user_id;
            $wa_app_template->whats_app_configuration_id  = $request->configuration_id;
            $wa_app_template->wa_template_id  = @$responseData['id'];
            $wa_app_template->parameter_format  = $responseData['parameter_format'];
            $wa_app_template->category  = $responseData['category'];
            $wa_app_template->sub_category  = @$responseData['sub_category'];
            $wa_app_template->template_language  = $responseData['language'];
            $wa_app_template->template_name  = $responseData['name'];
            $wa_app_template->template_type  = $template_type;
            $wa_app_template->header_text  = $header_text;
            $wa_app_template->header_variable  = (!empty($headerVariable && $headerVariable!='[]') ? json_encode($headerVariable) : null);
            $wa_app_template->media_type  = (!in_array(@$responseData['components'][0]['format'], [null, '', 'none', 'TEXT']) ? @$responseData['components'][0]['format'] : null);
            $wa_app_template->header_handle  = $header_handle;
            $wa_app_template->message  = $body_text;
            $wa_app_template->message_variable  = (is_array($bodyVariable) && sizeof($bodyVariable) ? json_encode($bodyVariable) : null);
            $wa_app_template->footer_text  =  $footer_text;
            $wa_app_template->status  = $status;
            $wa_app_template->wa_status  = @$responseData['status'];
            $wa_app_template->json_response  = json_encode(@$responseData);
            $wa_app_template->save();

            //delete all old records if exists
            WhatsAppTemplateButton::where('whats_app_template_id', $wa_app_template->id)->delete();
            if(@$responseData['components'][1]['type']=='BUTTONS' || @$responseData['components'][2]['type']=='BUTTONS' || @$responseData['components'][3]['type']=='BUTTONS')
            {
                if(!empty(@$responseData['components'][1]['type']) && @$responseData['components'][1]['type']=='BUTTONS')
                {
                    $buttons = @$responseData['components'][1]['buttons'];
                }
                elseif(!empty(@$responseData['components'][2]['type']) && @$responseData['components'][2]['type']=='BUTTONS')
                {
                    $buttons = @$responseData['components'][2]['buttons'];
                }
                else
                {
                    $buttons = @$responseData['components'][3]['buttons'];
                }
                
                if(is_array(@$buttons) && count(@$buttons) > 0)
                {
                    foreach (@$buttons as $key => $button) 
                    {
                        $btn_text = @$button['example'];
                        $button_variables = (!empty($btn_text) ?  implode(', ', $btn_text) : NULL);
                         
                        $button_val_name = '';
                        if(!empty(@$button['phone_number']))
                        {
                            $button_val_name='phone_number';
                        }
                        if(!empty(@$button['url']))
                        {
                            $button_val_name='url';
                        }

                        $wa_app_template_btn = new WhatsAppTemplateButton;
                        $wa_app_template_btn->whats_app_template_id  = $wa_app_template->id;
                        $wa_app_template_btn->button_type  = @$button['type'];
                        $wa_app_template_btn->button_text  = @$button['text'];
                        $wa_app_template_btn->button_val_name  = strtolower(@$button['type']);
                        $wa_app_template_btn->button_value  = ($button_val_name !='' )? $button[$button_val_name]: NULL;
                        $wa_app_template_btn->button_variables  = $button_variables;
                        $wa_app_template_btn->flow_id  = @$button['flow_id'];
                        $wa_app_template_btn->flow_action  = @$button['flow_action'];
                        $wa_app_template_btn->navigate_screen  = @$button['navigate_screen'];
                        $wa_app_template_btn->save();
                        if(@$button['type']=='CATALOG')
                        {
                            $wa_app_template->media_type = 'CATALOG';
                            $wa_app_template->save();
                        }
                    }
                }
            }
            DB::commit();

            $userInfo = $wa_app_template->user()->select('id', 'name')->first();
            
            $wa_app_template['name'] = $userInfo->name;
            $wa_app_template['user_id'] = $userInfo->id;

            return response()->json(prepareResult(false, $wa_app_template, trans('translate.pulled'), $this->intime), config('httpcodes.created'));
        } catch (RequestException $e) {
            // Handle the error response
            if ($e->hasResponse()) 
            {
                $response = $e->getResponse();
                $errorBody = json_decode($response->getBody(), true);

                // Log the error details
                \Log::error('Guzzle Error: ' . $e->getMessage(), [
                    'status_code' => $response->getStatusCode(),
                    'error_body' => $errorBody,
                ]);

                 return response(prepareResult(false, $errorBody,trans('translate.something_went_wrong')), config('httpcodes.bad_request'));  
            } 
            else 
            {
                // Handle non-HTTP exception (e.g., connection error)
                \Log::error('Guzzle Error: ' . $e->getMessage());

                return response(prepareResult(false, $e->getMessage(),trans('translate.something_went_wrong')), config('httpcodes.bad_request'));  
            }
        }
    }

    public function waPullAllTemplate(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',    
        ]);
        
        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }
        try {

            $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());
            $headerVariable = [];
            $bodyVariable = [];

            $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
            if(!$wa_config)
            {
                return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
            }
            $configuration_id = $request->configuration_id;

            //start removing all configure user templates
            $removedAllTemplates = WhatsAppTemplate::where('user_id', $user_id)
                ->where('whats_app_configuration_id', $configuration_id)
                ->pluck('id');

            $removedAllButtonTemplates = WhatsAppTemplateButton::whereIn('whats_app_template_id', $removedAllTemplates)->delete();
            WhatsAppTemplate::where('user_id', $user_id)
                ->where('whats_app_configuration_id', $configuration_id)
                ->delete();
            //end removing all configure user templates

            $accessToken = base64_decode($wa_config->access_token);
            $waba_id = $wa_config->waba_id;
            $apiVersion = (!empty($wa_config->app_version) ? $wa_config->app_version : 'v17.0');

            $client = new Client();
            $endoint = "https://graph.facebook.com/{$apiVersion}/{$waba_id}/message_templates";
            //\Log::info($endoint);
            $response = $client->get($endoint, [
                'query' => [
                    'access_token' => $accessToken,
                    'limit' => env('WA_PAGE_LIMIT', 25)
                ],
            ]);

            // Decode the JSON response
            $allTemplates = json_decode($response->getBody(), true);


            foreach (@$allTemplates['data'] as $key => $responseData) 
            {
                $status = (($responseData['status']=='APPROVED') ? '1' : '0');

                DB::beginTransaction();

                $header_variable = [];
                $header_handle = null;

                if(@$responseData['components']['format']=='TEXT')
                {
                    $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_text'][0] : [];
                    //$header_variable = (!empty($header_text) ?  implode(', ', $header_text) : NULL);

                } else{
                    $header_handle_wa[] = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['example']['header_handle'][0] : @$responseData['components'][1]['example']['header_handle'][0];

                    $header_handle = (!empty($header_handle_wa) ? implode(', ', $header_handle_wa) : NULL);
                }

                $wa_app_template = WhatsAppTemplate::where('user_id', $user_id)
                    ->where('wa_template_id', @$responseData['id'])
                    ->where('whats_app_configuration_id', $configuration_id)
                    ->first();
                if(!$wa_app_template)
                {
                    $wa_app_template = new WhatsAppTemplate;
                }

                $headerVariable = [];
                $bodyVariable = [];
                $header_text = (@$responseData['components'][0]['type']=='HEADER') ? @$responseData['components'][0]['text'] : NULL;

                $template_type = getWATemplateType(@$responseData['components'][0]['format']);

                /* Header Variable */
                $headerVariable = [];
                if($template_type!='none' && !empty($header_text))
                {
                    preg_match_all('/{{\s*(\w+)\s*}}/', $header_text, $matches);
                    $headerVariableNames = $matches[1];

                    $wrappedHeaderVariables = array_map(fn($var) => '{{' . $var . '}}', $headerVariableNames);
                    if(sizeof($wrappedHeaderVariables)>0)
                    {
                        $headerVariable = $wrappedHeaderVariables;
                    }
                }
                /* End Header Variable */

                $body_text = ((@$responseData['components'][0]['type']=='BODY') ? @$responseData['components'][0]['text'] : @$responseData['components'][1]['text']);

                /* Body Variables */
                preg_match_all('/{{\s*(\w+)\s*}}/', $body_text, $matches);
                $bodyVariableNames = $matches[1];

                $wrappedBodyVariables = array_map(fn($var) => '{{' . $var . '}}', $bodyVariableNames);
                $bodyVariable = $wrappedBodyVariables;
                /* End Body Variables */
                

                $footer_text = ((@$responseData['components'][1]['type']=='FOOTER') ? @$responseData['components'][1]['text'] : ((@$responseData['components'][2]['type']=='FOOTER') ? @$responseData['components'][2]['text'] : NULL));
               
                $wa_app_template->user_id  = $user_id;
                $wa_app_template->whats_app_configuration_id  = $request->configuration_id;
                $wa_app_template->wa_template_id  = @$responseData['id'];
                $wa_app_template->parameter_format  = $responseData['parameter_format'];
                $wa_app_template->category  = $responseData['category'];
                $wa_app_template->sub_category  = @$responseData['sub_category'];
                $wa_app_template->template_language  = $responseData['language'];
                $wa_app_template->template_name  = $responseData['name'];
                $wa_app_template->template_type  = $template_type;
                $wa_app_template->header_text  = $header_text;
                $wa_app_template->header_variable  = (!empty($headerVariable && $headerVariable!='[]') ? json_encode($headerVariable) : null);
                $wa_app_template->media_type  = (!in_array(@$responseData['components'][0]['format'], [null, '', 'none', 'TEXT']) ? @$responseData['components'][0]['format'] : null);
                $wa_app_template->header_handle  = $header_handle;
                $wa_app_template->message  = $body_text;
                $wa_app_template->message_variable  = (sizeof($bodyVariable) > 0 ? json_encode($bodyVariable) : null);
                $wa_app_template->footer_text  =  $footer_text;
                $wa_app_template->status  = $status;
                $wa_app_template->wa_status  = @$responseData['status'];
                $wa_app_template->json_response  = json_encode(@$responseData);
                $wa_app_template->save();

                //delete all old records if exists
                WhatsAppTemplateButton::where('whats_app_template_id', $wa_app_template->id)->delete();
                if(@$responseData['components'][1]['type']=='BUTTONS' || @$responseData['components'][2]['type']=='BUTTONS' || @$responseData['components'][3]['type']=='BUTTONS')
                {
                    if(!empty(@$responseData['components'][1]['type']) && @$responseData['components'][1]['type']=='BUTTONS')
                    {
                        $buttons = @$responseData['components'][1]['buttons'];
                    }
                    elseif(!empty(@$responseData['components'][2]['type']) && @$responseData['components'][2]['type']=='BUTTONS')
                    {
                        $buttons = @$responseData['components'][2]['buttons'];
                    }
                    else
                    {
                        $buttons = @$responseData['components'][3]['buttons'];
                    }
                    
                    if(is_array(@$buttons) && count(@$buttons) > 0)
                    {
                        foreach (@$buttons as $key => $button) 
                        {
                            $btn_text = @$button['example'];
                            $button_variables = (!empty($btn_text) ?  implode(', ', $btn_text) : NULL);
                             
                            $button_val_name = '';
                            if(!empty(@$button['phone_number']))
                            {
                                $button_val_name='phone_number';
                            }
                            if(!empty(@$button['url']))
                            {
                                $button_val_name='url';
                            }

                            $wa_app_template_btn = new WhatsAppTemplateButton;
                            $wa_app_template_btn->whats_app_template_id  = $wa_app_template->id;
                            $wa_app_template_btn->button_type  = @$button['type'];
                            $wa_app_template_btn->button_text  = @$button['text'];
                            $wa_app_template_btn->button_val_name  = strtolower(@$button['type']);
                            $wa_app_template_btn->button_value  = ($button_val_name !='' )? $button[$button_val_name]: NULL;
                            $wa_app_template_btn->button_variables  = $button_variables;
                            $wa_app_template_btn->flow_id  = @$button['flow_id'];
                            $wa_app_template_btn->flow_action  = @$button['flow_action'];
                            $wa_app_template_btn->navigate_screen  = @$button['navigate_screen'];
                            $wa_app_template_btn->save();
                            if(@$button['type']=='CATALOG')
                            {
                                $wa_app_template->media_type = 'CATALOG';
                                $wa_app_template->save();
                            }
                        }
                    }
                }
                DB::commit();
            }

            if(array_key_exists('next', $allTemplates['paging']))
            {
                //\Log::info('i m trigger');
                waPullAllTemplatePaging($accessToken, $user_id, $configuration_id, $allTemplates['paging']['next'], $allTemplates['paging']['cursors']['after']);
            }

            return response()->json(prepareResult(false, 'All template synced.', trans('translate.pulled'), $this->intime), config('httpcodes.created'));
        } catch (RequestException $e) {
            // Handle the error response
            if ($e->hasResponse()) 
            {
                $response = $e->getResponse();
                $errorBody = json_decode($response->getBody(), true);

                // Log the error details
                \Log::error('Guzzle Error: ' . $e->getMessage(), [
                    'status_code' => $response->getStatusCode(),
                    'error_body' => $errorBody,
                ]);

                 return response(prepareResult(false, $errorBody,trans('translate.something_went_wrong')), config('httpcodes.bad_request'));  
            } 
            else 
            {
                // Handle non-HTTP exception (e.g., connection error)
                \Log::error('Guzzle Error: ' . $e->getMessage());

                return response(prepareResult(false, $e->getMessage(),trans('translate.something_went_wrong')), config('httpcodes.bad_request'));  
            }
        }
    }

    public function pullMoreTemplates(Request $request, $no_of_records, $cursor)
    {
        if(($no_of_records>0) && ($cursor))
        {
            $request = request();
            $request->merge(['cursor' => $cursor]);
            return $this->waPullAllTemplate($request);
        }
    }

    public function waGeneratePayload(Request $request, $whats_app_template_id, $user_id)
    {
        $whatsapptemplate = WhatsAppTemplate::query();
        if(in_array(loggedInUserType(), [1,2]))
        {
            $whatsapptemplate->where('whats_app_templates.user_id', auth()->id());
        }
        else
        {
            $whatsapptemplate->where('user_id', $user_id);
        }
        $whatsapptemplate = $whatsapptemplate->find($whats_app_template_id);
        if(!$whatsapptemplate)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        try {
            $count = 1;
            $number = '919713753131';
            $country_id = '76';
            $prepareHeader = [];
            $country = \DB::table('countries')->find($country_id);
            $whatsAppTemplate = WhatsAppTemplate::where('user_id', $user_id)->find($whats_app_template_id);
            $currentArrKey = $count;
            $isRatio = false;
            $isFailedRatio = false;
            $upload_wa_file_path = "https://wa-file-path";
            $media_type = strtolower($whatsAppTemplate->media_type);

            $userInfo = User::find($user_id);

            $whatsappButtons = $whatsAppTemplate->whatsAppTemplateButtons;

            $parameter_format = $whatsAppTemplate->parameter_format;
            $header_text = $whatsAppTemplate->header_text;
            preg_match_all('/\{\{(.*?)\}\}/', $header_text, $match_header);
            $header_variables = (!empty($whatsAppTemplate->header_variable) ? json_decode($whatsAppTemplate->header_variable, true) : null);

            $body_text = $whatsAppTemplate->message;
            preg_match_all('/\{\{(.*?)\}\}/', $body_text, $match_body);
            $body_variables = (!empty($whatsAppTemplate->message_variable) ? json_decode($whatsAppTemplate->message_variable, true) : null);

            $footer_text = $whatsAppTemplate->footer_text;
            preg_match_all('/\{\{(.*?)\}\}/', $footer_text, $match_footer);
            $footer_variables = [];

            //check template Type
            $template_type = $whatsAppTemplate->template_type;

            if($request->is_gen_actual_payload)
            {
                $waGeneratePayload = 'prapareWAComponent';
                $waMsgPayload = 'waMsgPayload';
                $prapareWAButtonComponent = 'prapareWAButtonComponent';
            }
            else
            {
                $waGeneratePayload = 'prapareWAComponentSample';
                $waMsgPayload = 'waMsgPayloadSample';
                $prapareWAButtonComponent = 'prapareWAButtonComponentSample';
            }

            if($template_type=='MEDIA')
            {
                $latitude = null;
                $longitude = null;
                $location_name = null;
                $location_address = null;
                if($media_type=='location')
                {
                    $latitude = 'Location-Latitude';
                    $longitude = 'Location-Longitude';
                    $location_name = 'Location-Name';
                    $location_address = 'Location-Address';
                }

                $titleOrFileName = 'File-Caption';
                $header = $waGeneratePayload('header', $upload_wa_file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
            }
            else
            {
                $header = $waGeneratePayload('headerParameterValue', $header_variables, $parameter_format, $match_header[1]);
            }
            
            $body = $waGeneratePayload('bodyParameterValues', $body_variables, $parameter_format, $match_body[1]);

            $footer = $waGeneratePayload('footer', $footer_variables, $parameter_format, $match_footer[0]);

            // Button code needs to implement
            $urlArray = $coupon_code = $catalog_code = $flow_code = null;

            // buttons parameter
            $WhatsAppTemplateButtons = $whatsAppTemplate->whatsAppTemplateButtons;

            $i = 1;
            foreach ($WhatsAppTemplateButtons as $key => $button) 
            {
                $sub_type = $button->button_type;
                if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG','FLOW']))
                {
                    if($sub_type=='URL' && str_contains($button->button_value, '{{1}}'))
                    {
                        $urlArray[] = 'button_url_var_'. $i;
                        $i++;
                    }
                    elseif($sub_type=='COPY_CODE')
                    {
                        $coupon_code[] = 'button_coupon_var_1';
                    }
                    elseif($sub_type=='CATALOG')
                    {
                        $catalog_code[] = 'product_catalog_id_var_1';
                    }
                    elseif($sub_type=='FLOW')
                    {
                        $flow_code[] = 'flow_token_var_1';
                    }
                }
            }

            $buttons = $prapareWAButtonComponent($WhatsAppTemplateButtons, $urlArray, $coupon_code, $catalog_code, $flow_code);

            $obj = array_merge($header, $body, $footer, $buttons);
            if(empty(@$obj['buttons'])) {
                unset($obj['buttons']);
            }

            if (isset($obj[0]) && is_array($obj[0])) {
                $obj = array_merge($obj, $obj[0]);
                unset($obj[0]);
            }

            $messagePayload = $waMsgPayload($number, $whatsAppTemplate->template_name, $whatsAppTemplate->template_language, $obj, $whatsAppTemplate->whatsAppConfiguration->display_phone_number_req,$template_type);

            return response()->json(prepareResult(false, $messagePayload, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        try {
            $wa_app_template = WhatsAppTemplate::query();

            if(in_array(loggedInUserType(), [1,2]))
            {
                $wa_app_template->where('user_id', auth()->id());
            }

            $wa_app_template = $wa_app_template->with('whatsAppTemplateButtons')->find($id);

            if(!$wa_app_template)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }

            $userInfo = $wa_app_template->user()->select('id', 'name')->first();
            $wa_app_template['name'] = $userInfo->name;
            $wa_app_template['user_id'] = $userInfo->id;

            return response()->json(prepareResult(false, $wa_app_template, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, WhatsAppTemplate $wa_app_template)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id' => 'required|exists:whats_app_configurations,id',
            'category'    => 'required|in:Marketing,Utility,Authentication',
            'template_name'    => 'required',
            'template_language'    => 'required',
            'template_type'    => 'required|in:None,Text,Media',
        ]);
        $media_type  ='Text';
        if($request->template_type=='Media'){
            $media_type  = $request->media_type;
            $validation = \Validator::make($request->all(), [
                'media_type'    => 'required|in:IMAGE,DOCUMENT,VIDEO,LOCATION',
            
            ]);
            
        }

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = WhatsAppTemplate::where('user_id', $request->user_id)
            ->where('template_name', $request->template_name)
            ->where('category', $request->category)
            ->where('template_language', $request->template_language)
            ->where('id', '!=', $wa_app_template->id)
            ->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $wa_app_template->user_id  = $request->user_id;
            $wa_app_template->parameter_format  = (!empty($request->parameter_format) ? $request->parameter_format : 'POSITIONAL');
            $wa_app_template->category  = $request->category;
            $wa_app_template->template_language  = $request->template_language;
            $wa_app_template->template_name  = $request->template_name;
            $wa_app_template->template_type  = $request->template_type;
            $wa_app_template->header_text  = $request->header_text;
            $wa_app_template->media_type  = $request->media_type;
            $wa_app_template->media_url  = $request->media_url;
            $wa_app_template->media_file  = $request->media_file;
            $wa_app_template->placeholders  = $request->placeholders;
            $wa_app_template->message  = $request->message;
            $wa_app_template->footer_text  = $request->footer_text;
            $wa_app_template->button_type  = $request->button_type;
            $wa_app_template->sample_content  = $request->sample_content;

            if(!empty($request->button_type))
            {
                if(is_array(@$request->template_buttons) && count(@$request->template_buttons) > 0)
                {
                    $deleteOldTempBtn = WhatsAppTemplateButton::where('whats_app_template_id',$wa_app_template->id)
                    ->delete();

                    foreach ($request->template_buttons as $key => $button) 
                    {
                        $wa_app_template_btn = new WhatsAppTemplateButton;
                        $wa_app_template_btn->whats_app_template_id  = $wa_app_template->id;
                        $wa_app_template_btn->name  = $button['name'];
                        $wa_app_template_btn->button_text  = $button['button_text'];
                        $wa_app_template_btn->button_value  = $button['button_value'];
                        $wa_app_template_btn->flow_id  = @$button['flow_id'];
                        $wa_app_template_btn->flow_action  = @$button['flow_action'];
                        $wa_app_template_btn->navigate_screen  = @$button['navigate_screen'];
                        $wa_app_template_btn->save();
                    }

                }

            }

            DB::commit();

            $userInfo = $wa_app_template->user()->select('id', 'name')->first();
            $wa_app_template['name'] = $userInfo->name;
            $wa_app_template['user_id'] = $userInfo->id;

            return response()->json(prepareResult(false, $wa_app_template, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $whatsapptemplate = WhatsAppTemplate::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $whatsapptemplate->where('whats_app_templates.user_id', auth()->id());
            }
            $whatsapptemplate = $whatsapptemplate->find($id);
            if(!$whatsapptemplate)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            $whatsapptemplate->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waTemplateLanguages()
    {
        $languages = [
            "Afrikaans" => "af",
            "Albanian" => "sq",
            "Arabic" => "ar",
            "Azerbaijani" => "az",
            "Bengali" => "bn",
            "Bulgarian" => "bg",
            "Catalan" => "ca",
            "Chinese (CHN)" => "zh_CN",
            "Chinese (HKG)" => "zh_HK",
            "Chinese (TAI)" => "zh_TW",
            "Croatian" => "hr",
            "Czech" => "cs",
            "Danish" => "da",
            "Dutch" => "nl",
            "English" => "en",
            "English (UK)" => "en_GB",
            "English (US)" => "en_US",
            "Estonian" => "et",
            "Filipino" => "fil",
            "Finnish" => "fi",
            "French" => "fr",
            "Georgian" => "ka",
            "German" => "de",
            "Greek" => "el",
            "Gujarati" => "gu",
            "Hausa" => "ha",
            "Hebrew" => "he",
            "Hindi" => "hi",
            "Hungarian" => "hu",
            "Indonesian" => "id",
            "Irish" => "ga",
            "Italian" => "it",
            "Japanese" => "ja",
            "Kannada" => "kn",
            "Kazakh" => "kk",
            "Kinyarwanda" => "rw_RW",
            "Korean" => "ko",
            "Kyrgyz (Kyrgyzstan)" => "ky_KG",
            "Lao" => "lo",
            "Latvian" => "lv",
            "Lithuanian" => "lt",
            "Macedonian" => "mk",
            "Malay" => "ms",
            "Malayalam" => "ml",
            "Marathi" => "mr",
            "Norwegian" => "nb",
            "Persian" => "fa",
            "Polish" => "pl",
            "Portuguese (BR)" => "pt_BR",
            "Portuguese (POR)" => "pt_PT",
            "Punjabi" => "pa",
            "Romanian" => "ro",
            "Russian" => "ru",
            "Serbian" => "sr",
            "Slovak" => "sk",
            "Slovenian" => "sl",
            "Spanish" => "es",
            "Spanish (ARG)" => "es_AR",
            "Spanish (SPA)" => "es_ES",
            "Spanish (MEX)" => "es_MX",
            "Swahili" => "sw",
            "Swedish" => "sv",
            "Tamil" => "ta",
            "Telugu" => "te",
            "Thai" => "th",
            "Turkish" => "tr",
            "Ukrainian" => "uk",
            "Urdu" => "ur",
            "Uzbek" => "uz",
            "Vietnamese" => "vi",
            "Zulu" => "zu",
        ];
        return response()->json(prepareResult(false, $languages, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
    }

    public function waTemplateSubmit(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'template_id'    => 'required|exists:whats_app_templates,id',
        ]);
        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $wa_template = WhatsAppTemplate::where('user_id', $request->user_id)
                ->where('id', $request->template_id)
                ->where('status','0')
                ->first();

            $btn_type = (@$wa_template->button_type =='1') ?  'QUICK_REPLY' : '';
            $buttons = WhatsAppTemplateButton::where('whats_app_template_id', $request->template_id)->get();


            $accessToken = 'EAAIgPLInHcsBOZBI8SEstTu0uGghuxFgZCk49KSNd6YHas6x1cywOxDra4MZApySTstZAbnhQnY1OhJrbPwstquAHf6Wm0VUZBHzQJZBBAAmyUUZCKplhebVGPP4oMYzSGB9BOXXcRVlO30m1O5XCl6CVPSe0Yugjwlrbf5tHWoMnBBTpNTfcOGIY4PyyBBnPEFhoP3zaZCqFQMqUwH6QzcZD';
            $waba_id = '114715205025905';

            $client = new Client();

            $response = $client->post("https://graph.facebook.com/v12.0/{$waba_id}/message_templates", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    "name" => $wa_template->template_name,
                    "language" => $wa_template->template_language,
                    "category" => $wa_template->category,
                    "components" => [
                        [
                            "type" => "HEADER",
                            "format" => $wa_template->template_type,
                            "text" => $wa_template->header_text,
                            "example" => [
                                "header_text" => ["Summer Sale"],
                            ],
                        ],
                        [
                            "type" => "BODY",
                            "text" => $wa_template->message,
                            "example" => [
                                "body_text" => [
                                    ["the end of August", "25OFF", "25%"]
                                ],
                            ],
                        ],
                        [
                            "type" => "FOOTER",
                            "text" => $wa_template->footer_text,
                        ],
                        [
                            "type" => "BUTTONS",
                            "buttons" => [
                                [
                                    "type" => "QUICK_REPLY",
                                    "text" => "Unsubscribe from Promos",
                                ],
                                [
                                    "type" => "QUICK_REPLY",
                                    "text" => "Unsubscribe from All",
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            // Get the response body as JSON
            $responseData = json_decode($response->getBody(), true);

             return response()->json(prepareResult(false, $responseData, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (RequestException $e) {
            // Handle the error response
            if ($e->hasResponse()) 
            {
                $response = $e->getResponse();
                $errorBody = json_decode($response->getBody(), true);

                // Log the error details
                \Log::error('Guzzle Error: ' . $e->getMessage(), [
                    'status_code' => $response->getStatusCode(),
                    'error_body' => $errorBody,
                ]);

                 return response(prepareResult(false, $errorBody,trans('translate.something_went_wrong')), config('httpcodes.internal_server_error'));  
            } else {
                // Handle non-HTTP exception (e.g., connection error)
                \Log::error('Guzzle Error: ' . $e->getMessage());

                return response(prepareResult(false, $e->getMessage(),trans('translate.something_went_wrong')), config('httpcodes.internal_server_error'));  
            }
        }
    }

    public function downloadWASampleFile($whats_app_template_id, $type='normal')
    {
        try {
            $prepareHeader = ['mobile_numbers'];
            $destinationPath    = 'whatsapp-sample/';
            if($type=='custom')
            {
                $whatsapptemplate = WhatsAppTemplate::query();
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $whatsapptemplate->where('whats_app_templates.user_id', auth()->id());
                }
                $whatsapptemplate = $whatsapptemplate->find($whats_app_template_id);
                if(!$whatsapptemplate)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $header_text = $whatsapptemplate->header_text;
                
                $body_text = $whatsapptemplate->message;

                $footer_text = $whatsapptemplate->footer_text;
                    
                // Check parameter_format
                if($whatsapptemplate->parameter_format=='NAMED')
                {
                    
                    preg_match_all('/\{\{(.*?)\}\}/', $header_text, $match_header);
                    if(sizeof($match_header[1])>0) 
                    { 
                        $prepareHeader[] = $match_header[1];
                    }

                    preg_match_all('/\{\{(.*?)\}\}/', $body_text, $match_body);
                    if(sizeof($match_body[1])>0) 
                    { 
                        $prepareHeader[] = $match_body[1];
                    }

                    preg_match_all('/\{\{(.*?)\}\}/', $footer_text, $match_footer);
                    if(sizeof($match_footer[1])>0) 
                    { 
                        $prepareHeader[] = $match_footer[1];
                    }
                }
                else
                {
                    $totalVariablesInHeader = substr_count($header_text, "{{");
                    for ($i=1; $i <= $totalVariablesInHeader; $i++) 
                    { 
                        $prepareHeader[] = 'header_var_'. $i;
                    }

                    $totalVariablesInBody = substr_count($body_text, "{{");
                    for ($i=1; $i <= $totalVariablesInBody; $i++) 
                    { 
                        $prepareHeader[] = 'body_var_'. $i;
                    }
                    
                    $totalVariablesInFooter = substr_count($footer_text, "{{");
                    for ($i=1; $i <= $totalVariablesInFooter; $i++) 
                    { 
                        $prepareHeader[] = 'footer_var_'. $i;
                    }
                }
                
                if($whatsapptemplate->template_type=='MEDIA')
                {
                    if($whatsapptemplate->media_type=='LOCATION')
                    {
                        $prepareHeader[] = 'location_latitude';
                        $prepareHeader[] = 'location_longitude';
                        $prepareHeader[] = 'location_name';
                        $prepareHeader[] = 'location_address';
                    }
                    else
                    {
                        $prepareHeader[] = 'media_var_1';
                        $prepareHeader[] = 'caption_var_1';
                    }
                }

                // buttons parameter
                $WhatsAppTemplateButtons = $whatsapptemplate->whatsAppTemplateButtons;

                $i = 1;
                foreach ($WhatsAppTemplateButtons as $key => $button) 
                {
                    $sub_type = $button->button_type;
                    if(in_array($sub_type, ['URL', 'COPY_CODE','CATALOG','FLOW']))
                    {
                        
                        if($sub_type=='URL' && str_contains($button->button_value, '{{1}}'))
                        {
                            $prepareHeader[] = 'button_url_var_'. $i;
                            $i++;
                        }
                        elseif($sub_type=='COPY_CODE')
                        {
                            $prepareHeader[] = 'button_coupon_var_1';
                        }
                        elseif($sub_type=='CATALOG')
                        {
                            $prepareHeader[] = 'product_catalog_id_var_1';
                        }
                        elseif($sub_type=='FLOW')
                        {
                            $prepareHeader[] = 'flow_token_var_1';
                        }
                    }
                }
                $prepareHeader = collect($prepareHeader);
                $prepareHeader = $prepareHeader->flatten()->toArray();
                $createHeader = implode(",", $prepareHeader);

                //csv file save to the whatsapp folder
                
                $file_path = $destinationPath.'wa-custom-sms-sample-file'.'.csv';
                $fileDestination    = fopen ($file_path, "w");
                
                fputs($fileDestination, $createHeader);
                fclose($fileDestination);
            }
            else
            {
                //csv file save to the whatsapp folder
                $file_path = $destinationPath.'wa-normal-sms-sample-file.csv';
                if(!file_exists($file_path))
                {
                    $createHeader = implode(",", $prepareHeader);
                    $fileDestination    = fopen ($file_path, "w");
                    
                    fputs($fileDestination, $createHeader);
                    fclose($fileDestination);
                    
                }
            }
            $complete_path = env('APP_URL', 'https://ok-go.in').'/'.$file_path;

            return response()->json(prepareResult(false, $complete_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
