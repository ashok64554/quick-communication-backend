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
        /*return 
            "CREATE VIEW consumption_views AS
                SELECT 
                    users.id, 
                    users.name, 
                    users.email,
                    (SELECT count(*) FROM send_sms
                                WHERE send_sms.user_id = users.id
                            ) AS total_send_sms
                FROM users";*/

        return 'CREATE VIEW consumption_views AS (select `send_sms`.`user_id`, DATE_FORMAT(send_sms.created_at, "%Y-%m-%d") as send_date,
SUM(send_sms_histories.use_credit) as total_credit, COUNT(send_sms_histories.id) as total_submission,
COUNT(IF(send_sms_histories.stat = "DELIVRD", 0, NULL)) as total_delivered, COUNT(IF(send_sms_histories.stat =
"PENDING", 0, NULL)) as total_pending, COUNT(IF(send_sms_histories.stat = "Invalid", 0, NULL)) as total_invalid,
COUNT(IF(send_sms_histories.stat = "EXPIRED", 0, NULL)) as total_expired, COUNT(IF(send_sms_histories.stat = "FAILED",
0, NULL)) as total_failed, COUNT(IF(send_sms_histories.stat = "REJECTD", 0, NULL)) as total_reject from
`send_sms` inner join `send_sms_histories` on `send_sms`.`id` = `send_sms_histories`.`send_sms_id` group by
Date(send_sms.campaign_send_date_time)) union (select `send_sms`.`user_id`, DATE_FORMAT(send_sms.created_at, "%Y-%m-%d")
as send_date, SUM(send_sms_queues.use_credit) as total_credit, COUNT(send_sms_queues.id) as total_submission,
COUNT(IF(send_sms_queues.stat = "DELIVRD", 0, NULL)) as total_delivered, COUNT(IF(send_sms_queues.stat = "PENDING", 0,
NULL)) as total_pending, COUNT(IF(send_sms_queues.stat = "Invalid", 0, NULL)) as total_invalid,
COUNT(IF(send_sms_queues.stat = "EXPIRED", 0, NULL)) as total_expired, COUNT(IF(send_sms_queues.stat = "FAILED", 0,
NULL)) as total_failed, COUNT(IF(send_sms_queues.stat = "REJECTD", 0, NULL)) as total_reject from `send_sms`
inner join `send_sms_queues` on `send_sms`.`id` = `send_sms_queues`.`send_sms_id`)';
    }


    private function dropView(): string
    {
        return "DROP VIEW IF EXISTS `consumption_views`;";
    }
};
