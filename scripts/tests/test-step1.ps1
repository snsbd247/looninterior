$ErrorActionPreference = 'Continue'
$B = 'http://localhost/loon'
$script:ok = 0; $script:bad = 0
function Http($method, $path, $body = $null, $token = $null, $branch = $null) {
  $h = @{ Accept = 'application/json' }
  if ($token) { $h.Authorization = "Bearer $token" }
  if ($branch) { $h['X-Branch-Id'] = "$branch" }
  $p = @{ UseBasicParsing = $true; Uri = "$B$path"; Method = $method; Headers = $h }
  if ($null -ne $body) { $p.ContentType = 'application/json; charset=utf-8'; $p.Body = [Text.Encoding]::UTF8.GetBytes(($body | ConvertTo-Json -Depth 5)) }
  try { $r = Invoke-WebRequest @p; return @{ S = [int]$r.StatusCode; B = $r.Content } }
  catch { $resp = $_.Exception.Response; return @{ S = [int]$resp.StatusCode; B = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd() } }
}
function Check($name, $res, $expect) {
  if ($res.S -eq $expect) { $script:ok++; "  ok   $name ($($res.S))" } else { $script:bad++; "  FAIL $name expected $expect got $($res.S): $($res.B.Substring(0, [Math]::Min(200, $res.B.Length)))" }
}
function Login($email, $pw) { (Http POST '/api/admin/login' @{ email = $email; password = $pw }).B | ConvertFrom-Json }

$adminPw = (Select-String $PSScriptRoot\..\..\backend\.env -Pattern '^ADMIN_PASSWORD=(.*)$').Matches[0].Groups[1].Value
$a = Login 'admin@looninterior.com' $adminPw
$T = $a.token
"admin permissions: $($a.user.permissions.Count), all branches: $($a.user.can_all_branches)"

