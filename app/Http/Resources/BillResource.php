<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class BillResource extends JsonResource
{
    /**
     * Transform the resource into an array for the Bills view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isCancelled = $this->status === 'cancelled';

        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'date' => $this->created_at->format('M j, Y g:i A'),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'cashier_name' => $this->cashier_name,
            'shift_no' => $this->shift_no,
            'job_no' => $this->job_no,
            'note' => $this->note,
            'items' => $this->items->map(fn (SaleItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'serials' => array_column($item->units ?? [], 'serialNumber'),
                'qty' => $item->qty,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'line_total' => (float) $item->line_total,
            ])->values(),
            'services' => collect($this->services ?? [])->map(fn (array $service): array => [
                'name' => $service['name'],
                'job_no' => $service['jobNo'] ?? null,
                'price' => (float) $service['price'],
                'is_free' => ($service['chargeType'] ?? 'paid') === 'free',
                'free_reason' => $service['freeReason'] ?? '',
            ])->values(),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'points_redeemed' => $this->points_redeemed,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'payment_label' => $this->paymentLabel(),
            'payments' => $this->paymentLegs(),
            'is_split' => count($this->payments ?? []) > 1,
            'amount_tendered' => $this->amount_tendered === null ? null : (float) $this->amount_tendered,
            'change_amount' => $this->change_amount === null ? null : (float) $this->change_amount,
            'is_cancelled' => $isCancelled,
            'cancelled_at' => $this->cancelled_at?->format('M j, Y g:i A'),
            'cancelled_by_name' => $this->cancelled_by_name,
            'cancel_reason' => $this->cancel_reason,
            'urls' => [
                'update' => route('bills.update', $this->id),
                'reverse' => route('bills.reverse', $this->id),
                'email' => ! $isCancelled && filled($this->customer_email) ? route('sales.email', $this->id) : null,
            ],
        ];
    }
}
