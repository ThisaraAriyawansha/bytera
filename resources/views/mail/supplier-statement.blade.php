@extends('mail.layout')

@section('title', 'Account Statement')

@php
    $cell = 'padding:8px 0; border-bottom:1px solid #f4f4f5;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Account Statement</h1>
    <p style="margin:0 0 12px;">Dear {{ $supplier->name }},</p>
    <p style="margin:0 0 20px;">Here is the current statement of your account with {{ $shop->name }} as of {{ now()->format('M j, Y') }}.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; font-size:14px;">
        <tr>
            <td style="{{ $cell }} color:#71717a;">Total payable</td>
            <td style="{{ $cell }} text-align:right;">{{ \App\Support\Money::format($supplier->total_payable) }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Amount paid</td>
            <td style="{{ $cell }} text-align:right;">{{ \App\Support\Money::format($supplier->amount_paid) }}</td>
        </tr>
        <tr>
            <td style="padding:10px 0; font-weight:700;">Balance</td>
            <td style="padding:10px 0; text-align:right; font-weight:700; color:#e30613;">{{ \App\Support\Money::format($supplier->balance) }}</td>
        </tr>
    </table>

    @if ($payments->isNotEmpty())
        <p style="margin:0 0 8px; font-weight:600;">Recent payments</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:13px;">
            @foreach ($payments as $payment)
                <tr>
                    <td style="{{ $cell }}">{{ $payment->payment_no }}</td>
                    <td style="{{ $cell }} color:#71717a;">{{ $payment->created_at->format('M j, Y') }}</td>
                    <td style="{{ $cell }} color:#71717a;">{{ \App\Models\SupplierPayment::METHODS[$payment->method] ?? $payment->method }}</td>
                    <td style="{{ $cell }} text-align:right;">{{ \App\Support\Money::format($payment->amount) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin:0; color:#71717a; font-size:13px;">If anything here doesn't match your records, please reply to this email or call us.</p>
@endsection
