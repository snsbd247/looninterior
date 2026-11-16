@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 6px;font-size:20px;">New job application</h2>
<p style="margin:0 0 18px;color:#666666;">For <strong>{{ $job }}</strong> · {{ $a->created_at?->format('d M Y, h:i A') }}</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
@foreach (['Name' => $a->name, 'Email' => $a->email, 'Phone' => $a->phone] as $label => $value)
  <tr>
    <td style="padding:8px 10px;border-bottom:1px solid #eeeeee;color:#888888;width:130px;">{{ $label }}</td>
    <td style="padding:8px 10px;border-bottom:1px solid #eeeeee;font-weight:bold;">{{ $value }}</td>
  </tr>
@endforeach
</table>

@if (filled($a->cover_letter))
<p style="margin:18px 0 6px;color:#888888;font-size:13px;">Cover letter</p>
<div style="background:#faf8f5;border-left:3px solid #b08d57;padding:12px 14px;white-space:pre-line;">{{ $a->cover_letter }}</div>
@endif

<p style="margin:18px 0 0;color:#666666;font-size:13px;">The CV is attached to this e-mail.</p>
<p style="margin:18px 0 0;">
  <a href="{{ $url }}" style="display:inline-block;background:#1c1c1c;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;">Open in Admin Panel</a>
</p>
@endsection
