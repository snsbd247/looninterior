. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
"== setup: customer + approved quotation"
$lead = (Http POST '/api/business/leads' @{ name = 'T- Billing Lead'; phone = '01777777771'; email = 'bill@example.com' } $T).J.data
$cust = (Http POST "/api/business/leads/$($lead.id)/convert" $null $T).J.data
$q = (Http POST '/api/business/quotations' @{ customer_id = $cust.id; lead_id = $lead.id; title = 'T- Flat Interior'; date = $today; items = @(@{ section = 'Living'; description = 'Work'; unit = 'job'; qty = 1; rate = 1000000 }) } $T).J.data
Http POST "/api/business/quotations/$($q.id)/status" @{ status = 'approved' } $T | Out-Null

"== project"
Check 'project from draft quotation rejected' (Http POST '/api/business/projects' @{ quotation_id = (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = $today; items = @(@{ description = 'x'; qty = 1; rate = 1 }) } $T).J.data.id } $T) 422
$r = Http POST '/api/business/projects' @{ quotation_id = $q.id; start_date = $today } $T
Check 'project from approved quotation' $r 201
$P = $r.J.data
Assert "contract = quotation total ($($P.contract_value))" ([decimal]$P.contract_value -eq 1000000)
$pd = (Http GET "/api/business/projects/$($P.id)" $null $T).J
Assert 'default milestones' (@($pd.data.milestones).Count -eq 5)
Assert 'default schedule 50/30/20' (@($pd.data.schedules).Count -eq 3 -and [decimal]$pd.data.schedules[0].amount -eq 500000)
$ms = $pd.data.milestones | ForEach-Object { @{ id = $_.id; name = $_.name; progress = 0 } }
$ms[0].progress = 100; $ms[1].progress = 50
Check 'save milestones' (Http POST "/api/business/projects/$($P.id)/milestones" @{ milestones = $ms } $T) 200
Assert 'progress = avg (30)' ((Http GET "/api/business/projects/$($P.id)" $null $T).J.data.progress -eq 30)
Check 'add task' (Http POST "/api/business/projects/$($P.id)/tasks" @{ title = 'Order boards'; assigned_to = 1; due_date = $today } $T) 201

"== invoice from installment"
$pre = (Http GET "/api/business/invoices/prefill?schedule_id=$($pd.data.schedules[0].id)" $null $T).J.data
Assert 'prefill from schedule' ([decimal]$pre.items[0].rate -eq 500000)
$r = Http POST '/api/business/invoices' (@{ customer_id = $pre.customer_id; work_project_id = $pre.work_project_id; schedule_id = $pre.schedule_id; date = $today; due_date = $today; items = $pre.items }) $T
Check 'create invoice' $r 201
$I = $r.J.data
Check 'same installment twice rejected' (Http POST '/api/business/invoices' (@{ customer_id = $pre.customer_id; work_project_id = $pre.work_project_id; schedule_id = $pre.schedule_id; date = $today; items = $pre.items }) $T) 422
$cash = ((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object type -eq 'cash')[0]
$bal0 = [decimal]$cash.balance
Check 'cannot receive on draft' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 100; account_id = $cash.id; method = 'cash'; date = $today } $T) 422
Check 'issue' (Http POST "/api/business/invoices/$($I.id)/issue" $null $T) 200
Check 'issued cannot be edited' (Http POST "/api/business/invoices/$($I.id)" (@{ customer_id = $cust.id; date = $today; items = $pre.items }) $T) 422
Check 'overpayment rejected' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 500000.01; account_id = $cash.id; method = 'cash'; date = $today } $T) 422
Check 'future date rejected' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 10; account_id = $cash.id; method = 'cash'; date = '2099-01-01' } $T) 422
$r = Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 200000; account_id = $cash.id; method = 'bkash'; reference = 'TX123'; date = $today } $T
Check 'receive 2,00,000' $r 201
$PAY = $r.J.data
Assert "invoice partial ($($r.J.invoice.status))" ($r.J.invoice.status -eq 'partial' -and [decimal]$r.J.invoice.due -eq 300000)
Assert "receipt number ($($PAY.number))" ($PAY.number -match '^HO-RCV-')
$bal1 = [decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $cash.id).balance)
Assert "cash +200000 ($bal0 -> $bal1)" ($bal1 - $bal0 -eq 200000)
$r = Http POST "/api/business/invoices/$($I.id)/credit-note" @{ amount = 50000; reason = 'Design change discount'; date = $today } $T
Check 'credit note 50,000' $r 201
Check 'credit above due rejected' (Http POST "/api/business/invoices/$($I.id)/credit-note" @{ amount = 250001; reason = 'x'; date = $today } $T) 422
Check 'cancel invoice with payment rejected' (Http POST "/api/business/invoices/$($I.id)/cancel" @{ reason = 'x' } $T) 422
$r = Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 250000; account_id = $cash.id; method = 'cash'; date = $today } $T
Assert "fully paid ($($r.J.invoice.status))" ($r.J.invoice.status -eq 'paid' -and [decimal]$r.J.invoice.due -eq 0)
Check 'cancel first payment' (Http POST "/api/business/payments/$($PAY.id)/cancel" @{ reason = 'Cheque bounced' } $T) 200
$inv = (Http GET "/api/business/invoices/$($I.id)" $null $T).J.data
Assert "back to partial, due 200000 ($($inv.status) $($inv.due))" ($inv.status -eq 'partial' -and [decimal]$inv.due -eq 200000)
$bal2 = [decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $cash.id).balance)
Assert "cash = +250000 after cancel ($bal2)" ($bal2 - $bal0 -eq 250000)

