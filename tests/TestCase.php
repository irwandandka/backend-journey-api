<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', config('app.frontend_url', 'http://localhost:5173'));
    }

    protected function asAdminOrigin(): static
    {
        return $this->withHeader('Origin', env('ADMIN_URL'));
    }
}
