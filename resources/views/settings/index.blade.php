@php
    $roleBadge = fn (string $role): string => match ($role) {
        'Super Admin' => 'danger',
        'Admin' => 'info',
        'Manager' => 'warning',
        default => 'default',
    };
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $shopValue = fn (string $field): ?string => $errors->shop->any() ? old($field) : $settings->{$field};
    $newRecipient = $errors->notifyEmails->any() ? old('email') : '';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Settings" subtitle="Shop info, low stock alerts and team." />

        <x-flash />

        @unless ($canManage)
            <div class="mb-6 flex items-start gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600">
                <x-lucide-lock class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Only an Admin can change these settings.</span>
            </div>
        @endunless

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Shop Info --}}
            <section class="nexora-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <x-lucide-store class="h-4 w-4 text-brand" />
                    <h2 class="font-prata text-base text-ink">Shop Info</h2>
                </div>

                <form method="POST" action="{{ route('settings.shop.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <fieldset @disabled(! $canManage) class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="shop-name" class="{{ $labelClasses }}">Shop name *</label>
                                <input id="shop-name" name="name" type="text" required maxlength="100" class="nexora-input disabled:bg-zinc-50"
                                       value="{{ $shopValue('name') }}">
                                @error('name', 'shop') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shop-phone" class="{{ $labelClasses }}">Phone *</label>
                                <input id="shop-phone" name="phone" type="tel" required maxlength="30" class="nexora-input disabled:bg-zinc-50"
                                       value="{{ $shopValue('phone') }}">
                                @error('phone', 'shop') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="shop-email" class="{{ $labelClasses }}">Email</label>
                            <input id="shop-email" name="email" type="email" class="nexora-input disabled:bg-zinc-50"
                                   value="{{ $shopValue('email') }}">
                            @error('email', 'shop') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="shop-address" class="{{ $labelClasses }}">Address</label>
                            <textarea id="shop-address" name="address" rows="2" maxlength="500" class="nexora-input disabled:bg-zinc-50">{{ $shopValue('address') }}</textarea>
                            @error('address', 'shop') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    @if ($canManage)
                        <div class="flex justify-end">
                            <button type="submit" class="nexora-btn nexora-btn-primary">
                                <x-lucide-save class="h-4 w-4" /> Save
                            </button>
                        </div>
                    @endif
                </form>
            </section>

            {{-- Low Stock Alerts --}}
            <section class="nexora-card p-5">
                <div class="mb-1 flex items-center gap-2">
                    <x-lucide-bell class="h-4 w-4 text-brand" />
                    <h2 class="font-prata text-base text-ink">Low Stock Alerts</h2>
                </div>
                <p class="mb-4 text-sm text-zinc-500">These addresses get an email when a product drops to its low stock level.</p>

                @if ($canManage)
                    <form method="POST" action="{{ route('settings.notify-emails.store') }}" class="mb-4">
                        @csrf
                        <div class="flex gap-2">
                            <input name="email" type="email" required placeholder="name@example.com" class="nexora-input"
                                   value="{{ $newRecipient }}" aria-label="Recipient email">
                            <button type="submit" class="nexora-btn nexora-btn-primary shrink-0">
                                <x-lucide-plus class="h-4 w-4" /> Add
                            </button>
                        </div>
                        @error('email', 'notifyEmails') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </form>
                @endif

                <ul class="divide-y divide-zinc-100 rounded border border-zinc-200">
                    @forelse ($settings->notify_emails ?? [] as $email)
                        <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-lucide-mail class="h-4 w-4 shrink-0 text-zinc-400" />
                                <span class="truncate">{{ $email }}</span>
                            </span>
                            @if ($canManage)
                                <form method="POST" action="{{ route('settings.notify-emails.destroy') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="email" value="{{ $email }}">
                                    <button type="submit" class="rounded p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-brand" aria-label="Remove {{ $email }}">
                                        <x-lucide-x class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-3 py-4 text-center text-sm text-zinc-400">No recipients yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- Team --}}
        <section class="nexora-card mt-6"
                 x-data="teamManager(@js([
                     'storeUrl' => route('settings.users.store'),
                     'assignableRoles' => $assignableRoles,
                     'roleDefaults' => $roleDefaults,
                 ]))">
            <div class="flex flex-col justify-between gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2">
                    <x-lucide-users class="h-4 w-4 text-brand" />
                    <h2 class="font-prata text-base text-ink">Team</h2>
                    <span class="text-sm text-zinc-400">({{ $team->count() }})</span>
                </div>
                @if ($canManage)
                    <button type="button" class="nexora-btn nexora-btn-primary self-start" x-on:click="openAdd()">
                        <x-lucide-user-plus class="h-4 w-4" /> Add User
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Email</th>
                            <th class="px-5 py-3">Role</th>
                            <th class="px-5 py-3">Status</th>
                            @if ($canManage)
                                <th class="px-5 py-3 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($team as $member)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">
                                    {{ $member['name'] }}
                                    @if ($member['isSelf'])
                                        <span class="ml-1 text-xs font-normal text-zinc-400">(you)</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $member['email'] }}</td>
                                <td class="px-5 py-3"><x-badge :variant="$roleBadge($member['role'])">{{ $member['role'] }}</x-badge></td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$member['status'] === 'active' ? 'success' : 'default'" dot>
                                        {{ $member['status'] === 'active' ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                @if ($canManage)
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        @if ($member['canManage'])
                                            <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                    x-on:click="openEdit(@js($member))" aria-label="Edit {{ $member['name'] }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                    x-on:click="$dispatch('open-modal', @js([
                                                        'name' => 'delete-user',
                                                        'title' => 'Delete user?',
                                                        'message' => "Delete {$member['name']} ({$member['email']})? They will no longer be able to sign in.",
                                                        'action' => $member['deleteUrl'],
                                                    ]))"
                                                    aria-label="Delete {{ $member['name'] }}">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        @elseif ($member['canViewPermissions'])
                                            <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                    x-on:click="openEdit(@js($member), true)" aria-label="View {{ $member['name'] }}">
                                                <x-lucide-eye class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                {{-- Add User --}}
                <x-modal name="add-user" title="Add User">
                    <form id="add-user-form" class="space-y-4" x-on:submit.prevent="saveNew()">
                        <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                        <div>
                            <label for="new-name" class="{{ $labelClasses }}">Full Name</label>
                            <input id="new-name" type="text" class="nexora-input" placeholder="e.g. Kasun Perera" x-model="newUser.name" required autofocus>
                            <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="new-email" class="{{ $labelClasses }}">Email</label>
                            <input id="new-email" type="email" class="nexora-input" placeholder="name@example.com" x-model="newUser.email" required>
                            <p x-show="errors.email" x-text="errors.email" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="new-role" class="{{ $labelClasses }}">Role</label>
                            <select id="new-role" class="nexora-input" x-model="newUser.role" required>
                                @foreach ($assignableRoles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                            <p x-show="errors.role" x-text="errors.role" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="new-password" class="{{ $labelClasses }}">Password</label>
                                <input id="new-password" type="password" class="nexora-input" minlength="6" autocomplete="new-password" x-model="newUser.password" required>
                                <p x-show="errors.password" x-text="errors.password" x-cloak class="{{ $errorClasses }}"></p>
                            </div>
                            <div>
                                <label for="new-password-confirmation" class="{{ $labelClasses }}">Confirm Password</label>
                                <input id="new-password-confirmation" type="password" class="nexora-input" minlength="6" autocomplete="new-password" x-model="newUser.password_confirmation" required>
                            </div>
                        </div>
                        <p class="text-xs text-zinc-500">Minimum 6 characters. The user starts with the default permissions for their role.</p>
                    </form>

                    <x-slot:footer>
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'add-user')">Cancel</button>
                        <button type="submit" form="add-user-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                            <span x-show="! saving">Add User</span>
                            <span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </x-slot:footer>
                </x-modal>

                {{-- Edit User (read-only for Admin / Super Admin targets the viewer can't manage) --}}
                <x-modal name="edit-user" title="Edit User" max-width="2xl">
                    <form id="edit-user-form" class="space-y-5" x-on:submit.prevent="saveEdit()">
                        <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                        <div x-show="editing.readOnly" x-cloak class="flex items-start gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600">
                            <x-lucide-lock class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>You can't edit <span x-text="editing.role"></span> accounts. Their permissions are shown read-only.</span>
                        </div>

                        <p class="text-sm text-zinc-500" x-text="editing.email"></p>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-3">
                                <label for="edit-name" class="{{ $labelClasses }}">Full Name</label>
                                <input id="edit-name" type="text" class="nexora-input disabled:bg-zinc-50" x-model="editing.name" x-bind:disabled="editing.readOnly" required>
                                <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="edit-role" class="{{ $labelClasses }}">Role</label>
                                <template x-if="editing.readOnly">
                                    <input id="edit-role" type="text" class="nexora-input bg-zinc-50" x-bind:value="editing.role" disabled>
                                </template>
                                <template x-if="! editing.readOnly">
                                    <select id="edit-role" class="nexora-input" x-model="editing.role">
                                        @foreach ($assignableRoles as $role)
                                            <option value="{{ $role }}">{{ $role }}</option>
                                        @endforeach
                                    </select>
                                </template>
                                <p x-show="errors.role" x-text="errors.role" x-cloak class="{{ $errorClasses }}"></p>
                            </div>
                            <div>
                                <span class="{{ $labelClasses }}">Status</span>
                                <div class="flex rounded border border-zinc-200 p-0.5 text-sm">
                                    @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $status => $label)
                                        <button type="button" class="flex-1 rounded-sm px-2 py-1.5 transition-colors disabled:cursor-not-allowed"
                                                x-bind:class="editing.status === @js($status) ? 'bg-ink text-white' : 'text-zinc-500 hover:text-brand'"
                                                x-bind:disabled="editing.readOnly"
                                                x-on:click="editing.status = @js($status)">{{ $label }}</button>
                                    @endforeach
                                </div>
                                <p x-show="errors.status" x-text="errors.status" x-cloak class="{{ $errorClasses }}"></p>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h4 class="font-prata text-sm text-ink">Permissions</h4>
                                <button type="button" x-show="! editing.readOnly" class="nexora-btn nexora-btn-ghost px-2 py-1 text-xs" x-on:click="resetToRoleDefaults()">
                                    <x-lucide-rotate-ccw class="h-3.5 w-3.5" /> Reset to role defaults
                                </button>
                            </div>
                            <p x-show="errors.permissions" x-text="errors.permissions" x-cloak class="{{ $errorClasses }} mb-2"></p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($permissionGroups as $module => $permissions)
                                    <fieldset class="rounded border border-zinc-200 p-3">
                                        <legend class="px-1 text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $module }}</legend>
                                        <div class="space-y-1.5">
                                            @foreach ($permissions as $key => $label)
                                                <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700"
                                                       x-bind:class="editing.readOnly && 'cursor-default'">
                                                    <input type="checkbox" class="h-4 w-4 rounded-sm border-zinc-300 accent-brand"
                                                           x-bind:checked="hasPermission(@js($key))"
                                                           x-bind:disabled="editing.readOnly"
                                                           x-on:change="togglePermission(@js($key))">
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        </div>
                    </form>

                    <x-slot:footer>
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'edit-user')"
                                x-text="editing.readOnly ? 'Close' : 'Cancel'">Cancel</button>
                        <button type="submit" form="edit-user-form" x-show="! editing.readOnly" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                            <span x-show="! saving">Save Changes</span>
                            <span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </x-slot:footer>
                </x-modal>

                <x-confirm-dialog name="delete-user" title="Delete user?" confirm-label="Delete" />
            @endif
        </section>

        {{-- Database Usage --}}
        <section class="nexora-card mt-6 p-5">
            <div class="mb-4 flex items-center gap-2">
                <x-lucide-database class="h-4 w-4 text-brand" />
                <h2 class="font-prata text-base text-ink">Database Usage</h2>
                <span class="text-sm text-zinc-400">({{ number_format(array_sum($tableCounts)) }} rows)</span>
            </div>
            <div class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tableCounts as $table => $count)
                    <div class="flex items-center justify-between gap-2 border-b border-zinc-100 py-2 text-sm">
                        <span class="text-zinc-600">{{ $tableLabels[$table] }}</span>
                        <span class="font-medium tabular-nums text-ink">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Data tools (Super Admin only) --}}
        @if ($isSuperAdmin)
            <section class="nexora-card mt-6"
                     x-data="{ table: @js(old('table', '')), typed: '' }"
                     x-init="table && $nextTick(() => $dispatch('open-modal', 'clean-table'))">
                <div class="border-b border-zinc-200 p-5">
                    <div class="flex items-center gap-2">
                        <x-lucide-shield-alert class="h-4 w-4 text-brand" />
                        <h2 class="font-prata text-base text-ink">Data Tools</h2>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">Export any table, or clean a transactional table. Cleaning can't be undone.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-5 py-3">Table</th>
                                <th class="px-5 py-3 text-right">Rows</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($tableLabels as $table => $label)
                                <tr class="hover:bg-zinc-50">
                                    <td class="px-5 py-2.5">
                                        <span class="text-ink">{{ $label }}</span>
                                        <span class="ml-1 font-mono text-xs text-zinc-400">{{ $table }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($tableCounts[$table]) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                        <a href="{{ route('settings.data.export', ['table' => $table, 'format' => 'csv']) }}" class="nexora-btn nexora-btn-ghost px-2 py-1 text-xs">
                                            <x-lucide-download class="h-3.5 w-3.5" /> CSV
                                        </a>
                                        <a href="{{ route('settings.data.export', ['table' => $table, 'format' => 'json']) }}" class="nexora-btn nexora-btn-ghost px-2 py-1 text-xs">
                                            <x-lucide-download class="h-3.5 w-3.5" /> JSON
                                        </a>
                                        @if (in_array($table, $cleanableTables, true))
                                            <button type="button" class="nexora-btn nexora-btn-ghost px-2 py-1 text-xs"
                                                    x-on:click="table = @js($table); typed = ''; $dispatch('open-modal', 'clean-table')">
                                                <x-lucide-trash-2 class="h-3.5 w-3.5" /> Clean
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-modal name="clean-table" title="Clean table" max-width="sm">
                    <form id="clean-table-form" method="POST" x-bind:action="@js(route('settings.data.clean', ['table' => '__TABLE__'])).replace('__TABLE__', table)" class="space-y-3">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="table" x-bind:value="table">
                        <p class="text-sm text-zinc-600">
                            This permanently deletes <strong>every row</strong> in <span class="font-mono text-ink" x-text="table"></span>.
                            Type the table name to confirm.
                        </p>
                        <input name="confirmation" type="text" class="nexora-input font-mono" autocomplete="off" x-model="typed"
                               x-bind:placeholder="table" aria-label="Type the table name to confirm" autofocus>
                        @error('confirmation', 'clean') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </form>

                    <x-slot:footer>
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'clean-table')">Cancel</button>
                        <button type="submit" form="clean-table-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="typed !== table">
                            Clean table
                        </button>
                    </x-slot:footer>
                </x-modal>
            </section>
        @endif
    </div>
</x-layouts.app>
