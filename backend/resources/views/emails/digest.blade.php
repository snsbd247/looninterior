@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 14px;font-size:19px;">{{ $title }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13.5px;">
  <tr>
    @foreach ($head as $h)
      <th style="text-align:left;padding:8px;background:#1c1c1c;color:#ffffff;font-weight:bold;">{{ $h }}</th>
    @endforeach
  </tr>
  @foreach ($rows as $row)
    <tr>
      @foreach ($row as $cell)
        <td style="padding:8px;border-bottom:1px solid #eeeeee;vertical-align:top;">{{ $cell }}</td>
      @endforeach
    </tr>
  @endforeach
</table>
<p style="margin:22px 0 0;">
  <a href="{{ $url }}" style="display:inline-block;background:#b08d57;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:bold;">Open Admin Panel</a>
</p>
@endsection
