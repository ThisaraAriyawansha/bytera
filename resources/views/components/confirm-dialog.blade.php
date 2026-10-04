@props([
    'name',
    'title' => 'Are you sure?',
    'message' => '',
    'confirmLabel' => 'Confirm',
    'action' => null,
    'method' => 'DELETE',
])

{{--
    Open with `$dispatch('open-modal', '{{ $name }}')`, or override per row with
    `$dispatch('open-modal', { name, title, message, action, payload })`.
--}}
<div x-data="confirmDialog(@js(['name' => $name, 'title' => $title, 'message' => $message, 'action' => $action]))"
     x-on:open-modal.window="open($event.detail)"
     x-on:close-modal.window="($event.detail?.name ?? $event.detail) === name && (show = false)"
     x-on:keydown.escape.window="show && ! busy && (show = false)"
     x-show="show" x-cloak
     x-transition.opacity.duration.150ms
     x-on:click.self="busy || (show = false)"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
     role="alertdialog" aria-modal="true"
     {{ $attributes }}>
    <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-5 animate-fadeIn">
        <h3 class="font-prata text-lg text-ink" x-text="title">{{ $title }}</h3>
        <p class="mt-1 text-sm text-zinc-500" x-show="message" x-text="message">{{ $message }}</p>

        {{ $slot }}

        <form method="POST" x-ref="form" class="mt-5 flex justify-end gap-2" x-on:submit.prevent="confirm()">
            @csrf
            @method($method)
            <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="show = false" x-bind:disabled="busy">Cancel</button>
            <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="busy">
                <span x-show="! busy">{{ $confirmLabel }}</span>
                <span x-show="busy" x-cloak>Please wait…</span>
            </button>
        </form>
    </div>
</div>
