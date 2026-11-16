. "$PSScriptRoot\th.ps1"
# Phase G: Delete buttons. Unused records are deleted; used ones give 422 with the reason; money/stock documents use Cancel.
$today = Get-Date -Format 'yyyy-MM-dd'
function Drop($path, $token = $T) { Http DELETE $path $null $token }

"== generic master data (safe_delete)"
$br = (Http POST '/api/admin/branches' @{ name = 'T- Drop Branch'; code = 'TDB'; is_active = $true } $T).J.data
$r = Drop '/api/admin/branches/1'
Check 'head office in use' $r 422
Assert "reason given ($($r.J.message))" ($r.J.message -like '*used in*inactive*')
Check 'new unused branch deleted (auto cash + store removed too)' (Drop "/api/admin/branches/$($br.id)") 200
$tax = (Http POST '/api/admin/tax_rates' @{ name = 'T- Drop VAT'; rate = 3; is_active = $true } $T).J.data
Check 'unused tax rate deleted' (Drop "/api/admin/tax_rates/$($tax.id)") 200
$sys = ((Http GET '/api/admin/transaction_categories?per_page=100' $null $T).J.data | Where-Object is_system | Select-Object -First 1)
Check 'system category protected' (Drop "/api/admin/transaction_categories/$($sys.id)") 422
$item = (Http POST '/api/admin/items' @{ name = 'T- Drop Item'; unit = 'pcs'; is_active = $true } $T).J.data
$sup = (Http POST '/api/admin/suppliers' @{ name = 'T- Drop Supplier'; is_active = $true } $T).J.data
$store = ((Http GET '/api/business/lookup/stores' $null $T).J.data | Select-Object -First 1)
$po = (Http POST '/api/business/purchase-orders' @{ supplier_id = $sup.id; store_id = $store.id; date = $today; items = @(@{ item_id = $item.id; qty = 2; rate = 10 }) } $T).J.data
Check 'item used in a PO' (Drop "/api/admin/items/$($item.id)") 422
Check 'draft PO deleted' (Drop "/api/business/purchase-orders/$($po.id)") 200
Check 'item now unused -> deleted' (Drop "/api/admin/items/$($item.id)") 200
Check 'supplier now unused -> deleted' (Drop "/api/admin/suppliers/$($sup.id)") 200
Check 'website content delete unchanged' (Drop '/api/admin/faqs/999999') 404

"== CRM, quotations, projects"
$lead = (Http POST '/api/business/leads' @{ name = 'T- Drop Lead' } $T).J.data
$act = (Http POST "/api/business/leads/$($lead.id)/activities" @{ type = 'call'; note = 'x' } $T).J.data
Check 'delete follow-up' (Drop "/api/business/lead-activities/$($act.id)") 200
Check 'delete lead' (Drop "/api/business/leads/$($lead.id)") 200
$c1 = (Http POST '/api/business/customers' @{ name = 'T- Drop Customer' } $T).J.data
Check 'unused customer deleted' (Drop "/api/business/customers/$($c1.id)") 200
$c2 = (Http POST '/api/business/customers' @{ name = 'T- Kept Customer' } $T).J.data
$q = (Http POST '/api/business/quotations' @{ customer_id = $c2.id; title = 'T- Q'; date = $today; items = @(@{ description = 'A'; unit = 'job'; qty = 1; rate = 100 }) } $T).J.data
Check 'customer with quotation kept' (Drop "/api/business/customers/$($c2.id)") 422
Http POST "/api/business/quotations/$($q.id)/status" @{ status = 'sent' } $T | Out-Null
Check 'sent quotation cannot be deleted' (Drop "/api/business/quotations/$($q.id)") 422
$q2 = (Http POST '/api/business/quotations' @{ customer_id = $c2.id; title = 'T- Q2'; date = $today; items = @(@{ description = 'A'; unit = 'job'; qty = 1; rate = 100 }) } $T).J.data
Check 'draft quotation deleted' (Drop "/api/business/quotations/$($q2.id)") 200
$p = (Http POST '/api/business/projects' @{ customer_id = $c2.id; title = 'T- Drop Project' } $T).J.data
Http POST "/api/business/projects/$($p.id)/milestones" @{ milestones = @(@{ name = 'Design'; progress = 0 }) } $T | Out-Null
$ms = (Http GET "/api/business/projects/$($p.id)" $null $T).J.data.milestones
Check 'delete stage' (Drop "/api/business/projects/$($p.id)/milestones/$($ms[0].id)") 200
Check 'unused project deleted' (Drop "/api/business/projects/$($p.id)") 200
$p2 = (Http POST '/api/business/projects' @{ customer_id = $c2.id; title = 'T- Billed Project' } $T).J.data
Http POST '/api/business/invoices' @{ customer_id = $c2.id; work_project_id = $p2.id; date = $today; items = @(@{ description = 'A'; unit = 'job'; qty = 1; rate = 100 }) } $T | Out-Null
$r = Drop "/api/business/projects/$($p2.id)"
Check 'project with invoice kept' $r 422
Assert "says Cancelled instead ($($r.J.message))" ($r.J.message -like '*invoice*Cancelled*')

