@extends('mail.layout')

@section('title', 'Salary Payment '.$payment->payment_no)

@php
    $cell = 'padding:8px 0; border-bottom:1px solid #f4f4f5;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Salary Payment {{ $payment->payment_no }}</h1>
    <p style="margin:0 0 12px;">Dear {{ $payment->user_name }},</p>
    <p style="margin:0 0 20px;">Here is your payslip from {{ $shop->name }}.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:14px;">
        <tr>
            <td style="{{ $cell }} color:#71717a;">Type</td>
            <td style="{{ $cell }} text-align:right;">{{ $payment->typeLabel() }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Period</td>
            <td style="{{ $cell }} text-align:right;">{{ $payment->period_label }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Date</td>
            <td style="{{ $cell }} text-align:right;">{{ $payment->created_at->format('M j, Y') }}</td>
        </tr>
    </table>

    <p style="margin:0 0 8px; font-weight:600;">How this amount was calculated</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:14px;">
        @foreach ($calculation as $line)
            @if ($loop->last && count($calculation) > 1)
                <tr>
                    <td style="padding:10px 0; font-weight:700;">{{ $line['label'] }}</td>
                    <td style="padding:10px 0; text-align:right; font-weight:700; color:#e30613;">{{ \App\Support\Money::format($line['amount']) }}</td>
                </tr>
            @else
                <tr>
                    <td style="{{ $cell }} color:#71717a;">{{ $line['label'] }}</td>
                    <td style="{{ $cell }} text-align:right;">{{ \App\Support\Money::format($line['amount']) }}</td>
                </tr>
            @endif
        @endforeach
        @if (count($calculation) === 1)
            <tr>
                <td style="padding:10px 0; font-weight:700;">Amount paid</td>
                <td style="padding:10px 0; text-align:right; font-weight:700; color:#e30613;">{{ \App\Support\Money::format($payment->amount) }}</td>
            </tr>
        @endif
    </table>

    @if ($items !== [])
        <p style="margin:0 0 8px; font-weight:600;">Linked sales / jobs</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:13px;">
            @foreach ($items as $item)
                <tr>
                    <td style="{{ $cell }}">{{ $item['number'] }}</td>
                    <td style="{{ $cell }} color:#71717a;">{{ $item['kind'] === 'job' ? 'Job' : 'Sale' }}</td>
                    <td style="{{ $cell }} text-align:right;">{{ \App\Support\Money::format($item['amount']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (filled($payment->note))
        <p style="margin:0 0 20px; color:#52525b;"><strong>Note:</strong> {{ $payment->note }}</p>
    @endif

    <p style="margin:0; color:#71717a; font-size:13px;">If anything here doesn't look right, please speak to {{ $payment->issued_by_name }} or reply to this email.</p>
@endsection
