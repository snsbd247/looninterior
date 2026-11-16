<#
  Loon Interior: regression smoke test.
  Checks that every existing module still works (public site, SEO, forms, admin API).
  Run it BEFORE starting and AFTER finishing every task. Read-only: it creates no data.

  Usage (from the project root):
    powershell -ExecutionPolicy Bypass -File scripts\smoke-test.ps1
    powershell -ExecutionPolicy Bypass -File scripts\smoke-test.ps1 -Base https://yourdomain.com

  Admin e-mail/password are read from backend\.env (ADMIN_EMAIL / ADMIN_PASSWORD)
  unless -Email / -Password are given.

  When a new module or endpoint is added, add its checks to the matching section below.
#>
param(
  [string]$Base = 'http://localhost/loon',
  [string]$Email = '',
  [string]$Password = ''
)

$ErrorActionPreference = 'Stop'
$script:pass = 0
$script:fail = 0
$script:failures = @()

function Report([bool]$ok, [string]$name, [string]$detail = '') {
  if ($ok) { $script:pass++; Write-Host "  PASS  $name" -ForegroundColor Green }
  else { $script:fail++; $script:failures += "$name $detail"; Write-Host "  FAIL  $name $detail" -ForegroundColor Red }
}

function Http([string]$method, [string]$url, $body = $null, [hashtable]$headers = @{}) {
  $h = @{ Accept = 'application/json' } + $headers
  try {
    $params = @{ UseBasicParsing = $true; Uri = $url; Method = $method; Headers = $h }
    if ($null -ne $body) {
      $params.ContentType = 'application/json; charset=utf-8'
      $params.Body = [Text.Encoding]::UTF8.GetBytes(($body | ConvertTo-Json -Depth 6))
    }
    $r = Invoke-WebRequest @params
    return @{ Status = [int]$r.StatusCode; Body = $r.Content }
  } catch {
    $resp = $_.Exception.Response
    if ($null -eq $resp) { return @{ Status = 0; Body = $_.Exception.Message } }
    $text = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd()
    return @{ Status = [int]$resp.StatusCode; Body = $text }
  }
}

function Json($res) { try { return $res.Body | ConvertFrom-Json } catch { return $null } }

function Expect([string]$name, [string]$method, [string]$path, [int]$status, $body = $null, [hashtable]$headers = @{}) {
  $res = Http $method "$Base$path" $body $headers
  Report ($res.Status -eq $status) $name "(expected $status, got $($res.Status))"
  return $res
}

# Credentials from backend\.env
if (-not $Email -or -not $Password) {
  $envFile = Join-Path $PSScriptRoot '..\backend\.env'
  if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile) {
      if (-not $Email -and $line -match '^ADMIN_EMAIL=(.*)$') { $Email = $Matches[1].Trim('"') }
      if (-not $Password -and $line -match '^ADMIN_PASSWORD=(.*)$') { $Password = $Matches[1].Trim('"') }
    }
  }
}

Write-Host "`nSmoke test: $Base`n" -ForegroundColor Cyan

# ---------------------------------------------------------------- Public pages
Write-Host '[Public pages]'
foreach ($p in '/', '/about', '/services', '/projects', '/blog', '/careers', '/contact', '/get-quote', '/faq', '/service-request') {
  $res = Expect "page $p" GET $p 200
  if ($res.Status -eq 200 -and -not $res.Body.Contains('id="root"')) { Report $false "page $p has React root" }
}
Expect 'unknown page returns 404' GET '/this-page-does-not-exist' 404 | Out-Null

# ---------------------------------------------------------------- Public API
Write-Host '[Public API]'
$site = Json (Expect 'GET /api/site' GET '/api/site' 200)
Report ([bool]$site.settings.site_name) 'site settings present'
Report ([bool]$site.form_token) 'form_token issued'
Report ($null -eq $site.settings.notify_email) 'private settings not exposed'
foreach ($p in '/api/home', '/api/about', '/api/services', '/api/projects', '/api/posts', '/api/careers', '/api/faqs', '/api/testimonials') {
  Expect "GET $p" GET $p 200 | Out-Null
}
foreach ($pair in @(@('/api/services', '/services/'), @('/api/projects', '/projects/'), @('/api/posts', '/blog/'), @('/api/careers', '/careers/'))) {
  $list = Json (Http GET "$Base$($pair[0])")
  $slug = @($list.data)[0].slug
  if ($slug) {
    Expect "GET $($pair[0])/$slug" GET "$($pair[0])/$slug" 200 | Out-Null
    $page = Expect "page $($pair[1])$slug" GET "$($pair[1])$slug" 200
    Report ($page.Body -match '<link rel="canonical"') "SEO tags on $($pair[1])$slug"
  }
}

# ---------------------------------------------------------------- SEO
Write-Host '[SEO]'
$sm = Expect 'GET /sitemap.xml' GET '/sitemap.xml' 200
Report ($sm.Body -match '<urlset') 'sitemap has urlset'
$rb = Expect 'GET /robots.txt' GET '/robots.txt' 200
Report ($rb.Body -match 'Sitemap:') 'robots has Sitemap line'

# ---------------------------------------------------------------- Forms (no data is created)
Write-Host '[Forms / spam guard]'
Expect 'inquiry without token rejected' POST '/api/inquiries' 422 @{ name = 'Smoke'; phone = '0' } | Out-Null
Expect 'inquiry with honeypot silently dropped' POST '/api/inquiries' 201 @{ name = 'Smoke'; phone = '0'; website = 'x'; form_token = $site.form_token } | Out-Null

