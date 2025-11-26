<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppSendSmsHistory;
use App\Models\WhatsAppReplyThread;
use Illuminate\Support\Collection;
use \DB;

class ReportWaExportSummary implements FromCollection, WithHeadings
{
    use Exportable;

    protected $whats_app_send_sms_id;
    protected $whats_app_template_id;
    protected $configuration_id;
    protected $message_category;
    protected $from_date;
    protected $to_date;
    protected $user_id;

    public function __construct($whats_app_send_sms_id, $whats_app_template_id, $configuration_id, $message_category, $from_date, $to_date, $user_id)
    {
        $this->whats_app_send_sms_id = $whats_app_send_sms_id;
        $this->whats_app_template_id = $whats_app_template_id;
        $this->configuration_id = $configuration_id;
        $this->message_category = $message_category;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->user_id = $user_id;
    }

    public function headings(): array {
        return [
          'User Name',
          'Category',
          'Total Sent/Read',
          'Used Credit',
        ];
    }

    public function collection()
    {
        set_time_limit(0);
        $arr = [];

        // Query Table
        $query = WhatsAppSendSmsQueue::select('users.name', 'whats_app_send_sms_queues.template_category',
                DB::raw('SUM(use_credit) as used_credit'),
                DB::raw('COUNT(whats_app_send_sms_queues.id) as total_delivered')
            )
            ->join('users', 'whats_app_send_sms_queues.user_id', 'users.id')
            ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
            ->whereIn('whats_app_send_sms_queues.stat', ['read','delivered'])
            ->groupBy(['whats_app_send_sms_queues.template_category', 'whats_app_send_sms_queues.user_id'])
            ->orderBy('users.name', 'ASC');

        if(in_array(loggedInUserType(), [1,2]))
        {
            $query->where("whats_app_send_sms_queues.user_id", auth()->id());
        }
        else
        {
            if(!empty($this->user_id))
            {
                $query->where("whats_app_send_sms_queues.user_id", $this->user_id);
            }
        }

        if(!empty($this->whats_app_send_sms_id))
        {
            $query->where("whats_app_send_sms_queues.whats_app_send_sms_id", $this->whats_app_send_sms_id);
        }

        if(!empty($this->whats_app_template_id))
        {
            $query->where("whats_app_send_sms.whats_app_template_id", $this->whats_app_template_id);
        }

        if(!empty($this->configuration_id))
        {
            $query->where("whats_app_send_sms.whats_app_configuration_id", $this->configuration_id);
        }

        if(!empty($this->message_category))
        {
            $query->where("whats_app_send_sms_queues.template_category", $this->message_category);
        }

        if(!empty($this->from_date) && empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", $this->from_date);
        }

        if(empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", $this->to_date);
        }

        if(!empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", ">=", $this->from_date)
                ->whereDate("whats_app_send_sms.campaign_send_date_time", "<=", $this->to_date);
        }

        $reporDatas = $query->get();
        if($reporDatas->count()>0)
        {
            foreach($reporDatas as $key => $reporData)
            {
                $arr[] = [
                    'User Name' => $reporData->name,
                    'Category' => $reporData->template_category,
                    'Total Sent/Read' => $reporData->total_delivered,
                    'Used Credit' => $reporData->used_credit
                ];
            }
        }

        // History Table
        $query = WhatsAppSendSmsHistory::select('users.name', 'whats_app_send_sms_histories.template_category',
                DB::raw('SUM(use_credit) as used_credit'),
                DB::raw('COUNT(whats_app_send_sms_histories.id) as total_delivered')
            )
            ->join('users', 'whats_app_send_sms_histories.user_id', 'users.id')
            ->join('whats_app_send_sms', 'whats_app_send_sms_histories.whats_app_send_sms_id', 'whats_app_send_sms.id')
            ->whereIn('whats_app_send_sms_histories.stat', ['read','delivered'])
            ->groupBy(['whats_app_send_sms_histories.template_category', 'whats_app_send_sms_histories.user_id'])
            ->orderBy('users.name', 'ASC');

        if(in_array(loggedInUserType(), [1,2]))
        {
            $query->where("whats_app_send_sms_histories.user_id", auth()->id());
        }
        else
        {
            if(!empty($this->user_id))
            {
                $query->where("whats_app_send_sms_histories.user_id", $this->user_id);
            }
        }

        if(!empty($this->whats_app_send_sms_id))
        {
            $query->where("whats_app_send_sms_histories.whats_app_send_sms_id", $this->whats_app_send_sms_id);
        }

        if(!empty($this->whats_app_template_id))
        {
            $query->where("whats_app_send_sms.whats_app_template_id", $this->whats_app_template_id);
        }

        if(!empty($this->configuration_id))
        {
            $query->where("whats_app_send_sms.whats_app_configuration_id", $this->configuration_id);
        }

        if(!empty($this->message_category))
        {
            $query->where("whats_app_send_sms_histories.template_category", $this->message_category);
        }

        if(!empty($this->from_date) && empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", $this->from_date);
        }

        if(empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", $this->to_date);
        }

        if(!empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("whats_app_send_sms.campaign_send_date_time", ">=", $this->from_date)
                ->whereDate("whats_app_send_sms.campaign_send_date_time", "<=", $this->to_date);
        }

        $reporDatas = $query->get();
        if($reporDatas->count()>0)
        {
            foreach($reporDatas as $key => $reporData)
            {
                $arr[] = [
                    'User Name' => $reporData->name,
                    'Category' => $reporData->template_category,
                    'Total Sent/Read' => $reporData->total_delivered,
                    'Used Credit' => $reporData->used_credit
                ];
            }
        }
        //\Log::info($arr);
        $collection = collect($arr);

        $result = $collection
            ->groupBy(fn($item) => $item['User Name'].'|'.$item['Category'])
            ->map(function ($group) {
                return [
                    'User Name' => $group->first()['User Name'],
                    'Category' => $group->first()['Category'],
                    'Total Sent/Read' => $group->sum('Total Sent/Read'),
                    'Used Credit' => $group->sum(fn($item) => (float)$item['Used Credit']),
                ];
            })
            ->values()
            ->toArray();

        
        return new Collection([
            $result
        ]);
    }
}
