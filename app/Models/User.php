<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use Laravel\Sanctum\HasApiTokens;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CurrentParent;
use App\Models\PrimaryRoute;
use App\Models\SecondaryRoute;
use App\Models\IpWhiteListForApi;
use App\Models\Appsetting;
use App\Models\ContactGroup;
use App\Models\ManageSenderId;
use App\Models\DltTemplateGroup;
use App\Models\DltTemplate;
use App\Models\CreditLog;
use App\Models\Blacklist;
use App\Models\UserDocument;
use App\Models\Message;
use App\Models\SendSms;
use App\Models\UserDevice;
use App\Models\UserLog;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppFile;
use App\Models\VoiceUpload;
use App\Models\SpeedRatio;
use App\Models\WhatsAppCharge;
use App\Models\WhatsAppChatBot;
use App\Models\SubscribeWebhookEvent;
use Str;
use DateTimeInterface;
use App\Observers\UserObserver;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, CurrentParent;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'uuid',
        'userType',
        'is_belongs_to_admin',
        'parent_id',
        'current_parent_id',
        'name',
        'email',
        'username',
        'email_verified_at',
        'password',
        'mobile',
        'address',
        'country',
        'city',
        'zipCode',
        'companyName',
        'create_by',
        'companyLogo',
        'websiteUrl',
        'app_key',
        'app_secret',
        'support_person_id',
        'designation',
        'created_by',
        'is_show_ratio',
        'authority_type',
        'is_enabled_api_ip_security',
        'is_visible_dlt_template_group',
        'locktimeout',
        'status',
        'account_type',

        'otp_route',
        'promotional_route',
        'promotional_credit',
        'transaction_route',
        'transaction_credit',
        'two_waysms_route',
        'two_waysms_credit',
        'voice_sms_route',
        'voice_sms_credit',
        'whatsapp_credit',
        
        'allow_to_add_webhook',
        'webhook_callback_url',
        'webhook_signing_key',

        'allow_detail_report',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_key',
        'app_secret',
        'is_show_ratio',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'string',
    ];

    protected $dates = ['deleted_at'];

    public $guard_name = 'api';

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
            $model->app_key = Str::random(25);
            $model->app_secret = Str::random(15);
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class,'parent_id','id');
    }

    public function speedRatio()
    {
        return $this->hasOne(SpeedRatio::class,'user_id','id');
    }

    public function WhatsAppCharges()
    {
        return $this->hasMany(WhatsAppCharge::class, 'user_id', 'id');
    }

    public function whatsAppChatBots()
    {
        return $this->hasMany(WhatsAppChatBot::class, 'user_id', 'id');
    }

    public function subscribeWebhookEvents()
    {
        return $this->hasMany(SubscribeWebhookEvent::class, 'user_id', 'id');
    }

    public function userDevices()
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'id');
    }

    public function userLogs()
    {
        return $this->hasMany(User::class, 'user_id', 'id');
    }

    public function waConfigurations()
    {
        return $this->hasMany(WhatsAppConfiguration::class, 'user_id', 'id');
    }

    public function waTemplates()
    {
        return $this->hasMany(WhatsAppTemplate::class, 'user_id', 'id');
    }

    public function whatsAppFiles()
    {
        return $this->hasMany(WhatsAppFile::class, 'user_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(self::class,'created_by');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'current_parent_id', 'id');
    }

    public function grandchildren()
    {
        return $this->children()->with('grandchildren');
    }

    public function primaryRoutes()
    {
        return $this->hasMany(PrimaryRoute::class,'created_by', 'id');
    }

    public function secondaryRoute()
    {
        return $this->hasMany(SecondaryRoute::class,'created_by', 'id');
    }

    public function ipWhiteListForApis()
    {
        return $this->hasMany(IpWhiteListForApi::class, 'user_id', 'id');
    }

    public function contactGroups()
    {
        return $this->hasMany(ContactGroup::class, 'user_id', 'id');
    }

    public function manageSenderIds()
    {
        return $this->hasMany(ManageSenderId::class, 'user_id', 'id');
    }

    public function dltTemplateGroups()
    {
        return $this->hasMany(DltTemplateGroup::class, 'user_id', 'id');
    }

    public function dltTemplates()
    {
        return $this->hasMany(DltTemplate::class, 'user_id', 'id');
    }

    public function creditLogs()
    {
        return $this->hasMany(CreditLog::class, 'user_id', 'id');
    }

    public function blacklists()
    {
        return $this->hasMany(Blacklist::class, 'user_id', 'id');
    }

    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class, 'user_id', 'id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function sendSmses()
    {
        return $this->hasMany(SendSms::class, 'user_id', 'id');
    }

    public function voiceUploads()
    {
        return $this->hasMany(VoiceUpload::class, 'user_id', 'id');
    }

    public function promotionalRouteInfo()
    {
        return $this->belongsTo(SecondaryRoute::class, 'promotional_route', 'id');
    }

    public function transactionRouteInfo()
    {
        return $this->belongsTo(SecondaryRoute::class, 'transaction_route', 'id');
    }

    public function twoWaysmsRouteInfo()
    {
        return $this->belongsTo(SecondaryRoute::class, 'two_waysms_route', 'id');
    }

    public function voiceSmsRouteInfo()
    {
        return $this->belongsTo(SecondaryRoute::class, 'voice_sms_route', 'id');
    }
}
