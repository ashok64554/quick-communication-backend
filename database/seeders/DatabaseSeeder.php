<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        \DB::table('countries')->truncate();
        \DB::unprepared(file_get_contents(storage_path('backups/countries.sql')));
        $this->call(CreateMySqlEventSeeder::class);
        $this->call(BasicSetup::class);
        $this->call(DynamicContentSeeder::class);
        $this->call(NotificationTemplateSeeder::class);
        $this->call(DataSyncFromOldDb::class);
        $this->call(DocumentSeeder::class);
    }
}
