<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreGrnRequest;
use App\Http\Requests\Stock\UpdateGrnRequest;
use App\Http\Resources\GrnResource;
use App\Models\Grn;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\GrnService;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrnController extends Controller
{
    public function __construct(private GrnService $grns) {}

    /**
     * List GRNs in the From / To range (default last 30 days), searchable by GRN number or supplier.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $canEdit = $request->user()->can('grn.edit');

        return view('grn.index', [
            'grns' => Grn::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('grn_no', 'like', $pattern)
                    ->orWhere('supplier_name', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'canCreate' => $request->user()->can('grn.create'),
            'canEdit' => $canEdit,
            'supplierOptions' => $canEdit ? $this->supplierOptions() : [],
        ]);
    }

    /**
     * The New GRN page.
     */
    public function create(): View
    {
        return view('grn.create', [
            'supplierOptions' => $this->supplierOptions(),
            'products' => Product::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'track_serial', 'selling_price'])
                ->map(fn (Product $product): array => [
                    'value' => $product->id,
                    'label' => $product->name,
                    'description' => $product->sku.($product->track_serial ? ' · Serial tracked' : ''),
                    'sku' => $product->sku,
                    'track_serial' => $product->track_serial,
                    'selling_price' => (float) $product->selling_price,
                ])
                ->all(),
        ]);
    }

    /**
     * Receive the goods: stock lands at the chosen location and the supplier's payable goes up.
     */
    public function store(StoreGrnRequest $request): JsonResponse
    {
        $grn = $this->grns->receive($request->validated(), $request->user());

        $message = "{$grn->grn_no} saved — stock received into ".($grn->location === 'showroom' ? 'Showroom' : 'Stores').'.';

        session()->flash('status', $message);

        return response()->json(['message' => $message, 'redirect' => route('grn.index')], 201);
    }

    /**
     * Get a GRN with its items for the view modal.
     */
    public function show(Grn $grn): JsonResponse
    {
        return response()->json(['grn' => GrnResource::make($grn->load('items'))]);
    }

    /**
     * Admin edit: supplier, note and line prices only. Totals and supplier balances are left as received.
     */
    public function update(UpdateGrnRequest $request, Grn $grn): JsonResponse
    {
        $changes = $this->grns->update($grn, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "{$grn->grn_no} updated.",
            'grn' => GrnResource::make($grn->fresh()->load('items')),
        ]);
    }

    /**
     * Suppliers for the SearchableSelect: name, with the phone as a hint.
     *
     * @return list<array{value: int, label: string, description: string}>
     */
    private function supplierOptions(): array
    {
        return Supplier::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Supplier $supplier): array => [
                'value' => $supplier->id,
                'label' => $supplier->name,
                'description' => $supplier->phone,
            ])
            ->all();
    }
}
