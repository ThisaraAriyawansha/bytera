@extends('mail.layout')

@section('title', 'Low stock alert')

@php
    $cell = 'padding:8px 6px; border-bottom:1px solid #f4f4f5;';
    $head = 'padding:8px 6px; border-bottom:1px solid #e4e4e7; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:#71717a;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Low stock alert</h1>
    <p style="margin:0 0 20px;">{{ $products->count() === 1 ? 'This product has' : 'These products have' }} dropped to the low stock level after a sale. Restock with a GRN when you can.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; font-size:13px;">
        <tr>
            <td style="{{ $head }}">Product</td>
            <td style="{{ $head }}">SKU</td>
            <td style="{{ $head }} text-align:right;">Stock left</td>
            <td style="{{ $head }} text-align:right;">Threshold</td>
        </tr>
        @foreach ($products as $product)
            <tr>
                <td style="{{ $cell }}">{{ $product->name }}</td>
                <td style="{{ $cell }} color:#71717a;">{{ $product->sku }}</td>
                <td style="{{ $cell }} text-align:right; font-weight:700; color:{{ $product->total_stock === 0 ? '#e30613' : '#a16207' }};">{{ number_format($product->total_stock) }}</td>
                <td style="{{ $cell }} text-align:right; color:#71717a;">{{ number_format($product->low_stock_alert) }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0; color:#71717a; font-size:13px;">You get this email once per product until it is restocked.</p>
@endsection
