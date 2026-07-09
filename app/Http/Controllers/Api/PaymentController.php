<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_fcfa' => ['required', 'integer', 'min:100'],
            'method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
            'purpose' => ['required', 'in:wallet_recharge,trip,delivery,market_order'],
        ]);

        if ($data['purpose'] === 'wallet_recharge') {
            $result = $this->paymentService->rechargeWallet(
                $request->user(),
                $data['amount_fcfa'],
                $data['method'] ?? 'airtel_money',
            );

            return response()->json([
                'data' => $result['payment'],
                'balance_fcfa' => $result['balance_fcfa'],
                'transaction' => $result['transaction'],
            ], 201);
        }

        $payment = $this->paymentService->mockPayment(
            user: $request->user(),
            amountFcfa: $data['amount_fcfa'],
            method: $data['method'] ?? 'noki_pay',
            purpose: $data['purpose'],
        );

        return response()->json(['data' => $payment], 201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id || $request->user()->role === 'admin', 403);

        return response()->json(['data' => $payment]);
    }

    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id || $request->user()->role === 'admin', 403);

        if ($payment->status !== 'paid') {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
        }

        return response()->json([
            'data' => $payment->fresh(),
        ]);
    }
}
