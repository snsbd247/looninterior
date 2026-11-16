. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
$month = Get-Date -Format 'yyyy-MM'
"== HR: employees, attendance, leave"
$e1 = (Http POST '/api/business/employees' @{ name = 'T- Rahim Carpenter'; designation = 'Site Supervisor'; basic_salary = 30000; allowance = 3000; join_date = '2026-01-01' } $T).J.data
$e2 = (Http POST '/api/business/employees' @{ name = 'T- Karim Designer'; designation = 'Designer'; basic_salary = 40000 } $T).J.data
Assert 'employees created' ($e1.id -and $e2.id)
Check 'salary required' (Http POST '/api/business/employees' @{ name = 'x' } $T) 422
$day = (Get-Date).AddDays(-1).ToString('yyyy-MM-dd'); if ((Get-Date).Day -eq 1) { $day = $today }
Check 'mark e1 absent' (Http POST '/api/business/attendance' @{ date = $day; rows = @(@{ employee_id = $e1.id; status = 'absent' }, @{ employee_id = $e2.id; status = 'present' }) } $T) 200
Check 'future attendance rejected' (Http POST '/api/business/attendance' @{ date = '2099-01-01'; rows = @(@{ employee_id = $e1.id; status = 'absent' }) } $T) 422
$att = (Http GET "/api/business/attendance?date=$day" $null $T).J.data
Assert 'day view shows absent' ((($att | Where-Object employee_id -eq $e1.id).status) -eq 'absent')
$r = Http POST '/api/business/leaves' @{ employee_id = $e2.id; type = 'unpaid'; from_date = "$month-01"; to_date = "$month-02"; reason = 'Family' } $T
Check 'leave request' $r 201
Assert "leave days = 2 ($($r.J.data.days))" ([decimal]$r.J.data.days -eq 2)
Check 'approve leave' (Http POST "/api/business/leaves/$($r.J.data.id)/decide" @{ status = 'approved' } $T) 200
Check 'cannot decide twice' (Http POST "/api/business/leaves/$($r.J.data.id)/decide" @{ status = 'rejected' } $T) 422

"== payroll"
$r = Http POST '/api/business/payrolls/generate' @{ month = $month; working_days = 26 } $T
Check 'generate salary sheet' $r 201
$P = (Http GET "/api/business/payrolls/$($r.J.data.id)" $null $T).J.data
$i1 = $P.items | Where-Object employee_id -eq $e1.id; $i2 = $P.items | Where-Object employee_id -eq $e2.id
# e1: (30000+3000)/26 = 1269.23 per day, 1 absent day -> net 31730.77
Assert "e1 net 31730.77 ($($i1.net))" ([decimal]$i1.net -eq 31730.77)
# e2: 40000/26 = 1538.46 per day, 2 unpaid days -> deduction 3076.92 -> net 36923.08
Assert "e2 net 36923.08 ($($i2.net))" ([decimal]$i2.net -eq 36923.08)
Check 'bonus on e1' (Http POST "/api/business/payrolls/$($P.id)/items/$($i1.id)" @{ bonus = 2000; advance = 1000 } $T) 200
$P = (Http GET "/api/business/payrolls/$($P.id)" $null $T).J.data; $i1 = $P.items | Where-Object employee_id -eq $e1.id
Assert "total 69653.85 ($($P.total))" ([decimal]$P.total -eq 69653.85)
Check 'regenerate draft keeps one sheet' (Http POST '/api/business/payrolls/generate' @{ month = $month; working_days = 26 } $T) 201
Check 'cannot pay draft' (Http POST "/api/business/payrolls/$($P.id)/pay" @{ account_id = 1; date = $today } $T) 422
Check 'finalize' (Http POST "/api/business/payrolls/$($P.id)/finalize" $null $T) 200
Check 'cannot regenerate finalized' (Http POST '/api/business/payrolls/generate' @{ month = $month } $T) 422
$bank = (Http POST '/api/business/accounts' @{ name = 'T- Payroll Bank'; type = 'bank'; opening_balance = 200000 } $T).J.data
$P = (Http GET "/api/business/payrolls/$($P.id)" $null $T).J.data; $i1 = $P.items | Where-Object employee_id -eq $e1.id
Check 'pay salaries' (Http POST "/api/business/payrolls/$($P.id)/pay" @{ account_id = $bank.id; date = $today } $T) 200
$bal = [decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $bank.id).balance)
Assert "bank = 200000 - total ($bal)" ($bal -eq 200000 - [decimal]$P.total)
$tx = (Http GET "/api/business/transactions?account_id=$($bank.id)" $null $T).J.data
Assert 'ledger shows Salary expense' (@($tx | Where-Object { $_.category.name -eq 'Salary' }).Count -eq 1)
$pdf = Invoke-WebRequest -UseBasicParsing "$B/api/business/payrolls/$($P.id)/items/$($i1.id)/payslip" -Headers @{ Authorization = "Bearer $T" }
Assert 'payslip pdf' ($pdf.Headers['Content-Type'] -eq 'application/pdf')
Check 'unpay' (Http POST "/api/business/payrolls/$($P.id)/unpay" @{ reason = 'Wrong account' } $T) 200
Assert 'balance restored' ([decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $bank.id).balance) -eq 200000)

