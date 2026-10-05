<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\ForceCloseShiftRequest;
use App\Http\Requests\Finance\ReviewShiftRequest;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    /**
     * A shift for the Finance → Shifts view modal: every figure, the cash paid out of its drawer, force-close and
     * review info, and the sales made during it.
     */
    public function show(Request $request, Shift $shift): JsonResponse
    {
        return response()->json(['shift' => $this->payload($request, $shift)]);
    }

    /**
     * Force Close (Admin Override): Admin / Super Admin only, open shifts only.
     */
    public function forceClose(ForceCloseShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shifts->forceClose($shift, $request->user(), $request->validated('counted_cash'), $request->validated('note'));

        return response()->json([
            'message' => "{$shift->shift_no} has been force closed.",
            'shift' => $this->payload($request, $shift),
        ]);
    }

    /**
     * Review a closed shift: Approve or Flag with a note.
     */
    public function review(ReviewShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shifts->review($shift, $request->user(), $request->validated('decision'), $request->validated('note'));

        return response()->json([
            'message' => "{$shift->shift_no} ".($shift->review_status === 'approved' ? 'approved.' : 'flagged.'),
            'shift' => $this->payload($request, $shift),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, Shift $shift): array
    {
        $shift = $shift->fresh();
        $user = $request->user();
        $isOpen = $shift->status === 'open';

        return [
            'id' => $shift->id,
            'shift_no' => $shift->shift_no,
            'cashier_name' => $shift->cashier_name,
            'status' => $shift->status,
            'is_open' => $isOpen,
            'open_days' => $shift->openDays(),
            'opened_at' => $shift->opened_at->format('M j, Y g:i A'),
            'open_note' => $shift->open_note,
            'closed_at' => $shift->closed_at?->format('M j, Y g:i A'),
            'close_note' => $shift->close_note,
            'opening_float' => (float) $shift->opening_float,
            'cash_sales_total' => (float) $shift->cash_sales_total,
            'card_sales_total' => (float) $shift->card_sales_total,
            'transfer_sales_total' => (float) $shift->transfer_sales_total,
            'kokopay_sales_total' => (float) $shift->kokopay_sales_total,
            'sales_count' => $shift->sales_count,
            'cash_expenses_total' => (float) $shift->cash_expenses_total,
            'expected_cash' => $isOpen ? ShiftService::expectedCash($shift) : (float) $shift->expected_cash,
            'counted_cash' => $shift->counted_cash === null ? null : (float) $shift->counted_cash,
            'variance' => $shift->variance === null ? null : (float) $shift->variance,
            'force_closed' => $shift->force_closed,
            'closed_by_name' => $shift->closed_by_name,
            'review_status' => $shift->review_status,
            'review_label' => Shift::REVIEW_STATUSES[$shift->review_status]['label'] ?? null,
            'reviewed_at' => $shift->reviewed_at?->format('M j, Y g:i A'),
            'reviewed_by_name' => $shift->reviewed_by_name,
            'review_note' => $shift->review_note,
            'payouts' => $shift->expenses()->latest()->latest('id')->get()->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_no' => $expense->expense_no,
                'category' => $expense->categoryLabel(),
                'note' => $expense->note,
                'amount' => (float) $expense->amount,
                'time' => $expense->created_at->format('M j, g:i A'),
            ])->all(),
            'sales' => $shift->sales()->latest()->latest('id')->get()->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'customer_name' => $sale->customer_name,
                'payment_label' => $sale->paymentLabel(),
                'total_amount' => (float) $sale->total_amount,
                'is_cancelled' => $sale->status === 'cancelled',
                'time' => $sale->created_at->format('M j, g:i A'),
            ])->all(),
            'urls' => [
                'forceClose' => $isOpen && Permissions::isAdmin($user) ? route('finance.shifts.force-close', $shift) : null,
                'review' => ! $isOpen && $user->can('finance.reviewShift') ? route('finance.shifts.review', $shift) : null,
            ],
        ];
    }
}
