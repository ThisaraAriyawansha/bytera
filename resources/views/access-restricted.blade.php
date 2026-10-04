@php
    $message = $module
        ? "You don't have permission to view the {$module} page."
        : "You don't have permission to view this page.";
@endphp

<x-layouts.app>
    <x-access-restricted :message="$message" />
</x-layouts.app>
