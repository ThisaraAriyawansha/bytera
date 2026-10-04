<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Catalog\CustomerRequest;

class StoreSaleCustomerRequest extends CustomerRequest
{
    /**
     * The POS "New Customer" form is open to anyone who can sell.
     */
    public function authorize(): bool
    {
        return $this->user()->can('sales.view');
    }
}
