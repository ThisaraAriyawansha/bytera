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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_no')->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('cashier_name');
            $table->enum('status', ['open', 'closed']);
            $table->decimal('opening_float', 12, 2);
            $table->timestamp('opened_at');
            $table->text('open_note');
            $table->decimal('cash_sales_total', 12, 2);
            $table->decimal('card_sales_total', 12, 2);
            $table->decimal('transfer_sales_total', 12, 2);
            $table->decimal('kokopay_sales_total', 12, 2);
            $table->integer('sales_count');
            $table->decimal('cash_expenses_total', 12, 2);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->text('close_note');
            $table->boolean('force_closed')->default(false);
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_by_name')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'flagged'])->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_by_name')->nullable();
            $table->text('review_note');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no')->unique();
            $table->enum('category', ['rent', 'utilities', 'salaries', 'maintenance', 'marketing', 'other']);
            $table->decimal('amount', 12, 2);
            $table->text('note');
            $table->foreignId('paid_by_id')->constrained('users');
            $table->string('paid_by_name');
            $table->unsignedBigInteger('linked_salary_payment_id')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shift_no')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('user_name');
            $table->string('user_role');
            $table->enum('type', ['monthly', 'commission', 'hybrid']);
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_base', 12, 2)->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->json('commission_items');
            $table->string('period_label');
            $table->text('note');
            $table->foreignId('issued_by_id')->constrained('users');
            $table->string('issued_by_name');
            $table->foreignId('linked_expense_id')->constrained('expenses');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shift_no')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('linked_salary_payment_id')->references('id')->on('salary_payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['linked_salary_payment_id']);
        });

        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('shifts');
    }
};
