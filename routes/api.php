<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

//Route::post('login',[App\Http\Controllers\Api\Common\AuthController::class, 'login']);
//API Documents
Route::get('document-code-lang',[App\Http\Controllers\DocumentController::class, 'index'])->name('document-code-lang');

Route::get('get-web-content/{sub_part}/{token}/{mobile_num}',[App\Http\Controllers\DocumentController::class, 'getWebContent'])->name('get-web-content');

Route::post('take-response',[App\Http\Controllers\DocumentController::class, 'takeResponse'])->name('take-response');

Route::post('generate-verify-code', [App\Http\Controllers\DocumentController::class, 'generateVerifyCode'])->name('generate-verify-code');
Route::post('save-contact-us', [App\Http\Controllers\DocumentController::class, 'saveContactUs'])->name('save-contact-us');

Route::post('check-wa-chatbot', [App\Http\Controllers\WebhookController::class, 'checkWaChatbot'])->name('check-wa-chatbot');

// Testing routes start
/*****************************************/
//Route::post('check-cache', [App\Http\Controllers\FunctionTestController::class, 'checkCache'])->name('check-cache');
/*****************************************/
// Testing routes end

Route::get('countries',[App\Http\Controllers\DocumentController::class, 'countries'])->name('countries');

Route::get('public-app-setting',[App\Http\Controllers\Api\Admin\AppsettingController::class, 'publicAppSetting'])->name('public-app-setting');

Route::controller(App\Http\Controllers\Api\Admin\AppsettingController::class)->group(function () {
    Route::get('get-app-setting', 'index')->name('get-app-setting')->middleware('auth:api');
});

