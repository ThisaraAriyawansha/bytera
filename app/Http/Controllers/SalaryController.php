<?php

namespace App\Http\Controllers;

use App\Http\Requests\Salary\IssueSalaryRequest;
use App\Http\Requests\Salary\UpdateSalarySetupRequest;
use App\Mail\SalaryPayslipMail;
use App\Models\SalaryPayment;
use App\Models\Shift;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use App\Services\SalaryService;
use App\Services\ShiftService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SalaryController extends Controller
{
    /**
     * The Salary tabs, in order (SPEC §8.20).
     *
     * @var array<string, string>
     */
    public const TABS = [
        'issue' => 'Issue Payment',
        'history' => 'Payment History',
        'setup' => 'Employee Setup',
    ];

    public function __construct(private SalaryService $salaries) {}

    /**
     * The Salary page with the chosen tab. Payment History defaults to the last 30 days.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canIssue = $user->can('salary.issue');
        $tab = $request->query('tab');
        $tab = is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : ($canIssue ? 'issue' : 'history');

        $data = match ($tab) {
            'issue' => ['issueConfig' => [
                'employees' => SalaryService::employees()->map(fn (User $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name ?: $employee->email,
                    'role' => $employee->role,
                    'salary_type' => $employee->salary_type,
                    'monthly_amount' => $employee->salary_monthly_amount === null ? null : (float) $employee->salary_monthly_amount,
                    'commission_percent' => $employee->salary_commission_percent === null ? null : (float) $employee->salary_commission_percent,
                    'setup_label' => $employee->salarySetupLabel(),
                ])->all(),
                'openShifts' => Shift::query()->where('status', 'open')->orderBy('opened_at')->get()->map(fn (Shift $shift): array => [
                    'id' => $shift->id,
                    'label' => "{$shift->shift_no} · {$shift->cashier_name}",
                    'expected_cash' => ShiftService::expectedCash($shift),
                ])->all(),
                'urls' => [
                    'store' => route('salary.store'),
                    'commissionItems' => route('salary.commission-items'),
                    'history' => route('salary.index', ['tab' => 'history']),
                ],
            ]],
            'history' => $this->history($request),
            'setup' => ['employees' => SalaryService::employees()],
        };

        return view('salary.index', [
            'tab' => $tab,
            'canIssue' => $canIssue,
            'canManageConfig' => $user->can('salary.manageConfig'),
            'canDelete' => $user->can('salary.delete'),
            ...$data,
        ]);
    }

    /**
     * Employee Setup (`salary.manageConfig`): the pre-fill defaults for Issue Payment, or "Clear".
     */
    public function updateSetup(UpdateSalarySetupRequest $request, User $user): JsonResponse
    {
        $user = $this->salaries->updateSetup($user, $request->validated());

        $message = "Salary setup for {$user->name}: {$user->salarySetupLabel()}.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * The commission picker: unclaimed sales and jobs matching the search.
     */
    public function commissionItems(Request $request): JsonResponse
    {
        Gate::authorize('salary.issue');

        return response()->json(['data' => $this->salaries->commissionCandidates((string) $request->query('q'))]);
    }

    /**
     * Issue Payment (`salary.issue`): the SAL- payment and its linked salaries expense.
     */
    public function store(IssueSalaryRequest $request): JsonResponse
    {
        $payment = $this->salaries->issue($request->validated(), $request->user());

        $message = "{$payment->payment_no} of ".Money::format($payment->amount)." issued to {$payment->user_name}"
            .($payment->shift_no ? " — paid from {$payment->shift_no}'s drawer." : '.');

        session()->flash('status', $message);

        return response()->json([
            'message' => $message,
            'redirect' => route('salary.index', ['tab' => 'history']),
        ], 201);
    }

    /**
     * A payment for the view modal: how the amount was calculated, the linked sales / jobs and the note.
     */
    public function show(SalaryPayment $salaryPayment): JsonResponse
    {
        return response()->json(['payment' => $this->payload($salaryPayment)]);
    }

    /**
     * Email the payslip to the employee. The payment and the address are loaded here, never taken from the browser.
     */
    public function email(SalaryPayment $salaryPayment): JsonResponse
    {
        $email = (string) $salaryPayment->user?->email;

        if (! StrictEmail::isValid($email)) {
            throw ValidationException::withMessages([
                'email' => $email === '' ? 'This employee has no email address.' : "The employee's email address \"{$email}\" is not valid.",
            ]);
        }

        try {
            Mail::to($email)->send(new SalaryPayslipMail($salaryPayment, ShopSetting::current()));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['email' => 'The email could not be sent. Please try again.']);
        }

        $salaryPayment->update(['email_sent_at' => now()]);

        return response()->json([
            'message' => "Payslip sent to {$email}.",
            'payment' => $this->payload($salaryPayment),
        ]);
    }

    /**
     * Delete a payment (`salary.delete`): its expense goes, linked sales / jobs are freed and a still-open drawer
     * shift gets the cash back.
     */
    public function destroy(SalaryPayment $salaryPayment): RedirectResponse
    {
        Gate::authorize('salary.delete');

        $shiftRestored = $this->salaries->delete($salaryPayment);

        return to_route('salary.index', ['tab' => 'history'])->with(
            'status',
            "{$salaryPayment->payment_no} has been deleted"
                .($shiftRestored ? ' and '.Money::format($salaryPayment->amount)." put back into {$salaryPayment->shift_no}'s drawer." : '.'),
        );
    }

    /**
     * Payment History: payments in the date range, searchable by payment number or employee.
     *
     * @return array{payments: mixed, search: string}
     */
    private function history(Request $request): array
    {
        $range = DateRange::fromRequest($request);
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return [
            'payments' => SalaryPayment::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('payment_no', 'like', $pattern)
                    ->orWhere('user_name', 'like', $pattern)
                    ->orWhere('period_label', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SalaryPayment $payment): array
    {
        $payment = $payment->fresh(['user', 'linkedExpense']);

        return [
            'id' => $payment->id,
            'payment_no' => $payment->payment_no,
            'user_name' => $payment->user_name,
            'user_role' => $payment->user_role,
            'user_email' => $payment->user?->email,
            'type' => $payment->type,
            'type_label' => $payment->typeLabel(),
            'amount' => (float) $payment->amount,
            'commission_base' => $payment->commission_base === null ? null : (float) $payment->commission_base,
            'commission_percent' => $payment->commission_percent === null ? null : (float) $payment->commission_percent,
            'calculation' => SalaryService::calculationLines($payment),
            'items' => $payment->commission_items ?? [],
            'period_label' => $payment->period_label,
            'note' => $payment->note,
            'issued_by_name' => $payment->issued_by_name,
            'date' => $payment->created_at->format('M j, Y g:i A'),
            'expense_no' => $payment->linkedExpense?->expense_no,
            'shift_no' => $payment->shift_no,
            'email_sent_at' => $payment->email_sent_at?->format('M j, Y g:i A'),
            'urls' => [
                'email' => route('salary.email', $payment),
            ],
        ];
    }
}
