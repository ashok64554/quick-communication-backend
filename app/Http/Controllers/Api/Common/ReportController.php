<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\SendSms;
use App\Models\SendSmsQueue;
use App\Models\SendSmsHistory;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\VoiceSmsHistory;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\UserWiseMonthlyReport;
use App\Exports\ReportExportByID;
use App\Exports\VoiceReportExportByID;
use App\Exports\ReportExportBySenderID;
use App\Exports\ReportExportByNumber;
use App\Exports\TwoWayLogReportExportByCampaign;
use App\Exports\TwoWayResponseReportExportByCampaign;
use App\Exports\ReportWaExport;
use App\Exports\ReportWaExportSummary;
use App\Exports\ReportWaConversationExport;
use App\Exports\SmsDetailedReportExport;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Auth;
use DB;
use Exception;
use Excel;
use Illuminate\Support\Collection;
use App\Models\ConsumptionView;
use App\Models\TimeFrameReportView;
use App\Models\TwoWayComm;
use App\Models\ShortLink;
use App\Models\DltTemplateGroup;
use Cache;
use PDF;


class ReportController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }
    
    public function dashboard(Request $request)
    {
        set_time_limit(0);
        try
        {
            $user_id = null;
            if(in_array(loggedInUserType(), [0,3]))
            {
                $cacheUserId = auth()->id();
                if(!empty($request->user_id))
                {
                    $user_id = $request->user_id;
                }
            }
            else
            {
                if(loggedInUserType()==1)
                {
                    $user_id = !empty($request->user_id) ? $request->user_id : auth()->id();
                }
                else
                {
                    $user_id = auth()->id();
                }
                $cacheUserId = $user_id;
            }

            $user =  User::select('account_type',
                DB::raw('SUM(transaction_credit) as transaction_credit'),
                DB::raw('SUM(promotional_credit) as promotional_credit'),
                DB::raw('SUM(two_waysms_credit) as two_waysms_credit'),
                DB::raw('SUM(voice_sms_credit) as voice_sms_credit'),
                DB::raw('SUM(whatsapp_credit) as whatsapp_credit'),
                DB::raw('COUNT(id) as total_users'),
            );
            if(in_array(loggedInUserType(), [0,3]))
            {
                $user->withoutGlobalScope('parent_id');
            }

            if(!empty($user_id))
            {
                $user->where('id', $user_id);
            }
            $user = $user->first();

            if(in_array(loggedInUserType(), [0,3]))
            {
                $total_users = $user->total_users;
                $accountType = 1;
            }
            else
            {
                $childs = userChildAccounts(User::find($user_id));
                $total_users = count($childs);
                $accountType = $user->account_type;
            }

            $return['credit_info'] = [
                'total_users' => $total_users,
                'transaction' => $user->transaction_credit,
                'promotional' => $user->promotional_credit,
                'two_waysms' => $user->two_waysms_credit,
                'voice_sms' => $user->voice_sms_credit,
                'whatsapp' => $user->whatsapp_credit,
            ];

            // SMS reports
            $yesterday = (empty($request->yesterday)) ? date("Y-m-d", strtotime("-1 day", time())) : $request->yesterday;
            $today = (empty($request->today)) ? date("Y-m-d") : $request->today;

            $cacheName = $cacheUserId.date("Ymd");

            $seconds = (1 * 24 * 60 * 60); // 1 day
            $today_seconds = (30 * 60); // 30 minutes
            

            if(!empty($request->fresh))
            {
                Cache::forget('text_sms_'.$cacheName);
                Cache::forget('voice_sms_'.$cacheName);
                Cache::forget('whatsapp_sms_'.$cacheName);
                Cache::forget('today_text_sms_'.$cacheName);
                Cache::forget('today_voice_sms_'.$cacheName);
                Cache::forget('today_whatsapp_sms_'.$cacheName);
                Cache::forget('today_text_sms_status'.$cacheName);
            }

            $query = Cache::remember('text_sms_'.$cacheName, $seconds, function () use ($user_id, $yesterday, $accountType) {
                if($accountType==1)
                {
                    $query = \DB::table('send_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                        DB::raw('SUM(total_delivered) as total_delivered'),
                        DB::raw('SUM(total_failed) as total_failed'),
                        DB::raw('SUM(total_block_number) as total_block_number'),
                        DB::raw('SUM(total_invalid_number) as total_invalid_number'),
                        DB::raw('SUM(total_contacts - (total_delivered + total_failed + total_block_number + total_invalid_number)) as total_process'),
                    );
                    
                }
                else
                {
                    $query = \DB::table('send_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                    );
                }

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }
                
                if(!empty($yesterday))
                {
                    $query->whereDate('campaign_send_date_time', '<=', $yesterday);
                }

                $query = $query->first();
                return $query;
            });

            if($accountType==1)
            {
                $return['till_yesterday_sms'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts,
                    'total_delivered' => $query->total_delivered,
                    'total_failed' => $query->total_failed,
                    'total_block_number' => $query->total_block_number,
                    'total_invalid_number' => $query->total_invalid_number,
                    'total_process' => $query->total_process
                ];
            }
            else
            {
                $return['till_yesterday_sms'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts
                ];
            }
            

            // Voice SMS
            $query = Cache::remember('voice_sms_'.$cacheName, $seconds, function () use ($user_id, $yesterday, $accountType) {
                if($accountType==1)
                {
                    $query = \DB::table('voice_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                        DB::raw('SUM(total_delivered) as total_delivered'),
                        DB::raw('SUM(total_failed) as total_failed'),
                        DB::raw('SUM(total_block_number) as total_block_number'),
                        DB::raw('SUM(total_invalid_number) as total_invalid_number'),
                        DB::raw('SUM(total_contacts - (total_delivered + total_failed + total_block_number + total_invalid_number)) as total_process'),
                    );
                }
                else
                {
                    $query = \DB::table('voice_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used')
                    );
                }
                

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }

                if(!empty($yesterday))
                {
                    $query->whereDate('campaign_send_date_time', '<=', $yesterday);
                }

                $query = $query->first();
                return $query;
            });

            if($accountType==1)
            {
                $return['till_yesterday_voice'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts,
                    'total_delivered' => $query->total_delivered,
                    'total_failed' => $query->total_failed,
                    'total_block_number' => $query->total_block_number,
                    'total_invalid_number' => $query->total_invalid_number,
                    'total_process' => $query->total_process
                ];
            }
            else
            {
                $return['till_yesterday_voice'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts
                ];
            }

            // Whatsapp SMS
            $query = Cache::remember('whatsapp_sms_'.$cacheName, $seconds, function () use ($user_id, $yesterday, $accountType) {
                if($accountType==1)
                {
                    $query = \DB::table('whats_app_send_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                        DB::raw('SUM(total_sent) as total_sent'),
                        DB::raw('SUM(total_delivered) as total_delivered'),
                        DB::raw('SUM(total_read) as total_read'),
                        DB::raw('SUM(total_failed) as total_failed'),
                        DB::raw('SUM(total_other) as total_other'),
                        DB::raw('SUM(total_contacts - (total_sent + total_read + total_failed + total_delivered + total_other)) as total_process'),
                    );
                }
                else
                {
                    $query = \DB::table('whats_app_send_sms')->select(
                        DB::raw('SUM(total_contacts) as total_contacts'),
                        DB::raw('SUM(total_credit_deduct) as total_credit_used')
                    );
                }

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }

                if(!empty($yesterday))
                {
                    $query->whereDate('campaign_send_date_time', '<=', $yesterday);
                }

                $query = $query->first();
                return $query;
            });

            if($accountType==1)
            {
                $return['till_yesterday_whatsapp'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts,
                    'total_delivered' => $query->total_delivered,
                    'total_read' => $query->total_read,
                    'total_failed' => $query->total_failed,
                    'total_other' => $query->total_other,
                    'total_process' => $query->total_process
                ];
            }
            else
            {
                $return['till_yesterday_whatsapp'] = [
                    'total_credit_used' => $query->total_credit_used,
                    'total_sent' => $query->total_contacts
                ];
            }
            

            // Today 
            // SMS
            $query = Cache::remember('today_text_sms_'.$cacheName, $today_seconds, function () use ($user_id, $today) {
                $query = \DB::table('send_sms')->select(
                    DB::raw('SUM(total_contacts) as total_submission'),
                    DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                );

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }

                if(!empty($today))
                {
                    $query->whereDate('campaign_send_date_time', $today);
                }

                $query = $query->first();
                return $query;
            });


            $query_status = Cache::remember('today_text_sms_status'.$cacheName, $today_seconds, function () use ($user_id, $today) {
                $query_status = \DB::table('send_sms_queues')->select('send_sms_queues.stat', 
                    DB::raw('count(send_sms_queues.stat) as total_counts'),
                )
                ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id');

                if(!empty($user_id))
                {
                    $query_status->where('send_sms.user_id', $user_id);
                }

                if(!empty($today))
                {
                    $query_status->whereDate('send_sms.campaign_send_date_time', $today);
                }

                $query_status = $query_status->groupBy('send_sms_queues.stat')->get();
                return $query_status;
            });

            $return['today_sms'] = [
                'total_submission' => $query->total_submission,
                'total_credit_used' => $query->total_credit_used,
                'status_wise' => $query_status
            ];

            // Voice
            $query = Cache::remember('today_voice_sms_'.$cacheName, $today_seconds, function () use ($user_id, $today) {
                $query = \DB::table('voice_sms')->select(
                    DB::raw('SUM(total_contacts) as total_submission'),
                    DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                );

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }

                if(!empty($today))
                {
                    $query->whereDate('campaign_send_date_time', $today);
                }

                $query = $query->first();
                return $query;
            });

            $return['today_voice'] = [
                'total_submission' => $query->total_submission,
                'total_credit_used' => $query->total_credit_used,
            ];

            // Whatsapp
            $query = Cache::remember('today_whatsapp_sms_'.$cacheName, $today_seconds, function () use ($user_id, $today) {
                $query = \DB::table('whats_app_send_sms')->select(
                    DB::raw('SUM(total_contacts) as total_submission'),
                    DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                );

                if(!empty($user_id))
                {
                    $query->where('user_id', $user_id);
                }

                if(!empty($today))
                {
                    $query->whereDate('campaign_send_date_time', $today);
                }

                $query = $query->first();
                return $query;
            });

            $return['today_whatsapp'] = [
                'total_submission' => $query->total_submission,
                'total_credit_used' => $query->total_credit_used,
            ];


            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function serverInfo()
    {
        try
        {
            $cacheName = date('Ymd');
            $seconds = (30 * 60); // 30 minutes

            $return = Cache::remember('server_info_'.$cacheName, $seconds, function () 
            {
                $start_time = microtime(TRUE);
                $operating_system = PHP_OS_FAMILY;
                if ($operating_system === 'Windows') 
                {
                    // Win CPU
                    $wmi = new \COM('WinMgmts:\\\\.');
                    $cpus = $wmi->InstancesOf('Win32_Processor');
                    $cpuload = 0;
                    $cpu_count = 0;
                    foreach ($cpus as $key => $cpu) {
                        $cpuload += $cpu->LoadPercentage;
                        $cpu_count++;
                    }
                    // WIN MEM
                    $res = $wmi->ExecQuery('SELECT FreePhysicalMemory,FreeVirtualMemory,TotalSwapSpaceSize,TotalVirtualMemorySize,TotalVisibleMemorySize FROM Win32_OperatingSystem');
                    $mem = $res->ItemIndex(0);
                    $memtotal = round($mem->TotalVisibleMemorySize / 1000000,2);
                    $memavailable = round($mem->FreePhysicalMemory / 1000000,2);
                    $memused = round($memtotal-$memavailable,2);
                    // WIN CONNECTIONS
                    $connections = shell_exec('netstat -nt | findstr :80 | findstr ESTABLISHED | find /C /V ""'); 
                    $totalconnections = shell_exec('netstat -nt | findstr :80 | find /C /V ""');
                    $totalHttpConnections = shell_exec('netstat -an | findstr /C:":80 " /C:":443 " /C:":8080"'); 
                } 
                else 
                {
                    // Linux CPU
                    $load = sys_getloadavg();
                    $cpuload = $load[0];
                    $cpu_count = shell_exec('nproc');
                    // Linux MEM
                    $free = shell_exec('free');
                    $free = (string)trim($free);
                    $free_arr = explode("\n", $free);
                    $mem = explode(" ", $free_arr[1]);
                    $mem = array_filter($mem, function($value) { return ($value !== null && $value !== false && $value !== ''); }); // removes nulls from array
                    $mem = array_merge($mem); // puts arrays back to [0],[1],[2] after 
                    $memtotal = round($mem[1] / 1000000,2);
                    $memused = round($mem[2] / 1000000,2);
                    $memfree = round($mem[3] / 1000000,2);
                    $memshared = round($mem[4] / 1000000,2);
                    $memcached = round($mem[5] / 1000000,2);
                    $memavailable = round($mem[6] / 1000000,2);
                    // Linux Connections
                    $connections = `netstat -ntu | grep :80 | grep ESTABLISHED | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`; 
                    $totalconnections = `netstat -ntu | grep :80 | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`; 
                    $totalHttpConnections = `netstat | grep http | grep https | wc -l`;
                }

                //$memusage = round(($memavailable/$memtotal)*100);
                $memusage = round(($memused/$memtotal)*100);
                $phpload = round((((memory_get_usage() / 1024) / 1024)/ 1024),2);

                $diskfree = round(disk_free_space(".") / 1000000000);
                $disktotal = round(disk_total_space(".") / 1000000000);
                $diskused = round($disktotal - $diskfree);

                $diskusage = round($diskused/$disktotal*100);

                /*if ($memusage > 85 || $cpuload > 85 || $diskusage > 85) {
                    $trafficlight = 'red';
                } elseif ($memusage > 50 || $cpuload > 50 || $diskusage > 50) {
                    $trafficlight = 'orange';
                } else {
                    $trafficlight = 'green';
                }*/

                $end_time = microtime(TRUE);
                $time_taken = $end_time - $start_time;
                $total_time = round($time_taken,4);

                $return = [
                    'ram_usage_percentage' => $memusage,
                    'cpu_usage_percentage' => sprintf('%0.2f', $cpuload),
                    'hd_usage_percentage' => $diskusage,
                    //'color_code' => $trafficlight,
                    'established_connections' => (int) $connections,
                    'total_connections' => (int) $totalconnections,
                    'cpu_threads' => (int) $cpu_count,
                    'ram_total_gb' => $memtotal,
                    'ram_used_gb' => $memused,
                    'ram_available_gb' => $memavailable,
                    'hd_free_gb' => $diskfree,
                    'hd_used_gb' => $diskused,
                    'hd_total_gb' => $disktotal,
                    'server_name' => $_SERVER['SERVER_NAME'],
                    'server_addr' => env('KANNEL_IP'),
                    'php_version' => phpversion(),
                    'php_load_gb' => $phpload,
                    'total_http_connections' => (int) $totalHttpConnections

                ];
                return $return;
            });

            
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function deliveryReport(Request $request)
    {
        set_time_limit(0);
        try
        {
            $query =  SendSms::select('id','uuid','user_id', 'campaign', 'dlt_template_id', 'sender_id', 'route_type', 'country_id', 'sms_type', 'message', 'message_type', 'is_flash', 'campaign_send_date_time','is_campaign_scheduled', 'message_count', 'message_credit_size', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct', 'total_delivered', 'total_failed', 'is_credit_back', 'credit_back_date', 'status')
            ->with('dltTemplate:id,dlt_template_id,template_name')
            ->orderBy('id','DESC');
            if(in_array(loggedInUserType(), [0,3]))
            {
                $query->withoutGlobalScope('parent_id');
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
            }
            elseif(in_array(loggedInUserType(), [1]))
            {
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
                else
                {
                    $query->where('user_id', auth()->id());
                    $query->withoutGlobalScope('parent_id');
                }
            }
            else
            {
                $query->where('user_id', auth()->id());
                $query->withoutGlobalScope('parent_id');
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('campaign', 'LIKE', '%' . $search. '%')
                    ->orWhere('campaign_send_date_time', 'LIKE', '%' . $search. '%')
                    ->orWhere('sender_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('message', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->campaign))
            {
                $query->where('campaign', 'LIKE', '%'.$request->campaign.'%');
            }

            if(!empty($request->only_campaign) && $request->only_campaign=='yes')
            {
                $query->where('campaign', '!=', 'API');
            }

            if(!empty($request->dlt_template_id))
            {
                $query->where('dlt_template_id', $request->dlt_template_id);
            }

            if(!empty($request->sender_id))
            {
                $query->where('sender_id', 'LIKE', '%'.$request->sender_id.'%');
            }

            if(!empty($request->campaign_send_date_time))
            {
                $query->whereDate('campaign_send_date_time', $request->campaign_send_date_time);
            }

            if(!empty($request->is_campaign_scheduled) && $request->is_campaign_scheduled == 1)
            {
                $query->where('is_campaign_scheduled', 1);
            }
            elseif(!empty($request->is_campaign_scheduled) && $request->is_campaign_scheduled == 'no')
            {
                $query->where('is_campaign_scheduled', 0);
            }

            if(!empty($request->route_type))
            {
                $query->where('route_type', $request->route_type);
            }

            if(!empty($request->sms_type))
            {
                $query->where('sms_type', $request->sms_type);
            }

            if(!empty($request->message_type))
            {
                $query->where('message_type', $request->message_type);
            }

            if(!empty($request->status))
            {
                $query->whereDate('status', $request->status);
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

    public function scheduledCampaignReport(Request $request)
    {
        set_time_limit(0);
        try
        {
            $query =  SendSms::where('campaign_send_date_time', '>', date('Y-m-d H:i:s'))
            ->where('is_campaign_scheduled', 1)
            ->orderBy('id','DESC');
            if(in_array(loggedInUserType(), [0,3]))
            {
                $query->withoutGlobalScope('parent_id');
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
            }
            else
            {
                $query->where('user_id', auth()->id());
                $query->withoutGlobalScope('parent_id');
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

    public function msgConsumptionReport(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:1,2',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(auth()->user()->account_type==1)
        {
            $accountType = 1; //prepaid
        }
        else
        {
            $accountType = 2; //postpaid
        }
        

        try
        {
            if($request->type==1)
            {
                if($accountType==1)
                {
                    $query =  SendSms::select('id','user_id','campaign_send_date_time','total_contacts','total_block_number','total_invalid_number','total_credit_deduct')
                        ->where('user_id', $request->user_id)
                        ->with('user:id,name')
                        ->withCount([
                        'sendSmsQueues as total_queues_count',
                        'sendSmsHistories as total_history_count',

                        'sendSmsQueues as delivered_queue_count' => function ($query) {
                          $query->where('stat', 'DELIVRD');
                        },
                        'sendSmsHistories as delivered_history_count' => function ($query) {
                          $query->where('stat', 'DELIVRD');
                        },
                        
                        'sendSmsQueues as invalid_queue_count' => function ($query) {
                          $query->where('stat', 'Invalid');
                        },
                        'sendSmsHistories as invalid_history_count' => function ($query) {
                          $query->where('stat', 'Invalid');
                        },

                        'sendSmsQueues as blacklist_queue_count' => function ($query) {
                          $query->where('stat', 'BLACK');
                        },
                        'sendSmsHistories as blacklist_history_count' => function ($query) {
                          $query->where('stat', 'BLACK');
                        },
                        
                        'sendSmsQueues as expired_queue_count' => function ($query) {
                          $query->where('stat', 'EXPIRED');
                        },
                        'sendSmsHistories as expired_history_count' => function ($query) {
                          $query->where('stat', 'EXPIRED');
                        },
                        
                        'sendSmsQueues as failed_queue_count' => function ($query) {
                          $query->whereIn('stat', ['FAILED', 'UNDELIV']);
                        },
                        'sendSmsHistories as failed_history_count' => function ($query) {
                          $query->whereIn('stat', ['FAILED', 'UNDELIV']);
                        },
                        
                        'sendSmsQueues as rejected_queue_count' => function ($query) {
                          $query->where('stat', 'REJECTD');
                        },
                        'sendSmsHistories as rejected_history_count' => function ($query) {
                          $query->where('stat', 'REJECTD');
                        },

                        'sendSmsQueues as other_queue_count' => function ($query) {
                          $query->whereNotIn('stat', ['DELIVRD','Invalid','BLACK','EXPIRED','FAILED','REJECTD','UNDELIV']);
                        },
                        'sendSmsHistories as other_history_count' => function ($query) {
                          $query->whereNotIn('stat', ['DELIVRD','Invalid','BLACK','EXPIRED','FAILED','REJECTD','UNDELIV']);
                        }
                        
                    ]);
                }
                else
                {
                    $query =  SendSms::select('id','user_id','campaign_send_date_time','total_contacts','total_credit_deduct')
                        ->where('user_id', $request->user_id)
                        ->with('user:id,name');
                }
                

                if(in_array(loggedInUserType(), [0,3]))
                {
                    $query->withoutGlobalScope('parent_id');
                }

                if(!empty($request->from_date))
                {
                    $query->whereDate('campaign_send_date_time', '>=',$request->from_date);
                }
                if(!empty($request->to_date))
                {
                    $query->whereDate('campaign_send_date_time', '<=',$request->to_date);
                }

                if(empty($request->from_date) && empty($request->to_date))
                {
                    $query->whereDate('campaign_send_date_time', date('Y-m-d'));
                }
                $query = $query->get();
                return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            else
            {
                /*********************************
                 * if you want response something like this then uncomment this code
                 * [
                    {
                        "campaign_date": "2024-01-06",
                        "stat": "ACCEPTED",
                        "stat_wise_count": 9,
                        "used_credit": "63"
                    },
                    {
                        "campaign_date": "2024-01-06",
                        "stat": "DELIVRD",
                        "stat_wise_count": 3025069,
                        "used_credit": "21175483"
                    }
                ]
                 * 
                */
                /*
                    $data = DB::table('send_sms')->select(DB::raw('DATE(send_sms.campaign_send_date_time) as campaign_date'), 'stat', DB::raw('COUNT(send_sms_histories.id) as stat_wise_count'), DB::raw('SUM(send_sms_histories.use_credit) as used_credit'))
                        ->join('send_sms_histories', 'send_sms.id', 'send_sms_histories.send_sms_id')
                        ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
                        ->groupBy(DB::raw('DATE(send_sms.campaign_send_date_time)'))
                        ->groupBy('send_sms_histories.stat')
                        ->orderBy('campaign_date', 'ASC');

                        if(in_array(loggedInUserType(), [1,2]))
                        {
                            $data->where('send_sms.user_id', auth()->id());
                        }
                        elseif(!empty($request->user_id))
                        {
                            $data->where('send_sms.user_id', $request->user_id);
                        }

                        if(!empty($request->from_date))
                        {
                            $data->whereDate('send_sms.campaign_send_date_time', '>=', $request->from_date);
                        }

                        if(!empty($request->to_date))
                        {
                            $data->whereDate('send_sms.campaign_send_date_time', '<=', $request->to_date);
                        }

                        if(sizeof($request->user_id) && is_array($request->user_id)>0)
                        {
                            $data->whereIn('send_sms.user_id', $request->user_id);
                        }

                    $data = $data->get();
                    return response()->json(prepareResult(false, $data, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                */


                $from_date = $request->from_date;
                $to_date = $request->to_date;
                if(empty($request->from_date) && empty($request->to_date))
                {
                    $from_date = date('Y-m-d');
                    $to_date = date('Y-m-d');
                }
                $data = null;
                $dates = dateList($from_date, $to_date);

                //$users = implode(', ', $request->user_id);
                $users = $request->user_id;
                $data = array();
                foreach($dates as $date)
                {
                    /*
                    if(in_array(loggedInUserType(), [0,3]))
                    {
                         
                    }
                    else
                    {
                        
                    }
                    */

                    $cacheName = $users.'_'.$date.'_'.$accountType;
                    if($date==date('Y-m-d'))
                    {
                        $seconds = (1 * 60); // 1 minutes
                    }
                    else
                    {
                        $seconds = (15 * 24 * 60 * 60); // 15 days
                    }

                    if($accountType==1)
                    {
                        $result = Cache::remember('consumption_report_'.$cacheName, $seconds, function () use ($date, $users) {
                            return \DB::select('CALL getDateWiseConsumption(?, ?)', [$date, $users]);
                        });

                        
                        $campaign_send_date = $date;
                        $total_submission = 0;
                        $total_credit_submission = 0;
                        $delivered_count = 0;
                        $invalid_count = 0;
                        $black_count = 0;
                        $expired_count = 0;
                        $failed_count = 0;
                        $rejected_count = 0;
                        $process_count = 0;
                        foreach ($result as $key => $value) {
                            $total_submission = $total_submission + $value->total_submission_history;
                            $total_credit_submission = $total_credit_submission + $value->total_credit_submission_history;
                            $delivered_count = $delivered_count + $value->delivered_history_count;
                            $invalid_count = $invalid_count + $value->invalid_history_count;
                            $black_count = $black_count + $value->black_history_count;
                            $expired_count = $expired_count + $value->expired_history_count;
                            $failed_count = $failed_count + $value->failed_history_count;
                            $rejected_count = $rejected_count + $value->rejected_history_count;
                            $process_count = $process_count + $value->process_history_count;
                        }
                        $data[] = [
                            'date' => $date,
                            'total_submission' => $total_submission,
                            'total_credit_submission' => $total_credit_submission,
                            'delivered_count' => $delivered_count,
                            'invalid_count' => $invalid_count,
                            'black_count' => $black_count,
                            'expired_count' => $expired_count,
                            'failed_count' => $failed_count,
                            'rejected_count' => $rejected_count,
                            'process_count' => $process_count,
                        ];
                    }
                    else
                    {
                        $selected_users = $request->user_id;
                        $result = Cache::remember('consumption_report_'.$cacheName, $seconds, function () use ($date, $selected_users) {
                            return \DB::table('send_sms')->select(
                                DB::raw('SUM(total_contacts) as total_submission'),
                                DB::raw('SUM(total_credit_deduct) as total_credit_submission'),
                            )
                            ->where('user_id', $selected_users)
                            ->whereDate('campaign_send_date_time', $date)
                            ->first();
                        });
                        $data[] = [
                            'date' => $date,
                            'total_submission' => $result->total_submission,
                            'total_credit_submission' => $result->total_credit_submission,
                        ];
                    }
                }
                return response()->json(prepareResult(false, $data, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function reportByMobile(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'mobile'  => 'required|min:10|max:10',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try
        {
            $queue = SendSmsQueue::select('send_sms_queues.id','send_sms_queues.mobile','send_sms_queues.message','send_sms_queues.use_credit','send_sms_queues.submit_date','send_sms_queues.done_date','send_sms_queues.stat','send_sms_queues.err','send_sms.sender_id','users.name','users.name','dlt_templates.dlt_template_id')
            ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
            ->join('users', 'send_sms.user_id', 'users.id')
            ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
            ->where('send_sms_queues.mobile', '91'.$request->mobile);
            if(!empty($request->user_id))
            {
                $queue->where('send_sms.user_id', $request->user_id);
            }
            

            if(!empty($request->from_date))
            {
                $queue->whereDate('send_sms.campaign_send_date_time', '>=', $request->from_date);
            }
            if(!empty($request->to_date))
            {
                $queue->whereDate('send_sms.campaign_send_date_time', '<=', $request->to_date);
            }

            $history = SendSmsHistory::select('send_sms_histories.id','send_sms_histories.mobile','send_sms_histories.message','send_sms_histories.use_credit','send_sms_histories.submit_date','send_sms_histories.done_date','send_sms_histories.stat','send_sms_histories.err','send_sms.sender_id','users.name','users.name','dlt_templates.dlt_template_id')
            ->join('send_sms', 'send_sms_histories.send_sms_id', 'send_sms.id')
            ->join('users', 'send_sms.user_id', 'users.id')
            ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
            ->where('send_sms_histories.mobile', '91'.$request->mobile);
            if(!empty($request->user_id))
            {
                $history->where('send_sms.user_id', $request->user_id);
            }

            if(!empty($request->from_date))
            {
                $history->whereDate('send_sms.campaign_send_date_time', '>=', $request->from_date);
            }
            if(!empty($request->to_date))
            {
                $history->whereDate('send_sms.campaign_send_date_time', '<=', $request->to_date);
            }
            $query = $history->union($queue);
            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = count($query->get());
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
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function summaryReportByTemplateGroup(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'dlt_template_group_id'  => 'nullable|exists:dlt_template_groups,id',
            'date' => 'required|date_format:Y-m-d|before:tomorrow',
            'report' => 'required|numeric'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try
        {
            if(!in_array($request->report, [1,2]))
            {
                return response()->json(prepareResult(true, trans('translate.something_went_wrong'), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }

            $report = $request->report;
            $user_id = $request->user_id;
            $date = $request->date;
            $dlt_template_group_id = $request->dlt_template_group_id;
            $seconds = (($date == date('Y-m-d') ? 120 : (15 * 24 * 60 * 60)));

            if(in_array(loggedInUserType(), [1,2]))
            {
                $cacheName = auth()->id().'_'.$dlt_template_group_id.'_'.$date;
            }
            else
            {
                $cacheName = '_admin_'.$dlt_template_group_id.'_'.$date;
                
            }


            $summary_report_gen = Cache::remember('consumption_report_'.$cacheName, $seconds, function () use ($user_id, $date, $dlt_template_group_id, $report) 
            {
                if($report==1)
                {
                    if(!empty($date) && $date==date('Y-m-d'))
                    {
                        $summary_report_gen = DB::table('send_sms')
                        ->select('group_name', DB::raw('SUM(total_contacts) as total_submission'), DB::raw('SUM(total_credit_deduct) as total_credit_used'), DB::raw('SUM(total_invalid_number) as total_invalid_numbers'), DB::raw('SUM(total_block_number) as total_block_numbers'))
                        ->join('dlt_template_groups', 'send_sms.dlt_template_group_id', 'dlt_template_groups.id')
                        ->whereNotNull('dlt_template_group_id')
                        ->groupBy('dlt_template_groups.group_name');
                    }
                    else
                    {
                        $summary_report_gen = DB::table('send_sms')->select('group_name','stat', DB::raw('COUNT(send_sms_histories.id) as stat_wise_count'), DB::raw('SUM(send_sms_histories.use_credit) as used_credit'))
                        ->join('send_sms_histories', 'send_sms.id', 'send_sms_histories.send_sms_id')
                        ->join('dlt_template_groups', 'send_sms.dlt_template_group_id', 'dlt_template_groups.id')
                        ->whereNotNull('dlt_template_group_id')
                        ->groupBy('dlt_template_groups.group_name')
                        ->groupBy('send_sms_histories.stat')
                        ->orderBy('group_name', 'ASC');
                    }
                }
                elseif($report==2)
                {
                    if(!empty($date) && $date==date('Y-m-d'))
                    {
                        $summary_report_gen = DB::table('send_sms');
                    }
                    else
                    {
                        $summary_report_gen = DB::table('send_sms');
                    }

                    $summary_report_gen->select('group_name', DB::raw('SUM(total_contacts) as total_submission'), DB::raw('SUM(total_credit_deduct) as total_credit_used'))
                        ->join('dlt_template_groups', 'send_sms.dlt_template_group_id', 'dlt_template_groups.id')
                        ->whereNotNull('dlt_template_group_id')
                        ->groupBy('dlt_template_groups.group_name');
                }

                if(in_array(loggedInUserType(), [1,2]))
                {
                    $summary_report_gen->where('send_sms.user_id', auth()->id());
                }
                elseif(!empty($user_id))
                {
                    $summary_report_gen->where('send_sms.user_id', $user_id);
                }

                if(!empty($date))
                {
                    $summary_report_gen->whereDate('send_sms.campaign_send_date_time', $date);
                }

                if(!empty($dlt_template_group_id))
                {
                    $summary_report_gen->where('dlt_template_groups.id', $dlt_template_group_id);
                }

                $summary_report_gen = $summary_report_gen->get();
                return $summary_report_gen;
            });

            return response()->json(prepareResult(false, $summary_report_gen, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));       
        }
        catch (\Throwable $e) 
        {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function overviewReportByUser(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'sender_id' => 'required|exists:manage_sender_ids,sender_id'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try
        {
            $user = $request->user_id;
            $total_queue_stats['user'] = User::select('id', 'name')->find($user);
            $total_queue_stats['stat'] = DB::table('send_sms_queues')
                ->select('stat', DB::raw('COUNT(*) as stat_wise_count'), DB::raw('SUM(use_credit) as used_credit'))
                ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
                ->where('send_sms.sender_id', $request->sender_id)
                ->where('send_sms.user_id', $user)
                ->groupBy('stat')
                ->orderBy('stat', 'ASC')
                ->get();
            $total_history_stats['user'] = User::select('id', 'name')->find($user);
            $total_history_stats['stat'] = DB::table('send_sms_histories')
                ->select('stat', DB::raw('COUNT(*) as stat_wise_count'), DB::raw('SUM(use_credit) as used_credit'))
                ->join('send_sms', 'send_sms_histories.send_sms_id', 'send_sms.id')
                ->where('send_sms.sender_id', $request->sender_id)
                ->where('send_sms.user_id', $user)
                ->groupBy('stat')
                ->orderBy('stat', 'ASC')
                ->get();
            $returnObj = [
                'queue' => $total_queue_stats,
                'history' => $total_history_stats,
            ];
            return response()->json(prepareResult(false, $returnObj, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function monthWiseSubmissionReport(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'month'    => 'required|in:January,February,March,April,May,June,July,August,September,October,November,December',
            'year'    => 'required|numeric|digits:4',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try
        {
            $totals =  DB::table('user_wise_monthly_reports')
                ->select(
                    DB::raw('SUM(sms_count_submission) as sms_count_submission'), 
                    DB::raw('SUM(sms_count_delivered) as sms_count_delivered'), 
                    DB::raw('SUM(sms_count_failed) as sms_count_failed'), 
                    DB::raw('SUM(sms_count_rejected) as sms_count_rejected'), 
                    DB::raw('SUM(sms_count_invalid) as sms_count_invalid'),
                    DB::raw('SUM(mobile_count_submission) as mobile_count_submission'), 
                    DB::raw('SUM(mobile_count_delivered) as mobile_count_delivered'), 
                    DB::raw('SUM(mobile_count_failed) as mobile_count_failed'), 
                    DB::raw('SUM(mobile_count_rejected) as mobile_count_rejected'), 
                    DB::raw('SUM(mobile_count_invalid) as mobile_count_invalid'));

            if(in_array(loggedInUserType(), [0,3]))
            {
                if(!empty($request->user_id))
                {
                    $totals->where('user_id', $request->user_id);
                }
            }
            else
            {
                $totals->where('user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $totals->where(function ($q) use ($search) {
                    $q->where('group_name', 'LIKE', '%' . $search. '%')
                        ->orWhere('month', 'LIKE', '%' . $search. '%')
                        ->orWhere('year', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->group_name))
            {
                $totals->where('group_name', $request->group_name);
            }

            if(!empty($request->month))
            {
                $totals->where('month', $request->month);
            }

            if(!empty($request->year))
            {
                $totals->where('year', $request->year);
            }

            $totals = $totals->first();

            $query =  UserWiseMonthlyReport::select('user_id', 'group_name', 'month', 'year', 'sms_count_submission', 'sms_count_delivered', 'sms_count_failed', 'sms_count_rejected', 'sms_count_invalid', 'mobile_count_submission', 'mobile_count_delivered', 'mobile_count_failed', 'mobile_count_rejected', 'mobile_count_invalid', DB::raw('IFNULL(group_name, "NOT IN GROUP") as group_name'))->orderBy('id','ASC')->with('user:id,name');
            if(in_array(loggedInUserType(), [0,3]))
            {
                if(!empty($request->user_id))
                {
                    $query->where('user_id', $request->user_id);
                }
            }
            else
            {
                $query->where('user_id', auth()->id());
            }

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

            if(!empty($request->month))
            {
                $query->where('month', $request->month);
            }

            if(!empty($request->year))
            {
                $query->where('year', $request->year);
            }

            if($request->generate_pdf=='yes')
            {
                $year = $request->year;
                $month = strtolower($request->month);
                $fileName = 'nrtsms-'.$month.'-'.$year.'.pdf';
                $filePath = 'reports/' . $fileName;
                /*
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                */

                $all_list = $query->get();
                $data = [
                    'year' => $year,
                    'month' => $request->month,
                    'overall_total' => $totals,
                    'data' => $all_list,
                    'file_path' => $filePath
                ];

                $pdf = PDF::loadView('monthly-reports-pdf',compact('data'));
                $pdf->save($filePath);
                return response()->json(prepareResult(false, $data, trans('translate.report_generated'), $this->intime), config('httpcodes.success'));
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->makeHidden(['created_at', 'updated_at']);


                $pagination =  [
                    'overall_total' => $totals,
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
                $all_list = $query->get()->makeHidden(['created_at', 'updated_at']);
                $query = [
                    'overall_total' => $totals,
                    'data' => $all_list
                ];
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

    public function twoWayLinkClickLog(Request $request)
    {
        try
        {
            $query = ShortLink::select('short_links.id', 'short_links.two_way_comm_id','short_links.send_sms_id','short_links.link_expired','short_links.mobile_num','short_links.code','short_links.total_click','two_way_comms.is_web_temp','two_way_comms.title','two_way_comms.take_response')
            ->join('two_way_comms', 'short_links.two_way_comm_id', '=', 'two_way_comms.id')
            ->where('short_links.send_sms_id', $request->send_sms_id)
            ->with('linkClickLogs');
            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('two_way_comms.created_by', auth()->id())
                    ->withoutGlobalScope('parent_id');
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('two_way_comms.title', 'LIKE', '%' . $search. '%')
                    ->orWhere('two_way_comms.content', 'LIKE', '%' . $search. '%')
                    ->orWhere('short_links.mobile_num', 'LIKE', '%' . $search. '%');
                });
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
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function twoWayCaptureRecordLog(Request $request)
    {
        try
        {
            $query = ShortLink::select('short_links.id', 'short_links.two_way_comm_id','short_links.send_sms_id','short_links.link_expired','short_links.mobile_num','short_links.code','short_links.total_click','two_way_comms.is_web_temp','two_way_comms.title','two_way_comms.take_response')
            ->join('two_way_comms', 'short_links.two_way_comm_id', '=', 'two_way_comms.id')
            ->where('short_links.send_sms_id', $request->send_sms_id)
            ->with('twoWayCommFeedbacks','twoWayCommInterests','twoWayCommRatings');
            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('two_way_comms.created_by', auth()->id())
                    ->withoutGlobalScope('parent_id');
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('two_way_comms.title', 'LIKE', '%' . $search. '%')
                    ->orWhere('two_way_comms.content', 'LIKE', '%' . $search. '%')
                    ->orWhere('short_links.mobile_num', 'LIKE', '%' . $search. '%');
                });
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
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    //////////////////////////////////////////
    //////// Exports
    //////////////////////////////////////////
    public function reportExportById(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'send_sms_id'    => 'required|exists:send_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.$request->send_sms_id.'.xlsx';
            $data = Excel::store(new ReportExportByID($request->send_sms_id), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function reportExportBySenderId(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'sender_id'    => 'required|min:6',
            'from_date'    => 'required|date',
            'to_date'    => 'required|date',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.strtoupper($request->sender_id).'.xlsx';
            $data = Excel::store(new ReportExportBySenderID($request->sender_id, $request->from_date, $request->to_date), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];

            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function reportExportByMobile(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'mobile'  => 'required|min:10|max:10',
            'user_id' => 'nullable|array|exists:users,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.strtoupper($request->mobile).'.xlsx';
            $data = Excel::store(new ReportExportByNumber($request->user_id, $request->mobile, $request->from_date, $request->to_date), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];

            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function consumptionReportByView(Request $request)
    {
        /*
        $queues = SendSms::select(
                'send_sms.user_id',
                DB::raw('DATE_FORMAT(send_sms.created_at, "%Y-%m-%d") as send_date'),
                DB::raw('SUM(send_sms_queues.use_credit) as total_credit'),
                DB::raw('COUNT(send_sms_queues.id) as total_submission'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "DELIVRD", 0, NULL)) as total_delivered'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "PENDING", 0, NULL)) as total_pending'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "Invalid", 0, NULL)) as total_invalid'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "EXPIRED", 0, NULL)) as total_expired'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "FAILED", 0, NULL)) as total_failed'),
                \DB::raw('COUNT(IF(send_sms_queues.stat = "REJECTD", 0, NULL)) as total_reject'),
            )
            ->join('send_sms_queues', 'send_sms.id', '=', 'send_sms_queues.send_sms_id');
            //->where('user_id', $request->user_id);

        $histories = SendSms::select(
                'send_sms.user_id',
                DB::raw('DATE_FORMAT(send_sms.created_at, "%Y-%m-%d") as send_date'),
                DB::raw('SUM(send_sms_histories.use_credit) as total_credit'),
                DB::raw('COUNT(send_sms_histories.id) as total_submission'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "DELIVRD", 0, NULL)) as total_delivered'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "PENDING", 0, NULL)) as total_pending'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "Invalid", 0, NULL)) as total_invalid'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "EXPIRED", 0, NULL)) as total_expired'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "FAILED", 0, NULL)) as total_failed'),
                \DB::raw('COUNT(IF(send_sms_histories.stat = "REJECTD", 0, NULL)) as total_reject'),
            )
            ->join('send_sms_histories', 'send_sms.id', '=', 'send_sms_histories.send_sms_id')
            //->where('user_id', $request->user_id)
            ->union($queues)
            ->groupBy(DB::raw('Date(send_sms.campaign_send_date_time)'))
            ->toSql();
        return $histories;
        */

        $consumptions = ConsumptionView::select("*");
        if(!empty($request->user_id))
        {
            $consumptions->where('user_id', $request->user_id);
        }
        if(!empty($request->from_date))
        {
            $consumptions->whereDate('send_date', '>=', $request->from_date);
        }
        if(!empty($request->to_date))
        {
            $consumptions->whereDate('send_date', '<=', $request->to_date);
        }

        if(empty($request->from_date) && empty($request->to_date))
        {
            $consumptions->whereDate('send_date', date('Y-m-d'));
        }

        $consumptions = $consumptions->get()->toArray();
          
        return $consumptions;
    }

    public function getReportByTimeFrame(Request $request)
    {
        set_time_limit(0);
        if(in_array(loggedInUserType(), [0,3]))
        {
            try
            {
                $cacheName = date('Ymd');
                $seconds = (24 * 60 * 60); // 1 day

                $timeFrame = Cache::remember('time_frame_report_'.$cacheName, $seconds, function () 
                {
                    return TimeFrameReportView::select("*")->get()->toArray();
                });
                //return $timeFrame;
                $timeFrame = sumByKey($timeFrame);
                return $timeFrame;
            }
            catch (\Throwable $e) 
            {
                DB::rollback();
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
        }
        return response()->json(prepareResult(true, '', trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
    }

    public function reportExportByVoiceSmsId(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'voice_sms_id'    => 'required|exists:voice_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.$request->voice_sms_id.'.xlsx';
            $data = Excel::store(new VoiceReportExportByID($request->voice_sms_id), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function twowayReportLogExportByCamapign(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'campaign_id' => 'required|exists:send_sms,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [1,2]))
        {
            $checkCampaign = \DB::table('send_sms')
                ->where('id', $request->campaign_id)
                ->where('user_id', auth()->id())
                ->first();
            if(!$checkCampaign)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
        }

        try {
            $fileName = time().'-click-log-'.strtoupper($request->campaign_id).'.xlsx';
            $data = Excel::store(new TwoWayLogReportExportByCampaign($request->campaign_id), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];

            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function twowayReportResponseExportByCamapign(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'campaign_id' => 'required|exists:send_sms,id',
            'take_response' => 'required|in:1,2,3',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if(in_array(loggedInUserType(), [1,2]))
        {
            $checkCampaign = \DB::table('send_sms')
                ->where('id', $request->campaign_id)
                ->where('user_id', auth()->id())
                ->first();
            if(!$checkCampaign)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }
        }

        try {
            $fileName = time().'-response-'.strtoupper($request->campaign_id).'.xlsx';
            $data = Excel::store(new TwoWayResponseReportExportByCampaign($request->campaign_id, $request->take_response), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];

            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function exportWaReport(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'whats_app_send_sms_id'    => 'nullable|exists:whats_app_send_sms,id',
            'user_id'    => 'nullable|exists:users,id',
            'whats_app_template_id'    => 'nullable|exists:whats_app_templates,id',
            'from_date'    => 'nullable|date',
            'to_date'    => 'nullable|date',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.auth()->id().'.xlsx';
            $data = Excel::store(new ReportWaExport($request->whats_app_send_sms_id, $request->whats_app_template_id, $request->from_date, $request->to_date, $request->user_id), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function exportWaConversation(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'whats_app_send_sms_id'    => 'nullable|exists:whats_app_send_sms,id',
            'campaign_unique_key'    => 'nullable|exists:whats_app_reply_threads,queue_history_unique_key',
            'user_id'    => 'nullable|exists:users,id',
            'whats_app_template_id'    => 'nullable|exists:whats_app_templates,id',
            'from_date'    => 'nullable|date',
            'to_date'    => 'nullable|date',
            'display_phone_number' => 'nullable|exists:whats_app_configurations,display_phone_number_req'
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = 'conversation_'.time().'-'.auth()->id().'.xlsx';
            $data = Excel::store(new ReportWaConversationExport($request->whats_app_send_sms_id, $request->campaign_unique_key, $request->whats_app_template_id, $request->from_date, $request->to_date, $request->user_id, $request->display_phone_number), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getWaSummeryDownload(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'whats_app_send_sms_id'    => 'nullable|exists:whats_app_send_sms,id',
            'user_id'    => 'nullable|exists:users,id',
            'whats_app_template_id'    => 'nullable|exists:whats_app_templates,id',
            'configuration_id'    => 'nullable|exists:whats_app_configurations,id',
            'from_date'    => 'nullable|date',
            'to_date'    => 'nullable|date',
            'message_category' => 'nullable|in:Utility,Marketing,Authentication',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $fileName = time().'-'.auth()->id().'.xlsx';
            $data = Excel::store(new ReportWaExportSummary($request->whats_app_send_sms_id, $request->whats_app_template_id, $request->configuration_id, $request->message_category, $request->from_date, $request->to_date, $request->user_id), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function detailedReport(Request $request)
    {
        set_time_limit(0);
        try
        {
            $query = \DB::table('send_sms_queues as ssq')
                ->leftJoin('dlrcode_venders as dv', 'ssq.err', '=', 'dv.dlr_code')
                ->leftJoin('send_sms as sms', 'ssq.send_sms_id', '=', 'sms.id')
                ->select(
                    'ssq.err',
                    'dv.description',
                    \DB::raw('COUNT(ssq.id) AS total_count')
                )
                ->groupBy('ssq.err')
                ->orderBy('total_count', 'desc');
            if(in_array(loggedInUserType(), [0,3]))
            {
                if(!empty($request->user_id))
                {
                    $query->where('sms.user_id', $request->user_id);
                }
            }
            elseif(in_array(loggedInUserType(), [1]))
            {
                if(!empty($request->user_id))
                {
                    $query->where('sms.user_id', $request->user_id);
                }
                else
                {
                    $query->where('sms.user_id', auth()->id());
                }
            }
            else
            {
                $query->where('sms.user_id', auth()->id());
            }

            if(!empty($request->sender_id))
            {
                $query->where('sms.sender_id', $request->sender_id);
            }

            if(!empty($request->from))
            {
                $query->whereDate('sms.campaign_send_date_time','>=', $request->from);
            }
            if(!empty($request->to))
            {
                $query->whereDate('sms.campaign_send_date_time','<=', $request->to);
            }

            $query = $query->get();

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        }
        catch (\Throwable $e) 
        {
            DB::rollback();
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function exportDetailedReport(Request $request)
    {
        set_time_limit(0);
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'nullable|exists:users,id',
            'sender_id'    => 'nullable|exists:manage_sender_ids,sender_id',
            'dlt_template_id'    => 'nullable|exists:dlt_templates,dlt_template_id',
            'from_date'    => 'nullable|date',
            'to_date'    => 'nullable|date',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $from = $request->from_date . " 00:00:00";
        $to   = $request->to_date   . " 23:59:59";

        $filters = [
            'user_id'         => $request->user_id,
            'sender_id'       => $request->sender_id,
            'dlt_template_id' => $request->dlt_template_id,
        ];

        try {
            $fileName = time().'-'.auth()->id().'.xlsx';
            $data = Excel::store(new SmsDetailedReportExport($from, $to, $filters), $fileName, 'export_path');
            $csvfile = 'reports/'.$fileName;
            $return = [
                'file_path' => $csvfile
            ];
            return response()->json(prepareResult(false, $return, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
