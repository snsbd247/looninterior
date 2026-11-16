. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
$log = "$PSScriptRoot\..\..\backend\storage\logs\sms.log"
$before = if (Test-Path $log) { (Get-Content $log).Count } else { 0 }
"== SMS settings (log mode: no gateway URL)"
Check 'save sms settings' (Http POST '/api/business/settings' @{ biz_sms_enabled = '1'; biz_sms_url = ''; biz_sms_on_payment = '1'; biz_sms_on_ticket = '1' } $T) 200
Check 'bad method rejected' (Http POST '/api/business/settings' @{ biz_sms_method = 'PUT' } $T) 422
$r = Http POST '/api/business/sms/test' @{ to = '01711-000000' } $T
Check 'test sms' $r 200
$sup = RoleUser 'support'
Check 'support cannot test sms' (Http POST '/api/business/sms/test' @{ to = '01711000000' } $sup) 403
$c = (Http POST '/api/business/customers' @{ name = 'T- Sms Client'; phone = '01800000777' } $T).J.data
$inv = (Http POST '/api/business/invoices' @{ customer_id = $c.id; date = $today; items = @(@{ description = 'Work'; unit = 'job'; qty = 1; rate = 1000 }) } $T).J.data
Http POST "/api/business/invoices/$($inv.id)/issue" $null $T | Out-Null
$acct = ((Http GET '/api/business/lookup/accounts' $null $T).J.data | Select-Object -First 1).id
Check 'receive payment' (Http POST "/api/business/invoices/$($inv.id)/receive" @{ amount = 500; account_id = $acct; method = 'cash'; date = $today } $T) 201
Check 'open ticket' (Http POST '/api/business/tickets' @{ customer_id = $c.id; subject = 'T- sms ticket' } $T) 201
$new = Get-Content $log | Select-Object -Skip $before
Assert 'test sms logged with 880 number' (@($new | Where-Object { $_ -like '*8801711000000*Test SMS*' }).Count -eq 1)
Assert 'payment sms logged' (@($new | Where-Object { $_ -like '*8801800000777*received Tk 500.00*' }).Count -eq 1) ($new -join "`n")
Assert 'ticket sms logged' (@($new | Where-Object { $_ -like '*8801800000777*service request HO-TKT*' }).Count -eq 1)
Check 'gateway GET mode' (Http POST '/api/business/settings' @{ biz_sms_method = 'GET'; biz_sms_url = "$B/api/site?to={to}&msg={message}" } $T) 200
Check 'test sms via http gateway' (Http POST '/api/business/sms/test' @{ to = '01711000000' } $T) 200
Check 'gateway 404 reported as failure' (Http POST '/api/business/settings' @{ biz_sms_url = "$B/api/nope?to={to}" } $T) 200
Check 'failed gateway -> 422' (Http POST '/api/business/sms/test' @{ to = '01711000000' } $T) 422
Http POST '/api/business/settings' @{ biz_sms_url = '' } $T | Out-Null
Check 'turn sms off' (Http POST '/api/business/settings' @{ biz_sms_enabled = '0' } $T) 200
$n = (Get-Content $log).Count
Http POST '/api/business/tickets' @{ customer_id = $c.id; subject = 'T- no sms' } $T | Out-Null
Assert 'no sms when off' ((Get-Content $log).Count -eq $n)
Done

