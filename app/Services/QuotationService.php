<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    /**
     * Save a New Quotation (SPEC §8.9) with the next QUO- number. Totals are worked out here from the lines;
     * a catalogue item keeps the product's name and SKU as they are now. Quotations never touch stock.
     *
     * @param  array{
     *     customer_name?: ?string,
     *     customer_phone?: ?string,
     *     customer_address?: ?string,
     *     items: list<array{product_id?: ?int, product_name: string, qty: int, unit_price: float|int|string, discount?: float|int|string|null}>,
     *     discount_amount?: float|int|string|null,
     *     note?: ?string,
     *     valid_until: string,
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $preparedBy): Quotation
    {
        $products = Product::query()
            ->whereKey(array_filter(array_column($data['items'], 'product_id')))
            ->get(['id', 'name', 'sku'])
            ->keyBy('id');

        $lines = array_map(function (array $item) use ($products): array {
            $product = filled($item['product_id'] ?? null) ? $products[$item['product_id']] : null;
            $priceCents = SupplierService::cents($item['unit_price']);
            $discountCents = SupplierService::cents($item['discount'] ?? 0);

            return [
                'product_id' => $product?->id,
                'product_name' => $product->name ?? $item['product_name'],
                'sku' => $product?->sku,
                'qty' => (int) $item['qty'],
                'unit_price' => $priceCents / 100,
                'discount' => $discountCents / 100,
                'line_total' => ($priceCents - $discountCents) * (int) $item['qty'] / 100,
            ];
        }, $data['items']);

        $subtotalCents = SupplierService::cents(array_sum(array_column($lines, 'line_total')));
        $discountCents = SupplierService::cents($data['discount_amount'] ?? 0);

        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount must be between 0 and the subtotal.']);
        }

        return DB::transaction(function () use ($data, $preparedBy, $lines, $subtotalCents, $discountCents): Quotation {
            $quotation = Quotation::query()->create([
                'quotation_no' => Numbering::next('quotation', Numbering::PREFIXES['quotation']),
                'customer_name' => filled($data['customer_name'] ?? null) ? $data['customer_name'] : Quotation::WALK_IN_CUSTOMER,
                'customer_phone' => (string) ($data['customer_phone'] ?? ''),
                'customer_address' => (string) ($data['customer_address'] ?? ''),
                'prepared_by_id' => $preparedBy->id,
                'prepared_by_name' => $preparedBy->name ?: $preparedBy->email,
                'subtotal' => $subtotalCents / 100,
                'discount_amount' => $discountCents / 100,
                'total_amount' => ($subtotalCents - $discountCents) / 100,
                'valid_until' => $data['valid_until'],
                'status' => 'sent',
                'note' => (string) ($data['note'] ?? ''),
            ]);

            $quotation->items()->createMany($lines);

            return $quotation;
        });
    }

    /**
     * Mark Accepted / Mark Rejected. A converted quotation can't change.
     *
     * @param  'accepted'|'rejected'  $status
     *
     * @throws ValidationException
     */
    public function setStatus(Quotation $quotation, string $status): Quotation
    {
        return DB::transaction(function () use ($quotation, $status): Quotation {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status === 'converted') {
                throw ValidationException::withMessages(['status' => "{$quotation->quotation_no} has been converted and can't change."]);
            }

            if ($quotation->status === $status) {
                throw ValidationException::withMessages(['status' => "{$quotation->quotation_no} is already {$quotation->statusLabel()}."]);
            }

            $quotation->update(['status' => $status]);

            return $quotation;
        });
    }
}
