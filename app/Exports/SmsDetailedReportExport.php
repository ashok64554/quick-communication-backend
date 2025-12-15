<?php

namespace App\Exports;

use App\Models\Export;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;
use Throwable;

class SmsDetailedReportExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithChunkReading,
    ShouldQueue
{
    protected $from;
    protected $to;
    protected $exportId;
    protected $filters;

    public function __construct($from, $to, $exportId, $filters = [])
    {
        $this->from     = $from;
        $this->to       = $to;
        $this->exportId = $exportId;
        $this->filters  = $filters;
    }

    /**
     * --------------------------------------------------
     * QUERY (NO UNION — VERY IMPORTANT)
     * --------------------------------------------------
     */
    public function query()
    {
        $fromDate   = Carbon::parse($this->from);
        $toDate     = Carbon::parse($this->to);
        $todayStart = Carbon::today()->startOfDay();

        // Decide source table
        $table = $toDate->lt($todayStart)
            ? 'send_sms_histories'
            : 'send_sms_queues';

        $q = DB::table("$table as ssq")
            ->join('send_sms as sms', 'ssq.send_sms_id', '=', 'sms.id')
            ->join('users as u', 'sms.user_id', '=', 'u.id')
            ->leftJoin('dlt_templates as dt', 'sms.dlt_template_id', '=', 'dt.id')
            ->leftJoin('dlrcode_venders as dv', 'ssq.err', '=', 'dv.dlr_code')
            ->selectRaw("
                u.name                                              as user_name,
                dt.dlt_template_id                                 as dlt_template_id,
                ssq.mobile                                         as mobile,
                ssq.message                                        as message,
                ssq.use_credit                                     as use_credit,
                ssq.submit_date                                    as submit_date,
                ssq.done_date                                      as done_date,
                TIMESTAMPDIFF(SECOND, ssq.submit_date, ssq.done_date) as time_diff_seconds,
                ssq.stat                                           as stat,
                ssq.err                                            as code,
                dv.description                                     as code_description
            ")
            ->whereBetween('sms.campaign_send_date_time', [$this->from, $this->to])
            ->orderBy('ssq.submit_date', 'ASC');

        // OPTIONAL FILTERS
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
    }

    /**
     * --------------------------------------------------
     * HEADINGS (FINAL)
     * --------------------------------------------------
     */
    public function headings(): array
    {
        return [
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
        ];
    }

    /**
     * --------------------------------------------------
     * MAP (ORDER MUST MATCH SELECT)
     * --------------------------------------------------
     */
    public function map($row): array
    {
        return [
            $row->user_name ?? '',
            "'" . ($row->dlt_template_id ?? ''),
            "'" . ($row->mobile ?? ''),
            $row->message ?? '',
            $row->use_credit ?? 0,
            $row->submit_date ?? '',
            $row->done_date ?? '',
            $row->time_diff_seconds ?? 0,
            $row->stat ?? '',
            $row->code ?? '',
            $row->code_description ?? 'N/A',
        ];
    }

    /**
     * --------------------------------------------------
     * PERFORMANCE
     * --------------------------------------------------
     */
    public function chunkSize(): int
    {
        return 2000;
    }

    /**
     * --------------------------------------------------
     * STATUS HANDLING
     * --------------------------------------------------
     */
    public function __destruct()
    {
        Export::where('id', $this->exportId)
            ->update(['status' => 'completed']);
    }

    public function failed(Throwable $e): void
    {
        Export::where('id', $this->exportId)
            ->update([
                'status' => 'failed',
                'error'  => $e->getMessage()
            ]);
    }
}
