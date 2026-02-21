<?php

namespace App\Services;

use App\Models\User;
use App\Models\CryptoWallet;
use App\Models\CryptoTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CryptoBalanceService
{
  
    public function deposit(User $user, string $currency, float $amount, string $txid = null, array $metadata = [])
    {
        return DB::transaction(function () use ($user, $currency, $amount, $txid, $metadata) {
            $wallet = CryptoWallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => $currency],
                ['balance' => 0, 'hold' => 0]
            );

            $transaction = CryptoTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_DEPOSIT,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'currency' => $currency,
                'amount' => $amount,
                'txid' => $txid,
                'metadata' => $metadata
            ]);

            $wallet->balance += $amount;
            $wallet->save();

            $transaction->log('Deposit completed', [
                'balance_after' => $wallet->balance
            ]);

            return $transaction;
        });
    }


    public function withdraw(User $user, string $currency, float $amount, string $toAddress = null, float $fee = 0)
    {
        return DB::transaction(function () use ($user, $currency, $amount, $toAddress, $fee) {
            $wallet = CryptoWallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->firstOrFail();

            $totalAmount = $amount + $fee;

            if ($wallet->available_balance < $totalAmount) {
                throw new \Exception('Insufficient funds');
            }

            // Сначала блокируем средства
            $wallet->hold += $totalAmount;
            $wallet->save();

            $transaction = CryptoTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_WITHDRAWAL,
                'status' => CryptoTransaction::STATUS_PROCESSING,
                'currency' => $currency,
                'amount' => -$amount,
                'fee' => $fee,
                'to_address' => $toAddress,
                'metadata' => [
                    'withdrawal_requested_at' => now()
                ]
            ]);

            $transaction->log('Withdrawal initiated', [
                'amount' => $amount,
                'fee' => $fee,
                'total' => $totalAmount
            ]);

            return $transaction;
        });
    }

 
    public function confirmWithdrawal(CryptoTransaction $transaction, string $txid)
    {
        return DB::transaction(function () use ($transaction, $txid) {
            $wallet = $transaction->wallet;
            $totalAmount = abs($transaction->amount) + $transaction->fee;

            // Обновляем транзакцию
            $transaction->txid = $txid;
            $transaction->status = CryptoTransaction::STATUS_COMPLETED;
            $transaction->completed_at = now();
            $transaction->save();

            // Списываем средства
            $wallet->balance -= $totalAmount;
            $wallet->hold -= $totalAmount;
            $wallet->save();

            $transaction->log('Withdrawal completed', [
                'txid' => $txid,
                'balance_after' => $wallet->balance
            ]);

            return $transaction;
        });
    }

 
    public function cancelWithdrawal(CryptoTransaction $transaction, string $reason)
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $wallet = $transaction->wallet;
            $totalAmount = abs($transaction->amount) + $transaction->fee;


            $wallet->hold -= $totalAmount;
            $wallet->save();


            $transaction->status = CryptoTransaction::STATUS_CANCELLED;
            $transaction->error = $reason;
            $transaction->save();

            $transaction->log('Withdrawal cancelled', [
                'reason' => $reason,
                'unlocked_amount' => $totalAmount
            ]);

            CryptoTransaction::create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_REFUND,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'currency' => $wallet->currency,
                'amount' => $totalAmount,
                'metadata' => [
                    'original_transaction_id' => $transaction->id,
                    'reason' => $reason
                ]
            ]);

            return $transaction;
        });
    }

  
    public function pendingDeposit(User $user, string $currency, float $amount, string $txid, int $confirmations = 0)
    {
        return DB::transaction(function () use ($user, $currency, $amount, $txid, $confirmations) {
            $wallet = CryptoWallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => $currency],
                ['balance' => 0, 'hold' => 0]
            );

            $transaction = CryptoTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_DEPOSIT,
                'status' => CryptoTransaction::STATUS_PENDING,
                'currency' => $currency,
                'amount' => $amount,
                'txid' => $txid,
                'confirmations' => $confirmations
            ]);

            $transaction->log('Deposit pending', [
                'confirmations' => $confirmations,
                'required' => $transaction->getRequiredConfirmations()
            ]);

            return $transaction;
        });
    }

  
    public function updateConfirmations(string $txid, int $confirmations)
    {
        return DB::transaction(function () use ($txid, $confirmations) {
            $transaction = CryptoTransaction::where('txid', $txid)
                ->where('status', CryptoTransaction::STATUS_PENDING)
                ->first();

            if (!$transaction) {
                return null;
            }

            $transaction->confirmations = $confirmations;
            
            if ($transaction->hasEnoughConfirmations()) {
                $wallet = $transaction->wallet;
                
                // Начисляем средства
                $wallet->balance += $transaction->amount;
                $wallet->save();
                
                $transaction->status = CryptoTransaction::STATUS_COMPLETED;
                $transaction->completed_at = now();
                
                $transaction->log('Deposit confirmed', [
                    'confirmations' => $confirmations,
                    'balance_after' => $wallet->balance
                ]);
            }
            
            $transaction->save();

            return $transaction;
        });
    }


    public function chargeFee(User $user, string $currency, float $amount, string $description = null)
    {
        return DB::transaction(function () use ($user, $currency, $amount, $description) {
            $wallet = CryptoWallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->firstOrFail();

            if ($wallet->available_balance < $amount) {
                throw new \Exception('Insufficient funds for fee');
            }

            $transaction = CryptoTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_FEE,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'currency' => $currency,
                'amount' => -$amount,
                'metadata' => ['description' => $description]
            ]);

            $wallet->balance -= $amount;
            $wallet->save();

            $transaction->log('Fee charged', [
                'description' => $description,
                'balance_after' => $wallet->balance
            ]);

            return $transaction;
        });
    }


    public function getUserStats(User $user, string $currency = null)
    {
        $query = CryptoTransaction::where('user_id', $user->id);
        
        if ($currency) {
            $query->where('currency', $currency);
        }

        return [
            'total_transactions' => $query->count(),
            'total_deposits' => (clone $query)->ofType(CryptoTransaction::TYPE_DEPOSIT)->completed()->sum('amount'),
            'total_withdrawals' => abs((clone $query)->ofType(CryptoTransaction::TYPE_WITHDRAWAL)->completed()->sum('amount')),
            'total_fees' => (clone $query)->completed()->sum('fee'),
            'pending_transactions' => (clone $query)->pending()->count(),
            'last_transaction' => (clone $query)->latest()->first()
        ];
    }

 
    public function searchTransactions(array $filters = [])
    {
        $query = CryptoTransaction::query()->with(['user', 'wallet']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['txid'])) {
            $query->where('txid', 'like', "%{$filters['txid']}%");
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (!empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        return $query->orderBy('created_at', 'desc');
    }
}