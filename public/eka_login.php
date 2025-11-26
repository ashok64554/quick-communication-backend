<?php
$payload = [
    'api_key' => '630c3e90-bf2b-494c-a442-b84b8e0e077b',
    'client_id' => 'EC_173696588488053',
    'client_secret' => '708825e3-9060-4785-81d9-28064db1172d',
];

$url = 'https://api.eka.care/connect-auth/v1/account/login';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type:application/json',
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resultStr = curl_exec($ch);
print_r($resultStr);