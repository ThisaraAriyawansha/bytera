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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('cashier_name');
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_no')->nullable();
            $table->json('services')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'kokopay']);
            $table->json('payments')->nullable();
            $table->decimal('kokopay_charge_percent', 5, 2)->nullable();
            $table->decimal('kokopay_charge_amount', 12, 2)->nullable();
            $table->decimal('card_charge_percent', 5, 2)->nullable();
            $table->decimal('card_charge_amount', 12, 2)->nullable();
            $table->enum('payment_status', ['paid', 'partial', 'pending']);
            $table->decimal('amount_tendered', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->integer('points_redeemed')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shift_no')->nullable();
            $table->foreignId('commission_payment_id')->nullable()->constrained('salary_payments')->nullOnDelete();
            $table->enum('status', ['cancelled'])->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancelled_by_name')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('shift_id');
            $table->index('customer_id');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('sku');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->integer('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->integer('warranty_months');
            $table->json('units')->nullable();
            $table->json('batch_allocations')->nullable();
            $table->timestamps();
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number')->nullable();
            $table->integer('warranty_months');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'claimed']);
            $table->text('claim_note')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('end_date');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_address');
            $table->foreignId('prepared_by_id')->constrained('users');
            $table->string('prepared_by_name');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('valid_until');
            $table->enum('status', ['sent', 'accepted', 'rejected', 'expired', 'converted'])->default('sent');
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->integer('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
