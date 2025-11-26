<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TwoWayLogReportExportByCampaign implements WithMultipleSheets
{
    use Exportable;

    protected $campaign_id;

    public function __construct($campaign_id)
    {
        $this->campaign_id = $campaign_id;
    }

    public function sheets(): array
    {
        set_time_limit(7200);
        $sheets = [];
        $totalRecords = \DB::table(env('DB_DATABASE2W').'.link_click_logs')
                ->where('send_sms_id', $this->campaign_id)
                ->count();
        $limit = env('EXPORT_MULTIFILE_LIMIT', 1000000);
        $loopLimit = ceil($totalRecords / $limit);
        $start = ($totalRecords>0) ? 1 : 0;
        for ($i = $start; $i <= $loopLimit; $i++) {
            $sheets[] = new MultipleSheetTwoWayLogs($this->campaign_id, $limit, $i, $totalRecords);
        }
        return $sheets;
    }
}
