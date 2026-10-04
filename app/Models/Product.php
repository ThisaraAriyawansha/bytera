<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

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
     * Scope the query to products whose name, SKU or barcode contains the term.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $pattern)
            ->orWhere('sku', 'like', $pattern)
            ->orWhere('barcode', 'like', $pattern));
    }

    /**
     * Determine whether the product's total stock is at or below its low stock alert level.
     */
    public function isLowOnStock(): bool
    {
        return $this->total_stock <= $this->low_stock_alert;
    }

    /**
     * Get which of the given serial numbers this product already has a unit for, optionally ignoring one unit.
     *
     * @param  list<string>  $serials
     * @return list<string>
     */
    public function existingSerials(array $serials, ?int $ignoreUnitId = null): array
    {
        if ($serials === []) {
            return [];
        }

        return $this->units()
            ->whereIn('serial_number', $serials)
            ->when($ignoreUnitId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreUnitId))
            ->pluck('serial_number')
            ->all();
    }

    /**
     * Count the bills, warranties and stock documents that reference the product and would block deleting it.
     */
    public function documentReferenceCount(): int
    {
        return collect([SaleItem::class, Warranty::class, GrnItem::class, StockOutItem::class, StockTransferItem::class])
            ->sum(fn (string $model): int => $model::query()->where('product_id', $this->id)->count());
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
