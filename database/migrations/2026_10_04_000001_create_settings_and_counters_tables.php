<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->json('notify_emails');
            $table->timestamps();
        });

        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('otp', 6);
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->integer('value')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counters');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('shop_settings');
    }
};