"== accounts, stores, HR, tickets, users"
$acc = (Http POST '/api/business/accounts' @{ name = 'T- Drop Bank'; type = 'bank'; opening_balance = 0 } $T).J.data
Check 'unused account deleted' (Drop "/api/business/accounts/$($acc.id)") 200
$st = (Http POST '/api/business/stores' @{ name = 'T- Drop Store' } $T).J.data
Check 'unused store deleted' (Drop "/api/business/stores/$($st.id)") 200
$e = (Http POST '/api/business/employees' @{ name = 'T- Drop Employee'; basic_salary = 1000 } $T).J.data
$lv = (Http POST '/api/business/leaves' @{ employee_id = $e.id; type = 'casual'; from_date = $today; to_date = $today } $T).J.data
Check 'pending leave deleted' (Drop "/api/business/leaves/$($lv.id)") 200
$pr = (Http POST '/api/business/payrolls/generate' @{ month = '2026-01' } $T).J.data
Check 'employee on a salary sheet kept' (Drop "/api/business/employees/$($e.id)") 422
Check 'draft salary sheet deleted' (Drop "/api/business/payrolls/$($pr.id)") 200
Check 'employee now deleted' (Drop "/api/business/employees/$($e.id)") 200
$t1 = (Http POST '/api/business/tickets' @{ customer_id = $c2.id; subject = 'T- Drop ticket' } $T).J.data
Check 'open ticket deleted' (Drop "/api/business/tickets/$($t1.id)") 200
$t2 = (Http POST '/api/business/tickets' @{ customer_id = $c2.id; subject = 'T- Busy ticket' } $T).J.data
Http POST "/api/business/tickets/$($t2.id)" @{ status = 'in_progress' } $T | Out-Null
Check 'ticket in progress kept' (Drop "/api/business/tickets/$($t2.id)") 422
Check 'cannot delete yourself' (Drop '/api/business/users/1') 422
$sal = RoleUser 'sales'
Check 'sales cannot delete account' (Drop "/api/business/accounts/$(((Http GET '/api/business/lookup/accounts' $null $T).J.data | Select-Object -First 1).id)" $sal) 403
$uid = ((Http GET '/api/business/users' $null $T).J.data | Where-Object email -eq 'test-sales@example.com').id
Check 'user who signed in is kept (history)' (Drop "/api/business/users/$uid") 422
$nu = (Http POST '/api/business/users' @{ name = 'T- Never Used'; email = 'test-never@example.com'; role_id = 2; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T).J.data
Check 'fresh user deleted' (Drop "/api/business/users/$($nu.id)") 200
Done



