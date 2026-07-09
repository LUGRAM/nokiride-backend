<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public const METHODS = ['noki_pay', 'airtel_money', 'moov_money', 'card', 'cash'];

    public function mockPayment(
        User $user,
        int $amountFcfa,
        string $method,
        string $purpose,
        ?Model $payable = null,
        array $metadata = [],
    ): Payment {
        $payment = Payment::query()->create([
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'user_id' => $user->id,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'purpose' => $purpose,
            'amount_fcfa' => $amountFcfa,
            'method' => $method,
            'provider' => 'mock',
            'provider_reference' => 'MOCK-'.Str::upper(Str::random(10)),
            'status' => 'paid',
            'metadata' => $metadata + ['mock' => true],
            'paid_at' => now(),
        ]);

        Log::info('payment.mock_paid', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'user_id' => $user->id,
            'purpose' => $purpose,
            'amount_fcfa' => $amountFcfa,
            'method' => $method,
        ]);

        return $payment;
    }

    public function rechargeWallet(User $user, int $amountFcfa, string $method): array
    {
        return DB::transaction(function () use ($user, $amountFcfa, $method): array {
            $payment = $this->mockPayment(
                user: $user,
                amountFcfa: $amountFcfa,
                method: $method,
                purpose: 'wallet_recharge',
            );

            $user->increment('wallet_balance', $amountFcfa);

            $transaction = WalletTransaction::query()->create([
                'user_id' => $user->id,
                'reference' => 'WLT-'.Str::upper(Str::random(10)),
                'label' => 'Recharge wallet mock',
                'type' => 'credit',
                'method' => $method,
                'amount_fcfa' => $amountFcfa,
                'status' => 'completed',
            ]);

            Log::info('wallet.recharged_mock', [
                'payment_id' => $payment->id,
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'amount_fcfa' => $amountFcfa,
            ]);

            return [
                'payment' => $payment,
                'transaction' => $transaction,
                'balance_fcfa' => $user->fresh()->wallet_balance,
            ];
        });
    }
}
