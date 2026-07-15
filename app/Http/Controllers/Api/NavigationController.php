<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NavigationController extends Controller
{
    public function route(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $apiKey = config('services.google.routes_api_key');
        if (!$apiKey) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Le service de navigation n’est pas configuré.',
            ], 503);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline',
                ])
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                    'origin' => ['location' => ['latLng' => $data['origin']]],
                    'destination' => ['location' => ['latLng' => $data['destination']]],
                    'travelMode' => 'DRIVE',
                    'routingPreference' => 'TRAFFIC_AWARE',
                    'computeAlternativeRoutes' => false,
                    'languageCode' => 'fr-FR',
                    'units' => 'METRIC',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('google_routes.connection_failed', ['message' => $exception->getMessage()]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Le calcul d’itinéraire est temporairement indisponible.',
            ], 503);
        }

        if ($response->failed()) {
            Log::warning('google_routes.request_failed', [
                'http_status' => $response->status(),
                'body' => $response->json(),
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Google Routes n’a pas pu calculer cet itinéraire.',
            ], 502);
        }

        $route = $response->json('routes.0');
        $encodedPolyline = data_get($route, 'polyline.encodedPolyline');
        if (!$route || !$encodedPolyline) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Aucun itinéraire routier trouvé.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'encoded_polyline' => $encodedPolyline,
                'distance_meters' => (int) data_get($route, 'distanceMeters', 0),
                'duration_seconds' => $this->durationInSeconds((string) data_get($route, 'duration', '0s')),
            ],
        ]);
    }

    private function durationInSeconds(string $duration): int
    {
        return (int) round((float) rtrim($duration, 's'));
    }
}