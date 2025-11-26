<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class FileUploadController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }
    
    public function fileUpload(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'file'     => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $file = $request->file;
            $fileArray = array();
            $formatCheck = ['doc','docx','png','jpeg','jpg','gif','pdf','svg','mp4','webp','csv','xlsx'];

            $fileName   = time().'-'.rand(0,99999).'.' . $file->getClientOriginalExtension();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileSize = $file->getSize();
            if(!in_array($extension, $formatCheck))
            {
                return response()->json(prepareResult(true, [], trans('translate.file_not_allowed').'Only allowed : doc, docx, png, jpeg, jpg, gif, pdf, svg, mp4, webp, csv, xlsx', $this->intime), config('httpcodes.internal_server_error'));
            }

            //********************************
            //scan all files
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // Return MIME type
            $fileActCheck = finfo_file($finfo, $file);
            finfo_close($finfo);
            if($fileActCheck=='application/x-dosexec')
            {
                return response()->json(prepareResult(true, trans('translate.malicious_file'), trans('translate.malicious_file'), $this->intime), config('httpcodes.internal_server_error'));
            }
            //********************************

            $destinationPath = 'uploads/';
            if(in_array($extension, ['csv','xlsx'])) {
                if($request->file_for=='wa')
                {
                    $destinationPath = 'csv/wa_campaign/';
                }
                else
                {
                    $destinationPath = 'csv/campaign/';
                }
            }

            $file->move($destinationPath, $fileName);
            $file_location  = $destinationPath.$fileName;

            $fileInfo = [
                'file_name'         => $destinationPath.$fileName,
                'file_extension'    => $file->getClientOriginalExtension(),
                'uploading_file_name' => $file->getClientOriginalName(),
            ];
            return response()->json(prepareResult(false, $fileInfo, trans('translate.created'), $this->intime), config('httpcodes.created'));   
        }
        catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
