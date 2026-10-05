@extends('mail.layout-text')

@section('content')
Update on your repair

Dear {!! $job->customer_name !!},

There's an update on your job {!! $job->job_no !!}.

Status:  {!! \App\Models\Job::STATUSES[$update->status]['label'] ?? $update->status !!}
Device:  {!! $job->deviceLabel() !!}
@if (filled($update->note))
Note:    {!! $update->note !!}
@endif
@if ($update->repair_cost !== null)
Repair cost: {{ \App\Support\Money::format($update->repair_cost) }}
@endif
Updated: {{ $update->created_at->format('M j, Y g:i A') }}
@if ($update->status === 'done')

Your device is ready for pickup. Please bring your job number when you collect it.
@endif

If you have any questions, reply to this email or call us.
@endsection
