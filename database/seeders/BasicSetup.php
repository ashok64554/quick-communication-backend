<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Appsetting;
use App\Models\CampaignExecuter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use DB;
use Str;

class BasicSetup extends Seeder
{
    public function run()
    {
        DB::table('appsettings')->delete();
        $appSetting = new Appsetting();
        $appSetting->app_name    = 'SMS PORTAL';
        $appSetting->app_logo    = 'uploads/logo.png';
        $appSetting->contact_email       = 'info@nrtsms.com';
        $appSetting->contact_address     = '463 - A, Pacific Business Center, Behind D-Mart Shopping Center, Hoshangabad Rd, Bhopal, Madhya Pradesh 462026 India';
        $appSetting->contact_number   = '9713753131';
        $appSetting->tax_percentage   = '18.00';
        $appSetting->g_key   = '6Lcfn2wjAAAAAM7UluVrfdD5TXjQCY7Wu1b2WMg0';
        $appSetting->g_secret   = '6Lcfn2wjAAAAAGn1Iwyp9J6UOzqTnLibk5fGUGKw';
        $appSetting->save();

        //Admin account
        DB::table('users')->delete();
        $adminUser = new User();
        $adminUser->userType      = 0;
        $adminUser->parent_id  = null;
        $adminUser->name     = 'NewRise Technosys Pvt. Ltd.';
        $adminUser->email   = 'sms@nrt.co.in';
        $adminUser->username   = 'smsadmin';
        $adminUser->password      =  \Hash::make('P@ssw0red');
        $adminUser->email_verified_at = date('Y-m-d H:i:s');
        $adminUser->address       = 'India';
        $adminUser->country       = 99;
        $adminUser->mobile        = '9713753131';
        $adminUser->companyLogo   = 'uploads/logo.png';
        $adminUser->status        = '1';
        $adminUser->is_show_ratio = 1;

        $adminUser->uuid          = Str::random(35);
        $adminUser->app_key       = Str::random(35);
        $adminUser->app_secret    = Str::random(15);

        $adminUser->promotional_route   = 1;
        $adminUser->transaction_route   = 1;
        $adminUser->two_waysms_route   = 1;
        $adminUser->voice_sms_route   = 1;
        $adminUser->created_by   = 1;
        $adminUser->parent_id   = 1;
        $adminUser->save();

        //Roles & Permissions
        app()['cache']->forget('spatie.permission.cache');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'app-setting', 'guard_name' => 'api']);
        Permission::create(['name' => 'database-backup', 'guard_name' => 'api']);
        Permission::create(['name' => 'error-log-view', 'guard_name' => 'api']);

        Permission::create(['name' => 'edit-profile', 'guard_name' => 'api']);

        Permission::create(['name' => 'users-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-ddl-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-action', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-login', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-change-password', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-change-api-key', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-route-setting', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-credit-add', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-credit-reverse', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-account-log', 'guard_name' => 'api']);
        Permission::create(['name' => 'user-access-document', 'guard_name' => 'api']);
        Permission::create(['name' => 'view-login-log', 'guard_name' => 'api']);

        Permission::create(['name' => 'roles-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'role-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'role-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'role-delete', 'guard_name' => 'api']);


        Permission::create(['name' => 'admin-operation', 'guard_name' => 'api']);
        Permission::create(['name' => 'update-pending-status', 'guard_name' => 'api']);
        Permission::create(['name' => 'assign-route-to-user', 'guard_name' => 'api']);
        Permission::create(['name' => 'change-dlt-template-priority', 'guard_name' => 'api']);
        Permission::create(['name' => 'set-user-ratio', 'guard_name' => 'api']);
        Permission::create(['name' => 'admin-manage-campaign-status', 'guard_name' => 'api']);
        Permission::create(['name' => 'admin-approved-sender-id', 'guard_name' => 'api']);
        Permission::create(['name' => 'invalid-series', 'guard_name' => 'api']);


        Permission::create(['name' => 'staff-list', 'guard_name' => 'api']);

        Permission::create(['name' => 'role-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'permission-list', 'guard_name' => 'api']);

        Permission::create(['name' => 'phone-book-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'phone-book-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'phone-book-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'phone-book-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'phone-book-create', 'guard_name' => 'api']);

        Permission::create(['name' => 'dlt-template-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlt-template-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlt-template-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlt-template-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlt-template-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlt-template-action', 'guard_name' => 'api']);
        
        Permission::create(['name' => 'primary-route-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'primary-route-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'primary-route-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'primary-route-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'primary-route-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'primary-route-action', 'guard_name' => 'api']);
        Permission::create(['name' => 'check-primary-route-connection', 'guard_name' => 'api']);

        Permission::create(['name' => 'secondary-route-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'secondary-route-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'secondary-route-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'secondary-route-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'secondary-route-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'secondary-route-action', 'guard_name' => 'api']);

        Permission::create(['name' => 'sender-id-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'sender-id-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'sender-id-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'sender-id-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'sender-id-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'sender-id-action', 'guard_name' => 'api']);

        Permission::create(['name' => 'dlrcode-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlrcode-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlrcode-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlrcode-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlrcode-delete', 'guard_name' => 'api']);
        Permission::create(['name' => 'dlrcode-action', 'guard_name' => 'api']);

        Permission::create(['name' => 'blacklist', 'guard_name' => 'api']);

        Permission::create(['name' => 'send-sms', 'guard_name' => 'api']);
        Permission::create(['name' => 'voice-sms', 'guard_name' => 'api']);

        Permission::create(['name' => 'voice-file-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'voice-upload-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'voice-upload-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'voice-upload-delete', 'guard_name' => 'api']);

        Permission::create(['name' => 'voice-file-process', 'guard_name' => 'api']);
        Permission::create(['name' => 'bulk-voice-file-action', 'guard_name' => 'api']);
        Permission::create(['name' => 'sync-voice-template-to-vendor', 'guard_name' => 'api']);
        Permission::create(['name' => 'two-ways-sms', 'guard_name' => 'api']);
        Permission::create(['name' => 'two-ways-sms-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'two-ways-sms-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'two-ways-sms-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'two-ways-sms-delete', 'guard_name' => 'api']);

        Permission::create(['name' => 'reports', 'guard_name' => 'api']);

        Permission::create(['name' => 'credit-request', 'guard_name' => 'api']);

        Permission::create(['name' => 'upload-documents', 'guard_name' => 'api']);

        Permission::create(['name' => 'ip-white-list-for-api', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-sms-charge', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-sms-charge-create', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-sms', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-configurations', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-configuration-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-configuration-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-configuration-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-configuration-delete', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-file-upload', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-template-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-template-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-template-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-template-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-template-delete', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-qrcode-list', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-qrcode-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-qrcode-delete', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-get-analytics', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-get-conversation-analytics', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-get-template-analytics', 'guard_name' => 'api']);

        Permission::create(['name' => 'kannel-status-page', 'guard_name' => 'api']);

        Permission::create(['name' => 'whatsapp-chatbots', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-chatbot-create', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-chatbot-view', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-chatbot-edit', 'guard_name' => 'api']);
        Permission::create(['name' => 'whatsapp-chatbot-delete', 'guard_name' => 'api']);


        // create admin roles and assigning permissions
        $adminRole = Role::create(['name' => 'admin','actual_name'=> 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo(Permission::all());
        
        $adminUser->assignRole($adminRole);
        $adminUser->givePermissionTo(Permission::all());



        // create reseller role and assigning permissions
        $resellerRole = Role::create(['name' => 'reseller','actual_name'=> 'reseller', 'guard_name' => 'api']);

        $resellerPermission = [ 
            'edit-profile',
            'users-list',
            'user-ddl-list',
            'view-login-log',
            'user-view',
            'user-create',
            'user-edit',
            'user-delete',
            'user-action',
            'user-login',
            'user-change-password',
            'user-change-api-key',
            'user-credit-add',
            'user-credit-reverse',
            'user-account-log',
            'user-access-document',
            'roles-list',
            'role-create',
            'role-edit',
            'role-delete',
            'phone-book-list',
            'phone-book-view',
            'phone-book-edit',
            'phone-book-delete',
            'phone-book-create',
            'dlt-template-list',
            'dlt-template-view',
            'dlt-template-create',
            'dlt-template-edit',
            'dlt-template-delete',
            'dlt-template-action',
            'sender-id-list',
            'sender-id-view',
            'sender-id-create',
            'sender-id-edit',
            'sender-id-delete',
            'sender-id-action',
            'blacklist',
            'send-sms',
            'two-ways-sms',
            'two-ways-sms-create',
            'two-ways-sms-view',
            'two-ways-sms-edit',
            'two-ways-sms-delete',
            'reports',
            'credit-request',
            'upload-documents',
            'ip-white-list-for-api',
            'voice-sms',
            'voice-file-list',
            'voice-upload-create',
            'voice-upload-view',
            'voice-upload-delete',
            'whatsapp-sms',
            'whatsapp-configurations',
            'whatsapp-configuration-create',
            'whatsapp-configuration-edit',
            'whatsapp-configuration-view',
            'whatsapp-configuration-delete',
            'whatsapp-file-upload',
            'whatsapp-template-list',
            'whatsapp-template-create',
            'whatsapp-template-view',
            'whatsapp-template-edit',
            'whatsapp-template-delete'

        ];
        foreach ($resellerPermission as $permission) {
            $resellerRole->givePermissionTo($permission);
        }

        // create client role and assigning permissions
        $clientRole = Role::create(['name' => 'client','actual_name'=> 'client', 'guard_name' => 'api']);

        $clientPermission = [ 
            'user-ddl-list',
            'view-login-log',
            'edit-profile',
            'user-account-log',
            'user-access-document',
            'phone-book-list',
            'phone-book-view',
            'phone-book-edit',
            'phone-book-delete',
            'phone-book-create',
            'dlt-template-list',
            'dlt-template-view',
            'dlt-template-create',
            'dlt-template-edit',
            'dlt-template-delete',
            'dlt-template-action',
            'sender-id-list',
            'sender-id-view',
            'sender-id-create',
            'sender-id-edit',
            'sender-id-delete',
            'sender-id-action',
            'blacklist',
            'send-sms',
            'two-ways-sms',
            'two-ways-sms-create',
            'two-ways-sms-view',
            'two-ways-sms-edit',
            'two-ways-sms-delete',
            'reports',
            'credit-request',
            'upload-documents',
            'user-change-api-key',
            'ip-white-list-for-api',
            'voice-sms',
            'voice-file-list',
            'voice-upload-create',
            'voice-upload-view',
            'voice-upload-delete',
            'whatsapp-sms',
            'whatsapp-configurations',
            'whatsapp-configuration-create',
            'whatsapp-configuration-edit',
            'whatsapp-configuration-view',
            'whatsapp-configuration-delete',
            'whatsapp-file-upload',
            'whatsapp-template-list',
            'whatsapp-template-create',
            'whatsapp-template-view',
            'whatsapp-template-edit',
            'whatsapp-template-delete'
        ];
        foreach ($clientPermission as $permission) {
            $clientRole->givePermissionTo($permission);
        }

        // create client role and assigning permissions
        $employeeRole = Role::create(['name' => 'employee','actual_name'=> 'employee', 'guard_name' => 'api']);

        $employeePermission = [ 
            'user-ddl-list',
            'view-login-log',
            'edit-profile',
            'user-account-log',
            'user-access-document',
            'dlt-template-list',
            'dlt-template-view',
            'dlt-template-create',
            'dlt-template-edit',
            'dlt-template-delete',
            'dlt-template-action',
            'sender-id-list',
            'sender-id-view',
            'sender-id-create',
            'sender-id-edit',
            'sender-id-delete',
            'sender-id-action',
            'send-sms',
            'two-ways-sms',
            'two-ways-sms-create',
            'two-ways-sms-edit',
            'two-ways-sms-view',
            'two-ways-sms-delete',
            'voice-sms',
            'voice-file-list',
            'voice-upload-create',
            'voice-upload-view',
            'voice-upload-delete',
            'whatsapp-sms',
            'whatsapp-configurations',
            'whatsapp-configuration-create',
            'whatsapp-configuration-edit',
            'whatsapp-configuration-view',
            'whatsapp-configuration-delete',
            'whatsapp-file-upload',
            'whatsapp-template-list',
            'whatsapp-template-create',
            'whatsapp-template-view',
            'whatsapp-template-edit',
            'whatsapp-template-delete',
            'kannel-status-page',
        ];
        foreach ($employeePermission as $permission) {
            $employeeRole->givePermissionTo($permission);
        }


        //Default Reseller create NRT reseller
        //Admin account
        /*$adminReseller = new User();
        $adminReseller->userType      = 1;
        $adminReseller->parent_id  = 2;
        //$adminReseller->current_parent_id  = 1;
        $adminReseller->name     = 'NewRise Technosys Pvt. Ltd. Reseller';
        $adminReseller->email   = 'info@nrt.co.in';
        $adminReseller->username   = 'nrtreseller';
        $adminReseller->password      =  \Hash::make('P@ssw0red');
        $adminReseller->email_verified_at = date('Y-m-d H:i:s');
        $adminReseller->address       = 'India';
        $adminReseller->country       = 99;
        $adminReseller->mobile        = '9713753131';
        $adminReseller->companyLogo   = 'uploads/logo.png';
        $adminReseller->status        = '1';
        $adminReseller->is_show_ratio = 1;

        $adminReseller->uuid          = Str::random(35);
        $adminReseller->app_key       = Str::random(35);
        $adminReseller->app_secret    = Str::random(15);

        $adminReseller->promotional_route   = 1;
        $adminReseller->transaction_route   = 1;
        $adminReseller->two_waysms_route   = 1;
        $adminReseller->voice_sms_route   = 1;
        $adminReseller->created_by   = 1;
        $adminReseller->save();

        $adminReseller->assignRole($resellerRole);
        $adminReseller->givePermissionTo($resellerPermission);*/

    }

    
    
}
