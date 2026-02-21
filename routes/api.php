<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BalanceController;  
use App\Http\Controllers\Api\WebhookController; 

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::middleware('auth:sanctum')->group(function () {
    Route::get('/balance/{currency}', [BalanceController::class, 'getBalance']);
    Route::post('/withdraw', [BalanceController::class, 'withdraw']);
    Route::get('/transactions', [BalanceController::class, 'getTransactions']);
//});


Route::post('/webhook/transaction', [WebhookController::class, 'handleTransaction']);