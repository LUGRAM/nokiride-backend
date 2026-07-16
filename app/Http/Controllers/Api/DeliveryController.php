<?php

namespace App\Http\Controllers\Api;

use App\Events\DeliveryAssigned;
use App\Events\DeliveryTrackingStopped;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliveryController extends Controller
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
            'parcel_size' => ['nullable', 'in:small,medium,large'],
        ]);

        return response()->json(['data' => $this->pricingService->delivery((float) $data['distance_km'], $data['parcel_size'] ?? 'medium')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validate([
            'pickup_address' => ['required', 'string'],
            'dropoff_address' => ['required', 'string'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['required', 'numeric', 'between:-180,180'],
            'recipient_name' => ['required', 'string'],
            'recipient_phone' => ['required', 'string', 'regex:/^\+241\d{8}$/'],
            'parcel_size' => ['required', 'in:small,medium,large'],
            'parcel_note' => ['nullable', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
        ]);

        $estimate = $this->pricingService->delivery((float) $data['distance_km'], $data['parcel_size']);
        $driverProfile = Driver::query()->where('status', 'available')->first();

        $delivery = Delivery::create($data + [
            'user_id' => $request->user()->id,
            'reference' => 'DLV-'.Str::upper(Str::random(8)),
            'driver_id' => $driverProfile?->id,
            'price_fcfa' => $estimate['price_fcfa'],
            'estimated_minutes' => $estimate['estimated_minutes'],
            'status' => 'searching',
        ]);
        Log::info('delivery.created', ['delivery_id' => $delivery->id, 'user_id' => $request->user()->id]);

        $payment = $this->paymentService->mockPayment(
            user: $request->user(),
            amountFcfa: $delivery->price_fcfa,
            method: $data['payment_method'] ?? 'noki_pay',
            purpose: 'delivery',
            payable: $delivery,
        );

        $driverUserId = $delivery->driver?->user_id;
        if ($driverUserId && $payment->status === 'paid') {
            $driverProfile?->update(['status' => 'busy']);
            $delivery->update(['status' => 'assigned']);
            broadcast(new DeliveryAssigned($delivery, (int) $driverUserId));
        }

        return response()->json([
            'data' => $delivery->load('driver'),
            'payment' => $payment,
            'payment_reference' => $payment->reference,
        ], 201);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return response()->json(['data' => $delivery->load('driver')]);
    }

    public function currentAssignments(Request $request): JsonResponse
    {
        $deliveries = Delivery::query()
            ->whereHas('driver', fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereIn('status', ['searching', 'assigned', 'in_progress'])
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $deliveries]);
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        $data = $request->validate(['status' => ['required', 'in:searching,assigned,in_progress,delivered,cancelled']]);
        if ($request->user()->role !== 'admin') {
            $allowedTransitions = [
                'searching' => ['assigned', 'cancelled'],
                'assigned' => ['in_progress', 'delivered', 'cancelled'],
                'in_progress' => ['delivered', 'cancelled'],
            ];

            abort_unless(
                in_array($data['status'], $allowedTransitions[$delivery->status] ?? [], true),
                422,
                'Transition de statut non autorisée.'
            );
        }
        $delivery->update(['status' => $data['status'], 'delivered_at' => $data['status'] === 'delivered' ? now() : $delivery->delivered_at]);
        if (in_array($data['status'], ['delivered', 'cancelled'], true)) {
            $driverUserId = $delivery->driver?->user_id;
            $delivery->driver?->update(['status' => 'available']);
            if ($driverUserId) {
                broadcast(new DeliveryTrackingStopped($delivery, (int) $driverUserId));
            }
        }
        Log::info('delivery.status_updated', [
            'delivery_id' => $delivery->id,
            'user_id' => $request->user()->id,
            'status' => $data['status'],
        ]);

        return response()->json(['data' => $delivery->fresh('driver')]);
    }

}
