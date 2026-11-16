@extends('emails.layout')

@section('content')
<div style="white-space:pre-line;">{{ $body }}</div>
<p style="margin:24px 0 0;color:#888888;font-size:12px;">
  You receive this e-mail because you contacted {{ $site }}.
  @if ($unsubscribe)
    <a href="{{ $unsubscribe }}" style="color:#888888;">Unsubscribe</a>
  @endif
</p>
@endsection
