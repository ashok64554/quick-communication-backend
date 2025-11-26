<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\WhatsAppRateCard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Auth;
use DB;
use Exception;
use App\Imports\ContactNumberImport;
use Excel;

class CountryController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:app-setting');
    }

    public function index(Request $request)
    {
        try {
            $query = Country::select('id', 'name', 'iso', 'iso3', 'currency', 'currency_code', 'currency_symbol', 'phonecode', 'min', 'max');

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search. '%')
                        ->orWhere('currency', 'LIKE', '%'.$search.'%')
                        ->orWhere('currency_code', 'LIKE', '%'.$search.'%')
                        ->orWhere('phonecode', 'LIKE', '%'.$search.'%');
                });
            }

            if(!empty($request->name))
            {
                $query->where('name', 'LIKE', '%'.$request->name.'%');
            }

            if(!empty($request->currency))
            {
                $query->where('currency', 'LIKE', '%'.$request->currency.'%');
            }

            if(!empty($request->currency_code))
            {
                $query->where('currency_code', 'LIKE', '%'.$request->currency_code.'%');
            }

            if(!empty($request->phonecode))
            {
                $query->where('phonecode', 'LIKE', '%'.$request->phonecode.'%');
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $pagination =  [
                    'data' => $result,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage)
                ];
                $query = $pagination;
                if($request->other_function)
                {
                    return $pagination;
                }
            }
            else
            {
                $query = $query->get();
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'name'    => 'required',
            'iso'    => 'required',
            'iso3'    => 'required',
            'currency'    => 'required',
            'currency_code'    => 'required',
            'currency_symbol'    => 'required',
            'phonecode'    => 'required|numeric',
            'min'    => 'required|numeric',
            'max'    => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkCountry = Country::where('name', $request->name)->first();
        if($checkCountry)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $country = new Country;
            $country->name = $request->name;
            $country->iso = $request->iso;
            $country->iso3 = $request->iso3;
            $country->currency = $request->currency;
            $country->currency_code = $request->currency_code;
            $country->currency_symbol = $request->currency_symbol;
            $country->phonecode = $request->phonecode;
            $country->min = $request->min;
            $country->max = $request->max;
            $country->save();
            DB::commit();

            return response()->json(prepareResult(false, $country, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(Country $country)
    {
        try {
            return response()->json(prepareResult(false, $country, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, Country $country)
    {
        $validation = \Validator::make($request->all(), [
            'name'    => 'required',
            'iso'    => 'required',
            'iso3'    => 'required',
            'currency'    => 'required',
            'currency_code'    => 'required',
            'currency_symbol'    => 'required',
            'phonecode'    => 'required|numeric',
            'min'    => 'required|numeric',
            'max'    => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkCountry = Country::where('name', $request->name)->where('id', '!=', $country->id)->first();
        if($checkCountry)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            
            $country->name = $request->name;
            $country->iso = $request->iso;
            $country->iso3 = $request->iso3;
            $country->currency = $request->currency;
            $country->currency_code = $request->currency_code;
            $country->currency_symbol = $request->currency_symbol;
            $country->phonecode = $request->phonecode;
            $country->min = $request->min;
            $country->max = $request->max;
            $country->save();
            DB::commit();

            return response()->json(prepareResult(false, $country, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(Country $country)
    {
        try {
            $country->delete();
            return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waRateCard(Request $request)
    {
        try {
            $query = WhatsAppRateCard::query();

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('country_name', 'LIKE', '%' . $search. '%')
                        ->orWhere('currency', 'LIKE', '%'.$search.'%');
                });
            }

            if(!empty($request->country_name))
            {
                $query->where('country_name', 'LIKE', '%'.$request->country_name.'%');
            }

            if(!empty($request->currency))
            {
                $query->where('currency', 'LIKE', '%'.$request->currency.'%');
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $pagination =  [
                    'data' => $result,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage)
                ];
                $query = $pagination;
                if($request->other_function)
                {
                    return $pagination;
                }
            }
            else
            {
                $query = $query->get();
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waRateCardImport(Request $request)
    {
        try {
            $path = public_path('sample-file/wa-rate-card.csv');
            if(!file_exists($path))
            {
                return response()->json(prepareResult(false, [], trans('translate.file_not_found'), $this->intime), config('httpcodes.not_found'));
            }
            WhatsAppRateCard::truncate();
            $csv_data = Excel::toArray(new ContactNumberImport, $path);
            $allData = [];
            foreach ($csv_data[0] as $key => $data) 
            {
                $allData[] = [
                    'country_name' => $data['market'],
                    'marketing_charge' => $data['marketing'],
                    'utility_charge' => $data['utility'],
                    'authentication_charge' => $data['authentication'],
                    'authentication_international_charge' => (!empty($data['authentication_international']) ? $data['authentication_international'] : null),
                    'service_charge' => $data['service'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }
            WhatsAppRateCard::insert($allData);
            return response()->json(prepareResult(false, $allData, trans('translate.data_imported'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
