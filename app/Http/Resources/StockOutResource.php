<?php

namespace App\Http\Resources;

use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Services\StockService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockOut
 */
class StockOutResource extends JsonResource
{
    /**
     * Transform the resource into an array for the stock out view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->map(fn (StockOutItem $item): array => [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'qty' => $item->qty,
            'serial_numbers' => $item->serial_numbers ?? [],
            'cost_price' => (float) $item->cost_price,
            'line_total' => $item->qty * SupplierService::cents($item->cost_price) / 100,
        ])->values();

        return [
            'id' => $this->id,
            'number' => $this->stock_out_no,
            'location' => $this->location,
            'location_label' => StockService::LOCATIONS[$this->location] ?? $this->location,
            'issued_by_name' => $this->issued_by_name,
            'recipient' => $this->recipient,
            'reason' => $this->reason,
            'reason_label' => StockOut::REASONS[$this->reason] ?? $this->reason,
            'reason_detail' => $this->reason_detail,
            'job_id' => $this->job_id,
            'job_no' => $this->job_no,
            'note' => $this->note,
            'date' => $this->created_at->format('M j, Y g:i A'),
            'items' => $items,
            'total_cost' => round($items->sum('line_total'), 2),
            'updateUrl' => route('stock-out.update', $this->id),
        ];
    }
}
