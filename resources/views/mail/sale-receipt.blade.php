@extends('mail.layout')

@section('title', "Receipt {$sale->invoice_no}")

@php
    use App\Support\Money;

    $cell = 'padding:8px 6px; border-bottom:1px solid #f4f4f5;';
    $head = 'padding:8px 6px; border-bottom:1px solid #e4e4e7; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:#71717a;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Your receipt {{ $sale->invoice_no }}</h1>
    <p style="margin:0 0 12px;">Dear {{ $sale->customer_name }},</p>
    <p style="margin:0 0 20px;">Thank you for your purchase at {{ $shop->name }} on {{ $sale->created_at->format('M j, Y · g:i A') }}. Your bill is attached as a PDF.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:13px;">
        <tr>
            <td style="{{ $head }}">Item</td>
            <td style="{{ $head }} text-align:right;">Qty</td>
            <td style="{{ $head }} text-align:right;">Total</td>
        </tr>
        @foreach ($sale->services ?? [] as $service)
            <tr>
                <td style="{{ $cell }}">{{ $service['name'] }} <span style="color:#71717a;">(Service)</span></td>
                <td style="{{ $cell }} text-align:right;">1</td>
                <td style="{{ $cell }} text-align:right;">{{ Money::format($service['price']) }}</td>
            </tr>
        @endforeach
        @foreach ($sale->items as $item)
            <tr>
                <td style="{{ $cell }}">{{ $item->product_name }}</td>
                <td style="{{ $cell }} text-align:right;">{{ $item->qty }}</td>
                <td style="{{ $cell }} text-align:right;">{{ Money::format($item->line_total) }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:14px;">
        <tr>
            <td style="padding:4px 0; color:#71717a;">Subtotal</td>
            <td style="padding:4px 0; text-align:right;">{{ Money::format($sale->subtotal) }}</td>
        </tr>
        @if ((float) $sale->discount_amount > 0)
            <tr>
                <td style="padding:4px 0; color:#71717a;">Discount</td>
                <td style="padding:4px 0; text-align:right; color:#e30613;">- {{ Money::format($sale->discount_amount) }}</td>
            </tr>
        @endif
        @if ($sale->points_redeemed > 0)
            <tr>
                <td style="padding:4px 0; color:#71717a;">Points redeemed</td>
                <td style="padding:4px 0; text-align:right; color:#7e22ce;">- {{ Money::format($sale->points_redeemed) }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:10px 0; border-top:2px solid #0a0a0a; font-weight:700;">Total</td>
            <td style="padding:10px 0; border-top:2px solid #0a0a0a; text-align:right; font-weight:700;">{{ Money::format($sale->total_amount) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0; color:#71717a;">Payment</td>
            <td style="padding:4px 0; text-align:right;">
                @if (count($sale->paymentLegs()) > 1)
                    {{ collect($sale->paymentLegs())->map(fn ($leg) => $leg['label'].': '.Money::format($leg['amount']))->implode(' · ') }}
                @else
                    {{ $sale->paymentLabel() }}
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#71717a; font-size:13px;">Please keep this receipt for warranty claims.</p>
@endsection
