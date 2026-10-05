{{-- Bullet list: each <li> gets a red dot (see .manual-bullets in app.css). --}}
<ul {{ $attributes->class('manual-bullets space-y-1.5') }}>
    {{ $slot }}
</ul>
