<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewWallet', $user);

        return response()->json([
            'balance_fcfa' => $user->wallet_balance,
            'transactions' => $user->walletTransactions()->latest()->limit(30)->get(),
        ]);
    }

    public function recharge(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('requestRecharge', $user);

        $request->validate([
            'amount_fcfa' => ['required', 'integer', 'min:100'],
            'method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
        ]);
        $result = $this->paymentService->rechargeWallet(
            $user,
            $request->integer('amount_fcfa'),
            $request->input('method', 'airtel_money'),
        );

        return response()->json([
            'message' => 'Recharge mock réussie.',
            'balance_fcfa' => $result['balance_fcfa'],
            'transaction' => $result['transaction'],
            'payment' => $result['payment'],
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('requestRecharge', $user);

        $data = $request->validate([
            'amount_fcfa' => ['required', 'integer', 'min:100'],
            'recipient_phone' => [
                'required',
                'string',
                'regex:/^\+241\d{8}$/',
                Rule::exists('users', 'phone'),
            ],
        ]);

        $recipient = User::query()->where('phone', $data['recipient_phone'])->firstOrFail();
        abort_if($recipient->is($user), 422, 'Impossible de transférer vers votre propre compte.');

        $result = $this->paymentService->transferWallet(
            $user,
            $recipient,
            $data['amount_fcfa'],
        );

        return response()->json([
            'message' => 'Transfert mock réussi.',
            'balance_fcfa' => $result['balance_fcfa'],
            'transaction' => $result['transaction'],
            'payment' => $result['payment'],
        ]);
    }
}
