<?php

namespace App\Http\Controllers;

use App\Http\Requests\Warranty\ClaimWarrantyRequest;
use App\Models\Warranty;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarrantyController extends Controller
{
    /**
     * The Warranty page (SPEC §8.10): summary counts per computed status, status tabs, search by customer,
     * product or serial, and the paginated table (warranties ending soonest first).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Warranty::STATUSES) ? $status : null;

        $searched = fn (): Builder => Warranty::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('customer_name', 'like', $pattern)
                ->orWhere('product_name', 'like', $pattern)
                ->orWhere('serial_number', 'like', $pattern)));

        return view('warranty.index', [
            'warranties' => $searched()
                ->when($status !== null, fn (Builder $query) => $query->withComputedStatus($status))
                ->orderByRaw("case when status = 'claimed' then 1 else 0 end")
                ->orderBy('end_date')
                ->orderBy('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'counts' => collect(Warranty::STATUSES)->map(fn (array $meta, string $key): int => $searched()->withComputedStatus($key)->count()),
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Claim Warranty: mark it claimed with the note and the claim time. Only an active (or expiring) warranty
     * can be claimed.
     */
    public function claim(ClaimWarrantyRequest $request, Warranty $warranty): JsonResponse
    {
        $warranty = DB::transaction(function () use ($request, $warranty): Warranty {
            $warranty = Warranty::query()->whereKey($warranty->id)->lockForUpdate()->firstOrFail();

            if (! $warranty->isClaimable()) {
                throw ValidationException::withMessages([
                    'claim_note' => $warranty->status === 'claimed'
                        ? 'This warranty has already been claimed.'
                        : "This warranty expired on {$warranty->end_date->format('M j, Y')} and can't be claimed.",
                ]);
            }

            $warranty->update([
                'status' => 'claimed',
                'claim_note' => $request->validated('claim_note'),
                'claimed_at' => now(),
            ]);

            return $warranty;
        });

        $message = "Warranty claimed for {$warranty->product_name}".(filled($warranty->serial_number) ? " ({$warranty->serial_number})" : '').'.';

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }
}
