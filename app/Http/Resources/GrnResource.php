<?php

namespace App\Http\Resources;

use App\Models\Grn;
use App\Models\GrnItem;
use App\Services\StockService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Grn
 */
class GrnResource extends JsonResource
{
    /**
     * Transform the resource into an array for the GRN view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grn_no' => $this->grn_no,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'location' => $this->location,
            'location_label' => StockService::LOCATIONS[$this->location] ?? $this->location,
            'received_by_name' => $this->received_by_name,
            'total_cost' => (float) $this->total_cost,
            'note' => $this->note,
            'date' => $this->created_at->format('M j, Y g:i A'),
            'items' => $this->items->map(fn (GrnItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'qty' => $item->qty,
                'cost_price' => (float) $item->cost_price,
                'selling_price' => $item->selling_price === null ? null : (float) $item->selling_price,
                'serials' => $item->serials ?? [],
                'line_total' => $item->qty * SupplierService::cents($item->cost_price) / 100,
            ])->values(),
            'updateUrl' => route('grn.update', $this->id),
        ];
    }
}
