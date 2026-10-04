<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'grn_no',
    'supplier_id',
    'supplier_name',
    'total_cost',
    'received_by_id',
    'received_by_name',
    'note',
    'location',
])]
class Grn extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the GRN's line items.
     *
     * @return HasMany<GrnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class);
    }

    /**
     * Get the supplier the goods came from.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who received the goods.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }
}
