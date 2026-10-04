<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockOutRequest;
use App\Http\Requests\Stock\UpdateStockOutRequest;
use App\Http\Resources\StockOutResource;
use App\Models\Product;
use App\Models\StockOut;
use App\Services\StockOutService;
use App\Services\StockService;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockOutController extends Controller
{
    public function __construct(private StockOutService $stockOuts) {}

    /**
     * List stock outs in the From / To range (default last 30 days), searchable by number, recipient or staff.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return view('stock-out.index', [
            'stockOuts' => StockOut::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('stock_out_no', 'like', $pattern)
                    ->orWhere('recipient', 'like', $pattern)
                    ->orWhere('issued_by_name', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'canCreate' => $request->user()->can('stockOut.create'),
            'canEdit' => $request->user()->can('stockOut.edit'),
        ]);
    }

    /**
     * The New Stock Out page: products with stock in either location; the page filters by Issue From.
     */
    public function create(): View
    {
        return view('stock-out.create', [
            'products' => Product::query()
                ->where('total_stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'track_serial', 'stores_stock', 'showroom_stock'])
                ->map(fn (Product $product): array => [
                    'value' => $product->id,
                    'label' => $product->name,
                    'description' => $product->sku.($product->track_serial ? ' · Serial tracked' : ''),
                    'sku' => $product->sku,
                    'track_serial' => $product->track_serial,
                    'stock' => ['stores' => $product->stores_stock, 'showroom' => $product->showroom_stock],
                ])
                ->all(),
        ]);
    }

    /**
     * Issue the items from the chosen location.
     */
    public function store(StoreStockOutRequest $request): JsonResponse
    {
        $stockOut = $this->stockOuts->issue($request->validated(), $request->user());

        $message = "{$stockOut->stock_out_no} saved — stock issued from ".StockService::LOCATIONS[$stockOut->location].'.';

        session()->flash('status', $message);

        return response()->json(['message' => $message, 'redirect' => route('stock-out.index')], 201);
    }

    /**
     * Get a stock out with its items for the view modal.
     */
    public function show(StockOut $stockOut): JsonResponse
    {
        return response()->json(['record' => StockOutResource::make($stockOut->load('items'))]);
    }

    /**
     * Admin edit: recipient, reason, detail, note and job link only.
     */
    public function update(UpdateStockOutRequest $request, StockOut $stockOut): JsonResponse
    {
        $changes = $this->stockOuts->update($stockOut, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "{$stockOut->stock_out_no} updated.",
            'record' => StockOutResource::make($stockOut->fresh()->load('items')),
        ]);
    }
}
