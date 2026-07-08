<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Place::query()->where('is_active', true)->orderBy('name');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($places) => $places
                ->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->limit(30)->get()]);
    }
}
