<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOutService
{
    /**
     * Stock out fields an admin edit may change; location and items stay locked.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = ['recipient', 'reason', 'reason_detail', 'job', 'note'];

    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {}

    /**
     * Issue stock without a POS sale in one transaction (SPEC §8.14): FIFO-consume the location's batches
     * (or mark the picked serial units `issued`), lower the product counters, keep each line's cost and
     * write an `out` movement carrying the recipient, reason and job.
     *
     * @param  array{location: string, recipient: string, reason: string, reason_detail?: ?string, job_id?: ?int, note?: ?string, items: list<array{product_id: int, qty?: ?int, unit_ids?: list<int>}>}  $data
     *
     * @throws ValidationException
     */
    public function issue(array $data, User $issuedBy): StockOut
    {
        return DB::transaction(function () use ($data, $issuedBy): StockOut {
            $location = $data['location'];
            $job = $this->job($data);

            $products = Product::query()
                ->whereKey(collect($data['items'])->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stockOutNo = Numbering::next('stockOut', Numbering::PREFIXES['stockOut']);

            $stockOut = StockOut::query()->create([
                'stock_out_no' => $stockOutNo,
                'location' => $location,
                'issued_by_id' => $issuedBy->id,
                'issued_by_name' => $issuedBy->name ?: $issuedBy->email,
                'recipient' => $data['recipient'],
                'reason' => $data['reason'],
                'reason_detail' => (string) ($data['reason_detail'] ?? ''),
                'job_id' => $job?->id,
                'job_no' => $job?->job_no,
                'note' => (string) ($data['note'] ?? ''),
            ]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $unitIds = $product->track_serial ? array_values($item['unit_ids'] ?? []) : [];
                $qty = $product->track_serial ? count($unitIds) : (int) $item['qty'];

                $this->stock->adjustStock($product, $location, -$qty);

                [$costPrice, $serials] = $product->track_serial
                    ? $this->issueUnits($product, $location, $unitIds, $stockOut)
                    : [$this->issueFromBatches($product, $location, $qty), []];

                $stockOut->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'qty' => $qty,
                    'serial_numbers' => $serials,
                    'cost_price' => $costPrice,
                ]);

                $this->stock->recordMovement($product, 'out', -$qty, 'stock_out', $stockOut->id, "Stock out {$stockOutNo}", $issuedBy, [
                    'location' => $location,
                    ...$this->movementDetails($stockOut),
                ]);
            }

            return $stockOut;
        });
    }

    /**
     * Admin edit of a stock out (SPEC §8.14): recipient, reason, detail, note and job link only. The stock
     * movements of the document are kept in step, and the changes are audit logged in the same transaction.
     *
     * @param  array{recipient: string, reason: string, reason_detail?: ?string, job_id?: ?int, note?: ?string}  $data
     * @return list<array{field: string, before: mixed, after: mixed}> the changes made
     */
    public function update(StockOut $stockOut, array $data, User $editor): array
    {
        return DB::transaction(function () use ($stockOut, $data, $editor): array {
            $stockOut = StockOut::query()->whereKey($stockOut->id)->lockForUpdate()->firstOrFail();
            $job = $this->job($data);

            $patch = [
                'recipient' => $data['recipient'],
                'reason' => $data['reason'],
                'reason_detail' => (string) ($data['reason_detail'] ?? ''),
                'job_id' => $job?->id,
                'job_no' => $job?->job_no,
                'note' => (string) ($data['note'] ?? ''),
            ];

            $changes = $this->audit->diff(
                [...$stockOut->only(['recipient', 'reason_detail', 'note']), 'reason' => StockOut::REASONS[$stockOut->reason], 'job' => $stockOut->job_no],
                [...$patch, 'reason' => StockOut::REASONS[$patch['reason']], 'job' => $patch['job_no']],
                self::EDITABLE_FIELDS,
            );

            if ($changes === []) {
                return [];
            }

            $stockOut->update($patch);

            StockMovement::query()
                ->where('reference_type', 'stock_out')
                ->where('reference_id', $stockOut->id)
                ->update($this->movementDetails($stockOut));

            $this->audit->write($stockOut->getTable(), $stockOut->id, $stockOut->stock_out_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * FIFO-consume the location's batches and return the weighted unit cost.
     *
     * @throws ValidationException
     */
    private function issueFromBatches(Product $product, string $location, int $qty): float
    {
        $allocation = $this->stock->allocateFifo($product, $location, $qty);
        $this->stock->applyAllocation($allocation);

        return $allocation['costPrice'];
    }

    /**
     * Mark the picked serial units issued on this stock out, take them out of their batches, and return
     * their average cost and serial numbers.
     *
     * @param  list<int>  $unitIds
     * @return array{0: float, 1: list<string>}
     *
     * @throws ValidationException
     */
    private function issueUnits(Product $product, string $location, array $unitIds, StockOut $stockOut): array
    {
        $units = $this->stock->lockAvailableUnits($product, $location, $unitIds);
        $this->stock->takeUnitsFromBatches($units);

        $units->toQuery()->update([
            'status' => 'issued',
            'stock_out_id' => $stockOut->id,
            'issued_at' => now(),
        ]);

        $costCents = $units->sum(fn (ProductUnit $unit): int => SupplierService::cents($unit->cost_price));

        return [round($costCents / $units->count() / 100, 2), $units->pluck('serial_number')->all()];
    }

    /**
     * The job a "Job / Repair" stock out is for; other reasons never link a job.
     *
     * @param  array{reason: string, job_id?: ?int}  $data
     */
    private function job(array $data): ?Job
    {
        if ($data['reason'] !== 'job' || blank($data['job_id'] ?? null)) {
            return null;
        }

        return Job::query()->findOrFail($data['job_id']);
    }

    /**
     * The recipient / reason / job columns a stock out copies onto its movements.
     *
     * @return array{recipient: string, reason: string, reason_detail: string, job_id: ?int, job_no: ?string}
     */
    private function movementDetails(StockOut $stockOut): array
    {
        return [
            'recipient' => $stockOut->recipient,
            'reason' => $stockOut->reason,
            'reason_detail' => $stockOut->reason_detail,
            'job_id' => $stockOut->job_id,
            'job_no' => $stockOut->job_no,
        ];
    }
}