"== documents"
foreach ($u in "/api/business/invoices/$($I.id)/pdf?lang=bn", "/api/business/payments/$($r.J.data.id)/pdf?lang=en") {
  $pdfRes = Invoke-WebRequest -UseBasicParsing "$B$u" -Headers @{ Authorization = "Bearer $T" }
  Assert "pdf $u" ($pdfRes.Headers['Content-Type'] -eq 'application/pdf')
}
Check 'email invoice' (Http POST "/api/business/invoices/$($I.id)/email" @{ to = 'bill@example.com' } $T) 200

"== accounts"
$bank = (Http POST '/api/business/accounts' @{ name = 'T- City Bank'; type = 'bank'; bank_name = 'City Bank'; account_number = '123'; opening_balance = 100000 } $T).J.data
Check 'transfer cash -> bank' (Http POST '/api/business/transactions/transfer' @{ from_account_id = $cash.id; to_account_id = $bank.id; amount = 50000; date = $today; note = 'Deposit' } $T) 201
Check 'transfer more than balance rejected' (Http POST '/api/business/transactions/transfer' @{ from_account_id = $cash.id; to_account_id = $bank.id; amount = 999999999; date = $today } $T) 422
$cats = (Http GET '/api/business/lookup/categories' $null $T).J.data
$mat = ($cats | Where-Object name -eq 'Material Purchase')[0]
$r = Http POST '/api/business/transactions' @{ type = 'expense'; account_id = $bank.id; category_id = $mat.id; amount = 30000; date = $today; work_project_id = $P.id; description = 'Boards' } $T
Check 'expense on project' $r 201
$EXP = $r.J.data
Check 'system category blocked' (Http POST '/api/business/transactions' @{ type = 'income'; account_id = $bank.id; category_id = ($cats | Where-Object slug -eq 'customer_payment')[0].id; amount = 1; date = $today } $T) 422
Check 'wrong-type category blocked' (Http POST '/api/business/transactions' @{ type = 'income'; account_id = $bank.id; category_id = $mat.id; amount = 1; date = $today } $T) 422
$led = (Http GET "/api/business/accounts/$($bank.id)?from=$today&to=$today" $null $T).J
Assert "bank ledger 100000+50000-30000 = 120000 ($($led.closing))" ([decimal]$led.closing -eq 120000 -and [decimal]$led.opening -eq 100000)
Check 'cannot cancel auto entry' (Http POST "/api/business/transactions/$(((Http GET "/api/business/transactions?account_id=$($cash.id)&type=income" $null $T).J.data)[0].id)/cancel" @{ reason = 'x' } $T) 422
$fin = (Http GET "/api/business/projects/$($P.id)" $null $T).J.finance
Assert "project finance (received $($fin.received), cost $($fin.cost), profit $($fin.profit))" ([decimal]$fin.received -eq 250000 -and [decimal]$fin.cost -eq 30000 -and [decimal]$fin.profit -eq 970000)
Check 'cancel expense' (Http POST "/api/business/transactions/$($EXP.id)/cancel" @{ reason = 'Entered twice' } $T) 200
Assert 'cost back to 0' ([decimal](Http GET "/api/business/projects/$($P.id)" $null $T).J.finance.cost -eq 0)

