<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bills\ReverseBillRequest;
use App\Http\Requests\Bills\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Sale;
use App\Models\ShopSetting;
use App\Services\BillService;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillController extends Controller
{
    public function __construct(private BillService $bills) {}

    /**
     * List bills in the From / To range (default last 30 days), searchable by invoice number or customer.
     * Reversed bills stay listed, marked Cancelled.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return view('bills.index', [
            'bills' => Sale::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('invoice_no', 'like', $pattern)
                    ->orWhere('customer_name', 'like', $pattern)
                    ->orWhere('customer_phone', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'canEdit' => $request->user()->can('bills.edit'),
            'canReverse' => $request->user()->can('bills.cancel'),
        ]);
    }

    /**
     * A bill with its A4 print for the view modal.
     */
    public function show(Sale $sale): JsonResponse
    {
        return response()->json($this->viewPayload($sale));
    }

    /**
     * Edit Bill (`bills.edit`): customer details, note and payment method, audit logged.
     */
    public function update(UpdateBillRequest $request, Sale $sale): JsonResponse
    {
        $changes = $this->bills->update($sale, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "{$sale->invoice_no} updated.",
            ...$this->viewPayload($sale),
        ]);
    }

    /**
     * Reverse Bill (`bills.cancel`): cancel the sale and put stock, points and shift totals back.
     */
    public function reverse(ReverseBillRequest $request, Sale $sale): JsonResponse
    {
        $this->bills->reverse($sale, $request->validated('reason'), $request->user());

        return response()->json([
            'message' => "{$sale->invoice_no} has been reversed.",
            ...$this->viewPayload($sale),
        ]);
    }

    /**
     * The view modal payload: the bill and its rendered A4 print.
     *
     * @return array{bill: array<string, mixed>, billHtml: string}
     */
    private function viewPayload(Sale $sale): array
    {
        $sale = $sale->fresh()->load('items');

        return [
            'bill' => BillResource::make($sale)->resolve(),
            'billHtml' => view('sales.bill', ['sale' => $sale, 'shop' => ShopSetting::current()])->render(),
        ];
    }
}
