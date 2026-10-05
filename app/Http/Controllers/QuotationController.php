<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quotations\StoreQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\ShopSetting;
use App\Services\QuotationService;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    /**
     * List quotations in the From / To range (default last 30 days), filtered by status and searchable by
     * quotation number, customer or phone. Also the product list for the New Quotation item search.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Quotation::STATUSES) ? $status : null;

        return view('quotations.index', [
            'quotations' => Quotation::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($status !== null, fn (Builder $query) => $query->withDisplayStatus($status))
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('quotation_no', 'like', $pattern)
                    ->orWhere('customer_name', 'like', $pattern)
                    ->orWhere('customer_phone', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'status' => $status,
            'canDelete' => $request->user()->can('quotations.delete'),
            'quotationConfig' => [
                'products' => Product::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'sku', 'barcode', 'selling_price'])
                    ->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'price' => (float) $product->selling_price,
                    ])
                    ->all(),
                'defaultValidUntil' => today()->addDays(14)->toDateString(),
                'urls' => ['store' => route('quotations.store')],
            ],
        ]);
    }

    /**
     * Save a New Quotation, then open it in the view modal.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $quotation = $this->quotations->create($request->validated(), $request->user());

        return response()->json([
            'message' => "{$quotation->quotation_no} saved.",
            'showUrl' => route('quotations.show', $quotation),
        ], 201);
    }

    /**
     * A quotation with its A4 print for the view modal.
     */
    public function show(Quotation $quotation): JsonResponse
    {
        return response()->json($this->viewPayload($quotation));
    }

    /**
     * Mark Accepted / Mark Rejected.
     */
    public function updateStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected'])]])['status'];

        $quotation = $this->quotations->setStatus($quotation, $status);

        return response()->json([
            'message' => "{$quotation->quotation_no} marked {$quotation->statusLabel()}.",
            ...$this->viewPayload($quotation),
        ]);
    }

    /**
     * Delete a quotation (`quotations.delete`).
     */
    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('quotations.delete');

        $quotation->delete();

        return back()->with('status', "{$quotation->quotation_no} has been deleted.");
    }

    /**
     * The view modal payload: the quotation and its rendered A4 print.
     *
     * @return array{quotation: array<string, mixed>, printHtml: string}
     */
    private function viewPayload(Quotation $quotation): array
    {
        $quotation = $quotation->fresh()->load('items');

        return [
            'quotation' => QuotationResource::make($quotation)->resolve(),
            'printHtml' => view('quotations.print', ['quotation' => $quotation, 'shop' => ShopSetting::current()])->render(),
        ];
    }
}
