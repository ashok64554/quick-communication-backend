<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        \DB::statement($this->dropView());
        \DB::statement($this->createView());
    }

    public function down()
    {
        \DB::statement($this->dropView());
    }


    private function createView(): string
    {
        return 
            "CREATE VIEW time_frame_report_views AS
                (SELECT
                     FLOOR(TIMESTAMPDIFF(SECOND, `submit_date`, `done_date`)) AS difference_in_seconds,
                     COUNT('difference_in_seconds') AS total_delivered
                 FROM send_sms_queues 
                 WHERE stat = 'DELIVRD' AND 
                 FLOOR(TIMESTAMPDIFF(SECOND, `submit_date`, `done_date`)) < 30
                 GROUP BY difference_in_seconds) 
                UNION
                (SELECT
                    FLOOR(TIMESTAMPDIFF(SECOND, `submit_date`, `done_date`)) AS difference_in_seconds,
                    COUNT('difference_in_seconds') AS total_delivered
                FROM send_sms_histories 
                WHERE stat = 'DELIVRD' AND 
                FLOOR(TIMESTAMPDIFF(SECOND, `submit_date`, `done_date`)) < 30
                GROUP BY difference_in_seconds)";
    }

    private function dropView(): string
    {
        return "DROP VIEW IF EXISTS `time_frame_report_views`;";
    }
};
