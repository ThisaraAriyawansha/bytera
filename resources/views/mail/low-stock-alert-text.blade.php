@extends('mail.layout-text')

@section('content')
Low stock alert

{{ $products->count() === 1 ? 'This product has' : 'These products have' }} dropped to the low stock level after a sale. Restock with a GRN when you can.

@foreach ($products as $product)
- {!! $product->name !!} ({!! $product->sku !!}) · {{ number_format($product->total_stock) }} left · threshold {{ number_format($product->low_stock_alert) }}
@endforeach

You get this email once per product until it is restocked.
@endsection
