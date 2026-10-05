@extends('mail.layout-text')

@section('content')
Salary Payment {{ $payment->payment_no }}

Dear {!! $payment->user_name !!},

Here is your payslip from {!! $shop->name !!}.

Type:   {{ $payment->typeLabel() }}
Period: {!! $payment->period_label !!}
Date:   {{ $payment->created_at->format('M j, Y') }}

How this amount was calculated
@foreach ($calculation as $line)
- {!! $line['label'] !!}: {{ \App\Support\Money::format($line['amount']) }}
@endforeach
@if (count($calculation) === 1)
- Amount paid: {{ \App\Support\Money::format($payment->amount) }}
@endif
@if ($items !== [])

Linked sales / jobs
@foreach ($items as $item)
- {{ $item['number'] }} ({{ $item['kind'] === 'job' ? 'Job' : 'Sale' }}) · {{ \App\Support\Money::format($item['amount']) }}
@endforeach
@endif
@if (filled($payment->note))

Note: {!! $payment->note !!}
@endif

If anything here doesn't look right, please speak to {!! $payment->issued_by_name !!} or reply to this email.
@endsection
