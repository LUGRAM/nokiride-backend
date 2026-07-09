<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use Illuminate\Support\Facades\Log;

class FakeOtpProvider implements OtpProviderInterface
{
    public function send(string $phoneNumber, string $code): void
    {
        Log::info("[OTP] {$phoneNumber} => {$code}", ['provider' => 'fake']);
    }
}
