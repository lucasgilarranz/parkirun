<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_route_returns_valid_web_app_manifest(): void
    {
        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json');
        $response->assertJson([
            'name' => config('app.name'),
            'short_name' => config('app.name'),
            'display' => 'standalone',
            'start_url' => '/',
        ]);
    }

    public function test_home_page_includes_add_to_home_screen_offer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Add to home screen', escape: false);
    }
}
