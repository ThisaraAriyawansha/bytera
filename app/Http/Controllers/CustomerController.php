<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Minimum characters before the picker search runs.
     */
    public const SEARCH_MIN_LENGTH = 2;

    /**
     * Maximum results returned to the picker.
     */
    public const SEARCH_LIMIT = 8;

    /**
     * List customers, newest first, with a prefix search on name or phone.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('customers.index', [
            'customers' => Customer::query()
                ->when($search !== '', fn ($query) => $query->startingWith($search))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE),
            'search' => $search,
        ]);
    }

    /**
     * Add a customer.
     */
    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        $message = "{$customer->name} has been added.";

        session()->flash('status', $message);

        return response()->json(['message' => $message, 'customer' => CustomerResource::make($customer)], 201);
    }

    /**
     * Update a customer's contact details. Loyalty points are only changed by sales.
     */
    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        $message = "{$customer->name} has been updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Customer picker for the POS and Jobs: prefix match on name or phone, at most 8 results.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        abort_unless(Gate::any(['customers.view', 'sales.view', 'jobs.view']), 403);

        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return CustomerResource::collection([]);
        }

        return CustomerResource::collection(
            Customer::query()
                ->startingWith($term)
                ->orderBy('name')
                ->limit(self::SEARCH_LIMIT)
                ->get(),
        );
    }
}
