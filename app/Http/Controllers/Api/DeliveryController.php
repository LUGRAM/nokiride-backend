<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
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
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'pickup_address' => ['required', 'string'],
            'dropoff_address' => ['required', 'string'],
            'recipient_name' => ['required', 'string'],
            'recipient_phone' => ['required', 'string'],
            'parcel_size' => ['required', 'in:small,medium,large'],
            'parcel_note' => ['nullable', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
        ]);

        $estimate = $this->pricing((float) $data['distance_km'], $data['parcel_size']);

        $delivery = Delivery::create($data + [
            'reference' => 'DLV-'.Str::upper(Str::random(8)),
            'driver_id' => Driver::where('status', 'available')->value('id'),
            'price_fcfa' => $estimate['price_fcfa'],
            'estimated_minutes' => $estimate['estimated_minutes'],
            'status' => 'searching',
        ]);

        return response()->json(['data' => $delivery->load('driver')], 201);
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:searching,assigned,in_progress,delivered,cancelled']]);
        $delivery->update(['status' => $data['status'], 'delivered_at' => $data['status'] === 'delivered' ? now() : $delivery->delivered_at]);

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
