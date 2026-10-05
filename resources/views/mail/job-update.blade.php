@extends('mail.layout')

@section('title', 'Job '.$job->job_no)

@php
    use App\Models\Job;
    use App\Support\Money;

    $cell = 'padding:8px 0; border-bottom:1px solid #f4f4f5;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Update on your repair</h1>
    <p style="margin:0 0 12px;">Dear {{ $job->customer_name }},</p>
    <p style="margin:0 0 20px;">There's an update on your job <strong>{{ $job->job_no }}</strong>.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; font-size:14px;">
        <tr>
            <td style="{{ $cell }} color:#71717a;">Status</td>
            <td style="{{ $cell }} text-align:right; font-weight:700; color:#e30613;">{{ Job::STATUSES[$update->status]['label'] ?? $update->status }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Device</td>
            <td style="{{ $cell }} text-align:right;">{{ $job->deviceLabel() }}</td>
        </tr>
        @if (filled($update->note))
            <tr>
                <td style="{{ $cell }} color:#71717a; vertical-align:top;">Note</td>
                <td style="{{ $cell }} text-align:right;">{{ $update->note }}</td>
            </tr>
        @endif
        @if ($update->repair_cost !== null)
            <tr>
                <td style="{{ $cell }} color:#71717a;">Repair cost</td>
                <td style="{{ $cell }} text-align:right; font-weight:600;">{{ Money::format($update->repair_cost) }}</td>
            </tr>
        @endif
        <tr>
            <td style="{{ $cell }} color:#71717a;">Updated</td>
            <td style="{{ $cell }} text-align:right;">{{ $update->created_at->format('M j, Y g:i A') }}</td>
        </tr>
    </table>

    @if ($update->status === 'done')
        <p style="margin:0 0 12px;">Your device is ready for pickup. Please bring your job number when you collect it.</p>
    @endif
    <p style="margin:0; color:#71717a; font-size:13px;">If you have any questions, reply to this email or call us.</p>
@endsection
