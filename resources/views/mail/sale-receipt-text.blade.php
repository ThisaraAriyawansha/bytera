@extends('mail.layout-text')

@php
    use App\Support\Money;
@endphp

@section('content')
Your receipt {{ $sale->invoice_no }}

Dear {!! $sale->customer_name !!},

Thank you for your purchase at {!! $shop->name !!} on {{ $sale->created_at->format('M j, Y · g:i A') }}. Your bill is attached as a PDF.

@foreach ($sale->services ?? [] as $service)
- {!! $service['name'] !!} (Service) × 1 · {{ Money::format($service['price']) }}
@endforeach
@foreach ($sale->items as $item)
- {!! $item->product_name !!} × {{ $item->qty }} · {{ Money::format($item->line_total) }}
@endforeach

Subtotal: {{ Money::format($sale->subtotal) }}
@if ((float) $sale->discount_amount > 0)
Discount: - {{ Money::format($sale->discount_amount) }}
@endif
@if ($sale->points_redeemed > 0)
Points redeemed: - {{ Money::format($sale->points_redeemed) }}
@endif
Total:    {{ Money::format($sale->total_amount) }}
Payment:  {{ collect($sale->paymentLegs())->map(fn ($leg) => count($sale->paymentLegs()) > 1 ? $leg['label'].': '.Money::format($leg['amount']) : $leg['label'])->implode(' · ') }}

Please keep this receipt for warranty claims.
@endsection
