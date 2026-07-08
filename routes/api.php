<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);

    Route::get('places', [PlaceController::class, 'index']);
    Route::get('market/merchants', [MarketController::class, 'merchants']);
    Route::get('market/merchants/{merchant}/products', [MarketController::class, 'products']);

    Route::post('trips/estimate', [TripController::class, 'estimate']);
    Route::post('trips', [TripController::class, 'store']);
    Route::patch('trips/{trip}/status', [TripController::class, 'updateStatus']);

    Route::post('deliveries/estimate', [DeliveryController::class, 'estimate']);
    Route::post('deliveries', [DeliveryController::class, 'store']);
    Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);

    Route::get('wallet/{user}', [WalletController::class, 'show']);
    Route::post('wallet/{user}/recharge', [WalletController::class, 'recharge']);
});
