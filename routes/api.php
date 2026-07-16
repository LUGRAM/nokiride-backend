<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\Driver\DriverStateController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\BroadcastController;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:otp-send');
    Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:otp-verify');
    Route::post('otp/send', [OtpController::class, 'send'])->middleware('throttle:otp-send');
    Route::post('otp/resend', [OtpController::class, 'resend'])->middleware('throttle:otp-send');
    Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify');

    Route::middleware('throttle:public-api')->group(function () {
        Route::get('places', [PlaceController::class, 'index']);
        Route::get('market/merchants', [MarketController::class, 'merchants']);
        Route::get('market/merchants/{merchant}/products', [MarketController::class, 'products']);
        Route::post('payments/webhook/mock', [PaymentController::class, 'webhookMock']);

        Route::post('trips/estimate', [TripController::class, 'estimate']);
        Route::post('deliveries/estimate', [DeliveryController::class, 'estimate']);
    });

    Route::middleware(['auth:sanctum', 'throttle:authenticated-api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::patch('auth/active-role', [AuthController::class, 'updateActiveRole']);
        Route::get('auth/stats', [AuthController::class, 'stats']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('trips', [TripController::class, 'store']);
        Route::get('trips/{trip}', [TripController::class, 'show']);
        Route::post('trips/{trip}/accept', [TripController::class, 'accept']);
        Route::post('trips/{trip}/reject', [TripController::class, 'reject']);
        Route::get('driver/current-offers', [TripController::class, 'currentOffers']);
        Route::get('driver/current-deliveries', [DeliveryController::class, 'currentAssignments']);
        Route::post('driver/update-location', [DriverStateController::class, 'updateLocation']);
        Route::post('navigation/route', [NavigationController::class, 'route']);
        Route::patch('trips/{trip}/status', [TripController::class, 'updateStatus']);

        Route::post('deliveries', [DeliveryController::class, 'store']);
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);
        Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);

        Route::get('wallet', [WalletController::class, 'show']);
        Route::post('wallet/recharge', [WalletController::class, 'recharge']);
        Route::post('wallet/transfer', [WalletController::class, 'transfer']);

        Route::post('payments/initiate', [PaymentController::class, 'initiate']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);

        Route::post('market/orders', [MarketController::class, 'storeOrder']);
        Route::post('support/requests', [SupportController::class, 'store'])
            ->middleware('throttle:10,1');
    });
});
