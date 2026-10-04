<?php

namespace App\Support;

use App\Models\Grn;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The document behind each stock movement (SPEC §8.15): its number, and a link to the list page that shows it.
 */
class StockMovementReferences
{
    /**
     * The model and number column for each reference type that points at a document.
     *
     * @var array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    private const DOCUMENTS = [
        'grn' => [Grn::class, 'grn_no', 'grn.index'],
        'transfer' => [StockTransfer::class, 'transfer_no', 'stock-transfer.index'],
        'stock_out' => [StockOut::class, 'stock_out_no', 'stock-out.index'],
        'sale' => [Sale::class, 'invoice_no', 'bills.index'],
        'sale_cancel' => [Sale::class, 'invoice_no', 'bills.index'],
    ];

    /**
     * Resolve the reference of every movement with one query per document type. Batch edits have no document,
     * so they link to the product instead.
     *
     * @param  iterable<StockMovement>  $movements
     * @return array<int, array{number: string, url: ?string}> keyed by movement id
     */
    public static function resolve(iterable $movements): array
    {
        $movements = collect($movements);
        $numbers = [];

        foreach ($movements->groupBy('reference_type') as $type => $group) {
            if (isset(self::DOCUMENTS[$type])) {
                [$model, $column] = self::DOCUMENTS[$type];

                $numbers[$type] = $model::query()->whereKey($group->pluck('reference_id')->unique()->values())->pluck($column, 'id')->all();
            }
        }

        return $movements
            ->mapWithKeys(fn (StockMovement $movement): array => [
                $movement->id => self::reference($movement, $numbers[$movement->reference_type][$movement->reference_id] ?? null),
            ])
            ->all();
    }

    /**
     * Add an OR condition for each document type whose number matches the LIKE pattern.
     *
     * @param  Builder<StockMovement>  $query
     */
    public static function orWhereNumberLike(Builder $query, string $pattern): void
    {
        foreach (self::DOCUMENTS as $type => [$model, $column]) {
            $query->orWhere(fn (Builder $query) => $query
                ->where('reference_type', $type)
                ->whereIn('reference_id', $model::query()->select('id')->where($column, 'like', $pattern)));
        }
    }

    /**
     * @return array{number: string, url: ?string}
     */
    private static function reference(StockMovement $movement, ?string $number): array
    {
        if ($movement->reference_type === 'batch_edit') {
            return [
                'number' => "Batch #{$movement->reference_id}",
                'url' => $movement->product ? route('products.index', ['search' => $movement->product->sku]) : null,
            ];
        }

        if ($number === null) {
            return ['number' => '—', 'url' => null];
        }

        $date = $movement->created_at->toDateString();

        return [
            'number' => $number,
            'url' => route(self::DOCUMENTS[$movement->reference_type][2], ['search' => $number, 'from' => $date, 'to' => $date]),
        ];
    }
}
