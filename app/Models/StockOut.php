<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'stock_out_no',
    'location',
    'issued_by_id',
    'issued_by_name',
    'recipient',
    'reason',
    'reason_detail',
    'job_id',
    'job_no',
    'note',
])]
class StockOut extends Model
{
    /**
     * Get the stock out's line items.
     *
     * @return HasMany<StockOutItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOutItem::class);
    }

    /**
     * Get the user who issued the stock.
     *
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /**
     * Get the repair job the stock was issued for.
     *
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
