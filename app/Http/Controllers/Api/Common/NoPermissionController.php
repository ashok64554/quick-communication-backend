<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class NoPermissionController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }
    
    public function getVoiceSmscID()
    {
        try {
            $voicePreDefineArrey = [
                ['name'  => 'videocon'],
                ['name'  => 'vodaidea'],
                ['name'  => 'jio']
            ];

            return response()->json(prepareResult(false, $voicePreDefineArrey, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
