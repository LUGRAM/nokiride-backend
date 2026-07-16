<?php

namespace Tests\Feature;

use App\Events\DeliveryLocationUpdated;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DeliveryLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_courier_can_publish_a_delivery_location(): void
    {
        Event::fake([DeliveryLocationUpdated::class]);
        $customer = User::factory()->create();
        $driverUser = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'name' => $driverUser->name,
            'phone' => $driverUser->phone,
        ]);
        $delivery = Delivery::create([
            'reference' => 'DLV-TRACKING',
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'pickup_address' => 'Glass',
            'dropoff_address' => 'Akanda',
            'recipient_name' => 'Client Test',
            'recipient_phone' => '+24101020304',
            'parcel_size' => 'medium',
            'distance_km' => 8,
            'price_fcfa' => 2200,
            'estimated_minutes' => 24,
            'status' => 'assigned',
        ]);

        $this->actingAs($driverUser)->postJson('/api/v1/driver/update-location', [
            'delivery_id' => $delivery->id,
            'locations' => [[
                'position_id' => 'delivery-position-001',
                'latitude' => 0.3901,
                'longitude' => 9.4544,
                'recorded_at' => now()->toISOString(),
            ]],
        ])->assertOk();

        Event::assertDispatched(DeliveryLocationUpdated::class, fn ($event) =>
            $event->delivery->is($delivery)
        );
    }
}
