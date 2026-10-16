{{ __('mail.password_reset.title') }}

{{ __('mail.password_reset.intro', ['app' => config('app.name')]) }}

{{ __('mail.password_reset.button') }}:
{!! $url !!}

{{ __('mail.password_reset.validity', ['minutes' => $minutes]) }}

{{ __('mail.password_reset.ignore') }}
