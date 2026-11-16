@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">Reset your admin password</h2>
<p style="margin:0 0 18px;">Hello {{ $name }}, we received a request to reset the password of your admin account. Click the button below to choose a new password.</p>
<p style="margin:0 0 18px;">
  <a href="{{ $url }}" style="display:inline-block;background:#b08d57;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:4px;font-weight:bold;">Reset Password</a>
</p>
<p style="margin:0 0 8px;color:#666666;font-size:13px;">This link expires in {{ $minutes }} minutes and can be used once.</p>
<p style="margin:0;color:#666666;font-size:13px;">If you did not request this, you can ignore this e-mail. Your password will not change.</p>
@endsection
