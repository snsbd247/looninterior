. "$PSScriptRoot\th.ps1"
# Phase G: marketing campaigns (bulk SMS / e-mail), opt-out, Greenweb provider settings.
$log = "$PSScriptRoot\..\..\backend\storage\logs\sms.log"
$before = if (Test-Path $log) { (Get-Content $log).Count } else { 0 }

"== audience"
Http POST '/api/business/settings' @{ biz_sms_provider = 'custom'; biz_sms_url = '' } $T | Out-Null
$cA = (Http POST '/api/business/customers' @{ name = 'T- Mk Alpha'; phone = '01711-100001'; email = 'mk-alpha@example.com' } $T).J.data
$cB = (Http POST '/api/business/customers' @{ name = 'T- Mk Beta'; phone = '01711100002' } $T).J.data
Http POST '/api/business/customers' @{ name = 'T- Mk Dup'; phone = '+8801711100001' } $T | Out-Null
Http POST '/api/business/customers' @{ name = 'T- Mk Bad'; phone = '12345' } $T | Out-Null
$cO = (Http POST '/api/business/customers' @{ name = 'T- Mk OptOut'; phone = '01711100003' } $T).J.data
Check 'staff marks opt-out' (Http POST "/api/business/customers/$($cO.id)" @{ name = 'T- Mk OptOut'; phone = '01711100003'; marketing_opt_out = $true } $T) 200
Http POST '/api/business/leads' @{ name = 'T- Mk Lead New'; phone = '01811100004'; email = 'mk-lead@example.com'; source = 'facebook' } $T | Out-Null
Http POST '/api/business/leads' @{ name = 'T- Mk Lead Phone'; phone = '01811100005'; source = 'phone' } $T | Out-Null
$r = Http POST '/api/business/campaigns/preview' @{ channel = 'sms'; message = 'Hi {name}'; audience = @{ type = 'customers' } } $T
Check 'preview' $r 200
Assert "customers: 2 valid unique, opt-out/dup/bad skipped ($($r.J.count))" ($r.J.count -eq 2)
$r = Http POST '/api/business/campaigns/preview' @{ channel = 'sms'; audience = @{ type = 'both'; sources = @('facebook') } } $T
Assert "both + facebook leads = 3 ($($r.J.count))" ($r.J.count -eq 3)
$r = Http POST '/api/business/campaigns/preview' @{ channel = 'email'; audience = @{ type = 'both' } } $T
Assert "e-mail audience = 2 ($($r.J.count))" ($r.J.count -eq 2)
$bn = (-join ([char]0x0985, [char]0x09AB, [char]0x09BE, [char]0x09B0, ' ')) * 20  # 'offer ' in Bangla x20 = 100 chars
$r = Http POST '/api/business/campaigns/preview' @{ channel = 'sms'; message = $bn; audience = @{ type = 'customers' } } $T
Assert "Bangla 100 chars = 2 SMS parts ($($r.J.sms_parts))" ($r.J.sms_parts -eq 2)

"== SMS campaign (log mode)"
Check 'name required' (Http POST '/api/business/campaigns' @{ channel = 'sms'; message = 'x' } $T) 422
$c = (Http POST '/api/business/campaigns' @{ name = 'T- Eid offer'; channel = 'sms'; message = 'Dear {name}, Eid offer from {company}!'; audience = @{ type = 'both'; sources = @('facebook') } } $T).J.data
Check 'edit draft' (Http POST "/api/business/campaigns/$($c.id)" @{ name = 'T- Eid offer'; channel = 'sms'; message = 'Dear {name}, 10% Eid offer from {company}!'; audience = @{ type = 'both'; sources = @('facebook') } } $T) 200
Check 'start' (Http POST "/api/business/campaigns/$($c.id)/start" $null $T) 200
Check 'cannot start twice' (Http POST "/api/business/campaigns/$($c.id)/start" $null $T) 422
Check 'cannot edit after start' (Http POST "/api/business/campaigns/$($c.id)" @{ name = 'x'; channel = 'sms'; message = 'x' } $T) 422
$r = Http POST "/api/business/campaigns/$($c.id)/batch" $null $T
Check 'batch' $r 200
Assert "done, 3 sent ($($r.J.data.sent))" ($r.J.done -and $r.J.data.sent -eq 3 -and $r.J.data.status -eq 'sent')
$new = Get-Content $log | Select-Object -Skip $before
Assert 'personalised SMS logged' (@($new | Where-Object { $_ -like '*8801711100001*Dear T- Mk Alpha, 10% Eid offer*' }).Count -eq 1) ($new -join "`n")
Assert 'opt-out never messaged' (@($new | Where-Object { $_ -like '*8801711100003*' }).Count -eq 0)
Check 'sent campaign cannot be deleted' (Http DELETE "/api/business/campaigns/$($c.id)" $null $T) 422
$d = (Http GET "/api/business/campaigns/$($c.id)" $null $T).J
Assert 'report lists 3 recipients' (@($d.recipients.data).Count -eq 3)