"== labour"
$cust = (Http POST '/api/business/customers' @{ name = 'T- Labour Customer' } $T).J.data
$proj = (Http POST '/api/business/projects' @{ customer_id = $cust.id; title = 'T- Labour Project'; contract_value = 300000 } $T).J.data
$con = (Http POST '/api/admin/contractors' @{ name = 'T- Painter Group'; trade = 'painter'; daily_rate = 800; opening_due = 500; is_active = $true } $T).J.data
$r = Http POST '/api/business/labour' @{ work_project_id = $proj.id; contractor_id = $con.id; date = $today; description = 'Wall painting'; workers = 3; days = 2; rate = 800 } $T
Check 'labour entry 3 workers x 2 days x 800' $r 201
Assert "amount 4800 ($($r.J.data.amount))" ([decimal]$r.J.data.amount -eq 4800)
$fin = (Http GET "/api/business/projects/$($proj.id)" $null $T).J.finance
Assert "project labour cost 4800 ($($fin.labour), cost $($fin.cost))" ([decimal]$fin.labour -eq 4800 -and [decimal]$fin.cost -eq 4800)
$c = (Http GET "/api/business/contractors/$($con.id)" $null $T).J
Assert "contractor due 5300 ($($c.due))" ([decimal]$c.due -eq 5300)
$r = Http POST "/api/business/contractors/$($con.id)/pay" @{ amount = 3000; account_id = $bank.id; method = 'cash'; date = $today } $T
Check 'pay contractor' $r 201
Assert "due 2300" ([decimal](Http GET "/api/business/contractors/$($con.id)" $null $T).J.due -eq 2300)
$fin = (Http GET "/api/business/projects/$($proj.id)" $null $T).J.finance
Assert "no double counting (cost still 4800: $($fin.cost))" ([decimal]$fin.cost -eq 4800)
Check 'cancel contractor payment' (Http POST "/api/business/contractor-payments/$($r.J.data.id)/cancel" @{ reason = 'x' } $T) 200
Check 'pay more than balance rejected' (Http POST "/api/business/contractors/$($con.id)/pay" @{ amount = 99999999; account_id = $bank.id; method = 'cash'; date = $today } $T) 422

"== permissions"
$acc = RoleUser 'accounts'; $sal = RoleUser 'sales'; $sto = RoleUser 'store'
Check 'accounts views payroll' (Http GET '/api/business/payrolls' $null $acc) 200
Check 'accounts cannot add employee' (Http POST '/api/business/employees' @{ name = 'x'; basic_salary = 1 } $acc) 403
Check 'sales records labour' (Http POST '/api/business/labour' @{ work_project_id = $proj.id; date = $today; description = 'Helper'; workers = 1; days = 1; rate = 500 } $sal) 201
Check 'sales cannot pay contractor' (Http POST "/api/business/contractors/$($con.id)/pay" @{ amount = 1; account_id = $bank.id; method = 'cash'; date = $today } $sal) 403
Check 'store cannot see HR' (Http GET '/api/business/employees' $null $sto) 403
Done
