<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\CloseShiftRequest;
use App\Http\Requests\Sales\OpenShiftRequest;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    /**
     * Open a shift for the signed-in cashier with an opening cash float.
     */
    public function store(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->open($request->user(), $request->validated('opening_float'), $request->validated('note'));

        return response()->json([
            'message' => "{$shift->shift_no} is open.",
            'shift' => self::summary($shift),
        ], 201);
    }

    /**
     * Close the cashier's own shift with the counted cash and return the Expected / Counted / Variance result.
     */
    public function close(CloseShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shifts->close($shift, $request->user(), $request->validated('counted_cash'), $request->validated('note'));

        return response()->json([
            'message' => "{$shift->shift_no} is closed.",
            'result' => [
                'shift_no' => $shift->shift_no,
                'expected_cash' => (float) $shift->expected_cash,
                'counted_cash' => (float) $shift->counted_cash,
                'variance' => (float) $shift->variance,
            ],
        ]);
    }

    /**
     * The open shift as the POS shift pill and Close Shift modal need it.
     *
     * @return array<string, mixed>
     */
    public static function summary(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'shift_no' => $shift->shift_no,
            'opening_float' => (float) $shift->opening_float,
            'cash_sales_total' => (float) $shift->cash_sales_total,
            'card_sales_total' => (float) $shift->card_sales_total,
            'transfer_sales_total' => (float) $shift->transfer_sales_total,
            'kokopay_sales_total' => (float) $shift->kokopay_sales_total,
            'cash_expenses_total' => (float) $shift->cash_expenses_total,
            'sales_count' => $shift->sales_count,
            'expected_cash' => ShiftService::expectedCash($shift),
            'opened_at' => $shift->opened_at->toIso8601String(),
            'closeUrl' => route('shifts.close', $shift),
        ];
    }
}
