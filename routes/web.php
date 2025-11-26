<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->to(env('FRONT_URL'));
    //return view('welcome');
});

Route::get('/auth/facebook/callback', function () {
    
    dd('good');
});

Route::get('sha256', function () {
    $tagId = env('TagID', 5122);
    $entity_id = 1101172974420810001;
    $telemarketer_id = 1101172974420830004;
    $tlv_tagId_hash = genSHA256($tagId, $entity_id.','.$telemarketer_id);
    dd($tlv_tagId_hash, strlen($tlv_tagId_hash));
});

Route::post('sms-webhook-response', [App\Http\Controllers\WebhookController::class, 'smsWebhookResponse'])->name('sms-webhook-response');

Route::get('/client-login', [App\Http\Controllers\WebhookController::class, 'clientLogin']);

Route::get('code-info',[App\Http\Controllers\FunctionTestController::class, 'codeInfo'])->name('code-info');

Route::get('zip-download',[App\Http\Controllers\FunctionTestController::class, 'zipDownload'])->name('zip-download');
Route::get('test-wa-repush-campaign',[App\Http\Controllers\FunctionTestController::class, 'testWaRepushCampaign'])->name('test-wa-repush-campaign');

Route::post('voice-webhook',[App\Http\Controllers\WebhookController::class, 'voiceWebhook'])->name('voice-webhook');

Route::get('wa-webhook',[App\Http\Controllers\WebhookController::class, 'configureWaWebhook'])->name('wa-webhook');
Route::post('wa-webhook',[App\Http\Controllers\WebhookController::class, 'responseWaWebhook'])->name('post.wa-webhook');

Route::get('wa-partner-webhook',[App\Http\Controllers\WebhookController::class, 'configureWaPartnerWebhook'])->name('get.wa-partner-webhook');
Route::post('wa-partner-webhook',[App\Http\Controllers\WebhookController::class, 'responseWaPartnerWebhook'])->name('post.wa-partner-webhook');

Route::post('testing-webhook',[App\Http\Controllers\WebhookController::class, 'testingWebhook'])->name('post.testing-webhook');

Route::get('send-sms-at-the-rate',[App\Http\Controllers\FunctionTestController::class, 'sendSmsAtTheRate'])->name('send-sms-at-the-rate');

Route::any('thanks',[App\Http\Controllers\FunctionTestController::class, 'thanks'])->name('thanks');

Route::get('/optimize-command', function () {
    \Artisan::call('optimize:clear');
    \Artisan::call('cache:forget spatie.permission.cache');
    return redirect('/');
});

Route::get('app-logs-ashok', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index')->name('laravel-log-view');

Route::get('test-function',[App\Http\Controllers\FunctionTestController::class, 'testFunction'])->name('test-function');

Route::get('excel-to-csv',[App\Http\Controllers\FunctionTestController::class, 'excelToCsv'])->name('excel-to-csv');

Route::get('test-push-notification/{device_token}',[App\Http\Controllers\FunctionTestController::class, 'testPushNotification'])->name('test-push-notification');

Route::get('check-mail',[App\Http\Controllers\FunctionTestController::class, 'checkMail'])->name('check-mail');

Route::get('create-campaign',[App\Http\Controllers\FunctionTestController::class, 'createCampaign'])->name('create-campaign');

Route::post('create-campaign',[App\Http\Controllers\FunctionTestController::class, 'postCampaign'])->name('post-campaign');

Route::get('export-multi-sheet',[App\Http\Controllers\FunctionTestController::class, 'exportMultiSheet'])->name('export-multi-sheet');

Route::get('report-user-wise',[App\Http\Controllers\FunctionTestController::class, 'reportUserWise'])->name('report-user-wise');

Route::get('pending-sms-resend',[App\Http\Controllers\FunctionTestController::class, 'pendingSmsResend'])->name('pending-sms-resend');

Route::get('reimport-kannel-file',[App\Http\Controllers\FunctionTestController::class, 'reimportKannelFile'])->name('reimport-kannel-file');

Route::get('json-response/{unique_key}',[App\Http\Controllers\FunctionTestController::class, 'jsonResponse'])->name('json-response');

Route::get('voice-file-process',[App\Http\Controllers\FunctionTestController::class, 'voiceFileProcess']);

Route::get('check-api-campaign-completed',[App\Http\Controllers\FunctionTestController::class, 'checkApiCampaignCompleted']);

Route::get('buttons-variable-payload/{templateId?}',[App\Http\Controllers\FunctionTestController::class, 'buttonsVariablePayload']);

Route::get('quality-signal-report',[App\Http\Controllers\FunctionTestController::class, 'qualitySignalReport']);

Route::get('test-update-wa-dlr',[App\Http\Controllers\FunctionTestController::class, 'testUpdateWADlr']);

Route::get('copy-dlt-templates/{from_user_id}/{to_user_id}',[App\Http\Controllers\FunctionTestController::class, 'copyDltTemplates']);

Route::get('wa-generate-payload/{whats_app_template_id}',[App\Http\Controllers\FunctionTestController::class, 'waGeneratePayload']);

Route::get('test-wa-send-message',[App\Http\Controllers\FunctionTestController::class, 'testWaSendMessage']);

Route::get('calRemaingDays',[App\Http\Controllers\FunctionTestController::class, 'calRemaingDays']);

Route::get('wa-file-download',[App\Http\Controllers\FunctionTestController::class, 'waFileDownload'])->name('wa-file-download');

Route::namespace('App\Http\Controllers')->group(function () {
    Route::controller(ShortLinkController::class)->group(function () {
        Route::get('{sub_part}/{token}', 'shortenLink')->name('shorten.link');
        Route::get('link-expired', 'linkExpired')->name('link.expired');
    });
});