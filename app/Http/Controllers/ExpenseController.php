<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    /**
     * Add Expense (`finance.addExpense`), optionally paid from an open shift's cash drawer.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user());

        $message = "{$expense->expense_no} of ".Money::format($expense->amount).' added'.($expense->shift_no ? " — paid from {$expense->shift_no}'s drawer." : '.');

        session()->flash('status', $message);

        return response()->json(['message' => $message], 201);
    }

    /**
     * Delete an expense (`finance.deleteExpense`); a still-open drawer shift gets the cash back.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('finance.deleteExpense');

        try {
            $this->expenses->delete($expense);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('status', "{$expense->expense_no} has been deleted.");
    }
}
