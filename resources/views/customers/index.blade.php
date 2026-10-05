@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $total = $customers->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="recordForm(@js([
             'modal' => 'customer-form',
             'storeUrl' => route('customers.store'),
             'blank' => ['name' => '', 'phone' => '', 'phone2' => '', 'email' => '', 'address' => ''],
         ]))">
        <x-page-header title="Customers"
                       :subtitle="$search !== ''
                           ? number_format($total).' '.str('customer')->plural($total).' found'
                           : number_format($total).' '.str('customer')->plural($total).' in total'">
            <x-slot:actions>
                <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openAdd()">
                    <x-lucide-user-plus class="h-4 w-4" /> Add Customer
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash />

        <form method="GET" action="{{ route('customers.index') }}" class="mb-4 max-w-md">
            <x-search-input placeholder="Search by name or phone (start of it)" :value="$search" aria-label="Search customers" />
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Email</th>
                            <th class="px-5 py-3">Address</th>
                            <th class="whitespace-nowrap px-5 py-3 text-right">Loyalty Points</th>
                            <th class="px-5 py-3">Joined</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($customers as $customer)
                            <tr class="hover:bg-zinc-50">
                                <td class="min-w-[10rem] px-5 py-3 font-medium text-ink">{{ $customer->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">
                                    <div>{{ $customer->phone }}</div>
                                    @if ($customer->phone2)
                                        <div class="text-xs text-zinc-400">{{ $customer->phone2 }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-zinc-600">
                                    <span class="block max-w-[16rem] truncate" @if ($customer->email) title="{{ $customer->email }}" @endif>{{ $customer->email ?: '—' }}</span>
                                </td>
                                <td class="min-w-[10rem] px-5 py-3 text-zinc-600">{{ $customer->address ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($customer->loyalty_points > 0)
                                        <span class="badge badge-info tabular-nums">{{ number_format($customer->loyalty_points) }} pts</span>
                                    @else
                                        <span class="tabular-nums text-zinc-400">0 pts</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $customer->created_at?->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="openEdit(@js([
                                                'name' => $customer->name,
                                                'phone' => $customer->phone,
                                                'phone2' => $customer->phone2,
                                                'email' => $customer->email,
                                                'address' => $customer->address,
                                                'updateUrl' => route('customers.update', $customer),
                                            ]))"
                                            aria-label="Edit {{ $customer->name }}">
                                        <x-lucide-pencil class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No customers start with "'.$search.'".' : 'No customers yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$customers" />
        </div>

        <x-modal name="customer-form">
            <x-slot:title><span x-text="isEditing ? 'Edit Customer' : 'Add Customer'">Add Customer</span></x-slot:title>

            <form id="customer-form" class="space-y-4" x-on:submit.prevent="save()">
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div>
                    <label for="customer-name" class="{{ $labelClasses }}">Full name *</label>
                    <input id="customer-name" type="text" class="nexora-input" maxlength="100" autocomplete="off" x-model="form.name" required autofocus>
                    <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="customer-phone" class="{{ $labelClasses }}">Phone number *</label>
                        <input id="customer-phone" type="tel" class="nexora-input" maxlength="30" autocomplete="off" x-model="form.phone" required>
                        <p x-show="errors.phone" x-text="errors.phone" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="customer-phone2" class="{{ $labelClasses }}">Phone number 2 (optional)</label>
                        <input id="customer-phone2" type="tel" class="nexora-input" maxlength="30" autocomplete="off" x-model="form.phone2">
                        <p x-show="errors.phone2" x-text="errors.phone2" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
                <div>
                    <label for="customer-email" class="{{ $labelClasses }}">Email (optional)</label>
                    <input id="customer-email" type="email" class="nexora-input" autocomplete="off" x-model="form.email">
                    <p x-show="errors.email" x-text="errors.email" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="customer-address" class="{{ $labelClasses }}">Address (optional)</label>
                    <textarea id="customer-address" rows="2" class="nexora-input" maxlength="500" x-model="form.address"></textarea>
                    <p x-show="errors.address" x-text="errors.address" x-cloak class="{{ $errorClasses }}"></p>
                </div>
            </form>

            <x-slot:footer>
                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'customer-form')">Cancel</button>
                <button type="submit" form="customer-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                    <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Customer'">Save</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.app>
