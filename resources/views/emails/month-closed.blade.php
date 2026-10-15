<x-mail-layout :logo="$message->embed(public_path('brand/splitnest-icon-512.png'))">
    <h1 style="margin:0 0 20px;font-size:20px;line-height:1.3;color:#123E45;">{{ __('mail.closing_heading', ['month' => $closing->month]) }}</h1>
    <p style="margin:0 0 20px;">{!! nl2br(e($bodyText)) !!}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background:#edf5f3;border-radius:8px;padding:14px 16px;font-size:13px;color:#3f6561;">
                {{ __('mail.pdf_note') }}
            </td>
        </tr>
    </table>
</x-mail-layout>

