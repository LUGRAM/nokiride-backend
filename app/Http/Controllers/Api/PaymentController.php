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

        $payment = $this->paymentService->pendingPayment(
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

        return response()->json([
            'data' => $this->paymentService->markAsPaid($payment),
        ]);
    }

    public function webhookMock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required_without:provider_reference', 'string'],
            'provider_reference' => ['required_without:reference', 'string'],
            'status' => ['nullable', 'in:paid,failed'],
        ]);

        $payment = Payment::query()
            ->when($data['reference'] ?? null, fn ($query, $reference) => $query->where('reference', $reference))
            ->when($data['provider_reference'] ?? null, fn ($query, $providerReference) => $query->where('provider_reference', $providerReference))
            ->firstOrFail();

        if (($data['status'] ?? 'paid') === 'failed') {
            $payment->update(['status' => 'failed']);

            return response()->json(['data' => $payment->fresh()]);
        }

        return response()->json(['data' => $this->paymentService->markAsPaid($payment)]);
    }
}
