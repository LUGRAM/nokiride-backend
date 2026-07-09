<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_order_requires_authentication(): void
    {
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

        $this->postJson('/api/v1/market/orders', [
            'merchant_id' => $merchant->id,
            'delivery_address' => 'Akanda',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertUnauthorized();
    }

    public function test_authenticated_customer_can_create_market_order(): void
    {
        $user = User::factory()->create();
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

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/market/orders', [
                'merchant_id' => $merchant->id,
                'delivery_address' => 'Akanda',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.subtotal_fcfa', 1400)
            ->assertJsonPath('data.delivery_fee_fcfa', 500)
            ->assertJsonPath('data.total_fcfa', 1900)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.purpose', 'market_order');

        $this->assertDatabaseHas('market_orders', [
            'user_id' => $user->id,
            'merchant_id' => $merchant->id,
            'total_fcfa' => 1900,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'purpose' => 'market_order',
            'amount_fcfa' => 1900,
            'status' => 'paid',
        ]);
    }

    public function test_market_order_rejects_items_from_another_merchant(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create([
            'name' => 'Marché Test',
            'category' => 'Alimentation',
            'location' => 'Libreville',
        ]);
        $otherMerchant = Merchant::query()->create([
            'name' => 'Autre Marché',
            'category' => 'Alimentation',
            'location' => 'Owendo',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $otherMerchant->id,
            'name' => 'Produit externe',
            'price' => 1000,
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/market/orders', [
                'merchant_id' => $merchant->id,
                'delivery_address' => 'Akanda',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }
}
