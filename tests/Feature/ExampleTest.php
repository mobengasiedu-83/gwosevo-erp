<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_path_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_the_root_redirect_preserves_the_application_subdirectory(): void
    {
        $request = Request::create('https://localhost/gwosevo-erp/public/', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/gwosevo-erp/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/gwosevo-erp/public/index.php',
        ]);

        $response = TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle($request)
        );

        $response->assertRedirect('https://localhost/gwosevo-erp/public/dashboard');
    }
}
