<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'parcel_size' => ['nullable', 'in:small,medium,large'],
        ]);

        return response()->json(['data' => $this->pricing((float) $data['distance_km'], $data['parcel_size'] ?? 'medium')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validate([
            'pickup_address' => ['required', 'string'],
            'dropoff_address' => ['required', 'string'],
            'recipient_name' => ['required', 'string'],
            'recipient_phone' => ['required', 'string'],
            'parcel_size' => ['required', 'in:small,medium,large'],
            'parcel_note' => ['nullable', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
        ]);

        $estimate = $this->pricing((float) $data['distance_km'], $data['parcel_size']);

        $delivery = Delivery::create($data + [
            'user_id' => $request->user()->id,
            'reference' => 'DLV-'.Str::upper(Str::random(8)),
            'driver_id' => Driver::where('status', 'available')->value('id'),
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

        return response()->json([
            'data' => $delivery->load('driver'),
            'payment' => $payment,
        ], 201);
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        $data = $request->validate(['status' => ['required', 'in:searching,assigned,in_progress,delivered,cancelled']]);
        abort_unless(
            $request->user()->role === 'admin' || ($delivery->status === 'searching' && $data['status'] === 'cancelled'),
            422,
            'Transition de statut non autorisée.'
        );
        $delivery->update(['status' => $data['status'], 'delivered_at' => $data['status'] === 'delivered' ? now() : $delivery->delivered_at]);
        Log::info('delivery.status_updated', [
            'delivery_id' => $delivery->id,
            'user_id' => $request->user()->id,
            'status' => $data['status'],
        ]);

        return response()->json(['data' => $delivery->fresh('driver')]);
    }

    private function pricing(float $distanceKm, string $parcelSize): array
    {
        $surcharge = ['small' => 0, 'medium' => 300, 'large' => 700][$parcelSize];
        $raw = max(1000, 600 + $distanceKm * 200 + $surcharge);

        return [
            'distance_km' => round($distanceKm, 2),
            'price_fcfa' => (int) round($raw / 50) * 50,
            'estimated_minutes' => max(8, (int) round($distanceKm / 22 * 60)),
        ];
    }
}