Route::namespace('App\Http\Controllers\Api\Common')->group(function () {

    Route::controller(AuthController::class)->group(function () {
        Route::get('unauthorized', 'unauthorized')->name('unauthorized');
        Route::post('login', 'login')->name('login');
        Route::post('resent-otp', 'resentOtp')->name('resent-otp');
        Route::post('verify-otp', 'verifyOtp')->name('verify-otp');
        Route::post('sign-up', 'signUp')->name('sign-up');
        Route::post('forgot-password', 'forgotPassword')->name('forgot-password');
        Route::post('update-password', 'updatePassword')->name('update-password');
        Route::post('logout', 'logout')->name('logout')->middleware('auth:api');
        Route::post('change-password', 'changePassword')->name('changePassword')->middleware('auth:api');
        Route::post('add-webhook-url', 'addWebhookUrl')->name('add-webhook-url')->middleware('auth:api');
        Route::get('get-webhook-events', 'getWebhookEvents')->name('get-webhook-events');
        Route::get('get-all-timezones', 'getAllTimezones')->name('get-all-timezones');
    });

    Route::group(['middleware' => 'auth:api'],function () {
        Route::controller(FileUploadController::class)->group(function () {
            Route::post('file-upload', 'fileUpload')->name('file-upload');
        });

        Route::controller(UserController::class)->group(function () {
            Route::post('users', 'index')->name('users');
            Route::apiResource('user', UserController::class)->only(['store','destroy','show', 'update']);
            Route::post('users-for-ddl', 'usersForDdl')->name('users-for-ddl');
            Route::post('user-action', 'userAction')->name('user-action');
            Route::get('get-webhook-info/{user_id}', 'getWebhookInfo')->name('get-webhook-info');
        });

        Route::controller(UserLoginLogController::class)->group(function () {
            Route::post('view-login-log', 'viewLoginLog')->name('view-login-log');
        });

        Route::controller(AuthController::class)->group(function () {
            Route::post('child-login', 'childLogin')->name('child-login');
        });

        Route::controller(RoleController::class)->group(function () {
            Route::post('roles', 'index')->name('roles');
            Route::apiResource('role', RoleController::class)->only(['store','destroy', 'show', 'update']);
            Route::get('get-permissions', 'getPermissions')->name('get-permissions');
            Route::get('get-all-permissions', 'getAllPermissions')->name('get-all-permissions');
        });

        Route::controller(UserActionController::class)->group(function () {
            Route::post('user-change-api-key', 'userChangeApiKey')->name('user-change-api-key');
            Route::post('enable-additional-security', 'enableAdditionalSecurity')->name('enable-additional-security');
            Route::post('reset-user-password', 'resetUserPassword')->name('reset-user-password');
            Route::post('update-profile', 'updateProfile')->name('update-profile');
            Route::post('update-self-password', 'updateSelfPassword')->name('update-self-password');
        });

        Route::controller(UserCreditController::class)->group(function () {
            Route::post('user-credits', 'index')->name('user-credits');
            Route::apiResource('user-credit', UserCreditController::class)->only(['store']);
        });

        Route::controller(SampleFileController::class)->group(function () {
            Route::get('dlt-sample-file', 'dltSampleFile')->name('dlt-sample-file');
            Route::get('group-sample-file', 'groupSampleFile')->name('group-sample-file');
            Route::get('vandor-dlr-sample-file', 'vandorDlrSampleFile')->name('vandor-dlr-sample-file');
            Route::get('black-list-sample-file', 'blackListSampleFile')->name('black-list-sample-file');
            Route::get('normal-sms-campaign-file', 'normalSmsCampaignFile')->name('normal-sms-campaign-file');
            Route::get('custom-sms-campaign-file', 'customSmsCampaignFile')->name('custom-sms-campaign-file');
        });

        Route::controller(NotificationController::class)->group(function () {
            Route::post('notifications', 'index')->name('notifications');
            Route::apiResource('notification', NotificationController::class)->only(['destroy','show']);
            Route::get('notification-read/{id}', 'read')->name('notification-read');
            Route::get('user-notification-read-all', 'userNotificationReadAll')->name('user-notification-read-all');
            Route::get('user-notification-delete', 'userNotificationDelete')->name('user-notification-delete');
            Route::get('unread-notification-count', 'unreadNotificationsCount')->name('unread-notification-count');
        });

        Route::controller(ContactGroupController::class)->group(function () {
            Route::post('contact-groups', 'index')->name('contact-groups');
            Route::apiResource('contact-group', ContactGroupController::class)->only(['destroy','show', 'update']);
        });

        Route::controller(ContactNumberController::class)->group(function () {
            Route::post('contact-numbers', 'index')->name('contact-numbers');
            Route::apiResource('contact-number', ContactNumberController::class)->only(['store','destroy']);
            Route::post('contact-numbers-import', 'contactNumbersImport')->name('contact-numbers-import');
        });

        Route::controller(BlacklistController::class)->group(function () {
            Route::post('blacklists', 'index')->name('blacklists');
            Route::apiResource('blacklist', BlacklistController::class)->only(['store','destroy']);
            Route::post('blacklist-action', 'blacklistAction')->name('blacklist-action');
            Route::post('blacklists-import', 'blacklistsImport')->name('blacklists-import');
        });

        Route::controller(IpWhiteListForApiController::class)->group(function () {
            Route::post('ip-white-list-for-apis', 'index')->name('ip-white-list-for-apis');
            Route::apiResource('ip-white-list-for-api', IpWhiteListForApiController::class)->only(['store','destroy']);
        });

        Route::controller(ManageSenderIdController::class)->group(function () {
            Route::post('manage-sender-ids', 'index')->name('manage-sender-ids');
            Route::apiResource('manage-sender-id', ManageSenderIdController::class)->only(['store','destroy','update']);
            Route::post('sender-id-action', 'senderidAction')->name('sender-id-action');
            Route::post('assign-sender-id', 'assignSenderId')->name('assign-sender-id');
        });

        Route::controller(DltTemplateGroupController::class)->group(function () {
            Route::post('dlt-template-groups', 'index')->name('dlt-template-groups');
            Route::apiResource('dlt-template-group', DltTemplateGroupController::class)->only(['store','destroy','show','update']);
        });

        Route::controller(DltTemplateController::class)->group(function () {
            Route::post('dlt-templates', 'index')->name('dlt-templates');
            Route::apiResource('dlt-template', DltTemplateController::class)->only(['store','destroy','show','update']);
            Route::post('dlt-templates-import', 'dltTemplatesImport')->name('dlt-templates-import');
            Route::post('dlt-templates-assign-to-users', 'dltTemplatesAssignToUsers')->name('dlt-templates-assign-to-users');
            Route::post('dlt-templates-assign-to-group', 'dltTemplatesAssignToGroup')->name('dlt-templates-assign-to-group');
        });

        Route::controller(UserDocumentController::class)->group(function () {
            Route::post('user-documents', 'index')->name('user-documents');
            Route::apiResource('user-document', UserDocumentController::class)->only(['store','destroy']);
        });

        Route::controller(CreditRequestController::class)->group(function () {
            Route::post('credit-requests', 'index')->name('credit-requests');
            Route::apiResource('credit-request', CreditRequestController::class)->only(['store','destroy']);
            Route::post('credit-request-reply', 'creditRequestReply')->name('credit-request-reply');
        });

        Route::controller(TwowayssmsController::class)->group(function () {
            Route::post('two-ways-smses', 'index')->name('two-ways-smses');
            Route::apiResource('two-ways-sms', TwowayssmsController::class)->only(['store','destroy','show', 'update']);
        });


        //SMS
        Route::controller(SendSmsController::class)->group(function () {
            Route::post('campaign-list', 'index')->name('all-campaign-list');
            Route::apiResource('send-sms', SendSmsController::class)->only(['store','show']);
            Route::post('change-campaign-status-to-stop', 'changeCampaignStatusToStop')->name('change-campaign-status-to-stop');
            Route::post('repush-campaign', 'repushCampaign')->name('repush-campaign');
            Route::post('get-campaign-info/{send_sms_id}', 'getCampaignInfo')->name('get-campaign-info');
            Route::post('resend-this-sms', 'resendThisSms')->name('resend-this-sms');
            Route::get('get-campaign-current-status/{send_sms_id}', 'getCampaignCurrentStatus')->name('get-campaign-current-status');
            Route::get('get-dlr-explanation/{send_sms_id}', 'getDlrExplanation')->name('get-dlr-explanation');

            
        });

        /********************************************/
        // Voice

        Route::controller(NoPermissionController::class)->group(function () {
            Route::get('get-voice-smsc-id', 'getVoiceSmscID')->name('get-voice-smsc-id');
        });

        Route::controller(VoiceUploadController::class)->group(function () {
            Route::post('voice-file-list', 'index')->name('voice-file-list');
            Route::apiResource('voice-upload', VoiceUploadController::class)->only(['store','show','destroy']);
        });

        Route::controller(VoiceSmsController::class)->group(function () {
            Route::post('voice-campaign-list', 'index')->name('voice-campaign-list');
            Route::apiResource('voice-sms', VoiceSmsController::class)->only(['store','show']);
            Route::post('change-voice-campaign-status-to-stop', 'changeVoiceCampaignStatusToStop')->name('change-voice-campaign-status-to-stop');
            Route::post('get-voice-campaign-info/{send_sms_id}', 'getVoiceCampaignInfo')->name('get-voice-campaign-info');
            Route::post('resend-this-voice-sms', 'resendThisVoiceSms')->name('resend-this-voice-sms');
            Route::get('get-voice-campaign-current-status/{send_sms_id}', 'getVoiceCampaignCurrentStatus')->name('get-voice-campaign-current-status');
            Route::get('get-voice-campaign-summery-server/{voice_sms_id}', 'getVoiceCampaignSummeryServer')->name('get-voice-campaign-summery-server');
            Route::get('get-voice-campaign-calling-detail-server/{voice_sms_id}', 'getVoiceCampaignCallingDetailServer')->name('get-voice-campaign-calling-detail-server');
        });

        /********************************************/
        // Whatsapp


        Route::controller(WAChargeController::class)->group(function () {
            Route::post('wa-charges', 'index')->name('wa-charges');
            Route::apiResource('set-wa-charge', WAChargeController::class)->only(['store']);
        });

        Route::controller(WAConfigurationController::class)->group(function () {
            Route::post('wa-account-configurations', 'index')->name('wa-account-configurations');
            Route::apiResource('wa-account-configuration', WAConfigurationController::class)->only(['store','destroy','show', 'update']);
            Route::get('wa-account-quality-check/{whats_app_configuration_id}', 'waAccountQualityCheck')->name('wa-account-quality-check');

            // Register
            Route::post('wa-phone-number-register/{whats_app_configuration_id}', 'waPhoneNumberRegister')->name('wa-phone-number-register');

            Route::get('wa-phone-number-request-to-verify/{whats_app_configuration_id}', 'waPhoneNumberRequestToVerify')->name('wa-phone-number-request-to-verify');
            Route::post('wa-phone-number-verify/{whats_app_configuration_id}', 'waPhoneNumberVerify')->name('wa-phone-number-verify');
            Route::get('wa-business-category', 'waBusinessCategory')->name('wa-business-category');

            Route::post('wa-flow-signup/{fb_code}/{user_id}/{business_id}', 'waFlowSignup')->name('wa-flow-signup');

            Route::post('wa-subscribed-apps/{whats_app_configuration_id}', 'waSubscribedApps')->name('wa-subscribed-apps');

            Route::post('wa-debug-token/{whats_app_configuration_id}', 'waDebugToken')->name('wa-debug-token');

            // wa ecom
            Route::post('wa-get-commerce-settings/{whats_app_configuration_id}', 'waGetCommerceSettings')->name('wa-get-commerce-settings');
            Route::post('wa-set-commerce-settings/{whats_app_configuration_id}', 'waSetCommerceSettings')->name('wa-set-commerce-settings');

            Route::post('update-wa-token/{whats_app_configuration_id}', 'updateWaToken')->name('update-wa-token');
            Route::post('wa-business-profile/{whats_app_configuration_id}', 'waBusinessProfile')->name('wa-business-profile');
            Route::post('wa-calling-setting-change/{whats_app_configuration_id}', 'waCallingSettingChange')->name('wa-calling-setting-change');
            Route::get('wa-calling-setting-get/{whats_app_configuration_id}', 'waCallingSettingGet')->name('wa-calling-setting-get');

        });

        Route::controller(WAQrCodeController::class)->group(function () {
            Route::post('wa-qrcodes', 'index')->name('wa-qrcodes');
            Route::apiResource('wa-qrcode', WAQrCodeController::class)->only(['store','destroy']);
            Route::post('wa-qrcodes-sync', 'waQrcodesSync')->name('wa-qrcodes-sync');
        });

        Route::controller(WATemplateController::class)->group(function () {
            Route::post('wa-templates', 'index')->name('wa-templates');
            Route::apiResource('wa-template', WATemplateController::class)->only(['store','destroy','show', 'update']);
            Route::get('wa-template-languages', 'waTemplateLanguages')->name('wa-template-languages');
            Route::post('wa-template-submit', 'waTemplateSubmit')->name('wa-template-submit');
            Route::post('wa-pull-template', 'waPullTemplate')->name('wa-pull-template');
            Route::post('wa-pull-all-template', 'waPullAllTemplate')->name('wa-pull-all-template');
            Route::get('wa-generate-payload/{whats_app_template_id}/{user_id}', 'waGeneratePayload')->name('wa-generate-payload');
            Route::get('download-wa-sample-file/{whats_app_template_id}/{type?}', 'downloadWASampleFile')->name('download-wa-sample-file');
        });
        
        Route::controller(WAFileUploadController::class)->group(function () {

            Route::post('wa-files', 'index')->name('wa-files');

            Route::apiResource('wa-file', WAFileUploadController::class)->only(['store', 'show', 'destroy']);
            Route::post('upload-wa-file', 'uploadWaFile')->name('upload-wa-file');
        });

        Route::controller(WASendMessageController::class)->group(function () {
            Route::post('wa-campaigns', 'index')->name('wa-campaigns');
            Route::apiResource('wa-send-message', WASendMessageController::class)->only(['store', 'show']);
            Route::post('wa-get-campaign-info/{wa_send_sms_id}', 'waGetCampaignInfo')->name('wa-get-campaign-info');
            Route::post('wa-repush-campaign/{wa_send_sms_id}', 'waRepushCampaign')->name('wa-repush-campaign');
            Route::post('wa-campaign-stop', 'waCampaignStop')->name('wa-campaign-stop');
            Route::get('wa-get-campaign-current-status/{whats_app_send_sms_id}', 'waGetCampaignCurrentStatus')->name('wa-get-campaign-current-status');
            Route::post('wa-reply-thread-users', 'waReplyThreadUsers')->name('wa-reply-thread-users');
            Route::post('wa-reply-threads-case-wise', 'waReplyThreadCaseWise')->name('wa-reply-threads-case-wise');
            Route::post('wa-send-reply-message', 'waSendReplyMessage')->name('wa-send-reply-message');
            Route::post('wa-download-reply-file', 'waDownloadReplyFile')->name('wa-download-reply-file');
        });

        Route::controller(WAChatBotController::class)->group(function () {
            Route::post('wa-chatbots', 'index')->name('wa-chatbots');
            Route::apiResource('wa-chatbot', WAChatBotController::class)->only(['store','destroy','show', 'update']);
        });


        Route::controller(WAReportController::class)->group(function () {
            Route::post('get-analytics', 'getAnalytics')->name('get-analytics');
            Route::post('get-conversation-analytics', 'getConversationAnalytics')->name('get-conversation-analytics');
            Route::post('get-template-analytics', 'getTemplateAnalytics')->name('get-template-analytics');
            Route::post('get-credit-lines', 'getCreditLines')->name('get-credit-lines');
            Route::post('get-wa-summery', 'getWaSummery')->name('get-wa-summery');
            
        });


        /********************************************/


        //Reports
        Route::controller(ReportController::class)->group(function () {
            Route::post('dashboard', 'dashboard')->name('dashboard');
            Route::get('server-info', 'serverInfo')->name('server-info');
            Route::post('delivery-report', 'deliveryReport')->name('delivery-report');
            Route::post('scheduled-campaign-report', 'scheduledCampaignReport')->name('scheduled-campaign-report');
            Route::post('msg-consumption-report', 'msgConsumptionReport')->name('msg-consumption-report');
            Route::post('consumption-report-by-view', 'consumptionReportByView')->name('consumption-report-by-view');
            Route::post('report-by-mobile', 'reportByMobile')->name('report-by-mobile');
            Route::post('summary-report-by-template-group', 'summaryReportByTemplateGroup')->name('summary-report-by-template-group');
            Route::post('overview-report-by-user', 'overviewReportByUser')->name('overview-report-by-user');
            Route::post('get-report-by-time-frame', 'getReportByTimeFrame')->name('get-report-by-time-frame');
            Route::post('month-wise-submission-report', 'monthWiseSubmissionReport')->name('month-wise-submission-report');

            Route::post('detailed-report', 'detailedReport')->name('detailed-report');
            Route::post('export-detailed-report', 'exportDetailedReport')->name('export-detailed-report');
            Route::post('export-sms-report', 'exportSmsReportZIP')->name('export-sms-report');


            //Exports
            Route::get('exports', 'exports')->name('exports');
            Route::get('export-status/{export_id}', 'exportStatus')->name('export-status');
            Route::get('download-export/{file}', 'downloadZipExport')->name('download-export');



            Route::post('two-way-link-click-log', 'twoWayLinkClickLog')->name('two-way-link-click-log');
            Route::post('two-way-capture-record-log', 'twoWayCaptureRecordLog')->name('two-way-capture-record-log');


            //Exports
            Route::post('report-export-by-id', 'reportExportById')->name('report-export-by-id');
            Route::post('report-export-by-sender-id', 'reportExportBySenderId')->name('report-export-by-sender-id');
            Route::post('report-export-by-mobile', 'reportExportByMobile')->name('report-export-by-mobile');

            //voice
            Route::post('report-export-by-voice-sms-id', 'reportExportByVoiceSmsId')->name('report-export-by-voice-sms-id');

            // twoway
            Route::post('twoway-report-log-export-by-camapign', 'twowayReportLogExportByCamapign')->name('twoway-report-log-export-by-camapign');
            Route::post('twoway-report-response-export-by-camapign', 'twowayReportResponseExportByCamapign')->name('twoway-report-response-export-by-camapign');

            // Whatsapp
            Route::post('export-wa-report', 'exportWaReport')->name('export-wa-report');
            Route::post('export-wa-conversation', 'exportWaConversation')->name('export-wa-conversation');
            Route::post('get-wa-summery-download', 'getWaSummeryDownload')->name('get-wa-summery-download');
        });

    });

});


