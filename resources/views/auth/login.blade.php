<x-layouts.base>
    <x-slot:head>
        <meta name="robots" content="noindex">
        <script>
            window.onTurnstileLoad = () => document.dispatchEvent(new Event('turnstile:ready'));

            function loginPage(config) {
                return {
                    mode: 'signin',
                    email: '',
                    password: '',
                    code: '',
                    newPassword: '',
                    newPasswordConfirmation: '',
                    showPassword: false,
                    showNewPassword: false,
                    errors: config.errors,
                    success: '',
                    loading: false,
                    cooldown: 0,
                    cooldownTimer: null,
                    turnstileToken: '',
                    widgetId: null,

                    init() {
                        if (window.turnstile) {
                            this.renderTurnstile();
                        } else {
                            document.addEventListener('turnstile:ready', () => this.renderTurnstile(), { once: true });
                        }
                    },

                    get heading() {
                        return { signin: 'Sign in', forgot: 'Forgot password', reset: 'Reset password' }[this.mode];
                    },

                    get subtitle() {
                        return {
                            signin: 'Enter your credentials to continue',
                            forgot: "Enter your account email and we'll send you a 6-digit code",
                            reset: 'Enter the code we emailed you and choose a new password',
                        }[this.mode];
                    },

                    get buttonLabel() {
                        return { signin: 'Sign in', forgot: 'Send code', reset: 'Reset password' }[this.mode];
                    },

                    renderTurnstile() {
                        if (!config.siteKey || this.widgetId !== null) {
                            return;
                        }

                        this.widgetId = window.turnstile.render(this.$refs.turnstile, {
                            sitekey: config.siteKey,
                            callback: (token) => { this.turnstileToken = token; delete this.errors.turnstile_token; },
                            'expired-callback': () => { this.turnstileToken = ''; },
                            'error-callback': () => { this.turnstileToken = ''; },
                        });
                    },

                    resetTurnstile() {
                        this.turnstileToken = '';

                        if (window.turnstile && this.widgetId !== null) {
                            window.turnstile.reset(this.widgetId);
                        }
                    },

                    switchMode(mode) {
                        this.mode = mode;
                        this.errors = {};

                        if (mode !== 'signin') {
                            this.success = '';
                        }

                        this.resetTurnstile();
                    },

                    startCooldown() {
                        this.cooldown = config.cooldownSeconds;
                        clearInterval(this.cooldownTimer);
                        this.cooldownTimer = setInterval(() => {
                            this.cooldown--;

                            if (this.cooldown <= 0) {
                                clearInterval(this.cooldownTimer);
                            }
                        }, 1000);
                    },

                    async post(url, body) {
                        this.loading = true;
                        this.errors = {};

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ ...body, turnstile_token: this.turnstileToken }),
                            });
                            const data = await response.json().catch(() => ({}));

                            if (response.status === 422) {
                                this.errors = Object.fromEntries(
                                    Object.entries(data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
                                );
                            } else if (response.status === 419) {
                                this.errors = { form: 'Your session expired. Reloading the page…' };
                                setTimeout(() => window.location.reload(), 1200);
                            } else if (response.status === 429) {
                                this.errors = { form: 'Too many attempts. Please wait a minute and try again.' };
                            } else if (!response.ok) {
                                this.errors = { form: 'Something went wrong. Please try again.' };
                            }

                            return response.ok ? data : null;
                        } catch (error) {
                            this.errors = { form: 'Could not reach the server. Check your connection and try again.' };

                            return null;
                        } finally {
                            this.loading = false;
                            this.resetTurnstile();
                        }
                    },

                    async submit() {
                        if (this.mode === 'signin') {
                            const data = await this.post(config.routes.login, { email: this.email, password: this.password });

                            if (data) {
                                window.location.href = data.redirect;
                            }
                        } else if (this.mode === 'forgot') {
                            await this.sendCode();
                        } else {
                            const data = await this.post(config.routes.reset, {
                                email: this.email,
                                code: this.code,
                                password: this.newPassword,
                                password_confirmation: this.newPasswordConfirmation,
                            });

                            if (data) {
                                this.password = this.code = this.newPassword = this.newPasswordConfirmation = '';
                                this.switchMode('signin');
                                this.success = data.message;
                            }
                        }
                    },

                    async sendCode() {
                        const data = await this.post(config.routes.sendCode, { email: this.email });

                        if (data) {
                            this.switchMode('reset');
                            this.success = data.message;
                            this.startCooldown();
                        }
                    },
                };
            }
        </script>
        @if (filled($turnstileSiteKey))
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad" async defer></script>
        @endif
    </x-slot:head>

    <div class="min-h-dvh bg-white sm:bg-gradient-to-br sm:from-zinc-100 sm:via-zinc-50 sm:to-zinc-200 flex flex-col items-center sm:justify-center gap-6 pb-6 sm:p-8 overflow-x-hidden">
        <div
            class="relative w-full sm:max-w-[440px] md:max-w-[900px] md:min-h-[520px] bg-white sm:rounded-2xl sm:shadow-[0_30px_80px_-20px_rgba(10,10,10,0.25)] grid md:grid-cols-2 animate-fadeIn"
            x-data="loginPage(@js([
                'siteKey' => $turnstileSiteKey,
                'cooldownSeconds' => \App\Services\PasswordResetService::RESEND_COOLDOWN_SECONDS,
                'errors' => $errors->any() ? ['form' => $errors->first()] : (object) [],
                'routes' => [
                    'login' => route('login.store'),
                    'sendCode' => route('password.code'),
                    'reset' => route('password.reset'),
                ],
            ]))"
        >
            {{-- Brand panel --}}
            <div class="relative overflow-hidden rounded-b-3xl sm:rounded-b-none sm:rounded-t-2xl md:rounded-tr-none md:rounded-l-2xl bg-brand text-white px-6 pt-8 pb-7 sm:px-8 sm:py-10 md:px-12 md:py-10 flex flex-col justify-center">
                <div class="pointer-events-none absolute -top-[58px] -left-[58px] h-[192px] w-[192px] rounded-full border-[29px] border-white/10"></div>
                <div class="pointer-events-none absolute -bottom-[80px] right-10 h-40 w-40 rounded-full bg-white/10"></div>

                <div class="relative flex flex-col items-center text-center sm:items-start sm:text-left">
                    <img src="{{ asset('shop_logo/logo-white.png') }}" alt="{{ $shop->name }}" class="h-11 sm:h-14 md:h-[77px] w-auto mb-4 sm:mb-6 md:mb-8" onerror="this.remove()">
                    <h2 class="font-poppins font-semibold text-xl sm:text-2xl md:text-[26px] leading-tight">Welcome to {{ $shop->name }}</h2>
                    <div class="hidden sm:block mt-3.5 h-[3px] w-12 rounded-full bg-white"></div>
                    <p class="mt-2 sm:mt-4 text-[13px] sm:text-sm leading-relaxed text-white/90 max-w-[300px]">Manage repairs, sales and billing for your shop from one simple dashboard.</p>
                </div>
            </div>

            {{-- Decorative half-filled circle on the panel seam --}}
            <div class="pointer-events-none hidden md:flex absolute left-1/2 top-[82%] z-10 h-24 w-24 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-[0_10px_30px_-10px_rgba(10,10,10,0.2)]">
                <div class="h-[61px] w-[61px] rounded-full bg-[linear-gradient(to_right,#e30613_50%,#fff_50%)]"></div>
            </div>

            {{-- Decorative outlined diamond on the top-right corner --}}
            <div class="pointer-events-none hidden md:block absolute -right-9 top-10 h-[77px] w-[77px] rotate-45 rounded-[22px] border-[11px] border-zinc-200/60"></div>

            {{-- Form --}}
            <div class="relative px-5 pt-7 pb-6 sm:px-10 sm:py-8 md:px-14 md:py-12 flex flex-col justify-center">
                <div class="text-center">
                    <h1 class="font-poppins font-semibold text-2xl md:text-[22px] text-ink" x-text="heading">Sign in</h1>
                    <div class="mx-auto mt-1.5 h-[3px] w-7 rounded-full bg-brand"></div>
                    <p class="mt-2 sm:mt-3 text-sm md:text-[13px] text-zinc-600" x-text="subtitle">Enter your credentials to continue</p>
                </div>

                <div x-cloak x-show="success" class="mt-5 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 flex gap-2">
                    <x-lucide-circle-check class="w-4 h-4 mt-0.5 shrink-0" />
                    <span x-text="success"></span>
                </div>

                <div x-cloak x-show="errors.form" class="mt-5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 flex gap-2">
                    <x-lucide-circle-alert class="w-4 h-4 mt-0.5 shrink-0" />
                    <span x-text="errors.form"></span>
                </div>

                <form class="mt-5 sm:mt-6 flex flex-col gap-4" @submit.prevent="submit" novalidate>
                    {{-- Email (sign in + forgot) --}}
                    <div x-show="mode !== 'reset'">
                        <label for="email" class="block text-[13px] font-medium text-ink mb-1">Email</label>
                        <input id="email" type="email" x-model="email" autocomplete="username" placeholder="you@example.com" class="login-input" :class="errors.email && 'border-red-400'">
                        <p x-cloak x-show="errors.email" x-text="errors.email" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    {{-- Password (sign in) --}}
                    <div x-show="mode === 'signin'">
                        <div class="flex items-center justify-between mb-1">
                            <label for="password" class="block text-[13px] font-medium text-ink">Password</label>
                            <button type="button" class="text-xs text-zinc-500 hover:text-brand" @click="switchMode('forgot')">Forgot password?</button>
                        </div>
                        <div class="relative">
                            <input id="password" :type="showPassword ? 'text' : 'password'" x-model="password" autocomplete="current-password" placeholder="Enter your password" class="login-input pr-10" :class="errors.password && 'border-red-400'">
                            <button type="button" class="absolute inset-y-0 right-0 px-2 text-zinc-400 hover:text-brand" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                <x-lucide-eye class="w-4 h-4" x-show="!showPassword" />
                                <x-lucide-eye-off class="w-4 h-4" x-cloak x-show="showPassword" />
                            </button>
                        </div>
                        <p x-cloak x-show="errors.password" x-text="errors.password" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    {{-- Reset password fields --}}
                    <template x-if="mode === 'reset'">
                        <div class="flex flex-col gap-4">
                            <p class="text-sm text-zinc-600">
                                Code sent to <span class="font-medium text-ink" x-text="email"></span>
                                · <button type="button" class="text-brand hover:text-brand-dark font-medium" @click="switchMode('forgot')">Change</button>
                            </p>
                            <p x-show="errors.email" x-text="errors.email" class="-mt-3 text-xs text-red-600"></p>

                            <div>
                                <label for="code" class="block text-[13px] font-medium text-ink mb-1">6-digit code</label>
                                <input id="code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" x-model="code" @input="code = code.replace(/\D/g, '')" placeholder="000000" class="login-input tracking-[0.4em] font-medium" :class="errors.code && 'border-red-400'">
                                <p x-show="errors.code" x-text="errors.code" class="mt-1 text-xs text-red-600"></p>
                            </div>

                            <div>
                                <label for="new-password" class="block text-[13px] font-medium text-ink mb-1">New password</label>
                                <div class="relative">
                                    <input id="new-password" :type="showNewPassword ? 'text' : 'password'" x-model="newPassword" autocomplete="new-password" placeholder="At least 6 characters" class="login-input pr-10" :class="errors.password && 'border-red-400'">
                                    <button type="button" class="absolute inset-y-0 right-0 px-2 text-zinc-400 hover:text-brand" @click="showNewPassword = !showNewPassword" :aria-label="showNewPassword ? 'Hide password' : 'Show password'">
                                        <x-lucide-eye class="w-4 h-4" x-show="!showNewPassword" />
                                        <x-lucide-eye-off class="w-4 h-4" x-show="showNewPassword" />
                                    </button>
                                </div>
                                <p x-show="errors.password" x-text="errors.password" class="mt-1 text-xs text-red-600"></p>
                            </div>

                            <div>
                                <label for="new-password-confirmation" class="block text-[13px] font-medium text-ink mb-1">Confirm new password</label>
                                <input id="new-password-confirmation" :type="showNewPassword ? 'text' : 'password'" x-model="newPasswordConfirmation" autocomplete="new-password" placeholder="Repeat the new password" class="login-input">
                            </div>
                        </div>
                    </template>

                    {{-- Cloudflare Turnstile (one widget shared by all three modes) --}}
                    <div>
                        <div x-ref="turnstile" class="min-h-[65px] flex justify-center"></div>
                        <p x-cloak x-show="errors.turnstile_token" x-text="errors.turnstile_token" class="mt-1 text-center text-xs text-red-600"></p>
                    </div>

                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-brand py-3 text-[13px] font-semibold uppercase tracking-wide text-white shadow-[0_8px_18px_-6px_rgba(227,6,19,0.55)] transition-colors hover:bg-brand-dark disabled:opacity-60 disabled:cursor-not-allowed" :disabled="loading">
                        <x-lucide-loader-circle class="w-4 h-4 animate-spin" x-cloak x-show="loading" />
                        <span x-text="buttonLabel">Sign in</span>
                    </button>

                    <div x-cloak x-show="mode !== 'signin'" class="flex items-center justify-between text-sm">
                        <button type="button" class="inline-flex items-center gap-1 text-zinc-500 hover:text-brand" @click="switchMode('signin')">
                            <x-lucide-arrow-left class="w-4 h-4" /> Back to sign in
                        </button>
                        <button type="button" x-show="mode === 'reset'" class="font-medium text-brand hover:text-brand-dark disabled:text-zinc-400 disabled:cursor-not-allowed" :disabled="loading || cooldown > 0" @click="sendCode()">
                            <span x-text="cooldown > 0 ? `Resend code in ${cooldown}s` : 'Resend code'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <footer class="text-center text-xs leading-relaxed text-zinc-400">
            <p>&copy; {{ now()->year }} {{ $shop->name }}</p>
            <p>Design &amp; Developed by plexCode</p>
        </footer>
    </div>
</x-layouts.base>
