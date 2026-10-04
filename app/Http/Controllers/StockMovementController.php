<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\StockOut;
use App\Support\DateRange;
use App\Support\Pagination;
use App\Support\StockMovementReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMovementController extends Controller
{
    /**
     * Read-only list of every stock change (SPEC §8.15), filtered by date range, type and search.
     */
    public function index(Request $request): View
    {
        $movements = $this->filteredMovements($request)->paginate(Pagination::PER_PAGE)->withQueryString();

        return view('stock-movements.index', [
            'movements' => $movements,
            'references' => StockMovementReferences::resolve($movements->items()),
            'search' => trim((string) $request->query('search')),
            'type' => $this->type($request),
        ]);
    }

    /**
     * Download the filtered movements as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $movements = $this->filteredMovements($request);
        $range = DateRange::fromRequest($request);
        $filename = 'stock-movements-'.$range['from']->toDateString().'-to-'.$range['to']->toDateString().'.csv';

        return response()->streamDownload(function () use ($movements): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Type', 'Product', 'SKU', 'Qty', 'Location', 'By', 'Recipient', 'Reason', 'Reason Detail', 'Job', 'Supplier', 'Reference', 'Note'], escape: '');

            foreach ($movements->lazy(500)->chunk(500) as $chunk) {
                $references = StockMovementReferences::resolve($chunk);

                foreach ($chunk as $movement) {
                    fputcsv($output, [
                        $movement->created_at->format('Y-m-d H:i'),
                        StockMovement::REFERENCE_TYPES[$movement->reference_type] ?? $movement->reference_type,
                        $movement->product?->name,
                        $movement->product?->sku,
                        $movement->qty,
                        $movement->locationLabel(),
                        $movement->performed_by_name,
                        $movement->recipient,
                        $movement->reason === null ? null : (StockOut::REASONS[$movement->reason] ?? $movement->reason),
                        $movement->reason_detail,
                        $movement->job_no,
                        $movement->supplier_name,
                        $references[$movement->id]['number'],
                        $movement->note,
                    ], escape: '');
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Movements in the From / To range (default last 30 days), of one type if chosen, matching the product
     * name / SKU or the document number.
     *
     * @return Builder<StockMovement>
     */
    private function filteredMovements(Request $request): Builder
    {
        $range = DateRange::fromRequest($request);
        $type = $this->type($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return StockMovement::query()
            ->with('product:id,name,sku')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->when($type !== null, fn (Builder $query) => $query->where('reference_type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($pattern): void {
                $query->whereHas('product', fn (Builder $query) => $query
                    ->where('name', 'like', $pattern)
                    ->orWhere('sku', 'like', $pattern));

                StockMovementReferences::orWhereNumberLike($query, $pattern);
            }))
            ->latest()
            ->latest('id');
    }

    /**
     * The chosen type filter, or null for all types.
     */
    private function type(Request $request): ?string
    {
        $type = $request->query('type');

        return is_string($type) && array_key_exists($type, StockMovement::REFERENCE_TYPES) ? $type : null;
    }
}
