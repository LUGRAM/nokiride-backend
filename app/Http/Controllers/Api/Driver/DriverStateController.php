<?php

namespace App\Http\Controllers\Api\Driver;

use App\Events\DriverLocationUpdated;
use App\Events\DeliveryLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Models\Delivery;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverStateController extends Controller
{
    public function toggleOnline()
    {
        $user = Auth::user();
        $user->is_online = !$user->is_online;
        if (!$user->is_online) {
            $user->is_busy = false; // Reset busy status when going offline
        }
        $user->save();

        return response()->json([
            'status' => 'success',
            'is_online' => $user->is_online,
            'is_busy' => $user->is_busy
        ]);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_id' => ['nullable', 'required_without:delivery_id', 'integer', 'exists:trips,id'],
            'delivery_id' => ['nullable', 'required_without:trip_id', 'integer', 'exists:deliveries,id'],
            'locations' => ['required', 'array', 'min:1', 'max:50'],
            'locations.*.position_id' => ['required', 'string', 'max:100'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.heading' => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.speed' => ['nullable', 'numeric', 'min:0'],
            'locations.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'locations.*.recorded_at' => ['required', 'date'],
        ]);

        $user = Auth::user();
        $trip = isset($data['trip_id'])
            ? Trip::query()->with('driver')->findOrFail($data['trip_id'])
            : null;
        $delivery = isset($data['delivery_id'])
            ? Delivery::query()->with('driver')->findOrFail($data['delivery_id'])
            : null;
        $assignment = $trip ?? $delivery;
        abort_unless(
            $assignment?->driver?->user_id === $user->id &&
            in_array($assignment->status, ['searching', 'accepted', 'assigned', 'in_progress'], true),
            403,
            'Vous n’êtes pas le chauffeur actif de cette course.'
        );

        DB::transaction(function () use ($user, $data): void {
            foreach ($data['locations'] as $location) {
                $lat = (float) $location['latitude'];
                $lng = (float) $location['longitude'];

                DriverLocation::query()->firstOrCreate(
                    ['position_id' => $location['position_id']],
                    [
                        'user_id' => $user->id,
                        'location' => DB::raw("POINT($lng, $lat)"),
                        'heading' => $location['heading'] ?? null,
                        'speed' => $location['speed'] ?? null,
                        'recorded_at' => $location['recorded_at'],
                    ]
                );
            }
        });

        $latest = $data['locations'][array_key_last($data['locations'])];
        if ($trip) {
            broadcast(new DriverLocationUpdated($trip, $latest));
        } else {
            broadcast(new DeliveryLocationUpdated($delivery, $latest));
        }

        return response()->json([
            'status' => 'success',
            'accepted_positions' => count($data['locations']),
        ]);
    }
}
