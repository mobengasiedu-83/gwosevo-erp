<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /** Authenticate a fixture for tests unrelated to the sign-in/MFA flow. */
    protected function actingAsVerified(User $user): static
    {
        $this->app['session']->driver()->regenerate();
        $this->withSession(['identity.auth_version' => $user->fresh()->auth_version, 'identity.mfa_verified' => $user->id]);
        $this->actingAs($user);

        return $this;
    }
}
