<?php

namespace App\Http\Resources;

use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierPayment
 */
class SupplierPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array for the Payment History table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'date' => $this->created_at->format('M j, Y'),
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'method_label' => SupplierPayment::METHODS[$this->method] ?? $this->method,
            'reference' => $this->reference,
            'note' => $this->note,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'paid_by_name' => $this->paid_by_name,
            'updateUrl' => route('suppliers.payments.update', [$this->supplier_id, $this->id]),
        ];
    }
}
