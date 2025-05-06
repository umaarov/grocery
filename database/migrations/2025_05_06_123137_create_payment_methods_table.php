<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // credit_card, paypal ...
            $table->string('card_type')->nullable(); // visa, mastercard ..
            $table->string('card_number')->nullable();
            $table->string('card_holder')->nullable();
            $table->string('expiry_month')->nullable();
            $table->string('expiry_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
