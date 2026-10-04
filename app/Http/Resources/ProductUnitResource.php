<?php

namespace App\Http\Resources;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductUnit
 */
class ProductUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array for the Serial Numbers modal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'location' => $this->location,
            'updateUrl' => route('products.units.update', [$this->product_id, $this->id]),
            'deleteUrl' => route('products.units.destroy', [$this->product_id, $this->id]),
        ];
    }
}
