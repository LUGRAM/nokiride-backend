<?php

namespace Tests\Feature;

use App\Contracts\OtpProviderInterface;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private object $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new class implements OtpProviderInterface
        {
            public string $code = '';

            public function send(string $phoneNumber, string $code): void
            {
                $this->code = $code;
            }
        };
        $this->app->instance(OtpProviderInterface::class, $this->provider);
    }

    public function test_registration_uses_a_server_generated_otp(): void
    {
        $phone = '+24177123456';
        $this->postJson('/api/v1/otp/send', [
            'phone' => $phone,
            'purpose' => 'registration',
        ])->assertCreated();

        $verification = $this->postJson('/api/v1/otp/verify', [
            'phone' => $phone,
            'purpose' => 'registration',
            'code' => $this->provider->code,
        ])->assertOk()->json('data.verification_token');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Noki User',
            'phone' => $phone,
            'password' => 'secret12',
            'otp_verification_token' => $verification,
        ])->assertCreated()->assertJsonStructure(['user', 'token']);
    }

    public function test_expired_and_reused_otps_are_rejected(): void
    {
        $phone = '+24177222222';
        $this->postJson('/api/v1/otp/send', [
            'phone' => $phone,
            'purpose' => 'registration',
        ]);
        OtpCode::latest('id')->firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/otp/verify', [
            'phone' => $phone,
            'purpose' => 'registration',
            'code' => $this->provider->code,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/otp/resend', [
            'phone' => $phone,
            'purpose' => 'registration',
        ])->assertCreated();
        $payload = [
            'phone' => $phone,
            'purpose' => 'registration',
            'code' => $this->provider->code,
        ];
        $this->postJson('/api/v1/otp/verify', $payload)->assertOk();
        $this->postJson('/api/v1/otp/verify', $payload)->assertUnprocessable();
    }

    public function test_wrong_otp_is_blocked_after_maximum_attempts(): void
    {
        $phone = '+24177333333';
        $this->postJson('/api/v1/otp/send', [
            'phone' => $phone,
            'purpose' => 'registration',
        ]);

        for ($attempt = 0; $attempt < (int) config('otp.max_attempts'); $attempt++) {
            $this->postJson('/api/v1/otp/verify', [
                'phone' => $phone,
                'purpose' => 'registration',
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->assertSame(
            (int) config('otp.max_attempts'),
            OtpCode::latest('id')->firstOrFail()->attempt_count
        );
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['phone' => '+24177444444']);
        $user->createToken('old-session');

        $this->postJson('/api/v1/auth/password/forgot', ['phone' => $user->phone])->assertOk();
        $verification = $this->postJson('/api/v1/otp/verify', [
            'phone' => $user->phone,
            'purpose' => 'password_reset',
            'code' => $this->provider->code,
        ])->assertOk()->json('data.verification_token');

        $this->postJson('/api/v1/auth/password/reset', [
            'phone' => $user->phone,
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
            'otp_verification_token' => $verification,
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
