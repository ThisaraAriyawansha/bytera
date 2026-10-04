@props(['message' => "You don't have permission to view this page."])

<div {{ $attributes->merge(['class' => 'p-4 sm:p-8 flex items-center justify-center min-h-[60vh]']) }}>
    <div class="nexora-card w-full max-w-md p-8 text-center animate-fadeIn">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-light text-brand">
            <x-lucide-lock class="w-6 h-6" />
        </div>
        <h1 class="font-prata text-2xl text-ink">Access Restricted</h1>
        <p class="mt-2 text-sm text-zinc-500">{{ $message }}</p>
    </div>
</div>
