<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suppliers\SupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPaymentController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    /**
     * Supplier Payment Report: payments in the date range, plus the balances still owed.
     */
    public function index(Request $request): View
    {
        $payments = $this->filteredPayments($request);

        return view('suppliers.payments', [
            'payments' => (clone $payments)->paginate(Pagination::PER_PAGE)->withQueryString(),
            'paymentsTotal' => (float) (clone $payments)->reorder()->sum('amount'),
            'balances' => Supplier::query()->owing()->orderByDesc('balance')->orderBy('name')->get(),
            'search' => trim((string) $request->query('search')),
        ]);
    }

    /**
     * Download the filtered payments as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $payments = $this->filteredPayments($request);
        $range = DateRange::fromRequest($request);
        $filename = 'supplier-payments-'.$range['from']->toDateString().'-to-'.$range['to']->toDateString().'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Payment No.', 'Supplier', 'Amount', 'Method', 'Reference', 'Note', 'Balance Before', 'Balance After', 'Paid By', 'Date'], escape: '');

            foreach ($payments->lazy() as $payment) {
                fputcsv($output, [
                    $payment->payment_no,
                    $payment->supplier_name,
                    $payment->amount,
                    SupplierPayment::METHODS[$payment->method] ?? $payment->method,
                    $payment->reference,
                    $payment->note,
                    $payment->balance_before,
                    $payment->balance_after,
                    $payment->paid_by_name,
                    $payment->created_at->format('Y-m-d H:i'),
                ], escape: '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Record a payment to the supplier.
     */
    public function store(SupplierPaymentRequest $request, Supplier $supplier): JsonResponse
    {
        $payment = $this->suppliers->recordPayment($supplier, $request->validated(), $request->user());

        return response()->json([
            'message' => "Payment {$payment->payment_no} of ".Money::format($payment->amount).' recorded.',
        ], 201);
    }

    /**
     * Edit a recorded payment's amount, method, reference or note.
     */
    public function update(SupplierPaymentRequest $request, Supplier $supplier, SupplierPayment $payment): JsonResponse
    {
        $changes = $this->suppliers->updatePayment($payment, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "Payment {$payment->payment_no} updated.",
        ]);
    }

    /**
     * Payments in the From / To range (default last 30 days), searchable by payment number or supplier.
     *
     * @return Builder<SupplierPayment>
     */
    private function filteredPayments(Request $request): Builder
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return SupplierPayment::query()
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('payment_no', 'like', $pattern)
                ->orWhere('supplier_name', 'like', $pattern)))
            ->latest()
            ->latest('id');
    }
}
