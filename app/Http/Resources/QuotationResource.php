<?php

namespace App\Http\Resources;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array for the Quotations view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'quotation_no' => $this->quotation_no,
            'date' => $this->created_at->format('M j, Y g:i A'),
            'valid_until' => $this->valid_until->format('M j, Y'),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'prepared_by_name' => $this->prepared_by_name,
            'note' => $this->note,
            'status' => $this->status,
            'display_status' => $status,
            'status_label' => Quotation::STATUSES[$status]['label'],
            'status_variant' => Quotation::STATUSES[$status]['variant'],
            'items' => $this->items->map(fn (QuotationItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'qty' => $item->qty,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'line_total' => (float) $item->line_total,
            ])->values(),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'urls' => [
                'status' => route('quotations.status', $this->id),
                'destroy' => route('quotations.destroy', $this->id),
            ],
        ];
    }
}
