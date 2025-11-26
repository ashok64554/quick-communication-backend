<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use File;

class CreateDefaultDirectory extends Seeder
{
    public function run()
    {
        // through frontend
        File::ensureDirectoryExists('public/uploads');

        // files generated from app (Backend)
        File::ensureDirectoryExists('public/csv');
        File::ensureDirectoryExists('public/csv/campaign');
        File::ensureDirectoryExists('public/csv/queue');
        File::ensureDirectoryExists('public/csv/kannel');
        File::ensureDirectoryExists('public/csv/voice');
        File::ensureDirectoryExists('public/csv/wa_campaign');
        File::ensureDirectoryExists('public/voice');
        File::ensureDirectoryExists('public/whatsapp-sample');
        File::ensureDirectoryExists('public/whatsapp-file');
        File::ensureDirectoryExists('public/whatsapp-file/certificates');

        // files generated from app (Backend)
        File::ensureDirectoryExists('public/sample-file');
        File::ensureDirectoryExists('public/invoice');
        File::ensureDirectoryExists('public/reports');
        File::ensureDirectoryExists('public/temp');
    }
}
