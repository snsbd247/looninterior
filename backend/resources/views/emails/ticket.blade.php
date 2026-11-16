@extends('emails.layout')

@section('content')
@if ($forClient)
<h2 style="margin:0 0 12px;font-size:20px;">We received your service request</h2>
<p style="margin:0 0 16px;">Dear {{ $t->contact_name }}, thank you for contacting us. Your ticket number is:</p>
<p style="margin:0 0 16px;font-size:24px;font-weight:bold;color:#b08d57;">{{ $t->number }}</p>
<p style="margin:0 0 8px;">Our service team will call you soon to schedule a visit. Please mention this number when you contact us.</p>
@else
<h2 style="margin:0 0 12px;font-size:20px;">New service request {{ $t->number }}</h2>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin-top:10px;">
@foreach (['Name' => $t->contact_name, 'Phone' => $t->contact_phone, 'Address' => $t->address, 'Subject' => $t->subject, 'Details' => $t->description] as $k => $v)
  @if (filled($v))
  <tr><td style="padding:7px 10px;border-bottom:1px solid #eeeeee;color:#888888;width:110px;vertical-align:top;">{{ $k }}</td><td style="padding:7px 10px;border-bottom:1px solid #eeeeee;white-space:pre-line;">{{ $v }}</td></tr>
  @endif
@endforeach
</table>
@if (! $forClient && ! empty($url))
<p style="margin:22px 0 0;"><a href="{{ $url }}" style="display:inline-block;background:#1c1c1c;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;">Open ticket</a></p>
@endif
@endsection
