<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+241\d{8}$/'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        return response()->json(['user' => $user, 'token' => 'mobile-token-'.$user->id]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+241\d{8}$/',
                Rule::unique('users', 'phone'),
            ],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:4'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? '1234',
            'wallet_balance' => 5000,
        ]);

        return response()->json(['user' => $user, 'token' => 'mobile-token-'.$user->id], 201);
    }
}
