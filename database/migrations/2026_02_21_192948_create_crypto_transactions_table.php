<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txid')->nullable()->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('crypto_account_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']); 
            $table->string('operation_type'); 
            $table->string('currency', 10);
            $table->decimal('amount', 20, 8);
            $table->decimal('fee', 20, 8)->default(0);
            $table->decimal('balance_before', 20, 8);
            $table->decimal('balance_after', 20, 8);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->integer('confirmations')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('crypto_transactions');
    }
};
