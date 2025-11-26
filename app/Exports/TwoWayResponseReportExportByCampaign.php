<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TwoWayResponseReportExportByCampaign implements WithMultipleSheets
{
    use Exportable;

    protected $campaign_id;
    protected $take_response;

    public function __construct($campaign_id, $take_response)
    {
        $this->campaign_id = $campaign_id;
        $this->take_response = $take_response;
    }

    public function sheets(): array
    {
        set_time_limit(7200);
        $sheets = [];
        if($this->take_response==1)
        {
            $totalRecords = \DB::table(env('DB_DATABASE2W').'.two_way_comm_interests')
                ->where('send_sms_id', $this->campaign_id)
                ->count();
        }
        elseif($this->take_response==2)
        {
            $totalRecords = \DB::table(env('DB_DATABASE2W').'.two_way_comm_feedbacks')
                ->where('send_sms_id', $this->campaign_id)
                ->count();
        }
        else
        {
            $totalRecords = \DB::table(env('DB_DATABASE2W').'.two_way_comm_ratings')
                ->where('send_sms_id', $this->campaign_id)
                ->count();
        }
        
        $limit = env('EXPORT_MULTIFILE_LIMIT', 1000000);
        $loopLimit = ceil($totalRecords / $limit);
        $start = ($totalRecords>0) ? 1 : 0;
        for ($i = $start; $i <= $loopLimit; $i++) {
            $sheets[] = new MultipleSheetTwoWayResponse($this->campaign_id, $this->take_response, $limit, $i, $totalRecords);
        }
        return $sheets;
    }
}
