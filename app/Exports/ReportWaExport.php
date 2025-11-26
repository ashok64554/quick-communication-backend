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

class ReportWaExport implements FromCollection, WithHeadings
{
    use Exportable;

    protected $whats_app_send_sms_id;
    protected $whats_app_template_id;
    protected $from_date;
    protected $to_date;
    protected $user_id;

    public function __construct($whats_app_send_sms_id, $whats_app_template_id, $from_date, $to_date, $user_id)
    {
        $this->whats_app_send_sms_id = $whats_app_send_sms_id;
        $this->whats_app_template_id = $whats_app_template_id;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->user_id = $user_id;
    }

    public function headings(): array {
        return [
          'User Name',
          'Mobile No.',
          'Template',
          'Message',
          'Used Credit',
          'Submit Date',
          'Send Date',
          'Deliver Date',
          'Read Date',
          'Status',
          'Error'
        ];
     }

    public function collection()
    {
        $arr = [];
        $query = WhatsAppSendSms::select('id','user_id','whats_app_configuration_id','sender_number','whats_app_template_id','campaign_send_date_time')->with('whatsAppSendSmsQueues:id,whats_app_send_sms_id,mobile,message,use_credit,stat,error_info,submit_date,sent_date_time,delivered_date_time,read_date_time','user:id,name','whatsAppConfiguration:id,display_phone_number,verified_name','whatsAppTemplate:id,template_name');

        if(in_array(loggedInUserType(), [1,2]))
        {
            $query->where("user_id", auth()->id());
        }
        else
        {
            if(!empty($this->user_id))
            {
                $query->where("user_id", $this->user_id);
            }
        }

        if(!empty($this->whats_app_send_sms_id))
        {
            $query->where("id", $this->whats_app_send_sms_id);
        }

        if(!empty($this->whats_app_template_id))
        {
            $query->where("whats_app_template_id", $this->whats_app_template_id);
        }

        if(!empty($this->from_date) && empty($this->to_date))
        {
            $query->whereDate("campaign_send_date_time", $this->from_date);
        }

        if(empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("campaign_send_date_time", $this->to_date);
        }

        if(!empty($this->from_date) && !empty($this->to_date))
        {
            $query->whereDate("campaign_send_date_time", ">=", $this->from_date)
                ->whereDate("campaign_send_date_time", "<=", $this->to_date);
        }

        $reporDatas = $query->get();
        if($reporDatas->count()>0)
        {
            foreach($reporDatas as $key => $reporData)
            {
                if($reporData->whatsAppSendSmsQueues->count()>0)
                {
                    foreach($reporData->whatsAppSendSmsQueues as $data)
                    {
                        $arr[] = [
                          'User Name' => @$reporData->user->name,
                          'Mobile No.' => $data->mobile,
                          'Template' => @$reporData->whatsAppTemplate->template_name,
                          'Message' => $data->message,
                          'Used Credit' => $data->use_credit,
                          'Submit Date' => $reporData->campaign_send_date_time,
                          'Send Date' => $data->sent_date_time,
                          'Deliver Date' => $data->delivered_date_time,
                          'Read Date' => $data->read_date_time,
                          'Status' => $data->stat,
                          'Error' => $data->error_info,
                        ];
                    }
                }

                if($reporData->whatsAppSendSmsHistories->count()>0)
                {
                    foreach($reporData->whatsAppSendSmsHistories as $data)
                    {
                        $arr[] = [
                          'User Name' => @$reporData->user->name,
                          'Mobile No.' => $data->mobile,
                          'Template' => @$reporData->whatsAppTemplate->template_name,
                          'Message' => $data->message,
                          'Used Credit' => $data->use_credit,
                          'Submit Date' => $reporData->campaign_send_date_time,
                          'Send Date' => $data->sent_date_time,
                          'Deliver Date' => $data->delivered_date_time,
                          'Read Date' => $data->read_date_time,
                          'Status' => $data->stat,
                          'Error' => $data->error_info,
                        ];
                    }
                }
            }
        }
        return new Collection([
            $arr
        ]);
    }
}
