<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BalanceController;  
use App\Http\Controllers\Api\WebhookController; 
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh')->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->name('me')->middleware('auth:sanctum');
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/balance/{currency}', [BalanceController::class, 'getBalance']);
    Route::post('/withdraw', [BalanceController::class, 'withdraw']);
    Route::get('/transactions', [BalanceController::class, 'getTransactions']);
});


Route::post('/webhook/transaction', [WebhookController::class, 'handleTransaction']);