@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">{{ $title }} {{ $number }}</h2>
@if (filled($body))
<p style="margin:0 0 16px;white-space:pre-line;">{{ $body }}</p>
@else
<p style="margin:0 0 16px;">Dear Sir/Madam,<br>Please find the attached {{ strtolower($title) }} <b>{{ $number }}</b>.</p>
@endif
<p style="margin:0 0 16px;">Amount: <b>{{ $total }}</b></p>
<p style="margin:0;color:#666666;font-size:13px;">The document is attached as a PDF. Please reply to this e-mail if you have any questions.</p>
<p style="margin:18px 0 0;">Thank you,<br><b>{{ $site }}</b></p>
@endsection
