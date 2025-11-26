<?php

namespace Tests;
use App\Models\User;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Str;

trait UserLoginTrait 
{
    public $token;

    public function setupUser()
    {
        /*
        $this->user = User::factory()->create([
            'username' => Str::random(15),
            'password' => bcrypt('password')
        ]);

        $this->user->assignRole('admin');
        $this->user->givePermissionTo(Permission::all());*/

        $this->user = User::first();
        Passport::actingAs($this->user);

        //See Below
        $token = $this->user->createToken('authToken')->accessToken;

    }
}
