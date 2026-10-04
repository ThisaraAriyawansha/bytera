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
        Schema::create('stock_outs', function (Blueprint $table) {
            $table->id();
            $table->string('stock_out_no')->unique();
            $table->enum('location', ['stores', 'showroom']);
            $table->foreignId('issued_by_id')->constrained('users');
            $table->string('issued_by_name');
            $table->string('recipient');
            $table->enum('reason', ['job', 'sale', 'other']);
            $table->string('reason_detail');
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_no')->nullable();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('stock_out_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_out_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('sku');
            $table->integer('qty');
            $table->json('serial_numbers');
            $table->decimal('cost_price', 12, 2);
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->string('serial_number');
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->enum('status', ['in_stock', 'sold', 'issued']);
            $table->enum('location', ['stores', 'showroom']);
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('stock_out_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'location']);
            $table->index('serial_number');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer']);
            $table->integer('qty');
            $table->unsignedBigInteger('reference_id');
            $table->enum('reference_type', ['grn', 'transfer', 'stock_out', 'sale', 'sale_cancel', 'batch_edit']);
            $table->text('note');
            $table->foreignId('performed_by')->constrained('users');
            $table->string('performed_by_name');
            $table->enum('location', ['stores', 'showroom'])->nullable();
            $table->enum('from_location', ['stores', 'showroom'])->nullable();
            $table->enum('to_location', ['stores', 'showroom'])->nullable();
            $table->string('recipient')->nullable();
            $table->string('reason')->nullable();
            $table->string('reason_detail')->nullable();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('grns', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name');
            $table->decimal('total_cost', 12, 2);
            $table->foreignId('received_by_id')->constrained('users');
            $table->string('received_by_name');
            $table->text('note');
            $table->enum('location', ['stores', 'showroom']);
            $table->timestamps();
        });

        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('sku');
            $table->integer('qty');
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->json('serials');
            $table->foreignId('batch_id')->constrained('product_batches');
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->foreignId('transferred_by_id')->constrained('users');
            $table->string('transferred_by_name');
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('sku');
            $table->integer('qty');
            $table->json('serial_numbers');
            $table->json('source_batch_ids');
            $table->json('new_batch_ids');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('grns');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('stock_out_items');
        Schema::dropIfExists('stock_outs');
    }
};
