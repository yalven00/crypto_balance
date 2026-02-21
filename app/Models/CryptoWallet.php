<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoWallet extends Model
{
    protected $table = 'crypto_wallets';
    
    protected $fillable = [
        'user_id', 
        'currency', 
        'balance', 
        'hold', 
        'address'
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'hold' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }


    public function transactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id');
    }


    public function completedTransactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id')
                    ->where('status', CryptoTransaction::STATUS_COMPLETED);
    }

    public function pendingTransactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id')
                    ->where('status', CryptoTransaction::STATUS_PENDING);
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id')
                    ->where('amount', '>', 0);
    }


    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id')
                    ->where('amount', '<', 0);
    }


    public function getAvailableBalanceAttribute(): float
    {
        return $this->balance - $this->hold;
    }


    public function getTransactionsCountAttribute(): int
    {
        return $this->transactions()->count();
    }

    public function getTotalDepositsAttribute(): float
    {
        return $this->incomingTransactions()
                    ->completed()
                    ->sum('amount');
    }


    public function getTotalWithdrawalsAttribute(): float
    {
        return abs($this->outgoingTransactions()
                    ->completed()
                    ->sum('amount'));
    }

    public function getTotalFeesAttribute(): float
    {
        return $this->transactions()
                    ->completed()
                    ->sum('fee');
    }

    public function getLastTransactionAttribute(): ?CryptoTransaction
    {
        return $this->transactions()->latest()->first();
    }


    public function getIsActiveAttribute(): bool
    {
        return $this->balance > 0 || $this->hold > 0;
    }


    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 8) . ' ' . $this->currency;
    }


    public function getFormattedAvailableAttribute(): string
    {
        return number_format($this->available_balance, 8) . ' ' . $this->currency;
    }


    public function hasSufficientFunds(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

  
    public function holdFunds(float $amount): bool
    {
        if (!$this->hasSufficientFunds($amount)) {
            return false;
        }
        
        $this->hold += $amount;
        return $this->save();
    }


    public function releaseFunds(float $amount): bool
    {
        $this->hold = max(0, $this->hold - $amount);
        return $this->save();
    }


    public function debit(float $amount): bool
    {
        if ($this->balance < $amount) {
            return false;
        }
        
        $this->balance -= $amount;
        return $this->save();
    }


    public function credit(float $amount): bool
    {
        $this->balance += $amount;
        return $this->save();
    }


    public function scopeWithBalance($query)
    {
        return $query->where('balance', '>', 0);
    }


    public function scopeWithHold($query)
    {
        return $query->where('hold', '>', 0);
    }

 
    public function scopeOfCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }


    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('balance', '>', 0)
              ->orWhere('hold', '>', 0);
        });
    }

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (empty($wallet->address)) {
                $wallet->address = self::generateAddress($wallet->currency);
            }
        });

        static::created(function ($wallet) {
            \Log::info("New wallet created", [
                'user_id' => $wallet->user_id,
                'currency' => $wallet->currency,
                'address' => $wallet->address
            ]);
        });
    }


    protected static function generateAddress(string $currency): string
    {
        return '0x' . bin2hex(random_bytes(20));
    }
}