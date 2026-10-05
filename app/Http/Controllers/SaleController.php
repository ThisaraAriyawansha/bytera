<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\EmailBillRequest;
use App\Http\Requests\Sales\StoreSaleCustomerRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Resources\CustomerResource;
use App\Mail\SaleReceiptMail;
use App\Models\Customer;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\SubCategory;
use App\Rules\StrictEmail;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $sales,
        private ShiftService $shifts,
    ) {}

    /**
     * The POS page: active products with Showroom stock, active services, the category filters and the
     * cashier's open shift.
     */
    public function index(Request $request): View
    {
        $shift = $this->shifts->openShiftFor($request->user());

        return view('sales.index', [
            'pos' => [
                'products' => Product::query()
                    ->where('active', true)
                    ->where('showroom_stock', '>', 0)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Product $product): array => $this->productCard($product))
                    ->all(),
                'services' => Service::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Service $service): array => [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'default_price' => (float) $service->default_price,
                        'custom_fields' => $service->custom_fields ?? [],
                    ])
                    ->all(),
                'mainCategories' => MainCategory::query()
                    ->with(['subCategories' => fn ($query) => $query->orderBy('name')])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (MainCategory $main): array => [
                        'id' => $main->id,
                        'name' => $main->name,
                        'subs' => $main->subCategories->map(fn (SubCategory $sub): array => ['id' => $sub->id, 'name' => $sub->name])->all(),
                    ])
                    ->all(),
                'shift' => $shift === null ? null : ShiftController::summary($shift),
                'urls' => [
                    'openShift' => route('shifts.store'),
                    'checkout' => route('sales.store'),
                    'batches' => route('api.products.batches', '__PRODUCT__'),
                    'units' => route('api.products.units', '__PRODUCT__'),
                    'customerSearch' => route('api.customers.search'),
                    'customerStore' => route('sales.customers.store'),
                    'jobSearch' => route('api.jobs.billable'),
                ],
            ],
        ]);
    }

    /**
     * Check out the cart. Returns the bill for the Sale Complete modal and the products' new Showroom stock.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->validated(), $request->user());
        $sale->load('items', 'customer');

        $products = Product::query()->whereKey($sale->items->pluck('product_id'))->get();

        return response()->json([
            'message' => "{$sale->invoice_no} saved.",
            'sale' => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'total_amount' => (float) $sale->total_amount,
                'customer_email' => $sale->customer_email,
                'emailUrl' => filled($sale->customer_email) ? route('sales.email', $sale) : null,
            ],
            'billHtml' => view('sales.bill', ['sale' => $sale, 'shop' => ShopSetting::current()])->render(),
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'showroom' => $product->active ? $product->showroom_stock : 0,
            ])->all(),
            'customer' => $sale->customer === null ? null : CustomerResource::make($sale->customer),
            'shift' => ShiftController::summary($sale->shift()->firstOrFail()),
        ], 201);
    }

    /**
     * Add a customer from the POS Select Customer modal.
     */
    public function storeCustomer(StoreSaleCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return response()->json(['message' => "{$customer->name} has been added.", 'customer' => CustomerResource::make($customer)], 201);
    }

    /**
     * Email the bill PDF (rendered in the browser) to the customer on the sale. The sale and its email address
     * are loaded here; only the PDF comes from the browser.
     */
    public function email(EmailBillRequest $request, Sale $sale): JsonResponse
    {
        $email = (string) $sale->customer_email;

        if (preg_match(StrictEmail::PATTERN, $email) !== 1) {
            throw ValidationException::withMessages([
                'email' => $email === '' ? 'This bill has no customer email address.' : "The customer's email address \"{$email}\" is not valid.",
            ]);
        }

        Mail::to($email)->send(new SaleReceiptMail($sale->load('items'), ShopSetting::current(), $request->pdfBytes()));

        return response()->json(['message' => "Bill emailed to {$email}."]);
    }

    /**
     * A product as the POS grid shows it.
     *
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => (float) $product->selling_price,
            'showroom' => $product->showroom_stock,
            'stores' => $product->stores_stock,
            'track_serial' => $product->track_serial,
            'warranty_months' => $product->warranty_months,
            'main_category_id' => $product->main_category_id,
            'sub_category_id' => $product->sub_category_id,
        ];
    }
}
