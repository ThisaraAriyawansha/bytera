@extends('mail.layout-text')

@section('content')
Reset your password

Use this code to reset your {!! $shop->name !!} password:

    {!! $code !!}

This code is valid for {!! $validMinutes !!} minutes.

If you didn't ask to reset your password, you can ignore this email. Never share this code with anyone.
@endsection
