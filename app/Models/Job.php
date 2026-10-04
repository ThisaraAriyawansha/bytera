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
     * Job statuses with their label and badge variant (SPEC §8.7), in workflow order.
     *
     * @var array<string, array{label: string, variant: string}>
     */
    public const STATUSES = [
        'pending' => ['label' => 'Job Pending', 'variant' => 'warning'],
        'ongoing' => ['label' => 'Ongoing Job', 'variant' => 'default'],
        'done' => ['label' => 'Job Done', 'variant' => 'success'],
        'delivered' => ['label' => 'Delivered', 'variant' => 'info'],
        'unrepairable' => ['label' => "Can't Repair", 'variant' => 'danger'],
    ];

    /**
     * Device type buttons on the job note; "Other" asks for the type.
     *
     * @var list<string>
     */
    public const DEVICE_TYPES = ['Desktop', 'Laptop', 'Printer', 'Monitor', 'CCTV', 'Other'];

    /**
     * Quick-add part chips for each device type.
     *
     * @var array<string, list<string>>
     */
    public const PART_PRESETS = [
        'Laptop' => ['RAM', 'SSD', 'HDD', 'Battery', 'WiFi Card', 'Keyboard', 'Display'],
        'Desktop' => ['RAM', 'SSD', 'HDD', 'Processor', 'Motherboard', 'GPU', 'Power Supply', 'WiFi Card'],
        'Printer' => ['Cartridge', 'Toner', 'Drum Unit', 'Power Cable'],
        'Monitor' => ['Power Adapter', 'Stand', 'Cable'],
        'CCTV' => ['HDD', 'DVR/NVR', 'Camera', 'Power Adapter'],
    ];

    /**
     * Accessory checkboxes.
     *
     * @var list<string>
     */
    public const ACCESSORIES = ['Charger', 'Power Cable', 'Battery', 'Adapter', 'Bag', 'Mouse', 'Keyboard', 'HDD/SSD'];

    /**
     * Physical condition checkboxes.
     *
     * @var list<string>
     */
    public const CONDITIONS = ['Good', 'Scratches', 'Cracked', 'Broken Hinges', 'Liquid Damage', 'Missing Parts'];

    /**
     * Get the status label, e.g. "Job Done".
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    /**
     * Get the device as one line, e.g. "Laptop · Dell Inspiron 15".
     */
    public function deviceLabel(): string
    {
        $type = $this->device_type === 'Other' && filled($this->device_type_other) ? $this->device_type_other : $this->device_type;
        $name = trim($this->brand.' '.$this->model);

        return $name === '' ? (string) $type : "{$type} · {$name}";
    }

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