/*----------------Admin Route----------------*/
Route::namespace('App\Http\Controllers\Api\Admin')->group(function () {
    Route::group(['prefix' => 'administration', 'middleware' => ['admin', 'auth:api']],function () {
            
        Route::controller(AppsettingController::class)->group(function () {
            Route::post('app-setting-update', 'addUpdate')->name('app-setting-update');
            Route::get('get-current-kannel-status', 'getCurrentKannelStatus')->name('get-current-kannel-status');
            Route::post('contact-us', 'contactUs')->name('contact-us');
        });

        Route::controller(CountryController::class)->group(function () {
            Route::post('all-countries', 'index')->name('allCountries');
            Route::apiResource('country', CountryController::class)->only(['store','destroy','show', 'update']);
            Route::post('wa-rate-card', 'waRateCard')->name('wa-rate-card');
            Route::post('wa-rate-card-import', 'waRateCardImport')->name('wa-rate-card-import');
        });

        Route::controller(ManageDocumentController::class)->group(function () {
            Route::post('documents', 'index')->name('documents');
            Route::apiResource('document', ManageDocumentController::class)->only(['store','destroy','show', 'update']);
        });

        Route::controller(AdminActionController::class)->group(function () {
            Route::post('assign-route-to-user', 'assignRouteToUser')->name('assign-route-to-user');
            Route::post('change-dlt-template-priority', 'changeDltTemplatePriority')->name('change-dlt-template-priority');
            Route::post('set-user-ratio', 'setUserRatio')->name('set-user-ratio');
            Route::post('user-wise-credit-info', 'userWiseCreditInfo')->name('user-wise-credit-info');
            Route::post('get-daily-report', 'getDailyReport')->name('get-daily-report');
            
            //Route::get('reimport-kannel-file', 'reimportKannelFile')->name('reimport-kannel-file');

            // server commands
            Route::post('server-commands', 'serverCommands')->name('server-commands');
            
        });

        Route::controller(PrimaryRouteController::class)->group(function () {
            Route::post('primary-routes', 'index')->name('primary-routes');
            Route::apiResource('primary-route', PrimaryRouteController::class)->only(['store','destroy','show', 'update']);
            Route::post('primary-route-action', 'primaryRouteAction')->name('primary-route-action');
            Route::get('check-primary-route-connection/{primary_route_id}', 'checkPrimaryRouteConnection')->name('check-primary-route-connection');
            Route::get('check-voice-balance/{primary_route_id}', 'checkVoiceBalance')->name('check-voice-balance');
        });

        Route::controller(SecondaryRouteController::class)->group(function () {
            Route::post('secondary-routes', 'index')->name('secondary-routes');
            Route::apiResource('secondary-route', SecondaryRouteController::class)->only(['store','destroy','show', 'update']);
            Route::post('secondary-route-action', 'secondaryRouteAction')->name('secondary-route-action');
        });

        Route::controller(DlrcodeVenderController::class)->group(function () {
            Route::post('dlrcode-list', 'index')->name('dlrcodes-list');
            Route::apiResource('dlrcode', DlrcodeVenderController::class)->only(['store','destroy','show', 'update']);
            Route::post('dlrcode-action', 'dlrcodeAction')->name('dlrcode-action');
            Route::post('dlrcode-vender-import', 'dlrcodeVenderImport')->name('dlrcode-vender-import');
        });

        Route::controller(NotificationTemplateController::class)->group(function () {
            Route::post('notification-templates', 'index')->name('notification-templates');
            Route::apiResource('notification-template', NotificationTemplateController::class)->only(['store','destroy','show', 'update']);
        });

        Route::controller(InvalidSeriesController::class)->group(function () {
            Route::post('invalid-series-list', 'index')->name('invalid-series-list');
            Route::apiResource('invalid-series', InvalidSeriesController::class)->only(['store','destroy']);
        });

        Route::controller(MonthlyReportController::class)->group(function () {
            Route::post('user-wise-monthly-reports', 'index')->name('user-wise-monthly-reports');
            Route::apiResource('user-wise-monthly-report', MonthlyReportController::class)->only(['store', 'update']);
        });

        Route::controller(ManageCampaignController::class)->group(function () {
            Route::post('manage-campaign', 'manageCampaign')->name('manage-campaign');
            Route::get('change-campaign-status-to-complete/{send_sms_id}', 'changeCampaignStatusToComplete')->name('change-campaign-status-to-complete/{send_sms_id}');
            Route::get('get-user-route-info', 'getUserRouteInfo')->name('get-user-route-info');
            Route::post('informing-user-about-server', 'informingUserAboutServer')->name('informing-user-about-server');
            Route::get('reupdate-pending-status/{send_sms_id}', 'reupdatePendingStatus')->name('reupdate-pending-status');

            Route::post('manage-voice-campaign', 'manageVoiceCampaign')->name('manage-voice-campaign');
            
        });

        //Voice
        Route::controller(VoiceFileProcessController::class)->group(function () {
            Route::post('get-all-voice-file-by-status', 'getAllVoiceFileByStatus')->name('get-all-voice-file-by-status');
            Route::post('voice-file-process', 'voiceFileProcess')->name('voice-file-process');
            Route::get('check-voice-file-status/{voice_id}', 'checkVoiceFileStatus')->name('check-voice-file-status');
            Route::post('bulk-voice-file-action', 'bulkVoiceFileAction')->name('bulk-voice-file-action');
            Route::post('sync-voice-template-to-vendor', 'syncVoiceTemplateToVendor')->name('sync-voice-template-to-vendor');
        });

        Route::controller(WhatsAppAdminController::class)->group(function () {
            
        });

    });
});

