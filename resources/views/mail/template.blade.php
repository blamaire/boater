<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#e12628; padding:20px 32px;">
                            <img src="{{ asset('img/branding/rzvg-logo.jpg') }}" alt="RZVG" height="40" style="display:block; height:40px; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1f2937; font-size:15px; line-height:1.6;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f9fafb; color:#6b7280; font-size:12px; line-height:1.5;">
                            <p style="margin:0;">Roei- en Zeilvereniging Gouda &middot; {{ now()->year }}</p>
                            @if ($unsubscribeUrl !== null)
                                <p style="margin:4px 0 0;">
                                    <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Afmelden voor dit soort e-mails</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if ($trackingPixelUrl !== null)
        <img src="{{ $trackingPixelUrl }}" alt="" width="1" height="1" style="display:none; width:1px; height:1px;">
    @endif
</body>
</html>
