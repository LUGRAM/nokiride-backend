<?php

namespace App\Http\Controllers\Api\Trip;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripDispatch;
use App\Events\TripCancelled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    /**
     * Acceptation atomique d'une course par un chauffeur
     */
    public function accept(Request $request, $id)
    {
        $driverId = Auth::id();

        try {
            return DB::transaction(function () use ($id, $driverId) {
                // LOCK FOR UPDATE: Verrouille la ligne en BDD pour éviter que
                // deux chauffeurs n'acceptent simultanément.
                $trip = Trip::where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$trip) {
                    return response()->json(['error' => 'Course introuvable.'], 404);
                }

                if ($trip->status !== 'searching') {
                    return response()->json([
                        'error' => 'Désolé, cette course a déjà été acceptée ou annulée.',
                        'status' => $trip->status
                    ], 410);
                }

                // Attribution de la course
                $trip->update([
                    'driver_id' => $driverId,
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                // Marquer le chauffeur comme occupé
                Auth::user()->update(['is_busy' => true]);

                // CLEANUP: Annuler l'offre pour tous les autres chauffeurs sollicités
                $otherDispatches = TripDispatch::where('trip_id', $id)
                    ->where('driver_id', '!=', $driverId)
                    ->where('status', 'sent')
                    ->get();

                foreach ($otherDispatches as $dispatch) {
                    broadcast(new TripCancelled($trip, $dispatch->driver_id));
                }

                // TODO: Notifier le client que sa course est acceptée (Event/FCM)

                return response()->json([
                    'status' => 'success',
                    'message' => 'Course acceptée avec succès.',
                    'trip' => $trip
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de l\'acceptation.'], 500);
        }
    }

    /**
     * Rejet d'une course par un chauffeur
     */
    public function reject(Request $request, $id)
    {
        $driverId = Auth::id();

        TripDispatch::updateOrCreate(
            ['trip_id' => $id, 'driver_id' => $driverId],
            ['status' => 'rejected']
        );

        return response()->json(['status' => 'success', 'message' => 'Course rejetée.']);
    }

    /**
     * Récupérer les offres actuelles pour un chauffeur (Sync Check)
     */
    public function currentOffers()
    {
        $user = Auth::user();

        // On cherche des courses en statut 'searching' qui pourraient lui correspondre
        // (Pour simplifier, on renvoie les dernières courses proches non attribuées)
        // En prod, on croiserait avec une table 'trip_dispatches' pour savoir si elle lui a été proposée.
        $trips = Trip::where('status', 'searching')
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $trips
        ]);
    }
}
