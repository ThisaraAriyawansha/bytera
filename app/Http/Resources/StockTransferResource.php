<?php

namespace App\Http\Resources;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransfer
 */
class StockTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array for the transfer view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->transfer_no,
            'transferred_by_name' => $this->transferred_by_name,
            'note' => $this->note,
            'date' => $this->created_at->format('M j, Y g:i A'),
            'total_qty' => $this->items->sum('qty'),
            'items' => $this->items->map(fn (StockTransferItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'qty' => $item->qty,
                'serial_numbers' => $item->serial_numbers ?? [],
            ])->values(),
            'updateUrl' => route('stock-transfer.update', $this->id),
        ];
    }
}
