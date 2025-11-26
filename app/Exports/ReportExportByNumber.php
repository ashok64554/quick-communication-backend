<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\SendSms;
use App\Models\SendSmsHistory;
use App\Models\SendSmsQueue;
use Illuminate\Support\Collection;

class ReportExportByNumber implements FromCollection, WithHeadings
{
    use Exportable;

    protected $user_id;
    protected $mobile;
    protected $from_date;
    protected $to_date;

    public function __construct($user_id, $mobile, $from_date, $to_date)
    {
        $this->user_id = $user_id;
        $this->mobile = $mobile;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
    }

    public function headings(): array {
        return [
          'User Name',
          'Mobile No.',
          'DLT Template ID',
          'Message',
          'Used Credit',
          'Submit Date',
          'Done Date',
          'Status',
          'Code',
        ];
     }

    public function collection()
    {
        $arr = [];
        $queue = SendSmsQueue::select('send_sms_queues.id','send_sms_queues.mobile','send_sms_queues.message','send_sms_queues.use_credit','send_sms_queues.submit_date','send_sms_queues.done_date','send_sms_queues.stat','send_sms_queues.err','send_sms.sender_id','users.name','users.name','dlt_templates.dlt_template_id')
        ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
        ->join('users', 'send_sms.user_id', 'users.id')
        ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
        ->where('send_sms_queues.mobile', '91'.$this->mobile);
        if(count($this->user_id)>0)
        {
            $queue->whereIn('send_sms.user_id', $this->user_id);
        }

        if(!empty($this->from_date))
        {
            $queue->whereDate('send_sms.campaign_send_date_time', '>=', $this->from_date);
        }

        if(!empty($this->to_date))
        {
            $queue->whereDate('send_sms.campaign_send_date_time', '<=', $this->to_date);
        }

        $history = SendSmsHistory::select('send_sms_histories.id','send_sms_histories.mobile','send_sms_histories.message','send_sms_histories.use_credit','send_sms_histories.submit_date','send_sms_histories.done_date','send_sms_histories.stat','send_sms_histories.err','send_sms.sender_id','users.name','users.name','dlt_templates.dlt_template_id')
        ->join('send_sms', 'send_sms_histories.send_sms_id', 'send_sms.id')
        ->join('users', 'send_sms.user_id', 'users.id')
        ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
        ->where('send_sms_histories.mobile', '91'.$this->mobile);
        if(count($this->user_id)>0)
        {
            $history->whereIn('send_sms.user_id', $this->user_id);
        }

        if(!empty($this->from_date))
        {
            $history->whereDate('send_sms.campaign_send_date_time', '>=', $this->from_date);
        }
        
        if(!empty($this->to_date))
        {
            $history->whereDate('send_sms.campaign_send_date_time', '<=', $this->to_date);
        }
        $records = $history->union($queue)->get();
        
        foreach ($records as $key => $record) 
        {
            $arr[] = [
              'User Name' => $record->name,
              'Mobile No.' => $record->mobile,
              'DLT Template ID' => $record->dlt_template_id,
              'Message' => $record->message,
              'Used Credit' => $record->use_credit,
              'Submit Date' => $record->submit_date,
              'Done Date' => $record->done_date,
              'Status' => $record->stat,
              'Code' => $record->err,
            ];
        }

        return new Collection([
            $arr
        ]);
    }
}