<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;
use App\Models\Appsetting;

class NotificationTemplateSeeder extends Seeder
{
    public function run()
    {
        NotificationTemplate::truncate();
        $getAppSetting = Appsetting::first();
        $footer = '<br><br>
        <div style="border-bottom: 1px solid #f0ab04;"></div>
        <br>
                    Regards,<br>'
                    .$getAppSetting->app_name.',<br>'
                    .$getAppSetting->contact_address.',<br>'
                    .$getAppSetting->contact_number.',<br>'
                    .$getAppSetting->contact_email.',<br>';
        $logo = '<img src="'.$getAppSetting->app_logo.'" height="40px">';

        $templates = [
            [
                'notification_for' => 'forgot-password',
                'mail_subject' => 'Instructions to Reset Your Password',
                'mail_body' => 'Dear {{name}},
<br>
We received a request to reset your account password. Please follow the instructions below to create a new password:
<br>
1. Click on the following link: <a href="{{link}}" style="background: #f0ab04; padding: 5px; text-decoration: none; color:#fff">Password Reset Link</a><br>
2. Enter your new password in the provided fields.<br>
3. Confirm the changes.<br>
<br>
If you did not request a password reset, please ignore this email or contact our support team immediately at <strong>info@nrt.co.in</strong> to secure your account.
<br><br>
Thank you for your cooperation in keeping your account secure.
<br><br>

'.$footer.'<br>
'.$logo,
                'notification_subject' => null,
                'notification_body' => null,
                'custom_attributes' => '{{name}},{{link}}',
                'save_to_database' => false,
                'status_code' => null,
                'route_path' => null,
            ],
            [
                'notification_for' => 'password-changed',
                'mail_subject' => 'Your Password Has Been Successfully Changed',
                'mail_body' => 'Dear {{name}},
<br>
We are writing to inform you that your account password has been successfully changed. If you initiated this change, no further action is required.
<br>
If you did not request a password change, please contact our support team immediately at <strong>info@nrt.co.in</strong> to secure your account.
<br><br>
Thank you for your attention to this matter and for choosing our services.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => 'Your Password Has Been Successfully Changed',
                'notification_body' => '',
                'custom_attributes' => '{{name}}',
                'save_to_database' => false,
                'status_code' => 'success',
                'route_path' => null,
            ],
            [
                'notification_for' => 'send-otp-for-login',
                'mail_subject' => 'Your One-Time Password (OTP) for Login SMS Pannel',
                'mail_body' => 'Dear {{name}},<br>
To enhance the security of your account, we have implemented a One-Time Password (OTP) system for login. Please use the OTP below to access your account:
<br>
<strong>Your OTP: {{otp}}</strong>
This OTP is valid for today only and can be used once. If you did not request this OTP, please ignore this email or contact our support team immediately.
<br><br>
Thank you for your cooperation in keeping your account secure.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => null,
                'notification_body' => null,
                'custom_attributes' => '{{name}}, {{otp}}',
                'save_to_database' => false,
                'status_code' => 'success',
                'route_path' => null,
            ],
            [
                'notification_for' => 'important-notification',
                'mail_subject' => 'Important Notification – Please Read Carefully',
                'mail_body' => 'Dear {{name}},<br>
TThis is to inform you of an important update that requires your attention.
<br>
<strong>Details:</strong>


<br><br>
Thank you for your attention to this matter and for choosing our services.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => null,
                'notification_body' => null,
                'custom_attributes' => '{{name}}',
                'save_to_database' => false,
                'status_code' => 'success',
                'route_path' => null,
            ],
            [
                'notification_for' => 'credit-added',
                'mail_subject' => 'SMS Credits Successfully Added to Your Account',
                'mail_body' => 'Dear {{name}},<br>
We are pleased to inform you that <strong>{{no_of_credit}}</strong> SMS credits have been successfully added to your account. You can now use these credits to send messages as per your requirements.
<br>
To check your updated balance or for any assistance, please log in to your account or contact our support team at <strong>info@nrt.co.in</strong>.
<br><br>
Thank you for your continued trust in our services.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => 'Credit added',
                'notification_body' => '{{no_of_credit}} SMS credits have been successfully added to your account.',
                'custom_attributes' => '{{name}},{{no_of_credit}}',
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'user-credit-reverse',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'Credit Reversed',
                'notification_body' => '{{no_of_credit}}  SMS credits have been successfully refunded and added to your account.',
                'custom_attributes' => '{{no_of_credit}}',
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'wa-user-credit-reverse',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'WhatsApp Credit Reversed',
                'notification_body' => '{{amount_refund}}/- credit been successfully refunded and added to your account.',
                'custom_attributes' => '{{amount_refund}}',
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'user-api-credit-used',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'API SMS Credits Utilization',
                'notification_body' => 'We are informing you that {{no_of_credit}} SMS credits have been utilized through the API on {{date}}..',
                'custom_attributes' => '{{no_of_credit}}, {{date}}',
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'credit-requested-by-user',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'Credit requested by user',
                'notification_body' => '{{no_of_credit}} Credit requested by {{name}}.',
                'custom_attributes' => '{{name}},{{no_of_credit}}',
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'api-key-secret-changed',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'API key & secret changed',
                'notification_body' => 'Your API key & secret has been changed successfully.',
                'custom_attributes' => null,
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'enable-additional-security',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'Additional security',
                'notification_body' => 'Additional API security successfully {{action_name}}.',
                'custom_attributes' => null,
                'save_to_database' => true,
                'status_code' => 'success',
                'route_path' => 'credit-info',
            ],
            [
                'notification_for' => 'campaign-added',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'Campaign added',
                'notification_body' => 'Campaign added successfully, we\'ll update you when campaign start.',
                'custom_attributes' => null,
                'save_to_database' => false,
                'status_code' => 'info',
                'route_path' => 'credit-info',
            ],


            [
                'notification_for' => 'low-balance',
                'mail_subject' => null,
                'mail_body' => null,
                'notification_subject' => 'Low balance reminder',
                'notification_body' => 'Your account credit is too low, add credit to avoid inconvenience in future..',
                'custom_attributes' => null,
                'save_to_database' => true,
                'status_code' => 'info',
                'route_path' => 'credit-info',
            ],

            [
                'notification_for' => ' server-and-application-outage',
                'mail_subject' => 'Server and Application Outage',
                'mail_body' => 'Dear Client,
<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I hope this message finds you well. I regret to inform you that we are currently experiencing a server and application outage that is affecting our SMS services. This outage is under investigation, and our team is working diligently to resolve the issue as quickly as possible.
<br><br>
<strong>Details of the Outage:</strong>
<br>
Start Time: {{start_date_and_time}}<br>
Expected End Time: {{end_date_and_time}}<br>
Affected Services: Server and SMS applications will not work.
<br><br>
<strong>Impact on Services:</strong>
<br>
During this outage, you may experience disruptions in accessing your email, sending or receiving messages, and other related functionalities. We understand the critical nature of email services and sincerely apologize for any inconvenience this may cause.
<br><br>
<strong>What We Are Doing:</strong>
<br>
Our technical team is actively working on identifying and resolving the root cause of the issue. We are committed to restoring normal operations at the earliest. Regular updates on the progress will be communicated to you as new information becomes available.
<br><br>
Thank you for your patience and cooperation.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => 'Server and Application Outage',
                'notification_body' => 'I regret to inform you that we are currently experiencing a server and application outage that is affecting our SMS services. This outage is under investigation, and our team is working diligently to resolve the issue as quickly as possible.Start Time: {{start_date_and_time}} to Expected End Time: {{end_date_and_time}}',
                'custom_attributes' => '{{start_date_and_time}}, {{end_date_and_time}}',
                'save_to_database' => true,
                'status_code' => 'info',
                'route_path' => null,
            ],

            [
                'notification_for' => ' server-and-application-restored',
                'mail_subject' => 'Server and Application Restoration Notification',
                'mail_body' => 'Dear Client,
<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;We are pleased to inform you that the server and application services have been successfully restored. Our team has resolved the issues that caused the disruption, and all systems are now fully operational.
<br>
We apologize for any inconvenience this may have caused and appreciate your patience and understanding during this time. If you experience any further issues or require assistance, please do not hesitate to contact our support team at <strong>info@nrt.co.in</strong>.
<br><br>
Thank you for your continued trust in our services.
<br><br>
'.$footer.'<br>
'.$logo,
                'notification_subject' => 'Server and Application Restoration Notification',
                'notification_body' => 'We are pleased to inform you that the server and application services have been successfully restored. Our team has resolved the issues that caused the disruption, and all systems are now fully operational.',
                'custom_attributes' => '',
                'save_to_database' => true,
                'status_code' => 'info',
                'route_path' => null,
            ],
        ];

        foreach ($templates as $key => $template) {
            $data = new NotificationTemplate;
            $data->notification_for = $template['notification_for'];
            $data->mail_subject = $template['mail_subject'];
            $data->mail_body = $template['mail_body'];
            $data->notification_subject = $template['notification_subject'];
            $data->notification_body = $template['notification_body'];
            $data->custom_attributes = $template['custom_attributes'];
            $data->save_to_database = $template['save_to_database'];
            $data->status_code = $template['status_code'];
            $data->route_path = $template['route_path'];
            $data->save();
        }
    }
}
