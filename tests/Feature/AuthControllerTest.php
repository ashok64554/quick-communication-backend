<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Str;

class AuthControllerTest extends TestCase
{
    //use RefreshDatabase;

    public function test_login_with_email_and_password()
    {
        // User Create
        $user = User::factory()->create([
            'username' => Str::random(15),
            'password' => bcrypt('password')
        ]);

        $user->assignRole('admin');
        $user->givePermissionTo(Permission::all());

        $body = [
            'email' => $user->email,
            'password' => 'password'
        ];

        // Login
        $response = $this->json('POST', route('login'), $body, ['Accept' => 'application/json'])
        ->assertStatus(200);

    }

    public function test_login_with_invalid_email_and_password()
    {
        // User Create
        $user = User::factory()->create([
            'username' => Str::random(15),
            'password' => bcrypt('password')
        ]);

        $body = [
            'email' => $user->email,
            'password' => 'password-ss'
        ];

        // Login
        $this->json('POST', route('login'), $body, ['Accept' => 'application/json'])
        ->assertStatus(401);

    }
}
