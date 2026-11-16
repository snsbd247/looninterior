. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
"== setup: two customers, project, invoice, payment, ticket"
$c1 = (Http POST '/api/business/customers' @{ name = 'T- Portal Client'; phone = '01700000991'; email = 'portal-client@example.com' } $T).J.data
$c2 = (Http POST '/api/business/customers' @{ name = 'T- Other Client'; phone = '01700000992' } $T).J.data
$p1 = (Http POST '/api/business/projects' @{ customer_id = $c1.id; title = 'T- Portal Flat'; contract_value = 500000 } $T).J.data
$p2 = (Http POST '/api/business/projects' @{ customer_id = $c2.id; title = 'T- Other Flat'; contract_value = 100000 } $T).J.data
$inv = (Http POST '/api/business/invoices' @{ customer_id = $c1.id; work_project_id = $p1.id; date = $today; items = @(@{ description = 'Advance'; unit = 'job'; qty = 1; rate = 100000 }) } $T).J.data
$draft = (Http POST '/api/business/invoices' @{ customer_id = $c1.id; date = $today; items = @(@{ description = 'Draft only'; unit = 'job'; qty = 1; rate = 5 }) } $T).J.data
Check 'issue invoice' (Http POST "/api/business/invoices/$($inv.id)/issue" $null $T) 200
$acct = ((Http GET '/api/business/lookup/accounts' $null $T).J.data | Select-Object -First 1).id
$pay = (Http POST "/api/business/invoices/$($inv.id)/receive" @{ amount = 40000; account_id = $acct; method = 'cash'; date = $today } $T).J.data
$inv2 = (Http POST '/api/business/invoices' @{ customer_id = $c2.id; date = $today; items = @(@{ description = 'Other'; unit = 'job'; qty = 1; rate = 7 }) } $T).J.data
Http POST "/api/business/invoices/$($inv2.id)/issue" $null $T | Out-Null
$tk2 = (Http POST '/api/business/tickets' @{ customer_id = $c2.id; subject = 'T- other ticket' } $T).J.data

"== staff enables the portal"
Check 'enable without password rejected' (Http POST "/api/business/customers/$($c1.id)/portal" @{ enabled = $true } $T) 422
Check 'enable with short password rejected' (Http POST "/api/business/customers/$($c1.id)/portal" @{ enabled = $true; password = 'short' } $T) 422
Check 'enable portal' (Http POST "/api/business/customers/$($c1.id)/portal" @{ enabled = $true; password = 'Client#2026' } $T) 200
$cust = (Http GET "/api/business/customers/$($c1.id)" $null $T).J.data
Assert 'password hash never returned' (-not ($cust.PSObject.Properties.Name -contains 'portal_password'))
$sup = RoleUser 'support'
Check 'support cannot manage portal' (Http POST "/api/business/customers/$($c1.id)/portal" @{ enabled = $false } $sup) 403

"== login"
Check 'wrong password' (Http POST '/api/portal/login' @{ login = '01700000991'; password = 'nope-nope' }) 422
Check 'other customer (portal off)' (Http POST '/api/portal/login' @{ login = '01700000992'; password = 'Client#2026' }) 422
$r = Http POST '/api/portal/login' @{ login = '01700000991'; password = 'Client#2026' }
Check 'login by phone' $r 200
$C = $r.J.token
Check 'login by e-mail' (Http POST '/api/portal/login' @{ login = 'portal-client@example.com'; password = 'Client#2026' }) 200

"== token isolation"
Check 'customer token on admin API' (Http GET '/api/admin/me' $null $C) 401
Check 'customer token on business API' (Http GET '/api/business/customers' $null $C) 401
Check 'customer token on resource API' (Http GET '/api/admin/branches' $null $C) 401
Check 'staff token on portal API' (Http GET '/api/portal/me' $null $T) 401
Check 'no token' (Http GET '/api/portal/me') 401

