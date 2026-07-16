<?php

namespace Tests\Feature;

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DriverLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_driver_can_publish_a_location_batch(): void
    {
        Event::fake([DriverLocationUpdated::class]);
        $customer = User::factory()->create();
        $driverUser = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'name' => $driverUser->name,
            'phone' => $driverUser->phone,
            'status' => 'busy',
        ]);
        $trip = Trip::create([
            'reference' => 'TRP-TRACKING',
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'pickup_address' => 'Louis',
            'dropoff_address' => 'Owendo',
            'distance_km' => 12.5,
            'price_fcfa' => 3500,
            'estimated_minutes' => 30,
            'status' => 'accepted',
        ]);

        $payload = [
            'trip_id' => $trip->id,
            'locations' => [[
                'position_id' => 'position-001',
                'latitude' => 0.3901,
                'longitude' => 9.4544,
                'heading' => 125,
                'speed' => 8.2,
                'accuracy' => 6,
                'recorded_at' => now()->toISOString(),
            ]],
        ];
        $response = $this->actingAs($driverUser)
            ->postJson('/api/v1/driver/update-location', $payload);

        $response->assertOk()->assertJsonPath('accepted_positions', 1);
        $this->actingAs($driverUser)
            ->postJson('/api/v1/driver/update-location', $payload)
            ->assertOk();
        $this->assertDatabaseCount('driver_locations', 1);
        Event::assertDispatched(DriverLocationUpdated::class, fn ($event) =>
            $event->trip->is($trip) && $event->location['latitude'] === 0.3901
        );
    }

    public function test_an_unassigned_driver_cannot_publish_locations(): void
    {
        $customer = User::factory()->create();
        $assignedUser = User::factory()->create(['role' => 'driver']);
        $assignedDriver = Driver::create([
            'user_id' => $assignedUser->id,
            'name' => $assignedUser->name,
            'phone' => $assignedUser->phone,
        ]);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $trip = Trip::create([
            'reference' => 'TRP-FORBIDDEN',
            'user_id' => $customer->id,
            'driver_id' => $assignedDriver->id,
            'pickup_address' => 'Akanda',
            'dropoff_address' => 'Glass',
            'distance_km' => 8,
            'price_fcfa' => 2500,
            'estimated_minutes' => 20,
            'status' => 'accepted',
        ]);

        $this->actingAs($otherDriver)->postJson('/api/v1/driver/update-location', [
            'trip_id' => $trip->id,
            'locations' => [[
                'position_id' => 'position-forbidden',
                'latitude' => 0.4,
                'longitude' => 9.45,
                'recorded_at' => now()->toISOString(),
            ]],
        ])->assertForbidden();

        $this->assertDatabaseCount('driver_locations', 0);
    }
}
