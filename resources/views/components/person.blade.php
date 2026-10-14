@props(['name', 'email' => null, 'href' => null])
<span class="hh-person">@if($email && ($url = \App\Support\Gravatar::url($email)))<img src="{{ $url }}" alt="" class="hh-avatar" loading="lazy">@endif@if($href)<a class="hh-link" href="{{ $href }}"><b>{{ $name }}</b></a>@else<span>{{ $name }}</span>@endif</span>
