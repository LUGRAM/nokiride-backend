<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+241\d{8}$/'],
            'purpose' => ['required', 'in:registration,password_reset'],
        ]);
        $otp = $this->otpService->send($data['phone'], $data['purpose']);

        return response()->json([
            'message' => 'OTP généré.',
            'expires_at' => $otp->expires_at,
            'length' => (int) config('otp.length'),
        ], 201);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->send($request);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+241\d{8}$/'],
            'purpose' => ['required', 'in:registration,password_reset'],
            'code' => ['required', 'digits_between:4,8'],
        ]);

        return response()->json([
            'message' => 'OTP vérifié.',
            'data' => $this->otpService->verify($data['phone'], $data['purpose'], $data['code']),
        ]);
    }
}