"== own data only"
$me = (Http GET '/api/portal/me' $null $C).J
Assert "summary due 60000 ($($me.summary.due))" ([decimal]$me.summary.due -eq 60000)
$pr = (Http GET '/api/portal/projects' $null $C).J.data
Assert 'sees own project only' (@($pr).Count -eq 1 -and $pr[0].id -eq $p1.id)
Check 'own project detail' (Http GET "/api/portal/projects/$($p1.id)" $null $C) 200
Check 'other project 404' (Http GET "/api/portal/projects/$($p2.id)" $null $C) 404
$iv = (Http GET '/api/portal/invoices' $null $C).J.data
Assert 'issued invoice only (no draft)' (@($iv).Count -eq 1 -and $iv[0].id -eq $inv.id)
$pdf = Invoke-WebRequest -UseBasicParsing "$B/api/portal/invoices/$($inv.id)/pdf" -Headers @{ Authorization = "Bearer $C" }
Assert 'invoice pdf' ($pdf.Headers['Content-Type'] -eq 'application/pdf')
Check 'draft invoice pdf 404' (Http GET "/api/portal/invoices/$($draft.id)/pdf" $null $C) 404
Check 'other invoice pdf 404' (Http GET "/api/portal/invoices/$($inv2.id)/pdf" $null $C) 404
$pm = (Http GET '/api/portal/payments' $null $C).J.data
Assert 'sees own payment' (@($pm).Count -eq 1 -and [decimal]$pm[0].amount -eq 40000)
$pdf = Invoke-WebRequest -UseBasicParsing "$B/api/portal/payments/$($pay.id)/pdf" -Headers @{ Authorization = "Bearer $C" }
Assert 'receipt pdf' ($pdf.Headers['Content-Type'] -eq 'application/pdf')

"== tickets"
$r = Http POST '/api/portal/tickets' @{ subject = 'T- Drawer is stuck'; description = 'Kitchen drawer'; work_project_id = $p1.id } $C
Check 'open ticket' $r 201
$tid = $r.J.data.id
Check 'ticket on other customer project rejected' (Http POST '/api/portal/tickets' @{ subject = 'x'; work_project_id = $p2.id } $C) 404
Check 'other ticket 404' (Http GET "/api/portal/tickets/$($tk2.id)" $null $C) 404
Check 'client comment' (Http POST "/api/portal/tickets/$tid/comments" @{ body = 'Any update?' } $C) 201
Check 'staff internal note' (Http POST "/api/business/tickets/$tid/comments" @{ body = 'INTERNAL: customer pushed it hard' } $T) 201
Check 'staff public reply' (Http POST "/api/business/tickets/$tid/comments" @{ body = 'Technician visits tomorrow'; is_public = $true } $T) 201
$tv = (Http GET "/api/portal/tickets/$tid" $null $C).J
Assert 'client sees public reply' (@($tv.comments | Where-Object body -eq 'Technician visits tomorrow').Count -eq 1)
Assert 'internal note hidden' (@($tv.comments | Where-Object { $_.body -like 'INTERNAL*' }).Count -eq 0)
$st = (Http GET "/api/business/tickets/$tid" $null $T).J.data
Assert 'staff sees source portal' ($st.source -eq 'portal' -and $st.work_project_id -eq $p1.id)

"== password & disable"
Check 'change password wrong current' (Http POST '/api/portal/password' @{ current_password = 'bad'; password = 'NewPass#2026'; password_confirmation = 'NewPass#2026' } $C) 422
Check 'change password' (Http POST '/api/portal/password' @{ current_password = 'Client#2026'; password = 'NewPass#2026'; password_confirmation = 'NewPass#2026' } $C) 200
Check 'disable portal' (Http POST "/api/business/customers/$($c1.id)/portal" @{ enabled = $false } $T) 200
Check 'token dead after disable' (Http GET '/api/portal/me' $null $C) 401
Check 'cannot log in when disabled' (Http POST '/api/portal/login' @{ login = '01700000991'; password = 'NewPass#2026' }) 422
Check 'logout route needs token' (Http POST '/api/portal/logout') 401
Done
