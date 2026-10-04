@php
    use App\Models\Sale;
    use Illuminate\Support\HtmlString;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<x-layouts.app>
    <div class="flex flex-col lg:h-full lg:flex-row" x-data="posCart(@js($pos))">

        {{-- ═══ Left: catalogue ═══ --}}
        <section class="flex min-w-0 flex-1 flex-col p-4 sm:p-6 lg:overflow-hidden">
            <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <h1 class="font-prata text-2xl text-ink">New Sale</h1>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- "Find Job to Bill" (SPEC §8.4) goes here. --}}

                    <button type="button" x-show="! shift" x-cloak x-on:click="startOpenShift()"
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100">
                        <x-lucide-lock class="h-3.5 w-3.5" /> No open shift — tap to open
                    </button>

                    <div x-show="shift" x-cloak class="inline-flex flex-wrap items-center gap-x-3 gap-y-1 rounded-full border border-zinc-200 bg-zinc-100 py-1 pl-3 pr-1 text-xs text-zinc-600">
                        <span class="font-semibold text-ink" x-text="shift?.shift_no"></span>
                        <span class="tabular-nums">Cash <span x-text="money(shift?.cash_sales_total)"></span> · Card <span x-text="money(shift?.card_sales_total)"></span></span>
                        <button type="button" class="rounded-full bg-white px-2.5 py-1 font-medium text-ink shadow-sm hover:text-brand" x-on:click="startCloseShift()">Close Shift</button>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="mb-4 flex gap-6 border-b border-zinc-200" role="tablist">
                @foreach (['products' => 'Products', 'services' => 'Services'] as $tab => $tabLabel)
                    <button type="button" role="tab" class="-mb-px inline-flex items-center gap-2 border-b-2 pb-2.5 text-sm font-medium transition-colors"
                            x-bind:aria-selected="tab === @js($tab)"
                            x-bind:class="tab === @js($tab) ? 'border-brand text-brand' : 'border-transparent text-zinc-500 hover:text-ink'"
                            x-on:click="tab = @js($tab)">
                        {{ $tabLabel }}
                        <span class="rounded-full px-1.5 text-[11px] tabular-nums"
                              x-bind:class="tab === @js($tab) ? 'bg-brand text-white' : 'bg-zinc-100 text-zinc-500'"
                              x-text="@js($tab) === 'products' ? listedProducts.length : services.length"></span>
                    </button>
                @endforeach
            </div>

            <p x-show="notice" x-text="notice" x-cloak x-transition.opacity class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status"></p>

            {{-- Products tab --}}
            <div x-show="tab === 'products'" class="flex min-h-0 flex-1 flex-col">
                <div class="mb-4 grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                    <div class="relative">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input type="search" class="nexora-input pl-9" placeholder="Search product by name, SKU or barcode…" autocomplete="off" autofocus
                               x-model="search" x-on:keydown.enter.prevent="scanSearch()" aria-label="Search products">
                    </div>
                    <select class="nexora-input sm:w-44" x-model="mainCategoryId" aria-label="Main category">
                        <option value="">All categories</option>
                        <template x-for="main in mainCategories" :key="main.id">
                            <option :value="main.id" x-text="main.name"></option>
                        </template>
                    </select>
                    <select class="nexora-input disabled:bg-zinc-50 disabled:text-zinc-400 sm:w-44" x-model="subCategoryId" x-bind:disabled="! mainCategoryId" aria-label="Sub category">
                        <option value="">All subcategories</option>
                        <template x-for="sub in subCategories" :key="sub.id">
                            <option :value="sub.id" x-text="sub.name"></option>
                        </template>
                    </select>
                    <button type="button" class="nexora-btn nexora-btn-outline justify-center" x-on:click="clearFilters()">Clear</button>
                </div>

                <div class="min-h-0 flex-1 lg:overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <button type="button" class="nexora-card flex flex-col p-3 text-left transition hover:border-brand disabled:opacity-60"
                                    x-on:click="pickProduct(product)" x-bind:disabled="batchLoading && batchProduct?.id === product.id">
                                <span class="line-clamp-2 text-sm font-medium text-ink" x-text="product.name"></span>
                                <span class="text-xs text-zinc-400" x-text="product.sku"></span>
                                <span class="mt-auto flex items-end justify-between gap-2 pt-3">
                                    <span class="font-prata text-sm text-ink" x-text="money(product.price)"></span>
                                    <span class="badge" x-bind:class="product.showroom <= 5 ? 'badge-warning' : 'badge-default'" x-text="`${product.showroom} in stock`"></span>
                                </span>
                            </button>
                        </template>
                    </div>

                    <div x-show="filteredProducts.length === 0" x-cloak>
                        <x-empty-state icon="package-search" title="No products"
                                       message="Only active products with Showroom stock are listed. Transfer stock from Stores to sell more." />
                    </div>
                </div>
            </div>

            {{-- Services tab --}}
            <div x-show="tab === 'services'" x-cloak class="flex min-h-0 flex-1 flex-col">
                <div class="relative mb-4 max-w-md">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input type="search" class="nexora-input pl-9" placeholder="Search service by name…" autocomplete="off" x-model="serviceSearch" aria-label="Search services">
                </div>

                <div class="min-h-0 flex-1 lg:overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        <template x-for="service in filteredServices" :key="service.id">
                            <button type="button" class="nexora-card flex flex-col p-3 text-left transition hover:border-brand" x-on:click="openService(service)">
                                <span class="text-sm font-medium text-ink" x-text="service.name"></span>
                                <span class="line-clamp-2 text-xs text-zinc-400"
                                      x-text="service.description || (service.custom_fields ?? []).map((field) => field.label).join(', ')"></span>
                                <span class="mt-auto flex items-end justify-between gap-2 pt-3">
                                    <span class="font-prata text-sm text-ink" x-text="money(service.default_price)"></span>
                                    <span x-show="serviceCount(service.id) > 0" class="badge badge-info" x-text="`${serviceCount(service.id)} in bill`"></span>
                                </span>
                            </button>
                        </template>
                    </div>

                    <div x-show="filteredServices.length === 0" x-cloak>
                        <x-empty-state icon="hammer" title="No services" message="Active services from the Services page appear here." />
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══ Right: cart ═══ --}}
        <aside class="flex w-full flex-col border-t border-zinc-200 bg-white lg:h-full lg:w-96 lg:shrink-0 lg:border-l lg:border-t-0">
            <div class="flex-1 space-y-4 p-4 lg:overflow-y-auto">

                {{-- 1. Customer --}}
                <div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-left text-sm hover:border-brand" x-on:click="openCustomerPicker()">
                            <x-lucide-user class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="truncate" x-show="! customer">Walk-in customer (tap to select)</span>
                            <span class="truncate font-medium text-ink" x-show="customer" x-cloak x-text="customer ? `${customer.name} · ${customer.phone}` : ''"></span>
                        </button>
                        <button type="button" x-show="customer" x-cloak class="text-zinc-400 hover:text-brand" x-on:click="clearCustomer()" aria-label="Remove customer">
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500" x-show="customer" x-cloak>
                        <span class="font-medium text-purple-700" x-text="`${customer?.loyalty_points ?? 0} pts available`"></span> · Earns 1 pt per Rs. 100 spent
                    </p>
                    <p class="mt-1 text-xs text-zinc-500" x-show="! customer">Select a customer to earn 1% loyalty reward</p>
                </div>

                {{-- 2. Attached job (Find Job to Bill) renders here. --}}

                {{-- 3. Lines --}}
                <div class="space-y-2">
                    <template x-for="line in serviceLines" :key="'s' + line.key">
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink" x-text="line.name"></p>
                                    <p class="text-xs text-zinc-400">Service<span x-show="line.summary" x-text="' · ' + line.summary"></span></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" class="text-zinc-400 hover:text-brand" x-on:click="editServiceLine(line)" aria-label="Edit service">
                                        <x-lucide-pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button type="button" class="text-zinc-400 hover:text-brand" x-on:click="removeServiceLine(line)" aria-label="Remove service">
                                        <x-lucide-x class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <p class="mt-1 text-right text-sm font-medium tabular-nums text-ink" x-text="money(price(line.basePrice))"></p>
                        </div>
                    </template>

                    <template x-for="line in lines" :key="'p' + line.key">
                        <div class="rounded-md border border-zinc-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink" x-text="line.name"></p>
                                    <p class="break-words text-xs text-zinc-400" x-text="lineDetail(line)"></p>
                                </div>
                                <button type="button" class="shrink-0 text-zinc-400 hover:text-brand" x-on:click="removeLine(line)" aria-label="Remove line">
                                    <x-lucide-x class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="inline-flex items-center rounded-md border border-zinc-200">
                                    <button type="button" class="px-2 py-1 text-zinc-500 hover:text-brand" x-on:click="decrease(line)" aria-label="Decrease quantity">
                                        <x-lucide-minus class="h-3.5 w-3.5" />
                                    </button>
                                    <span class="w-8 text-center text-sm tabular-nums" x-text="line.qty"></span>
                                    <button type="button" class="px-2 py-1 text-zinc-500 hover:text-brand" x-on:click="increase(line)" aria-label="Increase quantity">
                                        <x-lucide-plus class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <span class="text-xs text-zinc-400">× <span x-text="money(price(line.basePrice))"></span></span>
                                <span class="ml-auto text-sm font-medium tabular-nums text-ink" x-text="money(lineTotalCents(line) / 100)"></span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <label class="text-xs text-zinc-500" :for="'discount-' + line.key">Discount</label>
                                <input type="number" min="0" step="0.01" class="nexora-input !py-1 !text-xs" placeholder="0 per unit" :id="'discount-' + line.key" x-model="line.discount">
                            </div>
                            <p x-show="lineDiscountError(line)" x-text="lineDiscountError(line)" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                    </template>

                    <div x-show="cartIsEmpty" class="flex flex-col items-center py-8 text-center">
                        <x-lucide-shopping-cart class="mb-2 h-8 w-8 text-zinc-300" />
                        <p class="text-sm font-medium text-ink">Cart is empty</p>
                        <p class="text-xs text-zinc-400">Tap a product or service to add</p>
                    </div>
                </div>

                {{-- 4. Totals --}}
                <div class="space-y-2 border-t border-zinc-200 pt-3 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500">Subtotal</span><span class="tabular-nums" x-text="money(subtotalCents / 100)"></span></div>
                    <div class="flex items-center justify-between gap-3">
                        <label for="pos-bill-discount" class="text-zinc-500">Bill Discount</label>
                        <input id="pos-bill-discount" type="number" min="0" step="0.01" class="nexora-input !w-32 !py-1 text-right" placeholder="0" x-model="billDiscount" x-bind:max="subtotalCents / 100">
                    </div>
                    <div class="flex items-center justify-between gap-3" x-show="customer && customer.loyalty_points > 0" x-cloak>
                        <label for="pos-redeem-points" class="text-zinc-500">Redeem Points <span class="text-xs text-zinc-400" x-text="`(max ${maxPoints})`"></span></label>
                        <input id="pos-redeem-points" type="number" min="0" step="1" class="nexora-input !w-32 !py-1 text-right" placeholder="0" x-model="redeemPoints" x-bind:max="maxPoints">
                    </div>
                    <div class="flex items-baseline justify-between pt-1">
                        <span class="font-medium text-ink">Total</span>
                        <span class="font-prata text-2xl tabular-nums text-ink" x-text="money(totalCents / 100)"></span>
                    </div>
                    <p x-show="charge.method" x-cloak class="rounded-md bg-zinc-50 px-2 py-1.5 text-[11px] leading-snug text-zinc-500">
                        Item prices above include a <span x-text="charge.percent"></span>% <span x-text="methodLabels[charge.method]"></span> surcharge (<span x-text="money(chargeAmountCents / 100)"></span>) — staff view only, not shown on the customer's bill
                        <button type="button" class="font-medium text-brand hover:underline" x-on:click="openCharge(charge.method)">Edit %</button>
                    </p>
                </div>

                {{-- 5–7. Payment --}}
                <div class="space-y-3 border-t border-zinc-200 pt-3">
                    <div class="grid grid-cols-4 gap-1.5">
                        @foreach (Sale::PAYMENT_METHODS as $method => $methodLabel)
                            <button type="button" class="rounded-md border px-2 py-2 text-xs font-medium transition-colors"
                                    @if ($method === 'kokopay') x-show="! isSplit" @endif
                                    x-bind:class="hasMethod(@js($method)) ? 'border-ink bg-ink text-white' : 'border-zinc-200 text-zinc-600 hover:border-ink'"
                                    x-bind:aria-pressed="hasMethod(@js($method))"
                                    x-on:click="toggleMethod(@js($method))">{{ $methodLabel }}</button>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-zinc-400" x-show="! isSplit && ! hasMethod('kokopay')">Select more than one method to split the payment.</p>

                    <button type="button" x-show="hasMethod('card') && ! cardPercent" x-cloak class="text-xs font-medium text-brand hover:underline" x-on:click="openCharge('card')">+ Add card charge %</button>

                    {{-- Cash only: tendered & change --}}
                    <div x-show="! isSplit && hasMethod('cash')" class="space-y-1">
                        <div class="flex items-center justify-between gap-3">
                            <label for="pos-tendered" class="text-sm text-zinc-500">Amount tendered</label>
                            <input id="pos-tendered" type="number" min="0" step="0.01" class="nexora-input !w-36 !py-1 text-right" x-bind:placeholder="totalCents / 100" x-model="tendered">
                        </div>
                        <p x-show="changeCents !== null && changeCents >= 0" x-cloak class="text-right text-sm font-medium text-green-700">Change: <span x-text="money(changeCents / 100)"></span></p>
                    </div>

                    {{-- Split legs --}}
                    <div x-show="isSplit" x-cloak class="space-y-2">
                        <template x-for="method in methods" :key="method">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-sm text-zinc-500" :for="'leg-' + method" x-text="methodLabels[method]"></label>
                                    <input type="number" min="0" step="0.01" class="nexora-input !w-36 !py-1 text-right" :id="'leg-' + method"
                                           x-show="method !== 'card'" x-model="legs[method]">
                                    <span x-show="method === 'card'" class="w-36 rounded border border-zinc-200 bg-zinc-50 px-3 py-1 text-right text-sm tabular-nums" x-text="money(legCents('card') / 100)"></span>
                                </div>
                                <p x-show="method === 'card' && charge.method === 'card'" class="mt-0.5 text-right text-[11px] text-zinc-500"
                                   x-text="`(${money(charge.cardBase ?? 0)} + ${charge.percent}% — swipe this)`"></p>
                            </div>
                        </template>
                        <p class="text-right text-sm font-medium">
                            <span x-show="splitDifferenceCents === 0" class="text-green-700">Balanced</span>
                            <span x-show="splitDifferenceCents > 0" class="text-red-600" x-text="`Remaining: ${money(splitDifferenceCents / 100)}`"></span>
                            <span x-show="splitDifferenceCents < 0" class="text-red-600" x-text="`Over by ${money(-splitDifferenceCents / 100)}`"></span>
                        </p>
                    </div>

                    {{-- 8. Points preview --}}
                    <p x-show="customer" x-cloak class="text-xs text-zinc-500">
                        Points after this sale: <span class="font-medium text-purple-700" x-text="`${pointsAfter} pts`"></span> <span x-text="`(+${pointsEarned})`"></span>
                    </p>
                </div>
            </div>

            {{-- 9–10. Checkout --}}
            <div class="space-y-2 border-t border-zinc-200 p-4">
                <p x-show="! shift" x-cloak class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Open a shift above before checking out.</p>
                <template x-if="errorMessages.length > 0">
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
                        <template x-for="message in errorMessages" :key="message"><p x-text="message"></p></template>
                    </div>
                </template>
                <button type="button" class="nexora-btn nexora-btn-primary w-full justify-center !py-3 !text-base disabled:cursor-not-allowed disabled:opacity-50"
                        x-bind:disabled="checkoutBlocker !== '' || processing" x-bind:title="checkoutBlocker" x-on:click="checkout()">
                    <span x-text="processing ? 'Processing…' : `Checkout — ${money(totalCents / 100)}`">Checkout</span>
                </button>
            </div>
        </aside>

        {{-- ═══ Modals ═══ --}}

        <x-modal name="pos-open-shift" title="Open Shift" max-width="sm" x-model="openShiftModal">
            <form class="space-y-4" x-on:submit.prevent="openShift()">
                <div>
                    <label for="shift-float" class="{{ $labelClasses }}">Opening cash float *</label>
                    <input id="shift-float" type="number" min="0" step="0.01" class="nexora-input" placeholder="0" autofocus x-model="openShiftForm.opening_float">
                    <p x-show="shiftErrors.opening_float" x-text="shiftErrors.opening_float" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="shift-open-note" class="{{ $labelClasses }}">Note</label>
                    <input id="shift-open-note" type="text" class="nexora-input" maxlength="500" placeholder="e.g. Morning shift" x-model="openShiftForm.note">
                </div>
                <p x-show="shiftErrors.shift || shiftErrors.form" x-text="shiftErrors.shift || shiftErrors.form" x-cloak class="{{ $errorClasses }}"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="openShiftModal = false">Cancel</button>
                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="shiftSaving">
                        <x-lucide-lock-open class="h-4 w-4" /> <span x-text="shiftSaving ? 'Opening…' : 'Open Shift'"></span>
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal name="pos-close-shift" max-width="sm" x-model="closeShiftModal"
                 :title="new HtmlString('Close Shift — <span x-text=&quot;shift?.shift_no&quot;></span>')">
            <form class="space-y-4" x-on:submit.prevent="closeShift()">
                <div>
                    <label for="shift-counted" class="{{ $labelClasses }}">Counted cash *</label>
                    <input id="shift-counted" type="number" min="0" step="0.01" class="nexora-input" placeholder="Cash in the drawer" autofocus x-model="closeShiftForm.counted_cash">
                    <p x-show="shiftErrors.counted_cash" x-text="shiftErrors.counted_cash" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="shift-close-note" class="{{ $labelClasses }}">Note</label>
                    <input id="shift-close-note" type="text" class="nexora-input" maxlength="500" placeholder="e.g. Rs. 200 short — coin drawer miscount" x-model="closeShiftForm.note">
                </div>
                <p x-show="shiftErrors.shift || shiftErrors.form" x-text="shiftErrors.shift || shiftErrors.form" x-cloak class="{{ $errorClasses }}"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="closeShiftModal = false">Cancel</button>
                    <button type="submit" class="nexora-btn nexora-btn-danger disabled:opacity-60" x-bind:disabled="shiftSaving">
                        <span x-text="shiftSaving ? 'Closing…' : 'Close Shift'"></span>
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal name="pos-shift-result" title="Shift Closed" max-width="sm" x-model="shiftResultModal">
            <template x-if="shiftResult">
                <div class="space-y-2 text-sm">
                    <p class="text-zinc-500"><span class="font-medium text-ink" x-text="shiftResult.shift_no"></span> is closed and waiting for review.</p>
                    <div class="flex justify-between border-b border-zinc-100 py-1.5"><span class="text-zinc-500">Expected</span><span class="tabular-nums" x-text="money(shiftResult.expected_cash)"></span></div>
                    <div class="flex justify-between border-b border-zinc-100 py-1.5"><span class="text-zinc-500">Counted</span><span class="tabular-nums" x-text="money(shiftResult.counted_cash)"></span></div>
                    <div class="flex justify-between py-1.5 font-medium">
                        <span>Variance</span>
                        <span class="tabular-nums" x-bind:class="shiftResult.variance < 0 ? 'text-red-600' : (shiftResult.variance > 0 ? 'text-green-700' : 'text-ink')"
                              x-text="`${shiftResult.variance > 0 ? '+' : (shiftResult.variance < 0 ? '−' : '')}${money(Math.abs(shiftResult.variance))} · ${varianceLabel(shiftResult.variance)}`"></span>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="shiftResultModal = false">Done</button>
                    </div>
                </div>
            </template>
        </x-modal>

        <x-modal name="pos-batch" max-width="lg" x-model="batchModal"
                 :title="new HtmlString('<span x-text=&quot;batchProduct?.name ?? \'Choose a batch\'&quot;></span>')">
            <div class="space-y-2">
                <button type="button" class="flex w-full items-center justify-between rounded-md border border-zinc-200 px-3 py-2.5 text-left text-sm hover:border-brand" x-on:click="chooseBatch(null)">
                    <span>
                        <span class="block font-medium text-ink">Auto (FIFO)</span>
                        <span class="text-xs text-zinc-400">Oldest stock first</span>
                    </span>
                    <span class="font-prata tabular-nums" x-text="money(batchProduct?.price)"></span>
                </button>
                <template x-for="batch in batches" :key="batch.id">
                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2.5 text-left text-sm hover:border-brand" x-on:click="chooseBatch(batch)">
                        <span>
                            <span class="block font-medium text-ink" x-text="`Batch received ${batch.received_at}`"></span>
                            <span class="text-xs text-zinc-400" x-text="`Cost ${money(batch.cost_price)} · ${batch.remaining_qty} left`"></span>
                            <span x-show="batch.selling_price < batch.cost_price" class="block text-xs font-medium text-red-600">Selling at a loss</span>
                        </span>
                        <span class="font-prata tabular-nums" x-text="money(batch.selling_price)"></span>
                    </button>
                </template>
                <p x-show="batchError" x-text="batchError" x-cloak class="{{ $errorClasses }}"></p>
            </div>
        </x-modal>

        <x-modal name="pos-serial" max-width="md" x-model="serialModal"
                 :title="new HtmlString('<span x-text=&quot;serialProduct?.name ?? \'Pick serial numbers\'&quot;></span>')">
            <div class="space-y-3">
                <p x-show="serialLoading" class="text-sm text-zinc-500">Loading serial numbers…</p>
                <div x-show="! serialLoading && serialUnits.length > 0" class="flex items-center justify-between text-xs text-zinc-500">
                    <span x-text="`${serialUnits.length} available in Showroom`"></span>
                    <button type="button" class="font-medium text-brand hover:underline" x-on:click="toggleAllSerials()"
                            x-text="serialSelected.length === serialUnits.length ? 'Clear all' : 'Select all'"></button>
                </div>
                <div class="max-h-72 divide-y divide-zinc-100 overflow-y-auto rounded-md border border-zinc-200" x-show="serialUnits.length > 0">
                    <template x-for="unit in serialUnits" :key="unit.id">
                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-zinc-50">
                            <input type="checkbox" class="accent-brand" :value="unit.id" x-model.number="serialSelected">
                            <span class="flex-1 font-mono" x-text="unit.serial_number"></span>
                            <span class="text-xs text-zinc-500" x-text="money(unit.selling_price ?? serialProduct?.price)"></span>
                        </label>
                    </template>
                </div>
                <p x-show="! serialLoading && serialUnits.length === 0 && ! serialError" class="text-sm text-zinc-500">No more serial numbers available in Showroom.</p>
                <p x-show="serialError" x-text="serialError" x-cloak class="{{ $errorClasses }}"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="serialModal = false">Cancel</button>
                    <button type="button" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="serialSelected.length === 0" x-on:click="addSerials()">
                        <x-lucide-shopping-cart class="h-4 w-4" /> Add to Cart
                    </button>
                </div>
            </div>
        </x-modal>

        <x-modal name="pos-service" max-width="md" x-model="serviceModal"
                 :title="new HtmlString('<span x-text=&quot;serviceDraft?.service.name&quot;></span>')">
            <template x-if="serviceDraft">
                <form class="space-y-4" x-on:submit.prevent="saveService()">
                    <div>
                        <label for="service-price" class="{{ $labelClasses }}">Price *</label>
                        <input id="service-price" type="number" min="0" step="0.01" class="nexora-input" x-model="serviceDraft.price">
                        <p x-show="serviceDraft.errors.price" x-text="serviceDraft.errors.price" class="{{ $errorClasses }}"></p>
                    </div>
                    <template x-for="field in serviceDraft.service.custom_fields ?? []" :key="field.id">
                        <div>
                            <template x-if="field.type === 'checkbox'">
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" class="accent-brand" x-model="serviceDraft.values[field.id]">
                                    <span x-text="field.label + (field.required ? ' *' : '')"></span>
                                </label>
                            </template>
                            <template x-if="field.type !== 'checkbox'">
                                <div>
                                    <label class="{{ $labelClasses }}" :for="'field-' + field.id" x-text="field.label + (field.required ? ' *' : '')"></label>
                                    <template x-if="field.type === 'textarea'">
                                        <textarea class="nexora-input" rows="3" :id="'field-' + field.id" :placeholder="field.placeholder ?? ''" x-model="serviceDraft.values[field.id]"></textarea>
                                    </template>
                                    <template x-if="field.type === 'select'">
                                        <select class="nexora-input" :id="'field-' + field.id" x-model="serviceDraft.values[field.id]">
                                            <option value="" x-text="field.placeholder || 'Select…'"></option>
                                            <template x-for="option in field.options ?? []" :key="option">
                                                <option :value="option" x-text="option"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="['text', 'number', 'date'].includes(field.type)">
                                        <input class="nexora-input" :type="field.type" :id="'field-' + field.id" :placeholder="field.placeholder ?? ''" x-model="serviceDraft.values[field.id]">
                                    </template>
                                </div>
                            </template>
                            <p x-show="serviceDraft.errors[field.id]" x-text="serviceDraft.errors[field.id]" class="{{ $errorClasses }}"></p>
                        </div>
                    </template>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="serviceModal = false">Cancel</button>
                        <button type="submit" class="nexora-btn nexora-btn-primary" x-text="serviceDraft.key ? 'Update' : 'Add to Bill'"></button>
                    </div>
                </form>
            </template>
        </x-modal>

        <x-modal name="pos-customer" title="Select Customer" max-width="md" x-model="customerModal">
            <div x-show="! customerCreating" class="space-y-3">
                <div class="relative">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input type="search" class="nexora-input pl-9" placeholder="Search by name or phone…" autocomplete="off" autofocus x-model="customerQuery" aria-label="Search customers">
                </div>
                <p x-show="customerQuery.trim().length < 2" class="text-xs text-zinc-400">Type at least 2 characters.</p>
                <p x-show="customerLoading" x-cloak class="text-xs text-zinc-400">Searching…</p>
                <div class="divide-y divide-zinc-100 rounded-md border border-zinc-200" x-show="customerResults.length > 0" x-cloak>
                    <template x-for="result in customerResults" :key="result.id">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50" x-on:click="selectCustomer(result)">
                            <span>
                                <span class="block font-medium text-ink" x-text="result.name"></span>
                                <span class="text-xs text-zinc-400" x-text="result.phone"></span>
                            </span>
                            <span class="badge badge-info" x-show="result.loyalty_points > 0" x-text="`${result.loyalty_points} pts`"></span>
                        </button>
                    </template>
                </div>
                <p x-show="! customerLoading && customerQuery.trim().length >= 2 && customerResults.length === 0" x-cloak class="text-sm text-zinc-500">No customers found.</p>
                <button type="button" class="nexora-btn nexora-btn-outline w-full justify-center" x-on:click="startNewCustomer()">
                    <x-lucide-user-plus class="h-4 w-4" /> New Customer
                </button>
            </div>

            <form x-show="customerCreating" x-cloak class="space-y-4" x-on:submit.prevent="saveCustomer()">
                <div>
                    <label for="pos-customer-name" class="{{ $labelClasses }}">Full name *</label>
                    <input id="pos-customer-name" type="text" class="nexora-input" maxlength="100" x-model="customerForm.name">
                    <p x-show="customerErrors.name" x-text="customerErrors.name" class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="pos-customer-phone" class="{{ $labelClasses }}">Phone number *</label>
                    <input id="pos-customer-phone" type="tel" class="nexora-input" maxlength="30" x-model="customerForm.phone">
                    <p x-show="customerErrors.phone" x-text="customerErrors.phone" class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="pos-customer-email" class="{{ $labelClasses }}">Email</label>
                    <input id="pos-customer-email" type="email" class="nexora-input" x-model="customerForm.email">
                    <p x-show="customerErrors.email" x-text="customerErrors.email" class="{{ $errorClasses }}"></p>
                </div>
                <p x-show="customerErrors.form" x-text="customerErrors.form" class="{{ $errorClasses }}"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="customerCreating = false">Back</button>
                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="customerSaving" x-text="customerSaving ? 'Saving…' : 'Save Customer'"></button>
                </div>
            </form>
        </x-modal>

        <x-modal name="pos-charge" max-width="sm" x-model="chargeModal"
                 :title="new HtmlString('<span x-text=&quot;methodLabels[chargeMethod] + \' Charge\'&quot;></span>')">
            <form class="space-y-4" x-on:submit.prevent="saveCharge()">
                <div>
                    <label for="pos-charge-percent" class="{{ $labelClasses }}">Surcharge %</label>
                    <input id="pos-charge-percent" type="number" min="0" max="100" step="0.01" class="nexora-input" placeholder="e.g. 3" autofocus x-model="chargeInput">
                    <p class="mt-1 text-xs text-zinc-500" x-show="chargeMethod === 'card' && isSplit">Applies only to the card portion of the bill.</p>
                    <p class="mt-1 text-xs text-zinc-500" x-show="! (chargeMethod === 'card' && isSplit)">Item prices are raised by this % — the customer's bill shows the higher prices, not a fee line.</p>
                    <p x-show="chargeError" x-text="chargeError" class="{{ $errorClasses }}"></p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="chargeModal = false">Cancel</button>
                    <button type="submit" class="nexora-btn nexora-btn-primary">Apply</button>
                </div>
            </form>
        </x-modal>

        <x-modal name="pos-sale-complete" title="Sale Complete" max-width="4xl" x-model="completeModal">
            <div class="space-y-4">
                <div class="no-print flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-600">
                        <x-lucide-circle-check class="inline h-4 w-4 text-green-600" />
                        <span class="font-medium text-ink" x-text="completed?.invoice_no"></span> saved —
                        <span x-text="money(completed?.total_amount)"></span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="printBill()">
                            <x-lucide-printer class="h-4 w-4" /> Print
                        </button>
                        <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-bind:disabled="billBusy !== ''" x-on:click="downloadBill()">
                            <x-lucide-download class="h-4 w-4" /> <span x-text="billBusy === 'download' ? 'Preparing…' : 'Download'"></span>
                        </button>
                        <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-show="completed?.emailUrl" x-bind:disabled="billBusy !== ''" x-on:click="emailBill()">
                            <x-lucide-mail class="h-4 w-4" /> <span x-text="billBusy === 'email' ? 'Sending…' : 'Email'"></span>
                        </button>
                        <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="newSale()">
                            <x-lucide-plus class="h-4 w-4" /> New Sale
                        </button>
                    </div>
                </div>
                <p x-show="billMessage" x-text="billMessage" x-cloak class="no-print rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="billError" x-text="billError" x-cloak class="no-print rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-100 p-2">
                    <div class="mx-auto w-[210mm] shadow-sm" x-html="billHtml"></div>
                </div>
            </div>
        </x-modal>
    </div>
</x-layouts.app>
