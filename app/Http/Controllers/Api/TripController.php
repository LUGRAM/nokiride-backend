<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Trip;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TripController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PricingService $pricingService,
    )
    {
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'service_type' => ['nullable', 'in:eco,premium'],
        ]);

        return response()->json(['data' => $this->pricingService->trip((float) $data['distance_km'], $data['service_type'] ?? 'eco')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $data = $request->validate([
            'pickup_address' => ['required', 'string'],
            'dropoff_address' => ['required', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'service_type' => ['nullable', 'in:eco,premium'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
        ]);

        $estimate = $this->pricingService->trip((float) $data['distance_km'], $data['service_type'] ?? 'eco');

        $trip = Trip::create($data + [
            'user_id' => $request->user()->id,
            'reference' => 'TRP-'.Str::upper(Str::random(8)),
            'driver_id' => Driver::where('status', 'available')->value('id'),
            'price_fcfa' => $estimate['price_fcfa'],
            'estimated_minutes' => $estimate['estimated_minutes'],
            'status' => 'searching',
        ]);
        Log::info('trip.created', ['trip_id' => $trip->id, 'user_id' => $request->user()->id]);

        $payment = $this->paymentService->mockPayment(
            user: $request->user(),
            amountFcfa: $trip->price_fcfa,
            method: $data['payment_method'] ?? 'noki_pay',
            purpose: 'trip',
            payable: $trip,
        );

        return response()->json([
            'data' => $trip->load('driver'),
            'payment' => $payment,
            'payment_reference' => $payment->reference,
        ], 201);
    }

    public function updateStatus(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate(['status' => ['required', 'in:searching,assigned,in_progress,completed,cancelled']]);
        if ($request->user()->role !== 'admin') {
            $allowedTransitions = [
                'searching' => ['assigned', 'cancelled'],
                'assigned' => ['in_progress', 'completed', 'cancelled'],
                'in_progress' => ['completed', 'cancelled'],
            ];

            abort_unless(
                in_array($data['status'], $allowedTransitions[$trip->status] ?? [], true),
                422,
                'Transition de statut non autorisée.'
            );
        }
        $trip->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'completed' ? now() : $trip->completed_at]);
        Log::info('trip.status_updated', [
            'trip_id' => $trip->id,
            'user_id' => $request->user()->id,
            'status' => $data['status'],
        ]);

        return response()->json(['data' => $trip->fresh('driver')]);
    }

}