"== reports"
$cb = (Http GET "/api/business/reports/cashbook?from=$today&to=$today" $null $T).J
Assert "cashbook totals (in $($cb.total_in) out $($cb.total_out))" ([decimal]$cb.total_in -eq 300000 -and [decimal]$cb.total_out -eq 50000)
$sm = (Http GET "/api/business/reports/summary?from=$today&to=$today" $null $T).J
Assert "summary income 250000 expense 0 ($($sm.income)/$($sm.expense))" ([decimal]$sm.income -eq 250000 -and [decimal]$sm.expense -eq 0)
Check 'profit-loss' (Http GET '/api/business/reports/profit-loss' $null $T) 200
Check 'projects report' (Http GET '/api/business/reports/projects' $null $T) 200
Check 'branches report (all)' (Http GET '/api/business/reports/branches' $null $T 'all') 200
$rv = (Http GET '/api/business/receivables' $null $T).J
Assert "receivables total 200000 ($($rv.total))" ([decimal]$rv.total -eq 200000)
$csv = Invoke-WebRequest -UseBasicParsing "$B/api/business/reports/cashbook?format=csv&from=$today&to=$today" -Headers @{ Authorization = "Bearer $T" }
Assert 'csv export' ($csv.Headers['Content-Type'] -match 'text/csv' -and $csv.Content.Length -gt 50)

"== permissions"
$acc = RoleUser 'accounts'; $sal = RoleUser 'sales'; $sto = RoleUser 'store'; $sup = RoleUser 'support'
Check 'accounts receives payment' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 1; account_id = $cash.id; method = 'cash'; date = $today } $acc) 201
Check 'sales views invoices' (Http GET '/api/business/invoices' $null $sal) 200
Check 'sales cannot receive' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 1; account_id = $cash.id; method = 'cash'; date = $today } $sal) 403
Check 'sales cannot see accounts' (Http GET '/api/business/accounts' $null $sal) 403
Check 'store cannot see invoices' (Http GET '/api/business/invoices' $null $sto) 403
Check 'store views projects' (Http GET "/api/business/projects/$($P.id)" $null $sto) 200
Check 'support cannot edit project' (Http POST "/api/business/projects/$($P.id)" @{ title = 'x' } $sup) 403
Check 'accounts cannot create project' (Http POST '/api/business/projects' @{ customer_id = $cust.id } $acc) 403

"== branch isolation"
$tb = (Http POST '/api/admin/branches' @{ name = 'CRM Bill Branch'; code = 'CTB' } $T).J.data.id
$tbCash = ((Http GET '/api/business/lookup/accounts' $null $T $tb).J.data)[0]
Assert 'new branch got Cash account' ($tbCash.name -eq 'Cash')
Check 'pay HO invoice into TB account rejected' (Http POST "/api/business/invoices/$($I.id)/receive" @{ amount = 1; account_id = $tbCash.id; method = 'cash'; date = $today } $T) 404
Check 'TB view cannot open HO invoice' (Http GET "/api/business/invoices/$($I.id)" $null $T $tb) 404
Check 'HO sales cannot open TB account ledger' (Http GET "/api/business/accounts/$($tbCash.id)" $null $acc) 404
Check 'cross-branch transfer by admin' (Http POST '/api/business/transactions/transfer' @{ from_account_id = $bank.id; to_account_id = $tbCash.id; amount = 1000; date = $today } $T 'all') 201
Check 'cross-branch transfer by HO-only user rejected' (Http POST '/api/business/transactions/transfer' @{ from_account_id = $bank.id; to_account_id = $tbCash.id; amount = 10; date = $today } $acc) 422
Done
