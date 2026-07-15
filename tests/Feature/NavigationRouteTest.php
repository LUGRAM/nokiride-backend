<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NavigationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_requires_authentication(): void
    {
        $this->postJson('/api/v1/navigation/route', [])->assertUnauthorized();
    }

    public function test_route_proxies_google_routes_and_normalizes_response(): void
    {
        config(['services.google.routes_api_key' => 'test-key']);
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'duration' => '325s',
                    'distanceMeters' => 4820,
                    'polyline' => ['encodedPolyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
                ]],
            ]),
        ]);
        $user = User::factory()->create(['role' => 'driver']);

        $this->actingAs($user)->postJson('/api/v1/navigation/route', [
            'origin' => ['latitude' => 0.4162, 'longitude' => 9.4673],
            'destination' => ['latitude' => 0.3901, 'longitude' => 9.4544],
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.distance_meters', 4820)
            ->assertJsonPath('data.duration_seconds', 325);

        Http::assertSent(fn ($request) =>
            $request->hasHeader('X-Goog-Api-Key', 'test-key') &&
            $request->hasHeader(
                'X-Goog-FieldMask',
                'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline'
            ) &&
            $request['travelMode'] === 'DRIVE'
        );
    }
}