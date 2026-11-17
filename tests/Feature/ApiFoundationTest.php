<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_versioned_status_reports_licensing_unavailable(): void
    {
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('api_version', 'v1')->assertJsonPath('licensing_available', false);
    }

    public function test_license_mutation_endpoints_do_not_exist_yet(): void
    {
        foreach (['activations', 'licenses', 'entitlements', 'offline-authorizations'] as $resource) {
            $this->postJson('/api/v1/'.$resource)->assertNotFound();
        }
        $this->assertFalse(config('soundmine.licensing.available'));
    }

    public function test_status_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/status')->assertOk();
        }
        $this->getJson('/api/v1/status')->assertTooManyRequests();
    }
}
