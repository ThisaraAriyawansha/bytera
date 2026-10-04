<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suppliers\SupplierRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Http\Resources\SupplierResource;
use App\Mail\SupplierStatementMail;
use App\Models\ShopSetting;
use App\Models\Supplier;
use App\Rules\StrictEmail;
use App\Support\Pagination;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * List suppliers with an outstanding summary on top and a name / phone search on the main table.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $user = $request->user();

        return view('suppliers.index', [
            'outstanding' => Supplier::query()
                ->owing()
                ->withMin('grns', 'created_at')
                ->orderByDesc('balance')
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()
                ->when($search !== '', fn (Builder $query) => $query->matching($search))
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'search' => $search,
            'canEditContact' => $user->can('suppliers.editContact'),
            'canRecordPayment' => $user->can('suppliers.recordPayment'),
            'canEditPayment' => $user->can('suppliers.editPayment'),
            'canSendStatement' => $this->maySendStatement($request),
        ]);
    }

    /**
     * Add a supplier. A new supplier owes nothing until a GRN is received from them.
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create([
            ...$request->validated(),
            'total_payable' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'payment_status' => 'paid',
        ]);

        $message = "{$supplier->name} has been added.";

        session()->flash('status', $message);

        return response()->json(['message' => $message, 'supplier' => SupplierResource::make($supplier)], 201);
    }

    /**
     * Get a supplier's contact, totals and payment history for the view modal.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'supplier' => SupplierResource::make($supplier),
            'payments' => SupplierPaymentResource::collection($supplier->payments()->latest()->latest('id')->get()),
        ]);
    }

    /**
     * Update a supplier's contact details. Balances are only changed by GRNs and payments.
     */
    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        $message = "{$supplier->name} has been updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Email the supplier their account statement (total payable, paid, balance).
     */
    public function sendStatement(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($this->maySendStatement($request), 403);

        $email = (string) $supplier->email;

        if (preg_match(StrictEmail::PATTERN, $email) !== 1) {
            throw ValidationException::withMessages([
                'email' => $email === ''
                    ? 'This supplier has no email address. Add one with Edit first.'
                    : "The supplier's email address \"{$email}\" is not valid.",
            ]);
        }

        Mail::to($email)->send(new SupplierStatementMail($supplier, ShopSetting::current()));

        $supplier->update(['last_statement_sent_at' => now()]);

        return response()->json(['message' => "Statement sent to {$email}."]);
    }

    /**
     * Sending a statement needs the permission and, on the server, an Admin or Super Admin (SPEC §4.4).
     */
    private function maySendStatement(Request $request): bool
    {
        return Gate::allows('suppliers.sendStatement') && Permissions::isAdmin($request->user());
    }
}
