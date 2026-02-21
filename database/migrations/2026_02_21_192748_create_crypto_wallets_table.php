<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10); 
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('hold', 20, 8)->default(0); 
            $table->string('address')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'currency']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('crypto_wallets');
    }
 
};
