<?php

namespace App\Http\Controllers\Api;

use App\Events\TripCancelled;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripDispatch;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function accept(Request $request, Trip $trip): JsonResponse
    {
        $driver = $request->user();

        return DB::transaction(function () use ($driver, $trip) {
            $lockedTrip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $dispatch = TripDispatch::query()
                ->where('trip_id', $lockedTrip->id)
                ->where('driver_id', $driver->id)
                ->lockForUpdate()
                ->first();

            if (!$dispatch || $dispatch->status !== 'sent') {
                return response()->json([
                    'status' => 'unavailable',
                    'message' => 'Cette proposition n’est plus disponible.',
                ], 409);
            }

            if ($lockedTrip->status !== 'searching') {
                $dispatch->update(['status' => 'expired']);

                return response()->json([
                    'status' => 'unavailable',
                    'message' => 'Cette course a déjà été acceptée ou annulée.',
                ], 409);
            }

            $lockedTrip->update([
                'driver_id' => $driver->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
            $dispatch->update(['status' => 'accepted']);
            $driver->update(['is_busy' => true]);

            $otherDriverIds = TripDispatch::query()
                ->where('trip_id', $lockedTrip->id)
                ->where('driver_id', '!=', $driver->id)
                ->where('status', 'sent')
                ->lockForUpdate()
                ->pluck('driver_id');

            TripDispatch::query()
                ->where('trip_id', $lockedTrip->id)
                ->whereIn('driver_id', $otherDriverIds)
                ->where('status', 'sent')
                ->update(['status' => 'expired']);

            DB::afterCommit(function () use ($lockedTrip, $otherDriverIds) {
                foreach ($otherDriverIds as $driverId) {
                    broadcast(new TripCancelled($lockedTrip, (int) $driverId));
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Course acceptée avec succès.',
                'trip' => $lockedTrip->fresh(),
            ]);
        });
    }

    public function reject(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'in:manual_reject,timeout'],
        ]);
        $terminalStatus = ($data['reason'] ?? null) === 'timeout' ? 'expired' : 'rejected';

        return DB::transaction(function () use ($request, $trip, $terminalStatus) {
            $dispatch = TripDispatch::query()
                ->where('trip_id', $trip->id)
                ->where('driver_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (!$dispatch) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Aucune proposition active pour cette course.',
                ], 404);
            }

            if ($dispatch->status === 'sent') {
                $dispatch->update(['status' => $terminalStatus]);
            }

            return response()->json([
                'status' => 'success',
                'dispatch_status' => $dispatch->fresh()->status,
            ]);
        });
    }

    public function currentOffers(Request $request): JsonResponse
    {
        $offers = TripDispatch::query()
            ->with('trip')
            ->where('driver_id', $request->user()->id)
            ->where('status', 'sent')
            ->whereHas('trip', fn ($query) => $query->where('status', 'searching'))
            ->latest()
            ->get()
            ->map(fn (TripDispatch $dispatch) => [
                'trip_id' => $dispatch->trip->id,
                'pickup_address' => $dispatch->trip->pickup_address,
                'destination_address' => $dispatch->trip->dropoff_address,
                'pickup_latitude' => $dispatch->trip->pickup_latitude,
                'pickup_longitude' => $dispatch->trip->pickup_longitude,
                'dropoff_latitude' => $dispatch->trip->dropoff_latitude,
                'dropoff_longitude' => $dispatch->trip->dropoff_longitude,
                'estimated_earnings' => $dispatch->trip->price_fcfa,
                'distance' => $dispatch->trip->distance_km,
                'timeout_seconds' => 15,
            ]);

        return response()->json(['status' => 'success', 'data' => $offers]);
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
