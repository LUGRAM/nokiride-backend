<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_a_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Noki User',
            'phone' => '+24177123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_login_returns_a_real_sanctum_token(): void
    {
        User::factory()->create([
            'phone' => '+24177123456',
            'password' => 'secret12',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+24177123456',
            'password' => 'secret12',
        ])->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->assertStringContainsString('|', $response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+24177999999',
                'password' => 'invalid-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+24177999999',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }

    public function test_me_requires_authentication_and_returns_the_token_user(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Ancien Nom',
            'phone' => '+24177111111',
            'email' => 'old@example.com',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Enzo Mezui',
                'phone' => '+24177000000',
                'email' => 'enzo@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Enzo Mezui')
            ->assertJsonPath('user.phone', '+24177000000')
            ->assertJsonPath('user.email', 'enzo@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Enzo Mezui',
            'phone' => '+24177000000',
        ]);
    }

    public function test_authenticated_user_stats_are_computed_from_real_records(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMonths(2)]);
        $merchant = Merchant::query()->create([
            'name' => 'Marché Test',
            'category' => 'Alimentation',
            'location' => 'Libreville',
            'delivery_fee' => 500,
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Tomates',
            'price' => 700,
        ]);

        $user->trips()->create([
            'reference' => 'TRP-STATS',
            'pickup_address' => 'Akanda',
            'dropoff_address' => 'Louis',
            'distance_km' => 8,
            'price_fcfa' => 2500,
            'estimated_minutes' => 20,
            'status' => 'completed',
        ]);
        $user->deliveries()->create([
            'reference' => 'DLV-STATS',
            'pickup_address' => 'Glass',
            'dropoff_address' => 'Owendo',
            'recipient_name' => 'Client',
            'recipient_phone' => '+24177123456',
            'parcel_size' => 'medium',
            'distance_km' => 6,
            'price_fcfa' => 2100,
            'estimated_minutes' => 18,
            'status' => 'delivered',
        ]);
        $order = $user->marketOrders()->create([
            'reference' => 'MKT-STATS',
            'merchant_id' => $merchant->id,
            'delivery_address' => 'Akanda',
            'subtotal_fcfa' => 1400,
            'delivery_fee_fcfa' => 500,
            'total_fcfa' => 1900,
            'status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price_fcfa' => 700,
            'total_fcfa' => 1400,
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/auth/stats')
            ->assertOk()
            ->assertJsonPath('data.total_trips', 1)
            ->assertJsonPath('data.total_deliveries', 1)
            ->assertJsonPath('data.total_market_orders', 1)
            ->assertJsonPath('data.total_orders', 3)
            ->assertJsonPath('data.total_spent_fcfa', 6500)
            ->assertJsonCount(3, 'data.recent_activities');
    }

    public function test_trip_uses_the_authenticated_user_and_ignores_client_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/trips', [
                'user_id' => $otherUser->id,
                'pickup_address' => 'Akanda',
                'dropoff_address' => 'Louis',
                'distance_km' => 8,
                'service_type' => 'eco',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);
    }

    public function test_user_cannot_update_another_users_trip(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $trip = $owner->trips()->create([
            'reference' => 'TRP-SECURITY',
            'pickup_address' => 'Akanda',
            'dropoff_address' => 'Louis',
            'distance_km' => 8,
            'price_fcfa' => 2500,
            'estimated_minutes' => 20,
            'status' => 'searching',
        ]);

        $this->withToken($otherUser->createToken('test')->plainTextToken)
            ->patchJson("/api/v1/trips/{$trip->id}/status", ['status' => 'completed'])
            ->assertForbidden();
    }

    public function test_customer_can_only_cancel_a_searching_trip(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create([
            'reference' => 'TRP-TRANSITION',
            'pickup_address' => 'Akanda',
            'dropoff_address' => 'Louis',
            'distance_km' => 8,
            'price_fcfa' => 2500,
            'estimated_minutes' => 20,
            'status' => 'searching',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/trips/{$trip->id}/status", ['status' => 'completed'])
            ->assertUnprocessable();

        $this->withToken($token)
            ->patchJson("/api/v1/trips/{$trip->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_wallet_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1200]);

        $this->getJson('/api/v1/wallet')->assertUnauthorized();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('balance_fcfa', 1200);
    }

    public function test_recharge_credits_the_wallet_with_mock_payment_provider(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1200]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/wallet/recharge', [
                'amount_fcfa' => 5000,
                'method' => 'airtel_money',
            ])
            ->assertOk()
            ->assertJsonPath('balance_fcfa', 6200)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('transaction.type', 'credit');

        $this->assertSame(6200, $user->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('payments', 1);
    }
}
