<?php

namespace App\Jobs;

use App\Events\TripRequested;
use App\Models\Trip;
use App\Models\User;
use App\Models\TripDispatch;
use App\Services\FcmNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchTripJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $radii = [2, 5, 10]; // Rayons en kilomètres pour le Waterfall
    protected int $timeoutPerStep = 20;  // Secondes d'attente par palier

    public function __construct(protected Trip $trip) {}

    public function handle(): void
    {
        foreach ($this->radii as $radius) {
            // 1. Vérifier si la course est toujours en attente
            $this->trip->refresh();
            if ($this->trip->status !== 'searching') {
                return;
            }

            // 2. Trouver les chauffeurs disponibles dans le rayon actuel
            $drivers = $this->getNearbyDrivers($radius);

            if ($drivers->isNotEmpty()) {
                Log::info("Dispatching Trip {$this->trip->id} to " . $drivers->count() . " drivers in {$radius}km radius.");

                $fcmService = app(FcmNotificationService::class);

                foreach ($drivers as $driver) {
                    // Un dispatch terminal (rejected/expired) ne doit jamais
                    // redevenir "sent", même si le job change de rayon.
                    $dispatch = TripDispatch::firstOrCreate(
                        ['trip_id' => $this->trip->id, 'driver_id' => $driver->id],
                        ['status' => 'sent']
                    );

                    if (!$dispatch->wasRecentlyCreated || $dispatch->status !== 'sent') {
                        continue;
                    }

                    // 1. WebSocket (Temps réel)
                    broadcast(new TripRequested($this->trip, $driver->id, $this->timeoutPerStep));

                    // 2. Push FCM
                    $fcmService->sendNewTripPush($driver, $this->trip, $this->timeoutPerStep);
                }

                // 3. Attendre la fin du palier
                sleep($this->timeoutPerStep);

                // Marquer comme 'expired' ceux qui n'ont pas répondu à ce tour
                $this->markSentAsExpired($drivers->pluck('id')->toArray());
            }
        }

        // Final check
        $this->trip->refresh();
        if ($this->trip->status === 'searching') {
            $this->trip->update(['status' => 'no_driver_found']);
        }
    }

    protected function getNearbyDrivers(int $radiusKm)
    {
        // Exclure les chauffeurs qui ont déjà rejeté ou dont l'offre a expiré
        $excludedDriverIds = TripDispatch::where('trip_id', $this->trip->id)
            ->whereIn('status', ['rejected', 'expired'])
            ->pluck('driver_id');

        return User::where('role', 'driver')
            ->where('is_online', true)
            ->where('is_busy', false)
            ->whereNotIn('id', $excludedDriverIds)
            ->whereRaw("ST_Distance_Sphere(
                POINT(?, ?),
                (SELECT location FROM driver_locations WHERE user_id = users.id ORDER BY created_at DESC LIMIT 1)
            ) <= ?", [
                $this->trip->pickup_longitude,
                $this->trip->pickup_latitude,
                $radiusKm * 1000
            ])
            ->limit(10)
            ->get();
    }

    protected function markSentAsExpired(array $driverIds)
    {
        // On ne marque comme expiré que si le trip est toujours en recherche
        $this->trip->refresh();
        if ($this->trip->status === 'searching') {
            TripDispatch::where('trip_id', $this->trip->id)
                ->whereIn('driver_id', $driverIds)
                ->where('status', 'sent')
                ->update(['status' => 'expired']);
        }
    }
}
