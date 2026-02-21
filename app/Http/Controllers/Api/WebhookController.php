<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CryptoBalanceService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    protected $balanceService;

    public function __construct(CryptoBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }
 
    public function handleTransaction(Request $request)
    {
      
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        $wallet = \App\Models\CryptoWallet::where('address', $data['to_address'])->first();

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 404);
        }

        try {
         
            if ($this->checkRisk($data['from_address'], $data['amount'])) {
                $this->balanceService->pendingDeposit(
                    $wallet->user,
                    $wallet->currency,
                    $data['amount'],
                    $data['txid'],
                    $data['confirmations'] ?? 0
                );
            } else {

                $this->balanceService->deposit(
                    $wallet->user,
                    $wallet->currency,
                    $data['amount'],
                    $data['txid']
                );
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function checkRisk($fromAddress, $amount)
    {

        $blacklist = ['bad_address_1', 'bad_address_2'];
        
        if (in_array($fromAddress, $blacklist)) {
            return true;
        }

        if ($amount > 10000) { 
            return true; 
        }

        return false; 
    }

    protected function verifySignature(Request $request)
    {
        
        return true;
    }
}