<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appsetting;
use App\Models\DltTemplate;
use App\Models\ManageSenderId;
use App\Models\Blacklist;
use App\Models\ContactGroup;
use App\Models\ContactNumber;
use App\Models\IpWhiteListForApi;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use DB;

class DataSyncFromOldDb extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // delete all fake user who dont have any sender id
        $oldFakeUsers = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('users')
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldFakeUsers as $key => $oldFakeUser) {
            $checkSenderId = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('manage_sender_ids')
            ->where('userId', $oldFakeUser->id)
            ->where('senderID', '!=', 'XXXXXX')
            ->count();
            if($checkSenderId<1)
            {
                DB::connection(env('DB_CONNECTION_OLD_2W'))
                ->table('users')->where('id', $oldFakeUser->id)->delete();
                echo $oldFakeUser->id.' ';
            }
        }

        $oldUsers = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('users')
            ->whereIn('userType', ['admin','reseller','staff'])
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldUsers as $key => $oldUser) 
        {
            $userTypeRole = $this->getUserTypeRole($oldUser->userType);
            $oldUserType = $userTypeRole['userType'];
            $userType = $oldUserType;
            $roleName = $userTypeRole['roleName'];

            $parent_id = (($userType==3) ? 1 : null);
            $current_parent_id = (($userType==3) ? 1 : null);

        
            $user = $this->createUser($userType, $oldUser->name, $oldUser->email, $oldUser->username, $oldUser->password, $oldUser->mobile, $oldUser->address, $oldUser->city, $oldUser->zipCode, $oldUser->country, $oldUser->companyLogo, $oldUser->websiteUrl, $oldUser->designation, $oldUser->authority_type, $oldUser->locktimeout, $oldUser->status, 1, 1, 1, 1, 0, ($oldUser->transaction_credit + $oldUser->otp_credit), 0, 0, $oldUser->is_enabled_api_ip_security, $oldUser->created_at, $oldUser->updated_at, $roleName, $oldUser->app_key, $oldUser->app_secret, $parent_id, $current_parent_id);

            $parent_id = $user->parent_id;

            //sender id and dlt template
            $this->createSenderId($oldUser->id, $user->id, $userType, $parent_id);

            //Blacklists 
            $this->createBlacklist($oldUser->id, $user->id, $userType, $parent_id);

            //Contact groups and number 
            $this->createContactGroup($oldUser->id, $user->id, $userType, $parent_id);

            //API IP white list 
            $this->createIPWhitelist($oldUser->id, $user->id, $userType, $parent_id);


            $clients = DB::connection(env('DB_CONNECTION_OLD_2W'))
                ->table('users')
                ->where('create_by', $oldUser->id)
                ->orderBy('id', 'ASC')
                ->get();
            foreach ($clients as $ckey => $client) 
            {
                $userTypeRole = $this->getUserTypeRole($client->userType);
                $userType = $userTypeRole['userType'];
                $roleName = $userTypeRole['roleName'];

                $parent_id = (($oldUserType==3) ? 1 : $user->id);
                $current_parent_id = (($oldUserType==3) ? 1 : $user->id);

                $clientUser = $this->createUser($userType, $client->name, $client->email, $client->username, $client->password, $client->mobile, $client->address, $client->city, $client->zipCode, $client->country, $client->companyLogo, $client->websiteUrl, $client->designation, $client->authority_type, $client->locktimeout, $client->status, 1, 1, 1, 1, 0, ($client->transaction_credit + $client->otp_credit), 0, 0, $client->is_enabled_api_ip_security, $client->created_at, $client->updated_at, $roleName, $client->app_key, $client->app_secret, $parent_id, $current_parent_id);

                $parent_id = $clientUser->parent_id;
                //sender id and dlt template
                $this->createSenderId($client->id, $clientUser->id, $userType, $parent_id);

                //Blacklists 
                $this->createBlacklist($client->id, $clientUser->id, $userType, $parent_id);

                //Contact groups and number 
                $this->createContactGroup($client->id, $clientUser->id, $userType, $parent_id);

                //API IP white list 
                $this->createIPWhitelist($client->id, $clientUser->id, $userType, $parent_id);
            }
        }

        // update OTP dlt_template priority 
        $newTemplateDB = \DB::table('dlt_templates')->where('dlt_message', 'LIKE', '%OTP%')->update([
            'priority' => '3'
        ]);
    }

    function getUserTypeRole($userType)
    {
        switch ($userType) {
            case 'admin':
                $userType = 0;
                $roleName = 'admin';
                break;
            case 'reseller':
                $userType = 1;
                $roleName = 'reseller';
                break;
            case 'client':
                $userType = 2;
                $roleName = 'client';
                break;
            case 'staff':
                $userType = 3;
                $roleName = 'employee';
                break;                
            default:
                $userType = 2;
                $roleName = 'client';
                break;
        }
        $return = [
            'userType' => $userType,
            'roleName' => $roleName
        ];
        return $return;
    }

    function createUser($userType, $name, $email, $username, $password, $mobile, $address, $city, $zipCode, $country, $companyLogo, $websiteUrl, $designation, $authority_type, $locktimeout, $status, $promotional_route, $transaction_route, $two_waysms_route, $voice_sms_route, $promotional_credit, $transaction_credit, $two_waysms_credit, $voice_sms_credit, $is_enabled_api_ip_security, $created_at, $updated_at, $roleName, $app_key, $app_secret, $parent_id=null, $current_parent_id=null)
    {
        $checkExistingUser = DB::table('users')->where('email', $email)->orWhere('username', $username)->first();
        if(!$checkExistingUser)
        {
            $user = new User;
            $user->userType = $userType;
            $user->name = $name;
            $user->email  = $email;
            $user->username  = $username;
            $user->password = $password;
            $user->mobile = $mobile;
            $user->address = $address;
            $user->city = $city;
            $user->zipCode = $zipCode;
            $user->country = $country;
            $user->companyLogo = $companyLogo;
            $user->websiteUrl = $websiteUrl;
            $user->designation = $designation;
            $user->authority_type = (($authority_type=='onDelivered') ? 1 : 2);
            $user->locktimeout = $locktimeout;
            $user->status = $status;

            //secrets
            $user->app_key = $app_key;
            $user->app_secret = $app_secret;

            //route assign
            $user->promotional_route = 4;
            $user->transaction_route = 4;
            $user->two_waysms_route = 4;
            $user->voice_sms_route = 4;

            //credit
            $user->promotional_credit = 0;
            $user->transaction_credit = $transaction_credit;
            $user->two_waysms_credit = 0;
            $user->voice_sms_credit = 0;
            
            $user->is_enabled_api_ip_security = $is_enabled_api_ip_security;

            $user->created_at = $created_at;
            $user->updated_at = $updated_at;

            $user->parent_id = $parent_id;
            $user->current_parent_id = $current_parent_id;

            $user->save();
            if($user) 
            {
                if(empty($parent_id) || $userType=='1')
                {
                    $user->parent_id = $user->id;
                    $user->save();
                }

                $role = Role::where('name', $roleName)->first();
                $permissions = $role->permissions->pluck('name');
                
                $user->assignRole($role->name);
                foreach ($permissions as $key => $permission) {
                    $user->givePermissionTo($permission);
                }
            }
            return $user;
        }
        else
        {
            return $checkExistingUser;
        }
    }

    function createSenderId($oldUserId, $newUserId, $userType, $parent_id)
    {
        $oldSenderIDs = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('manage_sender_ids')
            ->where('userId', $oldUserId)
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldSenderIDs as $key => $oldSenderID) 
        {
            $checkRecord = DB::table('manage_sender_ids')
                ->where('user_id', $newUserId)
                ->where('sender_id', $oldSenderID->senderID)
                ->first();
            if(!$checkRecord)
            {
                $manageSenderId = new ManageSenderId;
                if(in_array($userType, [0,3]))
                {
                    $manageSenderId->parent_id  = 1;
                }
                else
                {
                    $manageSenderId->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
                }

                $manageSenderId->user_id  = $newUserId;
                $manageSenderId->company_name  = $oldSenderID->companyName;
                $manageSenderId->entity_id  = $oldSenderID->entityID;
                $manageSenderId->header_id  = $oldSenderID->headerID;
                $manageSenderId->sender_id  = $oldSenderID->senderID;
                $manageSenderId->sender_id_type  = 1;
                $manageSenderId->status  = 1;
                $manageSenderId->save();
                if($manageSenderId)
                {
                    //done from here
                    $oldDltTemplates = DB::connection(env('DB_CONNECTION_OLD_2W'))
                        ->table('dlt_templates')
                        ->where('userId', $oldUserId)
                        ->where('sender_id', $manageSenderId->sender_id)
                        ->orderBy('id', 'ASC')
                        ->get();
                    foreach ($oldDltTemplates as $key => $oldDltTemplate) 
                    {
                        $checkDltRecord = DB::table('dlt_templates')
                            ->where('user_id', $newUserId)
                            ->where('manage_sender_id', $manageSenderId->id)
                            //->where('template_name', $oldDltTemplate->dlt_template_name)
                            ->where('dlt_template_id', $oldDltTemplate->dlt_template_id)
                            ->where('dlt_message', $oldDltTemplate->dlt_message)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if(!$checkDltRecord)
                        {
                            $dlt_template = new DltTemplate;
                            if(in_array($userType, [0,3]))
                            {
                                $dlt_template->parent_id  = 1;
                            }
                            else
                            {
                                $dlt_template->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
                            }

                            $dlt_template->user_id  = $newUserId;
                            $dlt_template->manage_sender_id  = $manageSenderId->id;
                            $dlt_template->template_name  = $oldDltTemplate->dlt_template_name;
                            $dlt_template->dlt_template_id  = $oldDltTemplate->dlt_template_id;
                            $dlt_template->entity_id  = $oldDltTemplate->entity_id;
                            $dlt_template->sender_id  = $oldDltTemplate->sender_id;
                            $dlt_template->header_id  = $oldDltTemplate->header_id;
                            $dlt_template->is_unicode  = $oldDltTemplate->is_unicode;
                            $dlt_template->dlt_message  = $oldDltTemplate->dlt_message;
                            $dlt_template->status  = 1;
                            $dlt_template->save();
                        }
                    }
                }
            }
        }
        return;
    }

    function createBlacklist($oldUserId, $newUserId, $userType, $parent_id)
    {
        $oldBlacklists = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('blacklists')
            ->where('created_by', $oldUserId)
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldBlacklists as $key => $oldBlacklist) 
        {
            $blacklist = new Blacklist;
            if(in_array($userType, [0,3]))
            {
                $blacklist->parent_id  = 1;
            }
            else
            {
                $blacklist->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
            }
            $blacklist->user_id = $newUserId;
            $blacklist->mobile_number = $oldBlacklist->mobile_number;
            $blacklist->save();
        }
        return;
    }

    function createContactGroup($oldUserId, $newUserId, $userType, $parent_id)
    {
        $oldGroups = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('contact_groups')
            ->where('userId', $oldUserId)
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldGroups as $key => $oldGroup) 
        {
            $checkRecord = DB::table('contact_groups')
                ->where('user_id', $newUserId)
                ->where('group_name', $oldGroup->groupName)
                ->first();
            if(!$checkRecord)
            {
                $contactGroup = new ContactGroup;
                if(in_array($userType, [0,3]))
                {
                    $contactGroup->parent_id  = 1;
                }
                else
                {
                    $contactGroup->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
                }
                $contactGroup->user_id = $newUserId;
                $contactGroup->group_name = $oldGroup->groupName;
                $contactGroup->description = $oldGroup->description;
                $contactGroup->save();
                if($contactGroup)
                {
                    //done from here
                    $oldContactNumbers = DB::connection(env('DB_CONNECTION_OLD_2W'))
                        ->table('contact_numbers')
                        ->where('groupId', $oldGroup->id)
                        ->orderBy('id', 'ASC')
                        ->get();
                    foreach ($oldContactNumbers as $key => $oldContactNumber) 
                    {
                        $checkCNRecord = DB::table('contact_numbers')
                            ->where('contact_group_id', $contactGroup->id)
                            ->where('number', $oldContactNumber->number)
                            ->first();
                        if(!$checkCNRecord)
                        {
                            $contactNumber = new ContactNumber;
                            if(in_array($userType, [0,3]))
                            {
                                $contactNumber->parent_id  = 1;
                            }
                            else
                            {
                                $contactNumber->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
                            }
                            $contactNumber->user_id = $newUserId;
                            $contactNumber->contact_group_id = $contactGroup->id;
                            $contactNumber->number = $oldContactNumber->number;
                            $contactNumber->save();
                        }
                    }
                }
            }
        }
        return;
    }

    function createIPWhitelist($oldUserId, $newUserId, $userType, $parent_id)
    {
        $oldIpWhiteLists = DB::connection(env('DB_CONNECTION_OLD_2W'))
            ->table('ip_white_list_for_apis')
            ->where('userId', $oldUserId)
            ->orderBy('id', 'ASC')
            ->get();
        foreach ($oldIpWhiteLists as $key => $oldIpWhiteList) 
        {
            $ip_address = new IpWhiteListForApi;
            if(in_array($userType, [0,3]))
            {
                $ip_address->parent_id  = 1;
            }
            else
            {
                $ip_address->parent_id = (empty($parent_id) ? $newUserId : $parent_id);
            }
            $ip_address->user_id  = $newUserId;
            $ip_address->ip_address = $oldIpWhiteList->ip_address;
            $ip_address->save();
        }
        return;
    }
}