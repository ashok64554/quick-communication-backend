<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LinkClickLog;
use App\Models\ShortLink;
use Browser;

class ShortLinkController extends Controller
{
    public function shortenLink($sub_part, $token, Request $request)
    {
        $shortlink = \DB::table(env('DB_DATABASE2W').'.short_links')
            ->where('sub_part', $sub_part)
            ->where('token', $token)
            ->first();
        if($shortlink) {
            if(strtotime($shortlink->link_expired) >= strtotime(date('Y-m-d'))) 
            {
                $redirect_url = $shortlink->link;
                if (filter_var($shortlink->link, FILTER_VALIDATE_URL) === FALSE) 
                {
                    $redirect_url = 'https://'.$redirect_url;
                }
                \DB::statement("UPDATE `".env('DB_DATABASE2W')."`.`short_links` SET `total_click`= total_click + 1 
                WHERE `id` = '".$shortlink->id."';");
                $this->logGenerate($shortlink->two_way_comm_id, $shortlink->id, $shortlink->send_sms_id, $shortlink->mobile_num);
                return redirect($redirect_url);
            }
            return redirect()->route('link.expired');
        } else {
            return redirect()->route('link.expired');
        }
    }

    public function linkExpired()
    {
        return view('link-expired');
    }

    private function logGenerate($two_way_comm_id, $short_link_id, $send_sms_id, $mobile)
    {
        $log = new LinkClickLog;
        $log->two_way_comm_id   = $two_way_comm_id;
        $log->short_link_id     = $short_link_id;
        $log->send_sms_id       = $send_sms_id;
        $log->mobile            = $mobile;
        $log->ip                = request()->ip();
        $log->browserName       = Browser::browserName();
        $log->browserFamily     = Browser::browserFamily();
        $log->browserVersion    = Browser::browserVersion();
        $log->browserEngine     = Browser::browserEngine();
        $log->platformName      = Browser::platformName();
        $log->deviceFamily      = Browser::deviceFamily();
        $log->deviceModel       = Browser::deviceModel();
        $log->save();
        return true;
    }
}
