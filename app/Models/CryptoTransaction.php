<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoTransaction extends Model
{
    protected $table = 'crypto_transactions';

    protected $fillable = [
        'txid',
        'user_id',
        'wallet_id',
        'type',
        'status',
        'currency',
        'amount',
        'fee',
        'from_address',
        'to_address',
        'confirmations',
        'error',
        'metadata',
        'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'confirmations' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_FEE = 'fee';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_REFUND = 'refund';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault([
            'name' => 'Deleted User'
        ]);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CryptoWallet::class, 'wallet_id')->withDefault();
    }

 
 
    public function getIsIncomingAttribute(): bool
    {
        return $this->amount > 0;
    }


    public function getIsOutgoingAttribute(): bool
    {
        return $this->amount < 0;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }


    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }


    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->amount);
    }


    public function getTotalAmountAttribute(): float
    {
        return $this->absolute_amount + $this->fee;
    }


    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->is_incoming ? '+' : '-';
        return $prefix . number_format($this->absolute_amount, 8) . ' ' . $this->currency;
    }


    public function getTypeNameAttribute(): string
    {
        return [
            self::TYPE_DEPOSIT => 'Пополнение',
            self::TYPE_WITHDRAWAL => 'Вывод',
            self::TYPE_FEE => 'Комиссия',
            self::TYPE_TRANSFER => 'Перевод',
            self::TYPE_REFUND => 'Возврат'
        ][$this->type] ?? $this->type;
    }


    public function getStatusNameAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'Ожидает',
            self::STATUS_PROCESSING => 'В обработке',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_CANCELLED => 'Отменена'
        ][$this->status] ?? $this->status;
    }


    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

}


    public function getCounterpartyAddressAttribute(): ?string
    {
        return $this->is_incoming ? $this->from_address : $this->to_address;
    }

    public function getConfirmationsProgressAttribute(): int
    {
        $required = $this->getRequiredConfirmations();
        if ($required === 0) return 100;
        
        return min(100, round(($this->confirmations / $required) * 100));
    }


    public function getExplorerUrlAttribute(): ?string
    {
        if (!$this->txid) return null;

        $explorers = [
            'BTC' => 'https://www.blockchain.com/btc/tx/',
            'ETH' => 'https://etherscan.io/tx/',
            'USDT' => 'https://etherscan.io/tx/',
            'default' => 'https://blockchair.com/transaction/'
        ];

        return ($explorers[$this->currency] ?? $explorers['default']) . $this->txid;
    }

    public function getProcessingTimeAttribute(): ?string
    {
        if (!$this->completed_at) return null;
        
        return $this->created_at->diffForHumans($this->completed_at, [
            'parts' => 2,
            'short' => true
        ]);
    }


    public function getRequiredConfirmations(): int
    {
        return match($this->currency) {
            'BTC' => 3,
            'ETH' => 12,
            'USDT' => 12,
            default => 3
        };
    }


    public function hasEnoughConfirmations(): bool
    {
        return $this->confirmations >= $this->getRequiredConfirmations();
    }


    public function markAsCompleted(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        return $this->save();
    }

    public function markAsFailed(string $error = null): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->error = $error;
        return $this->save();
    }

    public function markAsProcessing(): bool
    {
        $this->status = self::STATUS_PROCESSING;
        return $this->save();
    }

    public function addConfirmation(): bool
    {
        $this->confirmations++;
        
        if ($this->hasEnoughConfirmations() && $this->is_pending) {
            $this->markAsCompleted();
        }
        
        return $this->save();
    }


    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]);
    

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function addLog(string $message, array $context = []): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['logs'][] = [
            'message' => $message,
            'time' => now()->toIso8601String(),
            'context' => $context
        ];
        
        $this->metadata = $metadata;
        $this->save();
        
        \Log::info("Transaction #{$this->id}: {$message}", $context);
    }


    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeIncoming($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeWithTxid($query, string $txid)
    {
        return $query->where('txid', 'like', "%{$txid}%");
    }

    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }


    protected static function booted()
    {
        static::creating(function ($transaction) {
            // Автоматически подставляем user_id из кошелька
            if (!$transaction->user_id && $transaction->wallet_id) {
                $wallet = CryptoWallet::find($transaction->wallet_id);
                $transaction->user_id = $wallet?->user_id;
            }
        });

        static::created(function ($transaction) {
            $transaction->addLog('Transaction created');
        });

        static::updated(function ($transaction) {
            if ($transaction->wasChanged('status')) {
                $transaction->addLog('Status changed', [
         
           'from' => $transaction->getOriginal('status'),
 'to' => $transaction->status
 ]);
 }

 if ($transaction->wasChanged('confirmations')) {
 $transaction->addLog('Confirmations updated', [
 'confirmations' => $transaction->confirmations,
 'progress' => $transaction->confirmations_progress . '%'
 ]);
 }
 });
 }
 
}
