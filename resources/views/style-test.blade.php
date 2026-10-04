<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Style Test · M-Fixpro POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="p-4 sm:p-8 space-y-8 max-w-6xl mx-auto" x-data="{ modalOpen: false, confirmOpen: false }">

    {{-- Page header (SPEC §2.3) --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <h1 class="font-prata text-2xl text-ink">Style Test</h1>
            <p class="text-sm text-zinc-500">Every button, input, card and badge style from SPEC §2.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="nexora-btn nexora-btn-outline"><x-lucide-wrench class="w-4 h-4"/> New Job</button>
            <button type="button" class="nexora-btn nexora-btn-primary"><x-lucide-plus class="w-4 h-4"/> New Sale</button>
        </div>
    </div>

    {{-- Typography --}}
    <section class="nexora-card p-6 space-y-3">
        <h2 class="font-prata text-lg text-ink">Typography</h2>
        <p class="font-prata text-2xl">font-prata — Montserrat 700 (headings)</p>
        <p class="font-poppins">font-poppins — Poppins (default body, 14px / 1.6)</p>
        <p class="font-milonga">font-milonga — Open Sans</p>
        <p class="font-prata text-3xl text-ink">Rs. 12,500</p>
    </section>

    {{-- Colours --}}
    <section class="nexora-card p-6 space-y-4">
        <h2 class="font-prata text-lg text-ink">Colours</h2>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
            @foreach ([
                'bg-brand' => 'brand #e30613',
                'bg-brand-dark' => 'brand-dark #b8050f',
                'bg-brand-light' => 'brand-light #fff1f1',
                'bg-brand-logo' => 'brand-logo #ff0607',
                'bg-ink' => 'ink #0a0a0a',
            ] as $class => $label)
                <div>
                    <div class="{{ $class }} h-12 rounded-lg border border-zinc-200"></div>
                    <p class="mt-1 text-zinc-600">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        <div class="grid grid-cols-5 sm:grid-cols-10 gap-2 text-xs">
            @foreach (['bg-zinc-50', 'bg-zinc-100', 'bg-zinc-200', 'bg-zinc-300', 'bg-zinc-400', 'bg-zinc-500', 'bg-zinc-600', 'bg-zinc-700', 'bg-zinc-800', 'bg-zinc-900'] as $class)
                <div>
                    <div class="{{ $class }} h-10 rounded border border-zinc-200"></div>
                    <p class="mt-1 text-zinc-500">{{ substr($class, 3) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Buttons --}}
    <section class="nexora-card p-6 space-y-4">
        <h2 class="font-prata text-lg text-ink">Buttons</h2>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="nexora-btn nexora-btn-primary">Primary</button>
            <button type="button" class="nexora-btn nexora-btn-outline">Outline</button>
            <button type="button" class="nexora-btn nexora-btn-ghost">Ghost</button>
            <button type="button" class="nexora-btn nexora-btn-danger">Danger</button>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="nexora-btn nexora-btn-primary"><x-lucide-shopping-cart class="w-4 h-4"/> Checkout — Rs. 12,500</button>
            <button type="button" class="nexora-btn nexora-btn-outline"><x-lucide-download class="w-4 h-4"/> Export CSV</button>
            <button type="button" class="nexora-btn nexora-btn-ghost"><x-lucide-printer class="w-4 h-4"/> Print</button>
            <button type="button" class="nexora-btn nexora-btn-danger"><x-lucide-trash-2 class="w-4 h-4"/> Delete</button>
            <button type="button" class="nexora-btn nexora-btn-primary opacity-50 cursor-not-allowed" disabled>Disabled</button>
        </div>
        <div class="flex flex-wrap items-center gap-1">
            <span class="text-xs text-zinc-500 mr-2">Row icon buttons:</span>
            <button type="button" class="p-1.5 rounded text-zinc-500 hover:text-brand hover:bg-brand-light" title="View"><x-lucide-eye class="w-4 h-4"/></button>
            <button type="button" class="p-1.5 rounded text-zinc-500 hover:text-brand hover:bg-brand-light" title="Edit"><x-lucide-pencil class="w-4 h-4"/></button>
            <button type="button" class="p-1.5 rounded text-zinc-500 hover:text-brand hover:bg-brand-light" title="Delete"><x-lucide-trash-2 class="w-4 h-4"/></button>
        </div>
        <div class="inline-flex bg-zinc-100 rounded-full p-1 text-sm" x-data="{ period: 'today' }">
            @foreach (['today' => 'Today', '7d' => '7 Days', '30d' => '30 Days'] as $key => $label)
                <button type="button" class="px-4 py-1.5 rounded-full transition"
                        :class="period === '{{ $key }}' ? 'bg-white shadow text-ink font-medium' : 'text-zinc-500'"
                        @click="period = '{{ $key }}'">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    {{-- Inputs --}}
    <section class="nexora-card p-6 space-y-4">
        <h2 class="font-prata text-lg text-ink">Inputs</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">Text</label>
                <input type="text" class="nexora-input" placeholder="e.g. Kasun Perera">
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">Search</label>
                <div class="relative">
                    <x-lucide-search class="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2"/>
                    <input type="text" class="nexora-input pl-9" placeholder="Search product by name, SKU or barcode…">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">Number</label>
                <input type="number" class="nexora-input" placeholder="0" value="1500">
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">Select</label>
                <select class="nexora-input">
                    <option>All categories</option>
                    <option>Laptops</option>
                    <option>Accessories</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">From</label>
                <input type="date" class="nexora-input" value="{{ now()->subDays(30)->toDateString() }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-600 mb-1">To</label>
                <input type="date" class="nexora-input" value="{{ now()->toDateString() }}">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-zinc-600 mb-1">Textarea</label>
                <textarea class="nexora-input" rows="3" placeholder="Note about this update (what was done / changed)…"></textarea>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-brand" checked> Charger</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-brand"> Bag</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="loc" class="accent-brand" checked> Stores</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="loc" class="accent-brand"> Showroom</label>
            </div>
        </div>
    </section>

    {{-- Badges --}}
    <section class="nexora-card p-6 space-y-4">
        <h2 class="font-prata text-lg text-ink">Badges</h2>
        <div class="flex flex-wrap gap-2">
            <span class="badge badge-default">Default</span>
            <span class="badge badge-success">Success</span>
            <span class="badge badge-warning">Warning</span>
            <span class="badge badge-danger">Danger</span>
            <span class="badge badge-info">Info</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="badge badge-warning">Job Pending</span>
            <span class="badge badge-default">Ongoing Job</span>
            <span class="badge badge-success">Job Done</span>
            <span class="badge badge-info">Delivered</span>
            <span class="badge badge-danger">Can't Repair</span>
            <span class="badge badge-success">Paid</span>
            <span class="badge badge-danger">Cancelled</span>
        </div>
    </section>

    {{-- Cards --}}
    <section class="space-y-4">
        <h2 class="font-prata text-lg text-ink">Cards</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="nexora-card p-5">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Revenue · Today</p>
                <p class="font-prata text-2xl mt-1">Rs. 48,250</p>
                <span class="badge badge-success mt-2">▲ 12% vs yesterday</span>
            </div>
            <div class="nexora-card p-5">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">Active repairs</p>
                <p class="font-prata text-2xl mt-1">14</p>
                <p class="text-xs text-brand mt-2">3 overdue</p>
            </div>
            <div class="nexora-card p-5 flex flex-col items-center text-center">
                <x-lucide-lock class="w-8 h-8 text-zinc-400"/>
                <h3 class="font-prata text-lg mt-2">Access Restricted</h3>
                <p class="text-xs text-zinc-500">You don't have permission to view the Finance page.</p>
            </div>
        </div>

        {{-- Table card (SPEC §2.3) --}}
        <div class="nexora-card overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 font-medium uppercase tracking-wider border-b border-zinc-200">
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ([['INV-00012', 'Kasun Perera', 'Cash', '12,500', 'Paid'], ['INV-00011', 'Walk-in Customer', 'Cash + Card', '3,200', 'Paid'], ['INV-00010', 'Nimali Silva', 'KokoPay', '45,000', 'Cancelled']] as [$invoice, $customer, $payment, $total, $status])
                        <tr class="hover:bg-zinc-50">
                            <td class="px-4 py-3 font-medium">{{ $invoice }}</td>
                            <td class="px-4 py-3">{{ $customer }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $payment }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ $total }}</td>
                            <td class="px-4 py-3"><span class="badge {{ $status === 'Paid' ? 'badge-success' : 'badge-danger' }}">{{ $status }}</span></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" class="p-1.5 rounded text-zinc-500 hover:text-brand hover:bg-brand-light"><x-lucide-eye class="w-4 h-4"/></button>
                                <button type="button" class="p-1.5 rounded text-zinc-500 hover:text-brand hover:bg-brand-light"><x-lucide-pencil class="w-4 h-4"/></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-4 py-3 border-t border-zinc-200 text-xs text-zinc-500">
                <span>Showing 1–3 of 3</span>
                <div class="flex gap-1">
                    <button type="button" class="px-2.5 py-1 rounded border border-zinc-200">Prev</button>
                    <button type="button" class="px-2.5 py-1 rounded bg-brand text-white">1</button>
                    <button type="button" class="px-2.5 py-1 rounded border border-zinc-200">Next</button>
                </div>
            </div>
        </div>
    </section>

    {{-- Modals & Alpine --}}
    <section class="nexora-card p-6 space-y-4">
        <h2 class="font-prata text-lg text-ink">Modals (Alpine.js)</h2>
        <div class="flex flex-wrap gap-3">
            <button type="button" class="nexora-btn nexora-btn-outline" @click="modalOpen = true">Open modal</button>
            <button type="button" class="nexora-btn nexora-btn-danger" @click="confirmOpen = true">Open confirm dialog</button>
        </div>
        <p class="text-xs text-zinc-500">Animations: <span class="inline-block animate-fadeIn">animate-fadeIn</span> · sidebar uses animate-slideIn.</p>
    </section>

    <div x-show="modalOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="modalOpen = false" @keydown.escape.window="modalOpen = false">
        <div class="bg-white rounded-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200">
                <h3 class="font-prata text-lg">Open Shift</h3>
                <button type="button" class="text-zinc-400 hover:text-brand" @click="modalOpen = false"><x-lucide-x class="w-5 h-5"/></button>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 mb-1">Opening cash float</label>
                    <input type="number" class="nexora-input" placeholder="0">
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 mb-1">Note</label>
                    <input type="text" class="nexora-input" placeholder="e.g. Morning shift">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" @click="modalOpen = false">Cancel</button>
                    <button type="button" class="nexora-btn nexora-btn-primary">Open Shift</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="confirmOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="confirmOpen = false">
        <div class="bg-white rounded-xl w-full max-w-sm mx-4 p-5 animate-fadeIn">
            <h3 class="font-prata text-lg">Delete product?</h3>
            <p class="text-sm text-zinc-500 mt-1">This cannot be undone.</p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" class="nexora-btn nexora-btn-outline" @click="confirmOpen = false">Cancel</button>
                <button type="button" class="nexora-btn nexora-btn-primary" @click="confirmOpen = false">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
</body>
</html>
