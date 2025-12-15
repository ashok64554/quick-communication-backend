<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         $db = DB::getDatabaseName();

        $indexes = [
            [
                'table' => 'send_sms',
                'index' => 'idx_sms_date_user_sender',
                'sql'   => "CREATE INDEX idx_sms_date_user_sender
                            ON send_sms (campaign_send_date_time, user_id, sender_id, dlt_template_id)"
            ],
            [
                'table' => 'send_sms_queues',
                'index' => 'idx_ssq_send_sms_id',
                'sql'   => "CREATE INDEX idx_ssq_send_sms_id
                            ON send_sms_queues (send_sms_id, submit_date)"
            ],
            [
                'table' => 'send_sms_histories',
                'index' => 'idx_ssh_send_sms_id',
                'sql'   => "CREATE INDEX idx_ssh_send_sms_id
                            ON send_sms_histories (send_sms_id, submit_date)"
            ],
        ];

        foreach ($indexes as $idx) {
            $exists = DB::selectOne("
                SELECT 1
                FROM information_schema.STATISTICS
                WHERE table_schema = ?
                  AND table_name = ?
                  AND index_name = ?
                LIMIT 1
            ", [$db, $idx['table'], $idx['index']]);

            if (!$exists) {
                DB::statement($idx['sql']);
            }
        }
    }

    public function down(): void
    {
        DB::statement("DROP INDEX idx_sms_date_user_sender ON send_sms");
        DB::statement("DROP INDEX idx_ssq_send_sms_id ON send_sms_queues");
        DB::statement("DROP INDEX idx_ssh_send_sms_id ON send_sms_histories");
    }
};
