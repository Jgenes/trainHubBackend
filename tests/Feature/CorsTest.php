<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_production_origin_gets_cors_headers(): void
    {
        config()->set('cors.allowed_origins', ['*']);

        $this->withHeaders(['Origin' => 'https://traininghubtz.co.tz'])
            ->getJson('/api/public-courses')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://traininghubtz.co.tz');
    }

    public function test_preflight_for_register_is_allowed(): void
    {
        config()->set('cors.allowed_origins', ['*']);

        $response = $this->withHeaders([
            'Origin'                        => 'https://traininghubtz.co.tz',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers'=> 'content-type,authorization,ngrok-skip-browser-warning',
        ])->call('OPTIONS', '/api/register');

        $response->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://traininghubtz.co.tz')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_error_responses_also_carry_cors_headers(): void
    {
        config()->set('cors.allowed_origins', ['*']);

        // A 404 goes through the exception handler, which is where CORS headers
        // used to be lost (the browser then reported a misleading CORS error).
        $this->withHeaders(['Origin' => 'https://traininghubtz.co.tz'])
            ->getJson('/api/this-route-does-not-exist')
            ->assertNotFound()
            ->assertHeader('Access-Control-Allow-Origin', 'https://traininghubtz.co.tz');
    }

    public function test_explicit_allowlist_is_honoured(): void
    {
        config()->set('cors.allowed_origins', ['https://traininghubtz.co.tz']);
        config()->set('cors.allowed_origins_patterns', []);

        $this->withHeaders(['Origin' => 'https://traininghubtz.co.tz'])
            ->getJson('/api/public-reviews')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://traininghubtz.co.tz');

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/public-reviews')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
