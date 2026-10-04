<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array for the supplier view modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'total_payable' => (float) $this->total_payable,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) $this->balance,
            'payment_status' => $this->payment_status,
            'last_payment_at' => $this->last_payment_at?->format('M j, Y'),
            'last_statement_sent_at' => $this->last_statement_sent_at?->format('M j, Y g:i A'),
            'paymentsUrl' => route('suppliers.payments.store', $this->id),
            'statementUrl' => route('suppliers.statement', $this->id),
        ];
    }
}
