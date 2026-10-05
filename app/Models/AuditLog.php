<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['collection_name', 'doc_id', 'label', 'changes', 'performed_by_id', 'performed_by_name'])]
class AuditLog extends Model
{
    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * The record types admins can edit (SPEC §8.21), keyed by the table the audit row names.
     *
     * @var array<string, string>
     */
    public const RECORD_TYPES = [
        'sales' => 'Bill',
        'jobs' => 'Job',
        'grns' => 'GRN',
        'stock_transfers' => 'Stock Transfer',
        'stock_outs' => 'Stock Out',
        'supplier_payments' => 'Supplier Payment',
    ];

    /**
     * Get the record type label, e.g. "Bill".
     */
    public function recordTypeLabel(): string
    {
        return self::RECORD_TYPES[$this->collection_name] ?? str($this->collection_name)->replace('_', ' ')->singular()->title()->toString();
    }

    /**
     * Get a changed field's label: "customer_name" → "Customer name", keeping a GRN line's
     * "Product · cost_price" prefix → "Product · Cost price".
     */
    public static function fieldLabel(string $field): string
    {
        $segments = explode(' · ', $field);
        $last = array_pop($segments);

        return implode(' · ', [...$segments, str($last)->replace('_', ' ')->trim()->ucfirst()->toString()]);
    }

    /**
     * Get a stored before / after value as text for the view modal; null when it was empty.
     */
    public static function displayValue(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === '', $value === [] => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode('; ', array_map(
                fn (mixed $item): string => is_array($item)
                    ? implode(' · ', array_filter(array_map(fn (mixed $part): string => is_scalar($part) ? (string) $part : '', array_diff_key($item, ['id' => true])), 'strlen'))
                    : (string) self::displayValue($item),
                $value,
            )),
            default => (string) $value,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * Get the user who made the change.
     *
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
