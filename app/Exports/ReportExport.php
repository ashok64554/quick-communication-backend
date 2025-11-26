<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\SendSmsQueue;
use App\Models\SendSmsHistory;

class ReportExport implements WithMultipleSheets
{
    use Exportable;

    protected $sender_id;
    protected $from_date;
    protected $to_date;
    
    public function __construct($sender_id, $from_date, $to_date)
    {
        $this->sender_id = $sender_id;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
    }

    public function sheets(): array
    {
        set_time_limit(7200);
        $sheets = [];
        $result = \DB::select('CALL getTotalCountBySenderID(?,?,?)', [$this->sender_id, $this->from_date, $this->to_date]);
        $totalRecords = $result[0]->total_count;
        $limit = env('EXPORT_MULTIFILE_LIMIT', 1000000);
        $loopLimit = ceil($totalRecords / $limit);
        $start = ($totalRecords>0) ? 1 : 0;
        for ($i = $start; $i <= $loopLimit; $i++) {
            $sheets[] = new MultipleSheetExport($this->sender_id, $this->from_date, $this->to_date, $limit, $i, $totalRecords);
        }
        return $sheets;
    }
}
