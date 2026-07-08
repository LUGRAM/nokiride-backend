<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TripController extends Controller
{
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'service_type' => ['nullable', 'in:eco,premium'],
        ]);

        return response()->json(['data' => $this->pricing((float) $data['distance_km'], $data['service_type'] ?? 'eco')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'pickup_address' => ['required', 'string'],
            'dropoff_address' => ['required', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1'],
            'service_type' => ['nullable', 'in:eco,premium'],
        ]);

        $estimate = $this->pricing((float) $data['distance_km'], $data['service_type'] ?? 'eco');

        $trip = Trip::create($data + [
            'reference' => 'TRP-'.Str::upper(Str::random(8)),
            'driver_id' => Driver::where('status', 'available')->value('id'),
            'price_fcfa' => $estimate['price_fcfa'],
            'estimated_minutes' => $estimate['estimated_minutes'],
            'status' => 'searching',
        ]);

        return response()->json(['data' => $trip->load('driver')], 201);
    }

    public function updateStatus(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:searching,assigned,in_progress,completed,cancelled']]);
        $trip->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'completed' ? now() : $trip->completed_at]);

        return response()->json(['data' => $trip->fresh('driver')]);
    }

    private function pricing(float $distanceKm, string $serviceType): array
    {
        $multiplier = $serviceType === 'premium' ? 1.5 : 1;
        $raw = max(800, (500 + $distanceKm * 250) * $multiplier);

        return [
            'distance_km' => round($distanceKm, 2),
            'price_fcfa' => (int) round($raw / 50) * 50,
            'estimated_minutes' => max(5, (int) round($distanceKm / 25 * 60)),
        ];
    }
}
