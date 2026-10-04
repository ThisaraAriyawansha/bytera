@extends('mail.layout-text')

@section('content')
Account Statement

Dear {!! $supplier->name !!},

Here is the current statement of your account with {!! $shop->name !!} as of {{ now()->format('M j, Y') }}.

Total payable: {{ \App\Support\Money::format($supplier->total_payable) }}
Amount paid:   {{ \App\Support\Money::format($supplier->amount_paid) }}
Balance:       {{ \App\Support\Money::format($supplier->balance) }}
@if ($payments->isNotEmpty())

Recent payments
@foreach ($payments as $payment)
- {{ $payment->payment_no }} · {{ $payment->created_at->format('M j, Y') }} · {{ \App\Models\SupplierPayment::METHODS[$payment->method] ?? $payment->method }} · {{ \App\Support\Money::format($payment->amount) }}
@endforeach
@endif

If anything here doesn't match your records, please reply to this email or call us.
@endsection
