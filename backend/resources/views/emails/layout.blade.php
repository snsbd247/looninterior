<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $site }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:Arial,Helvetica,sans-serif;color:#1c1c1c;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#1c1c1c;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:.04em;">
        {{ $site }}
      </td></tr>
      <tr><td style="padding:24px;font-size:15px;line-height:1.6;">
        @yield('content')
      </td></tr>
      <tr><td style="padding:14px 24px;background:#faf8f5;color:#888888;font-size:12px;">
        This is an automatic e-mail from your website.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
