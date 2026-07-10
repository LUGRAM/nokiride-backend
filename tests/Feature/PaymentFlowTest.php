<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_mock_payment_can_be_initiated_and_read(): void
    {
        $user = User::factory()->create();

        $paymentId = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/payments/initiate', [
                'amount_fcfa' => 1500,
                'purpose' => 'trip',
                'method' => 'noki_pay',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'mock')
            ->json('data.id');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson("/api/v1/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.amount_fcfa', 1500);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson("/api/v1/payments/{$paymentId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_mock_webhook_marks_a_pending_payment_as_paid(): void
    {
        $user = User::factory()->create();
        $reference = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/payments/initiate', [
                'amount_fcfa' => 1500,
                'purpose' => 'trip',
                'method' => 'noki_pay',
            ])
            ->assertCreated()
            ->json('data.reference');

        $this->postJson('/api/v1/payments/webhook/mock', [
            'reference' => $reference,
            'status' => 'paid',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_trip_creation_generates_a_paid_mock_payment(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/trips', [
                'pickup_address' => 'Akanda',
                'dropoff_address' => 'Louis',
                'distance_km' => 8,
                'service_type' => 'eco',
                'payment_method' => 'noki_pay',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.purpose', 'trip');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'purpose' => 'trip',
            'status' => 'paid',
        ]);
    }

    public function test_delivery_creation_generates_a_paid_mock_payment(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/deliveries', [
                'pickup_address' => 'Akanda',
                'dropoff_address' => 'Louis',
                'recipient_name' => 'Client',
                'recipient_phone' => '+24177123456',
                'parcel_size' => 'medium',
                'distance_km' => 6,
                'payment_method' => 'noki_pay',
            ])
            ->assertCreated()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.purpose', 'delivery');
    }
}
