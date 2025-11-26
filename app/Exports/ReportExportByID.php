<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\SendSms;
use App\Models\SendSmsHistory;
use App\Models\SendSmsQueue;
use Illuminate\Support\Collection;

class ReportExportByID implements FromCollection, WithHeadings
{
    use Exportable;

    protected $send_sms_id;

    public function __construct($send_sms_id)
    {
        $this->send_sms_id = $send_sms_id;
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
        $reporData = SendSms::select('id','user_id','dlt_template_id','sender_id')->with('sendSmsQueues:id,send_sms_id,mobile,message,use_credit,stat,err,submit_date,done_date','sendSmsHistories:id,send_sms_id,mobile,message,use_credit,stat,err,submit_date,done_date','user:id,name','dltTemplate:id,dlt_template_id')
        ->find($this->send_sms_id);
        if($reporData)
        {
            if($reporData->sendSmsQueues->count()>0)
            {
                foreach($reporData->sendSmsQueues as $data)
                {
                    $arr[] = [
                      'User Name' => $reporData->user->name,
                      'Mobile No.' => $data->mobile,
                      'DLT Template ID' => $reporData->dltTemplate->dlt_template_id,
                      'Message' => $data->message,
                      'Used Credit' => $data->use_credit,
                      'Submit Date' => $data->submit_date,
                      'Done Date' => $data->done_date,
                      'Status' => $data->stat,
                      'Code' => $data->err,
                    ];
                }
            }
            else
            {
                foreach($reporData->sendSmsHistories as $data)
                {
                    $arr[] = [
                      'User Name' => $reporData->user->name,
                      'Mobile No.' => $data->mobile,
                      'DLT Template ID' => $reporData->dltTemplate->dlt_template_id,
                      'Message' => $data->message,
                      'Used Credit' => $data->use_credit,
                      'Submit Date' => $data->submit_date,
                      'Done Date' => $data->done_date,
                      'Status' => $data->stat,
                      'Code' => $data->err,
                    ];
                }
            }
            return new Collection([
                $arr
            ]);
        }
        return;
    }
}
