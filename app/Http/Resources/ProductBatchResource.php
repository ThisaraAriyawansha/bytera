<?php

namespace App\Http\Resources;

use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductBatch
 */
class ProductBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array for the Stock Batches modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'received_at' => $this->received_at->format('M j, Y'),
            'location' => $this->location,
            'cost_price' => (float) $this->cost_price,
            'selling_price' => $this->selling_price === null ? null : (float) $this->selling_price,
            'total_qty' => $this->total_qty,
            'remaining_qty' => $this->remaining_qty,
            'status' => $this->status,
            'note' => $this->note,
            'units_count' => $this->whenCounted('units'),
            'updateUrl' => route('products.batches.update', [$this->product_id, $this->id]),
            'unitsUrl' => route('products.units.index', [$this->product_id, $this->id]),
        ];
    }
}
