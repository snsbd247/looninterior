@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 6px;font-size:20px;">New {{ $label }}</h2>
<p style="margin:0 0 18px;color:#666666;">Received {{ $i->created_at?->format('d M Y, h:i A') }}</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
@foreach ([
    'Name' => $i->name,
    'Phone' => $i->phone,
    'Email' => $i->email,
    'Service' => $i->service,
    'Property Type' => $i->property_type,
    'Area Size' => $i->area_size,
    'Budget' => $i->budget,
    'Location' => $i->location,
] as $field => $value)
  @if (filled($value))
  <tr>
    <td style="padding:8px 10px;border-bottom:1px solid #eeeeee;color:#888888;width:130px;vertical-align:top;">{{ $field }}</td>
    <td style="padding:8px 10px;border-bottom:1px solid #eeeeee;font-weight:bold;">{{ $value }}</td>
  </tr>
  @endif
@endforeach
</table>

@if (filled($i->message))
<p style="margin:18px 0 6px;color:#888888;font-size:13px;">Message</p>
<div style="background:#faf8f5;border-left:3px solid #b08d57;padding:12px 14px;white-space:pre-line;">{{ $i->message }}</div>
@endif

<p style="margin:24px 0 0;">
  <a href="tel:{{ preg_replace('/[^0-9+]/', '', $i->phone) }}" style="display:inline-block;background:#b08d57;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;margin:0 6px 6px 0;">Call {{ $i->phone }}</a>
  <a href="{{ $url }}" style="display:inline-block;background:#1c1c1c;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;margin:0 0 6px;">Open in Admin Panel</a>
</p>
@endsection
