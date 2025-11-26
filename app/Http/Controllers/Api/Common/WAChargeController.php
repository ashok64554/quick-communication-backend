<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppCharge;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class WAChargeController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-sms-charge');
        $this->middleware('permission:whatsapp-sms-charge-create', ['only' => ['store']]);
    }

    public function index(Request $request)
    {
        try {
            $query = WhatsAppCharge::select('country_id','user_id','wa_marketing_charge','wa_utility_charge','wa_service_charge','wa_authentication_charge');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
            }
            elseif(!empty(($request->user_id)))
            {
                $query->where('user_id', $request->user_id);
            }

            $query = $query->with('Country:id,name,currency_code,currency_symbol,phonecode','user:id,promotional_credit,transaction_credit,two_waysms_credit,voice_sms_credit,whatsapp_credit as wa_amount')
                ->get();
            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'country_id'    => 'required|exists:countries,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {

            $whatsAppCharge = WhatsAppCharge::firstOrNew([
                'user_id' => $request->user_id,
                'country_id' => $request->country_id
            ]);

            $whatsAppCharge->wa_marketing_charge = $request->wa_marketing_charge;
            $whatsAppCharge->wa_utility_charge = $request->wa_utility_charge;
            $whatsAppCharge->wa_service_charge = $request->wa_service_charge;
            $whatsAppCharge->wa_authentication_charge = $request->wa_authentication_charge;
            $whatsAppCharge->save();
            DB::commit();
            
            return response()->json(prepareResult(false, $whatsAppCharge, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