"== setup: second branch + one user per role"
$br = (Http POST '/api/admin/branches' @{ name = 'Test Branch'; code = 'tb'; is_active = $true } $T).B | ConvertFrom-Json
$TB = $br.data.id; $HO = 1
"test branch id $TB code $($br.data.code)"
$roles = ((Http GET '/api/business/roles' $null $T).B | ConvertFrom-Json).data
$rid = @{}; $roles | ForEach-Object { $rid[$_.slug] = $_.id }
$users = @{}
foreach ($slug in 'accounts', 'store', 'sales', 'support') {
  $res = Http POST '/api/business/users' @{ name = "Test $slug"; email = "test-$slug@example.com"; role_id = $rid[$slug]; branch_ids = @($HO); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T
  Check "create $slug user" $res 201
  $users[$slug] = (Login "test-$slug@example.com" 'TestPass#123').token
}

"== website content permission (website.manage)"
Check 'sales -> /admin/services' (Http GET '/api/admin/services' $null $users.sales) 200
Check 'sales -> /admin/settings' (Http GET '/api/admin/settings' $null $users.sales) 200
Check 'store -> /admin/services' (Http GET '/api/admin/services' $null $users.store) 403
Check 'store -> /admin/dashboard' (Http GET '/api/admin/dashboard' $null $users.store) 403
Check 'accounts -> /admin/settings' (Http GET '/api/admin/settings' $null $users.accounts) 403
Check 'support -> delete service' (Http DELETE '/api/admin/services/1' $null $users.support) 403
Check 'store -> CV download' (Http GET '/api/admin/job_applications/1/cv' $null $users.store) 403
Check 'everyone -> /admin/me' (Http GET '/api/admin/me' $null $users.store) 200

"== admin-only business setup"
foreach ($slug in 'accounts', 'store', 'sales', 'support') {
  Check "$slug -> business/users" (Http GET '/api/business/users' $null $users[$slug]) 403
}
Check 'sales -> create branch' (Http POST '/api/admin/branches' @{ name = 'X'; code = 'X1' } $users.sales) 403
Check 'accounts -> business settings' (Http GET '/api/business/settings' $null $users.accounts) 403
Check 'support -> activity log' (Http GET '/api/business/activity-logs' $null $users.support) 403
Check 'store -> roles update' (Http POST "/api/business/roles/$($rid.store)" @{ name = 'Store'; permissions = @('website.manage') } $users.store) 403

"== branch isolation"
Check 'sales (HO only) asks TB' (Http GET '/api/business/permissions' $null $users.sales $TB) 403
Check 'sales asks ALL' (Http GET '/api/business/permissions' $null $users.sales 'all') 403
Check 'admin asks ALL' (Http GET '/api/business/activity-logs' $null $T 'all') 200
Check 'admin asks TB' (Http GET '/api/business/activity-logs' $null $T $TB) 200
Check 'admin asks unknown branch 9999' (Http GET '/api/business/activity-logs' $null $T 9999) 403

"== safety rules"
$me = ((Http GET '/api/admin/me' $null $T).B | ConvertFrom-Json).user
Check 'admin cannot deactivate self' (Http POST "/api/business/users/$($me.id)" @{ name = $me.name; email = $me.email; role_id = $rid.admin; branch_ids = @($HO); is_active = $false } $T) 422
Check 'admin cannot change own role' (Http POST "/api/business/users/$($me.id)" @{ name = $me.name; email = $me.email; role_id = $rid.sales; branch_ids = @($HO) } $T) 422
Check 'Admin role cannot be edited' (Http POST "/api/business/roles/$($rid.admin)" @{ name = 'Admin'; permissions = @() } $T) 422
Check 'Admin role cannot be deleted' (Http DELETE "/api/business/roles/$($rid.admin)" $null $T) 422
Check 'role in use cannot be deleted' (Http DELETE "/api/business/roles/$($rid.sales)" $null $T) 422
Check 'unknown permission rejected' (Http POST '/api/business/roles' @{ name = 'Bad'; permissions = @('hack.all') } $T) 422
# Phase G (owner decision): unused records can be deleted, used ones are kept with a reason (SafeDelete).
Check 'branch with users cannot be deleted' (Http DELETE "/api/admin/branches/$HO" $null $T) 422
$tmpTax = (Http POST '/api/admin/tax_rates' @{ name = 'T- Temp Tax'; rate = 2; is_active = $true } $T).B | ConvertFrom-Json | Select-Object -ExpandProperty data
Check 'unused tax rate can be deleted' (Http DELETE "/api/admin/tax_rates/$($tmpTax.id)" $null $T) 200
Check 'default VAT kept' (Http GET '/api/admin/tax_rates/1' $null $T) 200
Check 'duplicate branch code' (Http POST '/api/admin/branches' @{ name = 'Dup'; code = 'HO' } $T) 422
Check 'user needs a branch' (Http POST '/api/business/users' @{ name = 'NoBranch'; email = 'nobranch@example.com'; role_id = $rid.store; branch_ids = @(); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T) 422

"== website settings cannot write business keys"
Http POST '/api/admin/settings' @{ biz_company_name = 'HACKED'; tagline = (((Http GET '/api/admin/settings' $null $T).B | ConvertFrom-Json).data.tagline) } $users.sales | Out-Null
$biz = ((Http GET '/api/business/settings' $null $T).B | ConvertFrom-Json).data
Check 'biz key untouched by website settings' @{ S = $(if ($biz.biz_company_name -eq 'HACKED') { 0 } else { 200 }); B = '' } 200
$pub = ((Http GET '/api/site').B | ConvertFrom-Json).settings
Check 'biz keys not public' @{ S = $(if (($pub.PSObject.Properties.Name | Where-Object { $_ -like 'biz_*' }).Count) { 0 } else { 200 }); B = '' } 200

"== role permission change applies immediately"
$res = Http POST "/api/business/roles/$($rid.store)" @{ name = 'Store'; permissions = @('projects.view', 'inventory.view', 'inventory.manage', 'website.manage') } $T
Check 'admin adds website.manage to Store' $res 200
Check 'store now -> /admin/services' (Http GET '/api/admin/services' $null $users.store) 200
Http POST "/api/business/roles/$($rid.store)" @{ name = 'Store'; permissions = @('projects.view', 'inventory.view', 'inventory.manage') } $T | Out-Null
Check 'store back to no website' (Http GET '/api/admin/services' $null $users.store) 403

"== deactivate user signs them out"
$sid = ((Http GET '/api/business/users?q=test-support' $null $T).B | ConvertFrom-Json).data[0].id
Check 'deactivate support' (Http POST "/api/business/users/$sid" @{ name = 'Test support'; email = 'test-support@example.com'; role_id = $rid.support; branch_ids = @($HO); is_active = $false } $T) 200
Check 'support token rejected' (Http GET '/api/admin/me' $null $users.support) 401
Check 'support cannot log in' (Http POST '/api/admin/login' @{ email = 'test-support@example.com'; password = 'TestPass#123' }) 422

"== activity log recorded"
$log = ((Http GET '/api/business/activity-logs?per_page=100' $null $T 'all').B | ConvertFrom-Json).data
"  entries: $($log.Count); actions: $(($log | Group-Object action | ForEach-Object { "$($_.Name)=$($_.Count)" }) -join ', ')"
Check 'log has created User' @{ S = $(if ($log | Where-Object { $_.action -eq 'created' -and $_.subject_type -eq 'User' }) { 200 } else { 0 }); B = '' } 200
Check 'log has role update with changes' @{ S = $(if ($log | Where-Object { $_.subject_type -eq 'Role' -and $_.changes }) { 200 } else { 0 }); B = '' } 200
Check 'no passwords in log' @{ S = $(if ((($log | ConvertTo-Json -Depth 6) -match 'TestPass|password":')) { 0 } else { 200 }); B = '' } 200

"== sample PDFs"
foreach ($l in 'en', 'bn') {
  $r = Invoke-WebRequest -UseBasicParsing "$B/api/business/pdf/sample?lang=$l" -Headers @{ Authorization = "Bearer $T" }
  Check "pdf $l is a PDF" @{ S = $(if ($r.Headers['Content-Type'] -eq 'application/pdf' -and $r.RawContentLength -gt 10000) { 200 } else { 0 }); B = '' } 200
}

foreach ($t in $users.Values) { Http POST '/api/admin/logout' $null $t | Out-Null }
Http POST '/api/admin/logout' $null $T | Out-Null
""
"RESULT: $script:ok passed, $script:bad failed"
"TB=$TB"
