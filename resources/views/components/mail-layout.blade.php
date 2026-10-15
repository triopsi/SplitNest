@props(['logo'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f0f5f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f5f4;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">
                <tr>
                    <td style="text-align:center;padding-bottom:20px;">
                        <img src="{{ $logo }}" alt="{{ config('app.name') }}" width="56" height="56" style="display:inline-block;width:56px;height:56px;border-radius:14px;vertical-align:middle;">
                        <span style="display:inline-block;vertical-align:middle;margin-left:10px;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#123E45;">{{ config('app.name') }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border:1px solid #d9e6e2;border-radius:12px;padding:32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f3a3d;">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align:center;padding-top:16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b8a87;">
                        {{ config('app.name') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
