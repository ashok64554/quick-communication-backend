<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\UserLoginTrait;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, UserLoginTrait;
}
