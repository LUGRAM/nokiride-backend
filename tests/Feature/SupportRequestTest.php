<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_support_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/support/requests', [
            'category' => 'delivery',
            'subject' => 'Position du coursier figée',
            'message' => 'La position du coursier ne change plus depuis plusieurs minutes.',
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.reference', 'SUP-000001');

        $this->assertDatabaseHas('support_requests', [
            'user_id' => $user->id,
            'category' => 'delivery',
            'status' => 'open',
        ]);
    }

    public function test_support_request_requires_authentication(): void
    {
        $this->postJson('/api/v1/support/requests', [])->assertUnauthorized();
    }
}
