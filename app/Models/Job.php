<?php

namespace App\Models;

use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'job_no',
    'customer_id',
    'customer_name',
    'customer_company',
    'customer_address',
    'customer_city',
    'customer_phone',
    'customer_phone2',
    'customer_email',
    'device_type',
    'device_type_other',
    'brand',
    'model',
    'serial_no',
    'color',
    'parts',
    'fault_description',
    'accessories',
    'accessories_other',
    'physical_condition',
    'special_notes',
    'received_by_id',
    'received_by_name',
    'assigned_technician_id',
    'assigned_technician_name',
    'services',
    'estimated_cost',
    'advance_paid',
    'expected_delivery_date',
    'status',
    'repair_cost',
    'date_returned',
    'commission_payment_id',
])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parts' => 'array',
            'accessories' => 'array',
            'physical_condition' => 'array',
            'services' => 'array',
            'estimated_cost' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'expected_delivery_date' => 'date',
            'repair_cost' => 'decimal:2',
            'date_returned' => 'datetime',
        ];
    }

    /**
     * Get the job's status history.
     *
     * @return HasMany<JobStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class);
    }

    /**
     * Get the sales that billed the job.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get the stock issued for the job.
     *
     * @return HasMany<StockOut, $this>
     */
    public function stockOuts(): HasMany
    {
        return $this->hasMany(StockOut::class);
    }

    /**
     * Get the job's customer.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who received the device.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /**
     * Get the technician assigned to the job.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /**
     * Get the salary payment that paid commission on the job.
     *
     * @return BelongsTo<SalaryPayment, $this>
     */
    public function commissionPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'commission_payment_id');
    }
}
