@extends('mail.layout')

@section('title', 'Your password reset code')

@section('content')
    <h1 style="margin:0 0 12px; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700;">Reset your password</h1>
    <p style="margin:0 0 20px;">Use this code to reset your {{ $shop->name }} password:</p>
    <div style="margin:0 0 20px; padding:18px; background:#fff1f1; border:1px dashed #e30613; border-radius:8px; text-align:center;">
        <span style="font-family:'Courier New', monospace; font-size:34px; font-weight:700; letter-spacing:10px; color:#e30613;">{{ $code }}</span>
    </div>
    <p style="margin:0 0 8px;">This code is valid for <strong>{{ $validMinutes }} minutes</strong>.</p>
    <p style="margin:0; color:#71717a; font-size:13px;">If you didn't ask to reset your password, you can ignore this email. Never share this code with anyone.</p>
@endsection
