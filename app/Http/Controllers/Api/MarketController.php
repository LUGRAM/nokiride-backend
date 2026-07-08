<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function merchants(Request $request): JsonResponse
    {
        $query = Merchant::query()->where('is_active', true)->orderByDesc('rating');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($merchants) => $merchants
                ->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function products(Merchant $merchant): JsonResponse
    {
        return response()->json(['data' => $merchant->products()->where('is_available', true)->get()]);
    }
}
