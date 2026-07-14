<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\MarketOrder;
use App\Models\Trip;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'], // Relaxed validation temporarily for easier testing
            'password' => ['required', 'string'],
        ]);

        // Normalisation simple : si ça commence par 0, on remplace par +241
        $phone = $data['phone'];
        if (str_starts_with($phone, '0')) {
            $phone = '+241' . substr($phone, 1);
        }

        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::warning('auth.login_failed', ['phone' => $data['phone'], 'ip' => $request->ip()]);

            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $user->tokens()->delete();
        $token = $user->createToken('nokiride-mobile')->plainTextToken;
        Log::info('auth.login_succeeded', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', Rule::unique('users', 'phone')], // Relaxed validation
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6'],
            'otp_verification_token' => ['required', 'string'],
        ]);

        $phone = $data['phone'];
        if (str_starts_with($phone, '0')) {
            $phone = '+241' . substr($phone, 1);
        }

        $this->otpService->consumeVerification(
            $phone,
            'registration',
            $data['otp_verification_token']
        );

        $user = User::create([
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'wallet_balance' => 5000,
        ]);

        $token = $user->createToken('nokiride-mobile')->plainTextToken;
        Log::info('auth.registered', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+241\d{8}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'otp_verification_token' => ['nullable', 'string'],
        ]);

        if ($data['phone'] !== $user->phone) {
            if (! isset($data['otp_verification_token'])) {
                return response()->json([
                    'message' => 'Un code de vérification est requis pour changer de numéro.',
                    'errors' => ['otp_verification_token' => ['Le jeton de validation est absent.']],
                ], 422);
            }

            $this->otpService->consumeVerification(
                $data['phone'],
                'profile_update',
                $data['otp_verification_token']
            );
        }

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ]);

        Log::info('auth.profile_updated', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['user' => $user->fresh()]);
    }

    public function updateActiveRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active_role' => ['required', 'string', Rule::in(['client', 'driver'])],
        ]);

        $user = $request->user();
        if ($data['active_role'] === 'driver' && $user->role !== 'driver') {
            return response()->json(['message' => 'Compte chauffeur requis.'], 403);
        }

        $user->update(['active_role' => $data['active_role']]);
        Log::info('auth.active_role_updated', [
            'user_id' => $user->id,
            'active_role' => $data['active_role'],
            'ip' => $request->ip(),
        ]);

        return response()->json(['user' => $user->fresh()]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedTrips = $user->trips()->where('status', 'completed');
        $completedDeliveries = $user->deliveries()->whereIn('status', ['delivered', 'completed']);
        $marketOrders = $user->marketOrders();

        $recentTrips = $user->trips()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Trip $trip): array => [
                'type' => 'trip',
                'reference' => $trip->reference,
                'title' => $trip->pickup_address.' → '.$trip->dropoff_address,
                'status' => $trip->status,
                'amount_fcfa' => $trip->price_fcfa,
                'created_at' => $trip->created_at,
            ]);

        $recentDeliveries = $user->deliveries()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Delivery $delivery): array => [
                'type' => 'delivery',
                'reference' => $delivery->reference,
                'title' => $delivery->pickup_address.' → '.$delivery->dropoff_address,
                'status' => $delivery->status,
                'amount_fcfa' => $delivery->price_fcfa,
                'created_at' => $delivery->created_at,
            ]);

        $recentOrders = $user->marketOrders()
            ->with('merchant:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (MarketOrder $order): array => [
                'type' => 'market_order',
                'reference' => $order->reference,
                'title' => $order->merchant?->name ?? 'Commande Market',
                'status' => $order->status,
                'amount_fcfa' => $order->total_fcfa,
                'created_at' => $order->created_at,
            ]);

        return response()->json([
            'data' => [
                'total_trips' => (clone $completedTrips)->count(),
                'total_deliveries' => (clone $completedDeliveries)->count(),
                'total_market_orders' => (clone $marketOrders)->count(),
                'total_orders' => (clone $completedTrips)->count()
                    + (clone $completedDeliveries)->count()
                    + (clone $marketOrders)->count(),
                'total_spent_fcfa' => (int) (clone $completedTrips)->sum('price_fcfa')
                    + (int) (clone $completedDeliveries)->sum('price_fcfa')
                    + (int) (clone $marketOrders)->sum('total_fcfa'),
                'member_since' => $user->created_at?->toDateString(),
                'recent_activities' => $recentTrips
                    ->concat($recentDeliveries)
                    ->concat($recentOrders)
                    ->sortByDesc('created_at')
                    ->take(5)
                    ->values(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $request->user()->currentAccessToken()?->delete();
        Log::info('auth.logged_out', ['user_id' => $userId, 'ip' => $request->ip()]);

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+241\d{8}$/', 'exists:users,phone'],
        ]);
        $otp = $this->otpService->send($data['phone'], 'password_reset');

        return response()->json([
            'message' => 'OTP de récupération généré.',
            'expires_at' => $otp->expires_at,
            'length' => (int) config('otp.length'),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+241\d{8}$/', 'exists:users,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'otp_verification_token' => ['required', 'string'],
        ]);
        $this->otpService->consumeVerification(
            $data['phone'],
            'password_reset',
            $data['otp_verification_token']
        );

        $user = User::where('phone', $data['phone'])->firstOrFail();
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();
        Log::info('auth.password_reset', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }
}
