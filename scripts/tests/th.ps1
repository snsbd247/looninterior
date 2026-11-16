# Shared test helpers (dot-source). Creates temp role users on demand.
$ErrorActionPreference = 'Continue'
$B = 'http://localhost/loon'
$script:ok = 0; $script:bad = 0
function Http($method, $path, $body = $null, $token = $null, $branch = $null) {
  $h = @{ Accept = 'application/json' }
  if ($token) { $h.Authorization = "Bearer $token" }
  if ($branch) { $h['X-Branch-Id'] = "$branch" }
  $p = @{ UseBasicParsing = $true; Uri = "$B$path"; Method = $method; Headers = $h }
  if ($null -ne $body) { $p.ContentType = 'application/json; charset=utf-8'; $p.Body = [Text.Encoding]::UTF8.GetBytes(($body | ConvertTo-Json -Depth 8)) }
  try { $r = Invoke-WebRequest @p; return @{ S = [int]$r.StatusCode; B = $r.Content; J = ($r.Content | ConvertFrom-Json -ErrorAction SilentlyContinue) } }
  catch { $resp = $_.Exception.Response; if (-not $resp) { return @{ S = 0; B = $_.Exception.Message } }; $t = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd(); return @{ S = [int]$resp.StatusCode; B = $t; J = ($t | ConvertFrom-Json -ErrorAction SilentlyContinue) } }
}
function Check($name, $res, $expect) {
  if ($res.S -eq $expect) { $script:ok++; "  ok   $name" } else { $script:bad++; "  FAIL $name expected $expect got $($res.S): $($res.B.Substring(0, [Math]::Min(300, $res.B.Length)))" }
}
function Assert($name, $cond, $detail = '') { if ($cond) { $script:ok++; "  ok   $name" } else { $script:bad++; "  FAIL $name $detail" } }
function Login($email, $pw) { (Http POST '/api/admin/login' @{ email = $email; password = $pw }).J.token }
$adminPw = (Select-String $PSScriptRoot\..\..\backend\.env -Pattern '^ADMIN_PASSWORD=(.*)$').Matches[0].Groups[1].Value
$T = Login 'admin@looninterior.com' $adminPw
function RoleUser($slug) {
  $roles = (Http GET '/api/business/roles' $null $T).J.data
  $rid = ($roles | Where-Object slug -eq $slug).id
  Http POST '/api/business/users' @{ name = "Test $slug"; email = "test-$slug@example.com"; role_id = $rid; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T | Out-Null
  return (Login "test-$slug@example.com" 'TestPass#123')
}
function Done() { ""; "RESULT: $script:ok passed, $script:bad failed" }
