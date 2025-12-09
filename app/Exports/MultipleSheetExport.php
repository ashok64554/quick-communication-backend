<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\SendSms;
use App\Models\SendSmsHistory;
use App\Models\SendSmsQueue;
use Illuminate\Support\Collection;
use DB;

class MultipleSheetExport implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    protected $sender_id;
    protected $from_date;
    protected $to_date;
    protected $limit;
    protected $page_number;
    protected $totalRecords;
    
    public function __construct($sender_id, $from_date, $to_date, $limit, $page_number, $totalRecords)
    {
        $this->sender_id = $sender_id;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->limit = $limit;
        $this->page_number = $page_number;
        $this->totalRecords = $totalRecords;
    }

    public function headings(): array {
        return [
          'RAND STR',
          'User Name',
          'Message',
          'Used Credit',
          'Submit Date',
          'Done Date',
          'Status',
          'Code',
          'DLT Template ID',
          'Mobile No.',
        ];
    }
    
    public function collection()
    {
        if($this->totalRecords<1)
        {
            $collection = DB::table('send_sms_queues')
                ->where('id', 1)
                ->limit(0)
                ->get();
            return $collection; 
        }
        $from_date = !empty($this->from_date) ? $this->from_date : date('Y-m-01');
        $to_date = !empty($this->to_date) ? $this->to_date : date('Y-m-t');
        $queues = DB::table('send_sms_queues')
            ->select('send_sms_queues.unique_key','users.name','send_sms_queues.message','send_sms_queues.use_credit','send_sms_queues.submit_date','send_sms_queues.done_date','send_sms_queues.stat','send_sms_queues.err')
            ->addSelect(DB::raw("CONCAT(\"'\", dlt_templates.dlt_template_id, \"'\") AS dlt_template_id"))
            ->addSelect(DB::raw("CONCAT(\"'\", send_sms_queues.mobile, \"'\") AS mobile"))
            ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
            ->join('users', 'send_sms.user_id', 'users.id')
            ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
            ->where('send_sms.sender_id', $this->sender_id)
            ->whereDate('send_sms.campaign_send_date_time', '>=', $from_date)
            ->whereDate('send_sms.campaign_send_date_time', '<=', $to_date)
            ->orderBy('send_sms.user_id', 'ASC');
        
        if(strtotime($from_date)<strtotime(date('Y-m-d')))
        {
            $histories = DB::table('send_sms_histories')
            ->select('send_sms_histories.unique_key','users.name','send_sms_histories.message','send_sms_histories.use_credit','send_sms_histories.submit_date','send_sms_histories.done_date','send_sms_histories.stat','send_sms_histories.err')
            ->addSelect(DB::raw("CONCAT(\"'\", dlt_templates.dlt_template_id, \"'\") AS dlt_template_id"))
            ->addSelect(DB::raw("CONCAT(\"'\", send_sms_histories.mobile, \"'\") AS mobile"))
            ->join('send_sms', 'send_sms_histories.send_sms_id', 'send_sms.id')
            ->join('users', 'send_sms.user_id', 'users.id')
            ->join('dlt_templates', 'send_sms.dlt_template_id', 'dlt_templates.id')
            ->where('send_sms.sender_id', $this->sender_id)
            ->whereDate('send_sms.campaign_send_date_time', '>=', $from_date)
            ->whereDate('send_sms.campaign_send_date_time', '<=', $to_date)
            ->orderBy('send_sms.user_id', 'ASC')
            ->union($queues)
            ->offset(($this->page_number - 1) * $this->limit)
            ->limit($this->limit)
            ->get();
        }
        else
        {
            $histories = $queues->offset(($this->page_number - 1) * $this->limit)
            ->limit($this->limit)
            ->get();
        }
        return $histories;
    }

    public function title(): string
    {
        return $this->sender_id.'-'. $this->page_number;
    }
}
