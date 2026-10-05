@extends('mail.layout')

@section('title', 'Job '.$job->job_no)

@php
    use App\Support\Money;

    $cell = 'padding:8px 0; border-bottom:1px solid #f4f4f5;';
@endphp

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">We've received your device</h1>
    <p style="margin:0 0 12px;">Dear {{ $job->customer_name }},</p>
    <p style="margin:0 0 20px;">Thank you for choosing {{ $shop->name }}. Your device has been booked in for repair under job number <strong>{{ $job->job_no }}</strong>. Please keep this number for any enquiries.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; font-size:14px;">
        <tr>
            <td style="{{ $cell }} color:#71717a;">Job no.</td>
            <td style="{{ $cell }} text-align:right; font-weight:600;">{{ $job->job_no }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Received</td>
            <td style="{{ $cell }} text-align:right;">{{ $job->created_at->format('M j, Y g:i A') }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} color:#71717a;">Device</td>
            <td style="{{ $cell }} text-align:right;">{{ $job->deviceLabel() }}</td>
        </tr>
        @if (filled($job->serial_no))
            <tr>
                <td style="{{ $cell }} color:#71717a;">Serial no.</td>
                <td style="{{ $cell }} text-align:right;">{{ $job->serial_no }}</td>
            </tr>
        @endif
        <tr>
            <td style="{{ $cell }} color:#71717a; vertical-align:top;">Fault</td>
            <td style="{{ $cell }} text-align:right;">{{ $job->fault_description }}</td>
        </tr>
        @if ((float) $job->estimated_cost > 0)
            <tr>
                <td style="{{ $cell }} color:#71717a;">Estimated cost</td>
                <td style="{{ $cell }} text-align:right;">{{ Money::format($job->estimated_cost) }}</td>
            </tr>
        @endif
        @if ((float) $job->advance_paid > 0)
            <tr>
                <td style="{{ $cell }} color:#71717a;">Advance paid</td>
                <td style="{{ $cell }} text-align:right;">{{ Money::format($job->advance_paid) }}</td>
            </tr>
        @endif
        @if ($job->expected_delivery_date)
            <tr>
                <td style="{{ $cell }} color:#71717a;">Expected delivery</td>
                <td style="{{ $cell }} text-align:right;">{{ $job->expected_delivery_date->format('M j, Y') }}</td>
            </tr>
        @endif
    </table>

    <p style="margin:0; color:#71717a; font-size:13px;">We'll let you know as soon as there's an update on your repair.</p>
@endsection
