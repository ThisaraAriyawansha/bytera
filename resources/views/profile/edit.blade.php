@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $profileValue = fn (string $field): ?string => $errors->profile->any() ? old($field) : $user->{$field};
    $newEmail = $errors->email->any() ? old('email') : '';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="My Profile" subtitle="Your profile, email and password." />

        <x-flash />

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Profile Info --}}
            <section class="nexora-card p-5 lg:col-span-2">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ink font-prata text-sm text-white">{{ $user->initials() }}</div>
                    <div class="min-w-0">
                        <h2 class="font-prata text-base text-ink">Profile Info</h2>
                        <p class="truncate text-sm text-zinc-500">{{ $user->email }} · {{ $user->role }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="profile-name" class="{{ $labelClasses }}">Display name *</label>
                            <input id="profile-name" name="name" type="text" required maxlength="100" class="nexora-input" value="{{ $profileValue('name') }}">
                            @error('name', 'profile') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="profile-phone" class="{{ $labelClasses }}">Phone</label>
                            <input id="profile-phone" name="phone" type="tel" maxlength="30" class="nexora-input" value="{{ $profileValue('phone') }}">
                            @error('phone', 'profile') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="nexora-btn nexora-btn-primary">
                            <x-lucide-save class="h-4 w-4" /> Save
                        </button>
                    </div>
                </form>
            </section>

            {{-- Change Email --}}
            <section class="nexora-card p-5">
                <div class="mb-1 flex items-center gap-2">
                    <x-lucide-mail class="h-4 w-4 text-brand" />
                    <h2 class="font-prata text-base text-ink">Change Email</h2>
                </div>
                <p class="mb-4 text-sm text-zinc-500">We'll email a verification link to the new address. Your email changes only after you click it.</p>

                <form method="POST" action="{{ route('profile.email.request') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="new-email" class="{{ $labelClasses }}">New email</label>
                        <input id="new-email" name="email" type="email" required class="nexora-input" placeholder="name@example.com" value="{{ $newEmail }}">
                        @error('email', 'email') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email-current-password" class="{{ $labelClasses }}">Current password</label>
                        <input id="email-current-password" name="current_password" type="password" required autocomplete="current-password" class="nexora-input">
                        @error('current_password', 'email') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="nexora-btn nexora-btn-primary">
                            <x-lucide-mail class="h-4 w-4" /> Send verification link
                        </button>
                    </div>
                </form>
            </section>

            {{-- Change Password --}}
            <section class="nexora-card p-5">
                <div class="mb-4 flex items-center gap-2">
                    <x-lucide-key-round class="h-4 w-4 text-brand" />
                    <h2 class="font-prata text-base text-ink">Change Password</h2>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="password-current" class="{{ $labelClasses }}">Current password</label>
                        <input id="password-current" name="current_password" type="password" required autocomplete="current-password" class="nexora-input">
                        @error('current_password', 'password') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password-new" class="{{ $labelClasses }}">New password</label>
                        <input id="password-new" name="password" type="password" required minlength="6" autocomplete="new-password" class="nexora-input">
                        @error('password', 'password') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password-confirm" class="{{ $labelClasses }}">Confirm new password</label>
                        <input id="password-confirm" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password" class="nexora-input">
                    </div>
                    <p class="text-xs text-zinc-500">Minimum 6 characters.</p>
                    <div class="flex justify-end">
                        <button type="submit" class="nexora-btn nexora-btn-primary">
                            <x-lucide-lock class="h-4 w-4" /> Change Password
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>
