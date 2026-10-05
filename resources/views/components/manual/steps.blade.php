{{-- Numbered steps: each <li> gets a black circle number (see .manual-steps in app.css). --}}
<ol {{ $attributes->class('manual-steps space-y-3') }}>
    {{ $slot }}
</ol>
