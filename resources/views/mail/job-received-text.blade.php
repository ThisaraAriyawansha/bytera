@extends('mail.layout-text')

@section('content')
We've received your device

Dear {!! $job->customer_name !!},

Thank you for choosing {!! $shop->name !!}. Your device has been booked in for repair under job number {!! $job->job_no !!}. Please keep this number for any enquiries.

Job no.:   {!! $job->job_no !!}
Received:  {{ $job->created_at->format('M j, Y g:i A') }}
Device:    {!! $job->deviceLabel() !!}
@if (filled($job->serial_no))
Serial no.: {!! $job->serial_no !!}
@endif
Fault:     {!! $job->fault_description !!}
@if ((float) $job->estimated_cost > 0)
Estimated cost: {{ \App\Support\Money::format($job->estimated_cost) }}
@endif
@if ((float) $job->advance_paid > 0)
Advance paid:   {{ \App\Support\Money::format($job->advance_paid) }}
@endif
@if ($job->expected_delivery_date)
Expected delivery: {{ $job->expected_delivery_date->format('M j, Y') }}
@endif

We'll let you know as soon as there's an update on your repair.
@endsection
