<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly OtpProviderInterface $provider)
    {
    }

    public function send(string $phone, string $purpose): OtpCode
    {
        OtpCode::query()
            ->where('phone_number', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        $length = max(4, min(8, (int) config('otp.length')));
        $code = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);
        $otp = OtpCode::create([
            'user_id' => User::where('phone', $phone)->value('id'),
            'phone_number' => $phone,
            'purpose' => $purpose,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('otp.expires_minutes')),
            'max_attempts' => (int) config('otp.max_attempts'),
        ]);

        $this->provider->send($phone, $code);
        Log::info('otp.sent', ['otp_id' => $otp->id, 'purpose' => $purpose]);

        return $otp;
    }

    public function verify(string $phone, string $purpose, string $code): array
    {
        $otp = OtpCode::query()
            ->where('phone_number', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages(['code' => ['Aucun OTP actif.']]);
        }
        if ($otp->verified_at) {
            throw ValidationException::withMessages(['code' => ['Cet OTP a déjà été utilisé.']]);
        }
        if ($otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => ['Cet OTP a expiré.']]);
        }
        if ($otp->attempt_count >= $otp->max_attempts) {
            throw ValidationException::withMessages(['code' => ['Nombre maximal de tentatives atteint.']]);
        }

        $otp->increment('attempt_count');
        if (! Hash::check($code, $otp->code)) {
            Log::warning('otp.failed', ['otp_id' => $otp->id, 'attempt' => $otp->attempt_count]);
            throw ValidationException::withMessages(['code' => ['Code OTP incorrect.']]);
        }

        $verificationToken = Str::random(64);
        $otp->update([
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
        ]);
        Log::info('otp.verified', ['otp_id' => $otp->id, 'purpose' => $purpose]);

        return ['verification_token' => $verificationToken, 'expires_at' => $otp->expires_at];
    }

    public function consumeVerification(string $phone, string $purpose, string $token): void
    {
        $otp = OtpCode::query()
            ->where('phone_number', $phone)
            ->where('purpose', $purpose)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || ! hash_equals((string) $otp->verification_token_hash, hash('sha256', $token))) {
            throw ValidationException::withMessages(['otp_verification_token' => ['Validation OTP requise.']]);
        }
        $otp->update(['consumed_at' => now()]);
    }
}
