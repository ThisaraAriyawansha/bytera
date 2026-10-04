<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Brand;
use App\Models\MainCategory;
use App\Models\Product;
use App\Services\StockService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * List products, searchable by name, SKU or barcode and filterable by brand, main category and status.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $brandId = $request->integer('brand') ?: null;
        $mainCategoryId = $request->integer('category') ?: null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $products = Product::query()
            ->with(['brand:id,name', 'mainCategory:id,name', 'subCategory:id,name'])
            ->withCount(['batches', 'batches as active_batches_count' => fn (Builder $query) => $query->where('status', 'active')])
            ->when($search !== '', fn (Builder $query) => $query->matching($search))
            ->when($brandId, fn (Builder $query) => $query->where('brand_id', $brandId))
            ->when($mainCategoryId, fn (Builder $query) => $query->where('main_category_id', $mainCategoryId))
            ->when($status, fn (Builder $query) => $query->where('active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'filters' => ['brand' => $brandId, 'category' => $mainCategoryId, 'status' => $status],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'mainCategories' => MainCategory::query()
                ->with(['subCategories' => fn ($query) => $query->orderBy('name')->select(['id', 'name', 'main_category_id'])])
                ->orderBy('name')
                ->get(['id', 'name']),
            'canEdit' => $request->user()->can('products.edit'),
            'canDelete' => $request->user()->can('products.delete'),
            'canEditBatches' => $request->user()->can('products.batch.edit'),
            'canDeleteUnits' => $request->user()->can('products.unit.delete'),
        ]);
    }

    /**
     * Add a product. Initial stock becomes its first batch in Stores, with one unit per serial for serial products.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::query()->create([
                ...$request->productAttributes(),
                'total_stock' => 0,
                'stores_stock' => 0,
                'showroom_stock' => 0,
                'low_stock_alerted' => false,
            ]);

            $initialStock = (int) $request->validated('initial_stock');

            if ($initialStock > 0) {
                $batch = $this->stock->receiveBatch(
                    $product,
                    'stores',
                    $initialStock,
                    $request->validated('cost_price'),
                    null,
                    'Initial stock',
                    $product->track_serial ? $request->validated('serials') : [],
                );

                $this->stock->recordMovement($product, 'in', $initialStock, 'batch_edit', $batch->id, 'Initial stock', $request->user(), [
                    'location' => 'stores',
                ]);
            }

            return $product;
        });

        $message = "Product \"{$product->name}\" added.";

        session()->flash('status', $message);

        return response()->json(['message' => $message], 201);
    }

    /**
     * Update a product's details. Stock is only changed through batches.
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->productAttributes());

        $message = "Product \"{$product->name}\" updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a product, together with its batches, units and movements, unless a bill or stock document uses it.
     */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('products.delete');

        $documentCount = $product->documentReferenceCount();

        if ($documentCount > 0) {
            return to_route('products.index')->with(
                'error',
                "\"{$product->name}\" appears on {$documentCount} ".str('bill or stock record')->plural($documentCount)
                    ." and can't be deleted. Mark it Inactive instead.",
            );
        }

        $product->delete();

        return to_route('products.index')->with('status', "Product \"{$product->name}\" deleted.");
    }
}
