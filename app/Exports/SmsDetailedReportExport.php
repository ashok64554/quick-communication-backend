<?php

namespace App\Exports;

use App\Models\SendSmsQueue;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SmsDetailedReportExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    protected $from;
    protected $to;
    protected $filters;

    public function __construct($from, $to, $filters = [])
    {
        $this->from    = $from;
        $this->to      = $to;
        $this->filters = $filters;
    }

    public function query()
    {
        $query = DB::table('send_sms_queues as ssq')
            ->leftJoin('send_sms as sms', 'ssq.send_sms_id', '=', 'sms.id')
            ->leftJoin('users as u', 'sms.user_id', '=', 'u.id')
            ->leftJoin('dlt_templates as dt', 'sms.dlt_template_id', '=', 'dt.id')
            ->leftJoin('dlrcode_venders as dv', 'ssq.err', '=', 'dv.dlr_code')
            ->select(
                'u.name as user_name',
                'ssq.message',
                'ssq.use_credit',
                'ssq.submit_date',
                'ssq.done_date',
                'ssq.stat',
                'ssq.err as code',
                'dv.description as code_description',
                DB::raw("dt.dlt_template_id AS dlt_template_id"),
                DB::raw("ssq.mobile AS mobile"),
                DB::raw("TIMESTAMPDIFF(SECOND, ssq.submit_date, ssq.done_date) AS time_diff_seconds")
            )
            ->whereBetween('sms.campaign_send_date_time', [$this->from, $this->to]);

        // OPTIONAL FILTERS
        if (!empty($this->filters['user_id'])) {
            $query->where('sms.user_id', $this->filters['user_id']);
        }

        if (!empty($this->filters['sender_id'])) {
            $query->where('sms.sender_id', $this->filters['sender_id']);
        }

        if (!empty($this->filters['dlt_template_id'])) {
            $query->where('dlt_templates.dlt_template_id', $this->filters['dlt_template_id']);
        }

        return $query->orderBy('ssq.id', 'desc');
    }

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

    public function map($row): array
    {
        return [
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
            
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }
}