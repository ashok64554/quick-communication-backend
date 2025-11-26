<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\VoiceSms;
use App\Models\VoiceSmsHistory;
use App\Models\VoiceSmsQueue;
use Illuminate\Support\Collection;

class VoiceReportExportByID implements FromCollection, WithHeadings
{
    use Exportable;

    protected $voice_sms_id;

    public function __construct($voice_sms_id)
    {
        $this->voice_sms_id = $voice_sms_id;
    }

    public function headings(): array {
        return [
          'User Name',
          'Mobile No.',
          'Voice ID',
          'Used Credit',
          'Submit Date',
          'Done Date',
          'Start Date',
          'End Date',
          'DTMF',
          'Status',
        ];
     }

    public function collection()
    {
        $arr = [];
        $reporData = VoiceSms::select('id','user_id','voice_upload_id')->with('voiceSmsQueues:id,voice_sms_id,mobile,use_credit,stat,err,submit_date,done_date,start_time,end_time,duration,dtmf','voiceSmsHistories:id,voice_sms_id,mobile,use_credit,stat,err,submit_date,done_date,start_time,end_time,duration,dtmf','user:id,name','voiceUpload:id,voiceId')
        ->find($this->voice_sms_id);
        if($reporData)
        {
            if($reporData->voiceSmsQueues->count()>0)
            {
                foreach($reporData->voiceSmsQueues as $data)
                {
                    $arr[] = [
                      'User Name' => $reporData->user->name,
                      'Mobile No.' => $data->mobile,
                      'Voice ID' => $reporData->voiceUpload->voiceId,
                      'Used Credit' => $data->use_credit,
                      'Submit Date' => $data->submit_date,
                      'Done Date' => $data->done_date,
                      'Start Date' => $data->start_date,
                      'End Date' => $data->end_date,
                      'DTMF' => $data->dtmf,
                      'Status' => $data->stat,
                    ];
                }
            }
            else
            {
                foreach($reporData->voiceSmsHistories as $data)
                {
                    $arr[] = [
                      'User Name' => $reporData->user->name,
                      'Mobile No.' => $data->mobile,
                      'Voice ID' => $reporData->voiceUpload->voiceId,
                      'Used Credit' => $data->use_credit,
                      'Submit Date' => $data->submit_date,
                      'Done Date' => $data->done_date,
                      'Start Date' => $data->start_date,
                      'End Date' => $data->end_date,
                      'DTMF' => $data->dtmf,
                      'Status' => $data->stat,
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
