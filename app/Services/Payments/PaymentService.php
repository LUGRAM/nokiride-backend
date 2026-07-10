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

    public function pendingPayment(
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
            'status' => 'pending',
            'metadata' => $metadata + ['mock' => true],
        ]);

        Log::info('payment.pending_created', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'user_id' => $user->id,
            'purpose' => $purpose,
            'amount_fcfa' => $amountFcfa,
            'method' => $method,
        ]);

        return $payment;
    }

    public function markAsPaid(Payment $payment): Payment
    {
        if ($payment->status !== 'paid') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            Log::info('payment.marked_paid', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
            ]);
        }

        return $payment->fresh();
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

    public function transferWallet(User $sender, User $recipient, int $amountFcfa): array
    {
        return DB::transaction(function () use ($sender, $recipient, $amountFcfa): array {
            $lockedSender = User::query()->whereKey($sender->id)->lockForUpdate()->firstOrFail();
            $lockedRecipient = User::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedSender->wallet_balance < $amountFcfa, 422, 'Solde insuffisant.');

            $payment = $this->mockPayment(
                user: $lockedSender,
                amountFcfa: $amountFcfa,
                method: 'noki_pay',
                purpose: 'wallet_transfer',
                metadata: ['recipient_user_id' => $lockedRecipient->id],
            );

            $lockedSender->decrement('wallet_balance', $amountFcfa);
            $lockedRecipient->increment('wallet_balance', $amountFcfa);

            $debit = WalletTransaction::query()->create([
                'user_id' => $lockedSender->id,
                'reference' => 'WLT-'.Str::upper(Str::random(10)),
                'label' => 'Transfert à '.$lockedRecipient->name,
                'type' => 'debit',
                'method' => 'noki_pay',
                'amount_fcfa' => $amountFcfa,
                'status' => 'completed',
            ]);

            WalletTransaction::query()->create([
                'user_id' => $lockedRecipient->id,
                'reference' => 'WLT-'.Str::upper(Str::random(10)),
                'label' => 'Transfert reçu de '.$lockedSender->name,
                'type' => 'credit',
                'method' => 'noki_pay',
                'amount_fcfa' => $amountFcfa,
                'status' => 'completed',
            ]);

            Log::info('wallet.transferred_mock', [
                'payment_id' => $payment->id,
                'sender_id' => $lockedSender->id,
                'recipient_id' => $lockedRecipient->id,
                'amount_fcfa' => $amountFcfa,
            ]);

            return [
                'payment' => $payment,
                'transaction' => $debit,
                'balance_fcfa' => $lockedSender->fresh()->wallet_balance,
            ];
        });
    }
}
