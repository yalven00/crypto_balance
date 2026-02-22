<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens,Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


   public function cryptoWallets()
   {
   return $this->hasMany(CryptoWallet::class, 'user_id');
   }


   public function cryptoTransactions()
   {
   return $this->hasMany(CryptoTransaction::class, 'user_id');
   }

    public function getWallet(string $currency)
    {
        return $this->cryptoWallets()->where('currency', $currency)->first();
    }

    public function deposits()
    {
        return $this->cryptoTransactions()->where('type', CryptoTransaction::TYPE_DEPOSIT);
    }

    public function withdrawals()
    {
        return $this->cryptoTransactions()->where('type', CryptoTransaction::TYPE_WITHDRAWAL);
    }

    public function completedTransactions()
    {
        return $this->cryptoTransactions()->where('status', CryptoTransaction::STATUS_COMPLETED);
    }

    public function pendingTransactions()
    {
        return $this->cryptoTransactions()->where('status', CryptoTransaction::STATUS_PENDING);
    }

    public function getTotalBalanceAttribute()
    {
        return $this->cryptoWallets->sum('balance');
    }

    public function getTotalAvailableBalanceAttribute()
    {
        return $this->cryptoWallets->sum(function ($wallet) {
            return $wallet->available_balance;
        });
    }
}
