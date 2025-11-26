<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppReplyThread;
use Illuminate\Support\Collection;

class ReportWaConversationExport implements FromCollection, WithHeadings
{
    use Exportable;

    protected $whats_app_send_sms_id;
    protected $queue_history_unique_key;
    protected $whats_app_template_id;
    protected $from_date;
    protected $to_date;
    protected $user_id;
    protected $display_phone_number;
    
    public function __construct($whats_app_send_sms_id, $queue_history_unique_key, $whats_app_template_id, $from_date, $to_date, $user_id, $display_phone_number)
    {
        $this->whats_app_send_sms_id = $whats_app_send_sms_id;
        $this->queue_history_unique_key = $queue_history_unique_key;
        $this->whats_app_template_id = $whats_app_template_id;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->user_id = $user_id;
        $this->display_phone_number = $display_phone_number;
    }

    public function headings(): array {
        return [
          'WA Buss Number',
          'Campaign Name',
          'Template Name',
          'Profile Name',
          'Mobile No.',
          'Message',
          'Received Date',
          'Token',
        ];
     }

    public function collection()
    {
        $arr = [];
        $query = WhatsAppReplyThread::with('WhatsAppSendSms:id,user_id,whats_app_configuration_id,whats_app_template_id,campaign','user:id,name','WhatsAppSendSms.whatsAppTemplate:id,template_name');


        if(in_array(loggedInUserType(), [1,2]))
        {
            if(!empty($this->display_phone_number))
            {
                $checkNum = \DB::table('whats_app_configurations')->where('display_phone_number_req', $this->display_phone_number)->count();
                if($checkNum>0)
                {
                    $query->where("display_phone_number", $this->display_phone_number);
                }
                else
                {
                    $query->where("user_id", auth()->id());
                }
            }
            else
            {
                $query->where("user_id", auth()->id());
            }
            
        }
        else
        {
            if(!empty($this->user_id))
            {
                $query->where("user_id", $this->user_id);
            }

            if(!empty($this->display_phone_number))
            {
                $query->where("display_phone_number", $this->display_phone_number);
            }
        }

        if(!empty($this->whats_app_send_sms_id))
        {
            $query->where("whats_app_send_sms_id", $this->whats_app_send_sms_id);
        }

        if(!empty($this->queue_history_unique_key))
        {
            $query->where("queue_history_unique_key", $this->queue_history_unique_key);
        }

        if(!empty($this->from_date) && empty($this->to_date))
        {
            $query->whereDate("received_date", $this->from_date);
        }

        if(empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("received_date", $this->to_date);
        }

        if(!empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("received_date", ">=", $this->from_date)
                ->whereDate("received_date", "<=", $this->to_date);
        }

        
        
        $reporDatas = $query->get();
        if($reporDatas->count()>0)
        {
            foreach($reporDatas as $key => $reporData)
            {
                $arr[] = [
                    'WA Buss Number' => "'$reporData->display_phone_number'",
                    'Campaign Name' => @$reporData->WhatsAppSendSms->campaign,
                    'Template Name' => @$reporData->WhatsAppSendSms->whatsAppTemplate->template_name,
                    'Profile Name' => $reporData->profile_name,
                    'Mobile No.' => "'$reporData->phone_number_id'",
                    'Message' => $reporData->message,
                    'Received Date' => $reporData->received_date,
                    'Token' => $reporData->response_token,
                ];
            }
        }
        return new Collection([
            $arr
        ]);
    }
}
