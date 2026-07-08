<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'balance_fcfa' => $user->wallet_balance,
            'transactions' => $user->walletTransactions()->latest()->limit(30)->get(),
        ]);
    }

    public function recharge(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'amount_fcfa' => ['required', 'integer', 'min:100'],
            'method' => ['nullable', 'in:noki_pay,airtel_money,moov_money,card'],
        ]);

        $user->increment('wallet_balance', $data['amount_fcfa']);
        $transaction = WalletTransaction::create([
            'user_id' => $user->id,
            'reference' => 'WLT-'.Str::upper(Str::random(8)),
            'label' => 'Recharge portefeuille',
            'type' => 'credit',
            'method' => $data['method'] ?? 'airtel_money',
            'amount_fcfa' => $data['amount_fcfa'],
        ]);

        return response()->json(['balance_fcfa' => $user->fresh()->wallet_balance, 'transaction' => $transaction], 201);
    }
}
