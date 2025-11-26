<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use DB;

class MultipleSheetTwoWayLogs implements FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    protected $campaign_id;
    protected $limit;
    protected $page_number;
    protected $totalRecords;

    public function __construct($campaign_id, $limit, $page_number, $totalRecords)
    {
        $this->campaign_id = $campaign_id;
        $this->limit = $limit;
        $this->page_number = $page_number;
        $this->totalRecords = $totalRecords;
    }

    public function headings(): array {
        return [
          'Mobile',
          'IP Address',
          'Browser Name',
          'Date Time'
        ];
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

        $collection = DB::table(env('DB_DATABASE2W').'.link_click_logs')
            ->select('mobile', 'ip', 'browserName', 'created_at')
            ->where('send_sms_id', $this->campaign_id)
            ->offset(($this->page_number - 1) * $this->limit)
            ->limit($this->limit)
            ->get();
        return $collection;
    }

    public function title(): string
    {
        return 'log-'. $this->page_number;
    }
}
