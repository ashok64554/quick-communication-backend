<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use DB;


class MultipleSheetTwoWayResponse implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    protected $campaign_id;
    protected $take_response;
    protected $limit;
    protected $page_number;
    protected $totalRecords;

    public function __construct($campaign_id, $take_response, $limit, $page_number, $totalRecords)
    {
        $this->campaign_id = $campaign_id;
        $this->take_response = $take_response;
        $this->limit = $limit;
        $this->page_number = $page_number;
        $this->totalRecords = $totalRecords;
    }

    public function headings(): array {
        if($this->take_response==1)
        {
            return [
              'Mobile',
              'IP Address',
              'Interest',
              'Date Time'
            ];
        }
        elseif($this->take_response==2)
        {
            return [
              'Name',
              'Mobile',
              'Email',
              'Subject',
              'Comment',
              'IP Address',
              'Date Time'
            ];
        }
        else
        {
            return [
              'Mobile',
              'IP Address',
              'Rating',
              'Date Time'
            ];
        }
    }

    public function collection()
    {
        if($this->totalRecords<1)
        {
            $collection = DB::table(env('DB_DATABASE2W').'.link_click_logs')
                ->where('send_sms_id', $this->campaign_id)
                ->limit(0)
                ->get();
            return $collection; 
        }

        if($this->take_response==1)
        {
            $collection = DB::table(env('DB_DATABASE2W').'.two_way_comm_interests')
                ->select('mobile', 'ip', DB::raw("(CASE WHEN is_interest='1' THEN 'Yes' ELSE 'No' END) as interest"), 'created_at')
                ->where('send_sms_id', $this->campaign_id)
                ->offset(($this->page_number - 1) * $this->limit)
                ->limit($this->limit)
                ->get();
        }
        elseif($this->take_response==2)
        {
            $collection = DB::table(env('DB_DATABASE2W').'.two_way_comm_feedbacks')
                ->select('name', 'mobile', 'email', 'subject', 'comment', 'ip', 'created_at')
                ->where('send_sms_id', $this->campaign_id)
                ->offset(($this->page_number - 1) * $this->limit)
                ->limit($this->limit)
                ->get();
        }
        else
        {
            $collection = DB::table(env('DB_DATABASE2W').'.two_way_comm_ratings')
                ->select('mobile', 'ip', 'rating', 'created_at')
                ->where('send_sms_id', $this->campaign_id)
                ->offset(($this->page_number - 1) * $this->limit)
                ->limit($this->limit)
                ->get();
        }

        return $collection;
    }

    public function title(): string
    {
        if($this->take_response==1)
        {
            $title = 'interest';
        }
        elseif($this->take_response==2)
        {
            $title = 'feedback';
        }
        else
        {
            $title = 'rating';
        }
        return $title.'-'. $this->page_number;
    }
}