"== e-mail campaign"
$e = (Http POST '/api/business/campaigns' @{ name = 'T- Newsletter'; channel = 'email'; subject = 'News for {name}'; message = "Hello {name}`nNew designs are here."; audience = @{ type = 'both' } } $T).J.data
Check 'email needs subject' (Http POST '/api/business/campaigns' @{ name = 'x'; channel = 'email'; message = 'x' } $T) 422
Http POST "/api/business/campaigns/$($e.id)/start" $null $T | Out-Null
$r = Http POST "/api/business/campaigns/$($e.id)/batch" $null $T
Assert "e-mails sent ($($r.J.data.sent))" ($r.J.done -and $r.J.data.sent -eq 2)
Check 'unsigned unsubscribe rejected' (Http GET "/api/public/unsubscribe/customer/$($cA.id)") 403

"== Greenweb provider"
Check 'set Greenweb without token' (Http POST '/api/business/settings' @{ biz_sms_provider = 'greenweb'; biz_sms_greenweb_token = '' } $T) 200
$r = Http GET '/api/business/sms/balance' $null $T
Assert "balance says token missing ($($r.J.message))" ($r.J.ok -eq $false -and $r.J.message -like '*token*')
$g = (Http POST '/api/business/campaigns' @{ name = 'T- GW'; channel = 'sms'; message = 'Test'; audience = @{ type = 'customers' } } $T).J.data
Http POST "/api/business/campaigns/$($g.id)/start" $null $T | Out-Null
$r = Http POST "/api/business/campaigns/$($g.id)/batch" $null $T
Assert "no token -> all failed with reason ($($r.J.data.failed))" ($r.J.data.failed -eq 2)
Http POST '/api/business/settings' @{ biz_sms_greenweb_token = 'FAKE-TOKEN'; biz_sms_greenweb_url = "$B/api/site" } $T | Out-Null
$r = Http POST "/api/business/campaigns/$($g.id)/retry" $null $T
Check 'retry failed' $r 200
$r = Http POST "/api/business/campaigns/$($g.id)/batch" $null $T
Assert "gateway answer without Ok counts as failed ($($r.J.data.failed))" ($r.J.data.failed -eq 2)
Check 'bad greenweb url rejected' (Http POST '/api/business/settings' @{ biz_sms_greenweb_url = 'not a url' } $T) 422
Http POST '/api/business/settings' @{ biz_sms_provider = 'custom'; biz_sms_greenweb_token = ''; biz_sms_greenweb_url = '' } $T | Out-Null

"== draft delete, stop, permissions"
$x = (Http POST '/api/business/campaigns' @{ name = 'T- Draft'; channel = 'sms'; message = 'x'; audience = @{ type = 'customers' } } $T).J.data
Check 'delete draft' (Http DELETE "/api/business/campaigns/$($x.id)" $null $T) 200
$sup = RoleUser 'support'; $sal = RoleUser 'sales'
Check 'support cannot see campaigns' (Http GET '/api/business/campaigns' $null $sup) 403
Check 'sales sees campaigns' (Http GET '/api/business/campaigns' $null $sal) 200
Check 'sales can preview' (Http POST '/api/business/campaigns/preview' @{ channel = 'sms'; audience = @{ type = 'customers' } } $sal) 200
Done

