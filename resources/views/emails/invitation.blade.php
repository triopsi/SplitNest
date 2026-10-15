<x-mail-layout :logo="$message->embed(public_path('brand/splitnest-icon-512.png'))">
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#123E45;">{{ __('mail.invitation.title', ['app' => config('app.name')]) }}</h1>
    <p style="margin:0 0 24px;">{{ __('mail.invitation.intro', ['app' => config('app.name')]) }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;background:#15816F;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 32px;border-radius:8px;">{{ __('mail.invitation.button') }}</a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 20px;font-size:13px;color:#557b78;">{{ __('mail.invitation.validity', ['hours' => $hours]) }}</p>
    <p style="margin:0 0 24px;font-size:13px;color:#557b78;">{{ __('mail.invitation.fallback') }}<br><a href="{{ $url }}" style="color:#15816F;word-break:break-all;">{{ $url }}</a></p>
    <hr style="border:none;border-top:1px solid #e2ebe9;margin:0 0 20px;">
    <p style="margin:0;font-size:12px;color:#8aa5a2;">{{ __('mail.invitation.ignore') }}</p>
</x-mail-layout>

