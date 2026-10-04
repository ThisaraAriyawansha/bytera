<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'brand_id',
    'main_category_id',
    'sub_category_id',
    'sku',
    'barcode',
    'selling_price',
    'total_stock',
    'stores_stock',
    'showroom_stock',
    'low_stock_alert',
    'description',
    'warranty_months',
    'track_serial',
    'active',
    'low_stock_alerted',
])]
class Product extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'total_stock' => 'integer',
            'stores_stock' => 'integer',
            'showroom_stock' => 'integer',
            'low_stock_alert' => 'integer',
            'warranty_months' => 'integer',
            'track_serial' => 'boolean',
            'active' => 'boolean',
            'low_stock_alerted' => 'boolean',
        ];
    }

    /**
     * Get the product's brand.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the product's main category.
     *
     * @return BelongsTo<MainCategory, $this>
     */
    public function mainCategory(): BelongsTo
    {
        return $this->belongsTo(MainCategory::class);
    }

    /**
     * Get the product's sub category.
     *
     * @return BelongsTo<SubCategory, $this>
     */
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    /**
     * Get the product's stock batches.
     *
     * @return HasMany<ProductBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Get the product's serial-tracked units.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Get the product's stock movements.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
