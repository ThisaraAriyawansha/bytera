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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_company');
            $table->text('customer_address');
            $table->string('customer_city');
            $table->string('customer_phone');
            $table->string('customer_phone2');
            $table->string('customer_email');
            $table->string('device_type');
            $table->string('device_type_other');
            $table->string('brand');
            $table->string('model');
            $table->string('serial_no');
            $table->string('color');
            $table->json('parts');
            $table->text('fault_description');
            $table->json('accessories');
            $table->string('accessories_other');
            $table->json('physical_condition');
            $table->text('special_notes');
            $table->foreignId('received_by_id')->constrained('users');
            $table->string('received_by_name');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_technician_name');
            $table->json('services');
            $table->decimal('estimated_cost', 12, 2);
            $table->decimal('advance_paid', 12, 2);
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['pending', 'ongoing', 'done', 'delivered', 'unrepairable'])->default('pending');
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->timestamp('date_returned')->nullable();
            $table->foreignId('commission_payment_id')->nullable()->constrained('salary_payments')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_phone');
        });

        Schema::create('job_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'ongoing', 'done', 'delivered', 'unrepairable']);
            $table->text('note');
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->foreignId('updated_by_id')->constrained('users');
            $table->string('updated_by_name');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_status_history');
        Schema::dropIfExists('jobs');
    }
};
