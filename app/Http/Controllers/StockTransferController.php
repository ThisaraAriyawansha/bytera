<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockTransferRequest;
use App\Http\Requests\Stock\UpdateStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(private StockTransferService $transfers) {}

    /**
     * List transfers in the From / To range (default last 30 days), searchable by number or staff.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return view('stock-transfer.index', [
            'transfers' => StockTransfer::query()
                ->withSum('items as total_qty', 'qty')
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('transfer_no', 'like', $pattern)
                    ->orWhere('transferred_by_name', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'canCreate' => $request->user()->can('stockTransfer.create'),
            'canEdit' => $request->user()->can('stockTransfer.edit'),
        ]);
    }

    /**
     * The New Transfer page: only products with stock in Stores can be picked.
     */
    public function create(): View
    {
        return view('stock-transfer.create', [
            'products' => Product::query()
                ->where('stores_stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'track_serial', 'stores_stock', 'showroom_stock'])
                ->map(fn (Product $product): array => [
                    'value' => $product->id,
                    'label' => $product->name,
                    'description' => "{$product->sku} · Stores {$product->stores_stock}".($product->track_serial ? ' · Serial tracked' : ''),
                    'sku' => $product->sku,
                    'track_serial' => $product->track_serial,
                    'stock' => ['stores' => $product->stores_stock, 'showroom' => $product->showroom_stock],
                ])
                ->all(),
        ]);
    }

    /**
     * Move the items from Stores to Showroom.
     */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->transfer($request->validated(), $request->user());

        $message = "{$transfer->transfer_no} saved — stock moved from Stores to Showroom.";

        session()->flash('status', $message);

        return response()->json(['message' => $message, 'redirect' => route('stock-transfer.index')], 201);
    }

    /**
     * Get a transfer with its items for the view modal.
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json(['record' => StockTransferResource::make($stockTransfer->load('items'))]);
    }

    /**
     * Admin edit: the note only.
     */
    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $changes = $this->transfers->update($stockTransfer, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "{$stockTransfer->transfer_no} updated.",
            'record' => StockTransferResource::make($stockTransfer->fresh()->load('items')),
        ]);
    }
}
