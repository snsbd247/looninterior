. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
"== setup: customer with a finished project in warranty"
$cust = (Http POST '/api/business/customers' @{ name = 'T- Ticket Customer'; phone = '01822222222'; email = 'tk@example.com' } $T).J.data
$proj = (Http POST '/api/business/projects' @{ customer_id = $cust.id; title = 'T- Done Flat'; contract_value = 100000 } $T).J.data
Http POST "/api/business/projects/$($proj.id)" @{ title = 'T- Done Flat'; status = 'completed'; handover_date = $today; warranty_until = (Get-Date).AddYears(1).ToString('yyyy-MM-dd') } $T | Out-Null

"== website service request"
$tok = (Http GET '/api/site').J.form_token
Check 'no token rejected' (Http POST '/api/service-requests' @{ name = 'x'; phone = '1'; subject = 'y' }) 422
Start-Sleep 4
$r = Http POST '/api/service-requests' @{ name = 'T- Ticket Customer'; phone = '01822222222'; email = 'tk@example.com'; subject = 'Hinge loose'; description = 'Kitchen cabinet door'; form_token = $tok }
Check 'service request from website' $r 201
Assert "ticket number returned ($($r.J.number))" ($r.J.number -match '^HO-TKT-')
$list = (Http GET '/api/business/tickets?q=Hinge' $null $T).J.data
$W = $list[0]
Assert 'matched customer + project + warranty' ($W.customer.id -eq $cust.id -and $W.project.id -eq $proj.id)
$wd = (Http GET "/api/business/tickets/$($W.id)" $null $T).J.data
Assert "in warranty ($($wd.in_warranty))" ($wd.in_warranty -eq $true)
Check 'honeypot dropped' (Http POST '/api/service-requests' @{ name = 'Bot'; phone = '1'; subject = 'spam'; website = 'x'; form_token = $tok }) 201
Assert 'bot ticket not saved' (@((Http GET '/api/business/tickets?q=spam' $null $T).J.data).Count -eq 0)
$mail = Get-Content $PSScriptRoot\..\..\backend\storage\logs\mail.log -Raw -ErrorAction SilentlyContinue
Assert 'client got ticket e-mail' ($mail -match 'Service request received')

"== staff workflow"
$r = Http POST '/api/business/tickets' @{ contact_name = 'T- Walk-in'; contact_phone = '01833333333'; subject = 'Paint touch-up'; priority = 'high'; source = 'walk_in' } $T
Check 'staff opens ticket for new contact' $r 201
$N = $r.J.data
Check 'needs a subject' (Http POST '/api/business/tickets' @{ contact_name = 'x' } $T) 422
Check 'project of other customer rejected' (Http POST '/api/business/tickets' @{ customer_id = (Http POST '/api/business/customers' @{ name = 'T- Other' } $T).J.data.id; work_project_id = $proj.id; subject = 'x' } $T) 422
Check 'assign + schedule visit' (Http POST "/api/business/tickets/$($W.id)" @{ assigned_to = 1; visit_at = (Get-Date).AddDays(1).ToString('yyyy-MM-ddTHH:mm'); priority = 'high' } $T) 200
Check 'comment moves to in_progress' (Http POST "/api/business/tickets/$($W.id)/comments" @{ body = 'Visited, hinge replaced' } $T) 201
Assert 'status in_progress' ((Http GET "/api/business/tickets/$($W.id)" $null $T).J.data.status -eq 'in_progress')
Check 'resolve' (Http POST "/api/business/tickets/$($W.id)" @{ status = 'resolved' } $T) 200
$d = (Http GET "/api/business/tickets/$($W.id)" $null $T).J.data
Assert 'resolved_at set + timeline' ($d.resolved_at -and @($d.comments).Count -ge 4)
Check 'bad status rejected' (Http POST "/api/business/tickets/$($W.id)" @{ status = 'done' } $T) 422
$rep = (Http GET '/api/business/tickets/report' $null $T).J
Assert "report (total $($rep.total), resolved $($rep.resolved), warranty $($rep.in_warranty))" ($rep.total -ge 2 -and $rep.resolved -ge 1 -and $rep.in_warranty -ge 1)
$cd = (Http GET "/api/business/customers/$($cust.id)" $null $T).J.data
Assert 'customer profile shows tickets' (@($cd.tickets).Count -ge 1)

"== permissions & branch"
$sup = RoleUser 'support'; $sal = RoleUser 'sales'; $sto = RoleUser 'store'
Check 'support manages tickets' (Http POST "/api/business/tickets/$($N.id)/comments" @{ body = 'Called client' } $sup) 201
Check 'sales views tickets' (Http GET '/api/business/tickets' $null $sal) 200
Check 'sales cannot update ticket' (Http POST "/api/business/tickets/$($N.id)" @{ status = 'closed' } $sal) 403
Check 'store cannot view tickets' (Http GET '/api/business/tickets' $null $sto) 403
$tb = (Http POST '/api/admin/branches' @{ name = 'CRM Tk Branch'; code = 'CTB' } $T).J.data.id
Check 'other branch cannot open ticket' (Http GET "/api/business/tickets/$($N.id)" $null $T $tb) 404
Done
