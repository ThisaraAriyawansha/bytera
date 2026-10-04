@extends('mail.layout-text')

@section('content')
Confirm your new email

Hi {!! $userName !!},

You asked to change the email on your {!! $shop->name !!} account to {!! $newEmail !!}. Open the link below to confirm. Your email won't change until you do.

{!! $confirmationUrl !!}

If you didn't ask for this change, you can ignore this email.
@endsection
