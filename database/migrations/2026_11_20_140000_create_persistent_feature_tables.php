<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_low_id', 'user_high_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('provider')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->string('status');
            $table->string('reference')->unique();
            $table->timestamps();
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('trade')->nullable();
            $table->string('trade_label')->nullable();
            $table->string('urgency')->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->string('driver');
            $table->boolean('photo_provided')->default(false);
            $table->timestamps();
        });

        Schema::create('business_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plans');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