# ---------------------------------------------------------------- Admin
Write-Host '[Admin API]'
Expect 'admin API requires login' GET '/api/admin/me' 401 | Out-Null
if (-not $Email -or -not $Password) {
  Report $false 'admin credentials' '(set ADMIN_EMAIL/ADMIN_PASSWORD in backend\.env or pass -Email/-Password)'
} else {
  $login = Json (Expect 'admin login' POST '/api/admin/login' 200 @{ email = $Email; password = $Password })
  if ($login.token) {
    $auth = @{ Authorization = "Bearer $($login.token)" }
    Expect 'GET /api/admin/me' GET '/api/admin/me' 200 $null $auth | Out-Null
    Expect 'GET /api/admin/dashboard' GET '/api/admin/dashboard' 200 $null $auth | Out-Null
    Expect 'GET /api/admin/settings' GET '/api/admin/settings' 200 $null $auth | Out-Null

    # Website content resources (generic CRUD). Keep in sync with ResourceRegistry.php
    foreach ($r in 'sliders', 'services', 'project_categories', 'projects', 'testimonials', 'clients', 'team_members',
                   'stats', 'process_steps', 'features', 'faqs', 'posts', 'pages', 'careers', 'inquiries', 'job_applications') {
      Expect "list $r" GET "/api/admin/$r" 200 $null $auth | Out-Null
    }

    # ---- Business modules: add new checks here as each step is built ----

    # Step 1: foundation (roles, branches, users, settings, activity log, PDF)
    $me = Json (Http GET "$Base/api/admin/me" $null $auth)
    Report (@($me.user.permissions).Count -gt 0) 'me returns permissions'
    Report (@($me.user.branches).Count -gt 0) 'me returns branches'
    foreach ($p in '/api/business/permissions', '/api/business/roles', '/api/business/users', '/api/business/settings', '/api/business/activity-logs') {
      Expect "GET $p" GET $p 200 $null $auth | Out-Null
    }
    Expect 'list branches' GET '/api/admin/branches' 200 $null $auth | Out-Null
    Expect 'list tax_rates' GET '/api/admin/tax_rates' 200 $null $auth | Out-Null
    if ($me.user.can_all_branches) {
      Expect 'central view (X-Branch-Id: all)' GET '/api/business/activity-logs' 200 $null ($auth + @{ 'X-Branch-Id' = 'all' }) | Out-Null
    }
    Expect 'unknown branch rejected' GET '/api/business/activity-logs' 403 $null ($auth + @{ 'X-Branch-Id' = '999999' }) | Out-Null
    Expect 'business API requires login' GET '/api/business/users' 401 | Out-Null
    # Step 2: CRM
    foreach ($p in '/api/business/leads', '/api/business/leads?view=board', '/api/business/follow-ups', '/api/business/customers',
                   '/api/business/lookup/staff', '/api/business/lookup/tax-rates', '/api/business/lookup/branches',
                   '/api/business/quotations',
                   '/api/business/projects', '/api/business/invoices', '/api/business/payments', '/api/business/receivables',
                   '/api/business/accounts', '/api/business/transactions', '/api/business/lookup/accounts', '/api/business/lookup/categories',
                   '/api/business/reports/cashbook', '/api/business/reports/summary', '/api/business/reports/profit-loss',
                   '/api/business/reports/projects', '/api/business/reports/branches',
                   '/api/business/stock', '/api/business/stock-entries', '/api/business/purchase-orders', '/api/business/purchases',
                   '/api/business/suppliers', '/api/business/lookup/stores', '/api/business/lookup/items',
                   '/api/business/tickets', '/api/business/tickets/report', '/api/business/dashboard',
                   '/api/business/calendar', '/api/business/employees', '/api/business/attendance', '/api/business/attendance/month',
                   '/api/business/leaves', '/api/business/payrolls', '/api/business/labour', '/api/business/contractors',
                   '/api/business/campaigns') {
      Expect "GET $p" GET $p 200 $null $auth | Out-Null
    }

    try {
      $pdf = Invoke-WebRequest -UseBasicParsing -Uri "$Base/api/business/pdf/sample?lang=bn" -Headers $auth
      Report ($pdf.Headers['Content-Type'] -eq 'application/pdf') 'sample PDF (bn) renders'
    } catch { Report $false 'sample PDF (bn) renders' $_.Exception.Message }

    # Client portal: staff token must be refused; customer token is refused on staff APIs (scripts/tests/test-portal.ps1)
    Expect 'portal rejects staff token' GET '/api/portal/me' 401 $null $auth | Out-Null
    Expect 'unsubscribe needs signature' GET '/api/public/unsubscribe/customer/1' 403 | Out-Null
    Expect 'portal login validates' POST '/api/portal/login' 422 @{ login = 'nobody@example.com'; password = 'x' } | Out-Null
    Expect 'GET /portal page' GET '/portal' 200 | Out-Null

    Expect 'admin logout' POST '/api/admin/logout' 200 $null $auth | Out-Null
  }
}

# ---------------------------------------------------------------- Result
Write-Host ''
if ($script:fail -eq 0) {
  Write-Host "ALL PASSED ($($script:pass) checks)" -ForegroundColor Green
  exit 0
} else {
  Write-Host "FAILED: $($script:fail) of $($script:pass + $script:fail) checks" -ForegroundColor Red
  $script:failures | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
  exit 1
}
