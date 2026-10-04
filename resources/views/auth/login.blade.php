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

    <div class="min-h-screen bg-gradient-to-br from-zinc-100 via-zinc-50 to-zinc-200 flex items-center justify-center p-4 sm:p-8">
        <div
            class="w-full max-w-[440px] md:max-w-[900px] bg-white rounded-3xl shadow-[0_30px_80px_-20px_rgba(10,10,10,0.25)] overflow-hidden grid md:grid-cols-2 animate-fadeIn"
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
            <div class="relative overflow-hidden bg-[#e30613] text-white px-8 py-10 md:p-12 flex flex-col justify-center">
                <div class="pointer-events-none absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-20 h-72 w-72 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute top-1/2 right-10 h-20 w-20 rounded-full bg-white/5"></div>

                <div class="relative">
                    <img src="{{ asset('shop_logo/logo-white.png') }}" alt="{{ $shop->name }}" class="h-14 md:h-20 w-auto mb-6 md:mb-10" onerror="this.remove()">
                    <h2 class="font-prata text-2xl md:text-3xl leading-tight">Welcome to {{ $shop->name }}</h2>
                    <div class="mt-4 h-1 w-14 rounded-full bg-white"></div>
                    <p class="mt-4 text-sm md:text-base text-white/85 max-w-xs">Manage repairs, sales and billing for your shop from one simple dashboard.</p>
                </div>
            </div>

            {{-- Form --}}
            <div class="px-6 py-8 sm:px-10 md:p-12">
                <h1 class="font-prata text-2xl text-ink" x-text="heading">Sign in</h1>
                <p class="mt-1 text-sm text-zinc-500" x-text="subtitle">Enter your credentials to continue</p>

                <div x-cloak x-show="success" class="mt-5 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 flex gap-2">
                    <x-lucide-circle-check class="w-4 h-4 mt-0.5 shrink-0" />
                    <span x-text="success"></span>
                </div>

                <div x-cloak x-show="errors.form" class="mt-5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 flex gap-2">
                    <x-lucide-circle-alert class="w-4 h-4 mt-0.5 shrink-0" />
                    <span x-text="errors.form"></span>
                </div>

                <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit" novalidate>
                    {{-- Email (sign in + forgot) --}}
                    <div x-show="mode !== 'reset'">
                        <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">Email</label>
                        <input id="email" type="email" x-model="email" autocomplete="username" placeholder="you@example.com" class="nexora-input" :class="errors.email && 'border-red-400'">
                        <p x-cloak x-show="errors.email" x-text="errors.email" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    {{-- Password (sign in) --}}
                    <div x-show="mode === 'signin'">
                        <div class="flex items-center justify-between mb-1">
                            <label for="password" class="block text-sm font-medium text-zinc-700">Password</label>
                            <button type="button" class="text-xs font-medium text-brand hover:text-brand-dark" @click="switchMode('forgot')">Forgot password?</button>
                        </div>
                        <div class="relative">
                            <input id="password" :type="showPassword ? 'text' : 'password'" x-model="password" autocomplete="current-password" placeholder="••••••••" class="nexora-input pr-10" :class="errors.password && 'border-red-400'">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-zinc-400 hover:text-brand" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
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
                                <label for="code" class="block text-sm font-medium text-zinc-700 mb-1">6-digit code</label>
                                <input id="code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" x-model="code" @input="code = code.replace(/\D/g, '')" placeholder="000000" class="nexora-input tracking-[0.4em] font-medium" :class="errors.code && 'border-red-400'">
                                <p x-show="errors.code" x-text="errors.code" class="mt-1 text-xs text-red-600"></p>
                            </div>

                            <div>
                                <label for="new-password" class="block text-sm font-medium text-zinc-700 mb-1">New password</label>
                                <div class="relative">
                                    <input id="new-password" :type="showNewPassword ? 'text' : 'password'" x-model="newPassword" autocomplete="new-password" placeholder="At least 6 characters" class="nexora-input pr-10" :class="errors.password && 'border-red-400'">
                                    <button type="button" class="absolute inset-y-0 right-0 px-3 text-zinc-400 hover:text-brand" @click="showNewPassword = !showNewPassword" :aria-label="showNewPassword ? 'Hide password' : 'Show password'">
                                        <x-lucide-eye class="w-4 h-4" x-show="!showNewPassword" />
                                        <x-lucide-eye-off class="w-4 h-4" x-show="showNewPassword" />
                                    </button>
                                </div>
                                <p x-show="errors.password" x-text="errors.password" class="mt-1 text-xs text-red-600"></p>
                            </div>

                            <div>
                                <label for="new-password-confirmation" class="block text-sm font-medium text-zinc-700 mb-1">Confirm new password</label>
                                <input id="new-password-confirmation" :type="showNewPassword ? 'text' : 'password'" x-model="newPasswordConfirmation" autocomplete="new-password" placeholder="Repeat the new password" class="nexora-input">
                            </div>
                        </div>
                    </template>

                    {{-- Cloudflare Turnstile (one widget shared by all three modes) --}}
                    <div>
                        <div x-ref="turnstile" class="min-h-[65px]"></div>
                        <p x-cloak x-show="errors.turnstile_token" x-text="errors.turnstile_token" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <button type="submit" class="nexora-btn nexora-btn-primary w-full justify-center py-2.5 disabled:opacity-60 disabled:cursor-not-allowed" :disabled="loading">
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
    </div>
</x-layouts.base>
