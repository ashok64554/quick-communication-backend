<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SetupTest extends TestCase
{
    public function test_all_setup_commands()
    {
        //\Artisan::call('migrate',['-vvv' => true]);
        //\Artisan::call('passport:install',['-vvv' => true]);
        //\Artisan::call('db:seed',['-vvv' => true]);
        $response = $this->get('/api/countries');
        $response->assertStatus(200);
    }
}
