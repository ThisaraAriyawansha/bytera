{!! $shop->name !!}
========================================

@yield('content')

----------------------------------------
{!! $shop->name !!}
@if (filled($shop->phone))
Phone: {!! $shop->phone !!}
@endif
@if (filled($shop->email))
Email: {!! $shop->email !!}
@endif
@if (filled($shop->address))
{!! $shop->address !!}
@endif
