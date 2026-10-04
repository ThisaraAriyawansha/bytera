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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('main_category_id')->constrained();
            $table->foreignId('sub_category_id')->constrained();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->integer('total_stock');
            $table->integer('stores_stock');
            $table->integer('showroom_stock');
            $table->integer('low_stock_alert')->default(5);
            $table->text('description')->nullable();
            $table->integer('warranty_months')->default(0);
            $table->boolean('track_serial');
            $table->boolean('active')->default(true);
            $table->boolean('low_stock_alerted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->integer('total_qty');
            $table->integer('remaining_qty');
            $table->enum('status', ['active', 'depleted']);
            $table->enum('location', ['stores', 'showroom']);
            $table->foreignId('source_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['product_id', 'location', 'status', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('products');
    }
};