/*----------------API Route----------------*/
Route::namespace('App\Http\Controllers\Api')->group(function () {

    Route::group(['middleware' => ['request_log']],function () {

        Route::group(['prefix' => 'v1'],function () {
            Route::controller(ApiGetController::class)->group(function () {
                Route::get('account-status', 'accountStatus')->name('v1.account-status'); 
                Route::get('check-balance', 'checkBalance')->name('v1.check-balance'); 
                Route::get('approved-senderids', 'approvedSenderids')->name('v1.approved-senderids');
                Route::get('templates', 'templates')->name('v1.templates'); 
                Route::get('campaign-list', 'campaignList')->name('v1.campaign-list');         
                Route::get('send-message', 'sendMessage')->name('v1.send-message');         
                Route::get('generate-otp', 'sendMessage')->name('v1.generate-otp');         
                Route::get('send-otp', 'sendMessage')->name('v1.send-otp'); 
                Route::get('send-report/{response_token?}', 'sendReport')->name('v1.send-report'); 
                Route::get('send-number-report/{mobile_number?}/{response_token?}', 'sendNumberReport')->name('v1.send-number-report'); 
                Route::any('sms-overall-report', 'smsOverallReport')->name('v1.sms-overall-report'); 

                //Voice        
                Route::get('voice-templates', 'voiceTemplates')->name('v1.voice-templates');  
                Route::get('voice-send-sms', 'voiceSendSms')->name('v1.voice-send-sms');         
                Route::get('voice-send-otp', 'voiceSendSms')->name('v1.voice-send-otp');    

                // whatsapp
                Route::get('send-wa-message', 'sendWaMessage')->name('v1.send-wa-message');  
                Route::any('get-wa-report/{response_token}', 'getWaReport')->name('v1.get-wa-report');       
                Route::any('get-wa-templates/{template_name?}', 'getWaTemplates')->name('v1.get-wa-templates');
                Route::any('get-wa-files/{file_type?}', 'getWaFiles')->name('v1.get-wa-files');  

            });
        });

        //without v1 get APIs
        Route::controller(ApiGetController::class)->group(function () {
            Route::get('account-status', 'accountStatus')->name('account-status'); 
            Route::get('check-balance', 'checkBalance')->name('check-balance'); 
            Route::get('approved-senderids', 'approvedSenderids')->name('approved-senderids');
            Route::get('templates', 'templates')->name('templates'); 
            Route::get('campaign-list', 'campaignList')->name('campaign-list');
            Route::any('send-message', 'sendMessage')->name('send-message');  
            Route::any('generate-otp', 'sendMessage')->name('generate-otp');         
            Route::any('send-otp', 'sendMessage')->name('send-otp');         
            Route::post('send-custom-message', 'sendCustomMessage')->name('send-custom-message'); 

            Route::any('sms-overall-report', 'smsOverallReport')->name('sms-overall-report');    

            //Voice        
            Route::get('voice-templates', 'voiceTemplates')->name('voice-templates');  
            Route::post('voice-send-sms', 'voiceSendSms')->name('voice-send-sms');         
            Route::post('voice-send-otp', 'voiceSendSms')->name('voice-send-otp');  

            // whatsapp
            Route::any('send-wa-message', 'sendWaMessage')->name('send-wa-message');       
            Route::any('get-wa-report/{response_token}', 'getWaReport')->name('get-wa-report');       
            Route::any('get-wa-templates/{template_name?}', 'getWaTemplates')->name('get-wa-templates');       
            Route::any('get-wa-files/{file_type?}', 'getWaFiles')->name('get-wa-files');       
        });
    });
});
