<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
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

    public function updateLocation(Request $request)
    {
        $request->validate([
            'locations' => 'required|array',
            'locations.*.latitude' => 'required|numeric',
            'locations.*.longitude' => 'required|numeric',
        ]);

        $user = Auth::user();
        $locations = $request->locations;

        foreach ($locations as $loc) {
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];

            // Insertion en utilisant la fonction POINT() de MySQL pour le type geometry
            DriverLocation::create([
                'user_id' => $user->id,
                'location' => DB::raw("POINT($lng, $lat)"),
                'heading'  => $loc['heading'] ?? null,
                'speed'    => $loc['speed'] ?? null,
                'recorded_at' => $loc['timestamp'] ?? now(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
