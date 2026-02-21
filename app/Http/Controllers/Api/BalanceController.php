<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CryptoBalanceService;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    protected $balanceService;

    public function __construct(CryptoBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function getBalance(Request $request, string $currency)
    {
        $user = $request->user();
        
        $wallet = \App\Models\CryptoWallet::where('user_id', $user->id)
            ->where('currency', $currency)
            ->first();

        if (!$wallet) {
            return response()->json([
                'currency' => $currency,
                'balance' => 0,
                'hold' => 0,
                'available' => 0
            ]);
        }

        return response()->json([
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'hold' => $wallet->hold,
            'available' => $wallet->available_balance
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|in:BTC,ETH,USDT',
            'amount' => 'required|numeric|min:0.0001',
            'address' => 'required|string'
        ]);

        try {
          
            $this->balanceService->holdFunds(
                $request->user(),
                $request->currency,
                $request->amount
            );

            $transaction = $this->balanceService->withdraw(
                $request->user(),
                $request->currency,
                $request->amount,
                $request->address
            );

           
            $this->balanceService->releaseHold(
                $request->user(),
                $request->currency,
                $request->amount
            );

            return response()->json([
                'success' => true,
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {

            $this->balanceService->releaseHold(
                $request->user(),
                $request->currency,
                $request->amount
            );

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getTransactions(Request $request)
    {
        $transactions = \App\Models\CryptoTransaction::where('user_id', $request->user()->id)
            ->with('wallet')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }
}