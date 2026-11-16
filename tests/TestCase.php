<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Testovací klient Symfony jinak posílá Accept-Language: en-us.
        $this->withHeader('Accept-Language', 'cs-CZ,cs;q=0.9');
    }
}
