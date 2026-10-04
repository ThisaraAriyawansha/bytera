@extends('mail.layout')

@section('title', 'Confirm your new email')

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Confirm your new email</h1>
    <p style="margin:0 0 12px;">Hi {{ $userName }},</p>
    <p style="margin:0 0 20px;">You asked to change the email on your {{ $shop->name }} account to <strong>{{ $newEmail }}</strong>. Click the button below to confirm. Your email won't change until you do.</p>
    <p style="margin:0 0 20px;">
        <a href="{{ $confirmationUrl }}" style="display:inline-block; padding:10px 20px; background:#e30613; color:#ffffff; text-decoration:none; font-size:14px; font-weight:500; border-radius:4px;">Confirm new email</a>
    </p>
    <p style="margin:0 0 8px; color:#71717a; font-size:13px;">Or copy this link into your browser:<br><a href="{{ $confirmationUrl }}" style="color:#e30613; word-break:break-all;">{{ $confirmationUrl }}</a></p>
    <p style="margin:0; color:#71717a; font-size:13px;">If you didn't ask for this change, you can ignore this email.</p>
@endsection
