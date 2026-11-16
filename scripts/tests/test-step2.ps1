. "$PSScriptRoot\th.ps1"
"== website form -> lead"
$tok = (Http GET '/api/site').J.form_token
Start-Sleep 4
Check 'website quote request' (Http POST '/api/inquiries' @{ name = 'CRM Test Visitor'; phone = '01799999991'; service = 'Office Interior'; budget = '10-20 Lakh'; message = 'Need office design'; form_token = $tok }) 201
$leads = (Http GET '/api/business/leads?q=01799999991' $null $T).J.data
Assert 'lead auto-created from website' (@($leads).Count -eq 1 -and $leads[0].source -eq 'website' -and $leads[0].service -eq 'Office Interior')
Check 'same phone again' (Http POST '/api/inquiries' @{ name = 'CRM Test Visitor'; phone = '01799999991'; message = 'Any update?'; form_token = $tok }) 201
$leads2 = (Http GET '/api/business/leads?q=01799999991' $null $T).J.data
Assert 'no duplicate lead (added to timeline)' (@($leads2).Count -eq 1)
$L = $leads[0].id
$detail = (Http GET "/api/business/leads/$L" $null $T).J.data
Assert 'timeline has website notes' (@($detail.activities).Count -ge 2)

"== manual lead + activities + follow-up"
$r = Http POST '/api/business/leads' @{ name = 'CRM Manual Lead'; phone = '01799999992'; source = 'facebook'; expected_value = 850000; assigned_to = 1 } $T
Check 'create lead' $r 201
$M = $r.J.data.id
Check 'bad source rejected' (Http POST '/api/business/leads' @{ name = 'X'; source = 'tv' } $T) 422
Check 'log call with follow-up' (Http POST "/api/business/leads/$M/activities" @{ type = 'call'; note = 'Discussed 3 bed apartment'; follow_up_at = (Get-Date).AddHours(-1).ToString('yyyy-MM-ddTHH:mm') } $T) 201
$lm = (Http GET "/api/business/leads/$M" $null $T).J.data
Assert 'call moved new -> contacted' ($lm.stage -eq 'contacted')
Assert 'next follow-up set' ([bool]$lm.next_follow_up_at)
$fu = (Http GET '/api/business/follow-ups' $null $T).J.data
Assert 'appears in my follow-ups (overdue)' (@($fu | Where-Object { $_.lead.id -eq $M }).Count -eq 1)
$aid = ($fu | Where-Object { $_.lead.id -eq $M })[0].id
Check 'mark follow-up done' (Http POST "/api/business/lead-activities/$aid/toggle" $null $T) 200
$lm = (Http GET "/api/business/leads/$M" $null $T).J.data
Assert 'follow-up cleared' (-not $lm.next_follow_up_at)

"== pipeline"
Check 'lost needs reason' (Http POST "/api/business/leads/$M/stage" @{ stage = 'lost' } $T) 422
Check 'move to negotiation' (Http POST "/api/business/leads/$M/stage" @{ stage = 'negotiation' } $T) 200
$r = Http POST "/api/business/leads/$M/stage" @{ stage = 'won' } $T
Check 'won' $r 200
Assert 'customer created on won' ([bool]$r.J.data.customer_id)
$C = $r.J.data.customer_id
$board = (Http GET '/api/business/leads?view=board' $null $T).J
Assert 'board groups by stage' ($board.stages.Count -eq 7 -and @($board.data.won | Where-Object id -eq $M).Count -eq 1)
Check 'convert website lead' (Http POST "/api/business/leads/$L/convert" $null $T) 201
$c2 = (Http GET '/api/business/customers?q=01799999991' $null $T).J.data
Assert 'customer from convert' (@($c2).Count -eq 1)
Check 'customer detail' (Http GET "/api/business/customers/$C" $null $T) 200
Check 'update customer' (Http POST "/api/business/customers/$C" @{ name = 'CRM Manual Lead'; phone = '01799999992'; company = 'Test Co' } $T) 200

"== permissions"
$st = RoleUser 'store'; $sa = RoleUser 'sales'; $su = RoleUser 'support'
Check 'store cannot view leads' (Http GET '/api/business/leads' $null $st) 403
Check 'sales views leads' (Http GET '/api/business/leads' $null $sa) 200
Check 'sales creates lead' (Http POST '/api/business/leads' @{ name = 'CRM Sales Lead' } $sa) 201
Check 'support views customers' (Http GET '/api/business/customers' $null $su) 200
Check 'support cannot edit lead' (Http POST "/api/business/leads/$M" @{ name = 'hack' } $su) 403
Check 'support cannot change stage' (Http POST "/api/business/leads/$M/stage" @{ stage = 'lost'; lost_reason = 'x' } $su) 403

"== branch isolation"
$tb = (Http POST '/api/admin/branches' @{ name = 'CRM Test Branch'; code = 'CTB' } $T).J.data.id
$r = Http POST '/api/business/leads' @{ name = 'CRM TB Lead' } $T $tb
Check 'admin creates lead in other branch' $r 201
$TBL = $r.J.data.id
Check 'HO user cannot open TB lead (404)' (Http GET "/api/business/leads/$TBL" $null $sa) 404
Assert 'TB lead not in HO list' (@((Http GET '/api/business/leads?q=CRM TB' $null $sa).J.data).Count -eq 0)
Assert 'admin sees it in all view' (@((Http GET '/api/business/leads?q=CRM TB' $null $T 'all').J.data).Count -eq 1)
Check 'all mode create without branch rejected' (Http POST '/api/business/leads' @{ name = 'CRM NoBranch' } $T 'all') 422
Check 'all mode create with branch' (Http POST '/api/business/leads' @{ name = 'CRM AllBranch'; branch_id = $tb } $T 'all') 201
Check 'staff lookup' (Http GET '/api/business/lookup/staff' $null $sa) 200
"TB=$tb"
Done
