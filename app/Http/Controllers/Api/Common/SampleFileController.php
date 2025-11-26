<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class SampleFileController extends Controller
{
    protected $intime;
    protected $destinationPath;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->destinationPath = 'sample-file/';
    }

    public function dltSampleFile()
    {
        try {
            $file_path = $this->destinationPath.'sample-file-import-dlt-template.xlsx';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function groupSampleFile()
    {
        try {
            $file_path = $this->destinationPath.'sample-file-upload-group.csv';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function vandorDlrSampleFile()
    {
        try {
            $file_path = $this->destinationPath.'sample-file-upload-vendor-dlr-code.csv';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function blackListSampleFile()
    {
        try {
            $file_path = $this->destinationPath.'sample-file-black-list.csv';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function normalSmsCampaignFile()
    {
        try {
            $file_path = $this->destinationPath.'normal-sms-campaign-file.csv';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function customSmsCampaignFile()
    {
        try {
            $file_path = $this->destinationPath.'custom-sms-campaign-file.csv';
            return response()->json(prepareResult(false, $file_path, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
