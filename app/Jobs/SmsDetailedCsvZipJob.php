<?php

namespace App\Jobs;

use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use ZipArchive;
use Throwable;

class SmsDetailedCsvZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ROWS_PER_FILE = 500000;

    protected $from;
    protected $to;
    protected $exportId;
    protected $filters;

    public function __construct($from, $to, $exportId, array $filters)
    {
        $this->from     = $from;
        $this->to       = $to;
        $this->exportId = $exportId;
        $this->filters  = $filters;
    }

    public function handle()
    {
        /*\Log::info('CSV ZIP JOB STARTED', [
            'export_id' => $this->exportId
        ]);*/
        
        $export = Export::findOrFail($this->exportId);

        $tmpDir = storage_path("app/export_path/tmp/{$export->id}");
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $fromDate    = Carbon::parse($this->from);
        $toDate      = Carbon::parse($this->to);
        $todayStart  = Carbon::today()->startOfDay();

        $onlyToday = $fromDate->gte($todayStart);
        $onlyPast  = $toDate->lt($todayStart);


        $buildQuery = function ($table) {

            $q = DB::table("$table as ssq")
                ->join('send_sms as sms', 'ssq.send_sms_id', '=', 'sms.id')
                ->join('users as u', 'sms.user_id', '=', 'u.id')
                ->leftJoin('dlt_templates as dt', 'sms.dlt_template_id', '=', 'dt.id')
                ->leftJoin('dlrcode_venders as dv', 'ssq.err', '=', 'dv.dlr_code')
                ->select(
                    'u.name as user_name',
                    'dt.dlt_template_id as dlt_template_id',
                    'ssq.mobile',
                    'ssq.message',
                    'ssq.use_credit',
                    'ssq.submit_date',
                    'ssq.done_date',
                    'ssq.stat',
                    'ssq.err as code',
                    'dv.description as code_description',
                    DB::raw('TIMESTAMPDIFF(SECOND, ssq.submit_date, ssq.done_date) AS time_diff_seconds')
                )
                ->whereBetween('sms.campaign_send_date_time', [$this->from, $this->to])
                ->orderBy('ssq.id');

            // ---------------- FILTERS ----------------
            if (!empty($this->filters['user_id'])) {
                $q->where('sms.user_id', $this->filters['user_id']);
            }

            if (!empty($this->filters['sender_id'])) {
                $q->where('sms.sender_id', $this->filters['sender_id']);
            }

            if (!empty($this->filters['dlt_template_id'])) {
                $q->where('dt.dlt_template_id', $this->filters['dlt_template_id']);
            }

            return $q;
        };


        $queries = [];

        if ($onlyToday) {
            $queries[] = $buildQuery('send_sms_queues');
        } elseif ($onlyPast) {
            $queries[] = $buildQuery('send_sms_histories');
        } else {
            $queries[] = $buildQuery('send_sms_queues');
            $queries[] = $buildQuery('send_sms_histories');
        }


        $fileIndex = 1;
        $rowCount  = 0;
        $handle    = null;

        foreach ($queries as $query) {
            foreach ($query->cursor() as $row) {

                if ($rowCount % self::ROWS_PER_FILE === 0) {
                    if ($handle) fclose($handle);

                    $csvPath = "{$tmpDir}/sms_report_part_{$fileIndex}.csv";
                    $handle = fopen($csvPath, 'w');

                    // HEADER (MATCHES YOUR FINAL DECISION)
                    fputcsv($handle, [
                        'User Name',
                        'DLT Template ID',
                        'Mobile No.',
                        'Message',
                        'Used Credit',
                        'Submit Date',
                        'Done Date',
                        'Time Diff (Seconds)',
                        'Status',
                        'Code',
                        'Code Description',
                    ]);

                    $fileIndex++;
                }

                fputcsv($handle, [
                    $row->user_name,
                    "'" . $row->dlt_template_id,
                    "'" . $row->mobile,
                    $row->message,
                    $row->use_credit,
                    $row->submit_date,
                    $row->done_date,
                    $row->time_diff_seconds ?? 0,
                    $row->stat,
                    $row->code,
                    $row->code_description ?? 'N/A',
                ]);

                $rowCount++;
            }
        }

        if ($handle) fclose($handle);


        $zipPath = storage_path("app/export_path/{$export->file_name}");
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (glob($tmpDir . '/*.csv') as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        // Cleanup
        array_map('unlink', glob("$tmpDir/*.csv"));
        rmdir($tmpDir);

        $export->update(['status' => 'completed']);
    }

    public function failed(Throwable $e)
    {
        Export::where('id', $this->exportId)->update([
            'status' => 'failed',
            'error'  => $e->getMessage()
        ]);
    }
}
