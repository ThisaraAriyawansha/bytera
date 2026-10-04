<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $shop->name)</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:Poppins, 'Segoe UI', Arial, sans-serif; color:#0a0a0a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border:1px solid #e4e4e7; border-radius:8px; overflow:hidden;">
                <tr>
                    <td style="background:#e30613; padding:20px 28px;">
                        <span style="font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:20px; font-weight:700; color:#ffffff;">{{ $shop->name }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px; font-size:14px; line-height:1.6; color:#0a0a0a;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="background:#fafafa; border-top:1px solid #e4e4e7; padding:16px 28px; font-size:12px; line-height:1.6; color:#71717a;">
                        <strong style="color:#52525b;">{{ $shop->name }}</strong>
                        @if (filled($shop->phone))
                            <br>Phone: {{ $shop->phone }}
                        @endif
                        @if (filled($shop->email))
                            <br>Email: {{ $shop->email }}
                        @endif
                        @if (filled($shop->address))
                            <br>{{ $shop->address }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
