<#
  Loon Interior: full business API test suite (DEV / LOCAL ONLY).

  !!! It CREATES test data and then WIPES ALL BUSINESS DATA (leads, customers, quotations, projects,
  !!! invoices, payments, transactions, stock, tickets, document numbers). Never run it on a live site.

  Usage (from the project root, local XAMPP):
    powershell -ExecutionPolicy Bypass -File scripts\tests\run-all.ps1 -ConfirmWipe

  What it does: reset â†’ step tests 1,2,3,4,6,7,8 â†’ reset. Between scripts it waits ~60 s because the
  admin login is rate-limited (10 per minute per IP).
#>
param([switch]$ConfirmWipe, [string]$Mysql = 'C:\xampp\mysql\bin\mysql.exe', [string]$Database = 'loon_interior')

if (-not $ConfirmWipe) {
  Write-Host 'This deletes ALL business data in the local database. Re-run with -ConfirmWipe to continue.' -ForegroundColor Yellow
  exit 2
}
$envFile = Join-Path $PSScriptRoot '..\..\backend\.env'
if ((Get-Content $envFile) -match '^APP_URL=https?://(?!localhost|127\.0\.0\.1)') {
  Write-Host 'APP_URL is not localhost. Refusing to run against a non-local site.' -ForegroundColor Red
  exit 2
}

function Reset() { Get-Content (Join-Path $PSScriptRoot 'dev-reset-business-data.sql') -Raw | & $Mysql -u root $Database }
$total = 0; $failed = 0
Reset
$steps = 'test-step1.ps1', 'test-step2.ps1', 'test-step3.ps1', 'test-step4.ps1', 'test-step6.ps1', 'test-step7.ps1', 'test-step8.ps1', 'test-hr-labour.ps1', 'test-portal.ps1', 'test-sms.ps1', 'test-delete.ps1', 'test-marketing.ps1', 'test-calculator.ps1'
foreach ($i in 0..($steps.Count - 1)) {
  $s = $steps[$i]
  Write-Host "`n### $s" -ForegroundColor Cyan
  if ($s -eq 'test-step8.ps1') { & (Join-Path $PSScriptRoot 'test-step4.ps1') | Out-Null; Start-Sleep 62 } # dashboard needs some data
  $out = & (Join-Path $PSScriptRoot $s) 2>&1 | Out-String
  $out -split "`n" | Where-Object { $_ -match 'FAIL|RESULT' } | ForEach-Object { Write-Host $_ }
  if ($out -match 'RESULT: (\d+) passed, (\d+) failed') { $total += [int]$Matches[1] + [int]$Matches[2]; $failed += [int]$Matches[2] }
  Reset
  if ($i -lt $steps.Count - 1) { Start-Sleep 62 }
}
Remove-Item (Join-Path $PSScriptRoot '..\..\backend\storage\logs\mail.log') -ErrorAction SilentlyContinue
Write-Host ''
if ($failed -eq 0) { Write-Host "ALL TESTS PASSED ($total)" -ForegroundColor Green; exit 0 }
Write-Host "FAILED: $failed of $total" -ForegroundColor Red
exit 1
