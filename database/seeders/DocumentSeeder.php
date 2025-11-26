<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Document;

class DocumentSeeder extends Seeder
{
    public function run()
    {
        Document::truncate();
        $data = [
            [
                'code_lang' => 'PHP',
                'title' => 'Check account status',
                'slug' => 'check-account-status',
                'api_information' => 'Account status is reflecting on this whether it is active or alive we can say in which particular mode the account is.

AppKey-AppSecret: Login authentication key (unique for every user) ',
                'api_code' => '',
                'response_description' => 'Response',
                'api_response' => '{
    "status": true,
    "intime": "2022-09-08T12:44:49.922077Z",
    "outtime": "2022-09-08T12:44:49.961360Z",
    "message": "Account is in active status.",
    "data": []
}',
            ],
        ];

        $document = Document::insert($data);
    }
}
