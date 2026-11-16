. "$PSScriptRoot\th.ps1"
$today = Get-Date -Format 'yyyy-MM-dd'
function StockOf($itemId, $storeId, $br = $null) { $s = (Http GET "/api/business/stock?store_id=$storeId" $null $T $br).J.data | Where-Object id -eq $itemId; return [decimal]$s.qty }
function Materials($projId) { return [decimal](Http GET "/api/business/projects/$projId" $null $T).J.finance.materials }

"== setup"
$cat = (Http POST '/api/admin/item_categories' @{ name = 'T- Boards'; is_active = $true } $T).J.data
$board = (Http POST '/api/admin/items' @{ name = 'T- MDF Board 18mm'; sku = 'T-MDF18'; item_category_id = $cat.id; unit = 'sheet'; min_stock = 5; is_active = $true } $T).J.data
$hinge = (Http POST '/api/admin/items' @{ name = 'T- Soft Hinge'; unit = 'pcs'; is_active = $true } $T).J.data
$sup = (Http POST '/api/admin/suppliers' @{ name = 'T- Akij Board Ltd'; phone = '01811111111'; opening_due = 1000; is_active = $true } $T).J.data
$store = ((Http GET '/api/business/lookup/stores' $null $T).J.data | Where-Object name -eq 'Main Store')[0]
$cust = (Http POST '/api/business/customers' @{ name = 'T- Stock Customer'; phone = '01811111112' } $T).J.data
$proj = (Http POST '/api/business/projects' @{ customer_id = $cust.id; title = 'T- Stock Project'; contract_value = 500000 } $T).J.data
$bank = (Http POST '/api/business/accounts' @{ name = 'T- Bank'; type = 'bank'; opening_balance = 100000 } $T).J.data
Assert 'setup ok' ($board.id -and $sup.id -and $store.id -and $proj.id -and $bank.id)

"== purchase order"
$r = Http POST '/api/business/purchase-orders' @{ supplier_id = $sup.id; store_id = $store.id; date = $today; items = @(@{ item_id = $board.id; qty = 20; rate = 100 }, @{ item_id = $hinge.id; qty = 50; rate = 40 }) } $T
Check 'create PO' $r 201
$PO = (Http GET "/api/business/purchase-orders/$($r.J.data.id)" $null $T).J.data
Assert "PO total 4000 ($($PO.total))" ([decimal]$PO.total -eq 4000)
Check 'mark sent' (Http POST "/api/business/purchase-orders/$($PO.id)/status" @{ status = 'sent' } $T) 200
Check 'PO pdf' @{ S = (Invoke-WebRequest -UseBasicParsing "$B/api/business/purchase-orders/$($PO.id)/pdf" -Headers @{ Authorization = "Bearer $T" }).StatusCode; B = '' } 200

"== GRN + weighted average"
$poBoard = ($PO.items | Where-Object item_id -eq $board.id)[0]
$r = Http POST '/api/business/purchases' @{ type = 'grn'; supplier_id = $sup.id; store_id = $store.id; purchase_order_id = $PO.id; date = $today; supplier_ref = 'CH-1'; items = @(@{ item_id = $board.id; purchase_order_item_id = $poBoard.id; qty = 10; rate = 100 }) } $T
Check 'GRN 10 @ 100 against PO' $r 201
$G1 = $r.J.data
Assert "GRN number ($($G1.number))" ($G1.number -match '^HO-GRN-')
Assert "PO partial ($((Http GET "/api/business/purchase-orders/$($PO.id)" $null $T).J.data.status))" ((Http GET "/api/business/purchase-orders/$($PO.id)" $null $T).J.data.status -eq 'partial')
Check 'GRN 10 @ 130 (no PO)' (Http POST '/api/business/purchases' @{ type = 'grn'; supplier_id = $sup.id; store_id = $store.id; date = $today; items = @(@{ item_id = $board.id; qty = 10; rate = 130 }) } $T) 201
$st = (Http GET "/api/business/stock?store_id=$($store.id)" $null $T).J.data | Where-Object id -eq $board.id
Assert "stock 20 ($($st.qty)), avg 115 ($($st.avg_cost)), value 2300 ($($st.value))" ([decimal]$st.qty -eq 20 -and [decimal]$st.avg_cost -eq 115 -and [decimal]$st.value -eq 2300)
Check 'GRN with non-stock line rejected' (Http POST '/api/business/purchases' @{ type = 'grn'; supplier_id = $sup.id; store_id = $store.id; date = $today; items = @(@{ description = 'Misc'; qty = 1; rate = 10 }) } $T) 422

"== issue / return / transfer / adjust"
$r = Http POST '/api/business/stock-entries' @{ type = 'issue'; store_id = $store.id; work_project_id = $proj.id; date = $today; items = @(@{ item_id = $board.id; qty = 5 }) } $T
Check 'issue 5 to project' $r 201
$ISS = $r.J.data
Assert "stock 15 ($(StockOf $board.id $store.id))" ((StockOf $board.id $store.id) -eq 15)
Assert "project materials 575 ($(Materials $proj.id))" ((Materials $proj.id) -eq 575)
Check 'issue more than stock rejected' (Http POST '/api/business/stock-entries' @{ type = 'issue'; store_id = $store.id; work_project_id = $proj.id; date = $today; items = @(@{ item_id = $board.id; qty = 100 }) } $T) 422
Assert 'failed issue changed nothing' ((StockOf $board.id $store.id) -eq 15)
Check 'return 1 from project' (Http POST '/api/business/stock-entries' @{ type = 'return'; store_id = $store.id; work_project_id = $proj.id; date = $today; items = @(@{ item_id = $board.id; qty = 1 }) } $T) 201
Assert "materials 460 ($(Materials $proj.id))" ((Materials $proj.id) -eq 460)
Check 'adjust without reason rejected' (Http POST '/api/business/stock-entries' @{ type = 'adjust'; store_id = $store.id; date = $today; items = @(@{ item_id = $board.id; qty = -1 }) } $T) 422
Check 'adjust -1 (damaged)' (Http POST '/api/business/stock-entries' @{ type = 'adjust'; store_id = $store.id; date = $today; notes = 'Damaged sheet'; items = @(@{ item_id = $board.id; qty = -1 }) } $T) 201
Assert "stock 15 after return+adjust ($(StockOf $board.id $store.id))" ((StockOf $board.id $store.id) -eq 15)
Check 'cancel the issue' (Http POST "/api/business/stock-entries/$($ISS.id)/cancel" @{ reason = 'Wrong project' } $T) 200
Assert "stock 20 after cancel ($(StockOf $board.id $store.id))" ((StockOf $board.id $store.id) -eq 20)
Assert "materials -115 (only the return left) ($(Materials $proj.id))" ((Materials $proj.id) -eq -115)
$tb = (Http POST '/api/admin/branches' @{ name = 'CRM Inv Branch'; code = 'CTB' } $T).J.data.id
$tbStore = ((Http GET '/api/business/lookup/stores' $null $T $tb).J.data)[0]
Assert 'new branch got Main Store' ($tbStore.name -eq 'Main Store')
Check 'transfer 4 to other branch store (all view)' (Http POST '/api/business/stock-entries' @{ type = 'transfer'; store_id = $store.id; to_store_id = $tbStore.id; date = $today; items = @(@{ item_id = $board.id; qty = 4 }) } $T 'all') 201
Assert "HO 16 / TB 4 ($(StockOf $board.id $store.id) / $(StockOf $board.id $tbStore.id $tb))" ((StockOf $board.id $store.id) -eq 16 -and (StockOf $board.id $tbStore.id $tb) -eq 4)
Check 'issue to project of other branch rejected' (Http POST '/api/business/stock-entries' @{ type = 'issue'; store_id = $tbStore.id; work_project_id = $proj.id; date = $today; items = @(@{ item_id = $board.id; qty = 1 }) } $T 'all') 422
$led = (Http GET "/api/business/stock/items/$($board.id)" $null $T 'all').J
Assert "item ledger has movements ($(@($led.lines).Count))" (@($led.lines).Count -ge 8)

"== direct purchase & supplier"
Check 'direct purchase on credit' (Http POST '/api/business/purchases' @{ type = 'direct'; supplier_id = $sup.id; work_project_id = $proj.id; date = $today; items = @(@{ description = 'Glass partition'; unit = 'sqft'; qty = 20; rate = 100 }) } $T) 201
Assert "materials 1885 ($(Materials $proj.id))" ((Materials $proj.id) -eq 1885)
$bal0 = [decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $bank.id).balance)
Check 'cash purchase paid now (no supplier)' (Http POST '/api/business/purchases' @{ type = 'direct'; work_project_id = $proj.id; date = $today; pay_now_account_id = $bank.id; pay_method = 'cash'; items = @(@{ description = 'Screws'; qty = 1; rate = 500 }) } $T) 201
$bal1 = [decimal](((Http GET '/api/business/lookup/accounts' $null $T).J.data | Where-Object id -eq $bank.id).balance)
Assert "bank -500 ($bal0 -> $bal1)" ($bal0 - $bal1 -eq 500)
Assert "materials 2385 ($(Materials $proj.id))" ((Materials $proj.id) -eq 2385)
Assert "project expenses not double counted ($((Http GET "/api/business/projects/$($proj.id)" $null $T).J.finance.expenses))" ([decimal](Http GET "/api/business/projects/$($proj.id)" $null $T).J.finance.expenses -eq 0)
$s1 = (Http GET "/api/business/suppliers/$($sup.id)" $null $T).J
# opening 1000 + GRN 1000 + GRN 1300 + direct 2000 = 5300
Assert "supplier due 5300 ($($s1.due))" ([decimal]$s1.due -eq 5300)
$r = Http POST "/api/business/suppliers/$($sup.id)/pay" @{ amount = 3000; account_id = $bank.id; method = 'bank'; date = $today; purchase_id = $G1.id } $T
Check 'pay more than purchase left rejected' $r 422
$r = Http POST "/api/business/suppliers/$($sup.id)/pay" @{ amount = 1000; account_id = $bank.id; method = 'bank'; date = $today; purchase_id = $G1.id } $T
Check 'pay 1000 against GRN' $r 201
$SP = $r.J.data
Assert "due 4300 ($((Http GET "/api/business/suppliers/$($sup.id)" $null $T).J.due))" ([decimal](Http GET "/api/business/suppliers/$($sup.id)" $null $T).J.due -eq 4300)
Check 'cancel paid GRN rejected' (Http POST "/api/business/purchases/$($G1.id)/cancel" @{ reason = 'x' } $T) 422
Check 'cancel supplier payment' (Http POST "/api/business/supplier-payments/$($SP.id)/cancel" @{ reason = 'Wrong account' } $T) 200
Assert "due back to 5300" ([decimal](Http GET "/api/business/suppliers/$($sup.id)" $null $T).J.due -eq 5300)
Check 'pay more than bank balance rejected' (Http POST "/api/business/suppliers/$($sup.id)/pay" @{ amount = 99999999; account_id = $bank.id; method = 'bank'; date = $today } $T) 422
$list = (Http GET '/api/business/suppliers' $null $T).J
Assert 'supplier list has due' ((($list.data | Where-Object id -eq $sup.id).due) -eq 5300)

"== cancel GRN corrects stock and average"
$g2 = (Http GET "/api/business/purchases?q=GRN" $null $T).J.data | Where-Object { [decimal]$_.total -eq 1300 }
Check 'cancel GRN 10 @ 130' (Http POST "/api/business/purchases/$($g2[0].id)/cancel" @{ reason = 'Returned to supplier' } $T) 200
$st = (Http GET "/api/business/stock?store_id=$($store.id)" $null $T).J.data | Where-Object id -eq $board.id
Assert "HO stock 6 ($($st.qty))" ([decimal]$st.qty -eq 6)

"== permissions"
$sto = RoleUser 'store'; $acc = RoleUser 'accounts'; $sal = RoleUser 'sales'
Check 'store creates GRN' (Http POST '/api/business/purchases' @{ type = 'grn'; supplier_id = $sup.id; store_id = $store.id; date = $today; items = @(@{ item_id = $hinge.id; qty = 10; rate = 40 }) } $sto) 201
Check 'store cannot pay supplier' (Http POST "/api/business/suppliers/$($sup.id)/pay" @{ amount = 1; account_id = $bank.id; method = 'cash'; date = $today } $sto) 403
Check 'store cannot use pay-now' (Http POST '/api/business/purchases' @{ type = 'direct'; work_project_id = $proj.id; date = $today; pay_now_account_id = $bank.id; items = @(@{ description = 'x'; qty = 1; rate = 1 }) } $sto) 422
Check 'accounts views stock' (Http GET '/api/business/stock' $null $acc) 200
Check 'accounts cannot issue stock' (Http POST '/api/business/stock-entries' @{ type = 'adjust'; store_id = $store.id; date = $today; notes = 'x'; items = @(@{ item_id = $hinge.id; qty = 1 }) } $acc) 403
Check 'accounts pays supplier' (Http POST "/api/business/suppliers/$($sup.id)/pay" @{ amount = 10; account_id = $bank.id; method = 'bank'; date = $today } $acc) 201
Check 'sales cannot see stock' (Http GET '/api/business/stock' $null $sal) 403
Check 'stock csv' @{ S = (Invoke-WebRequest -UseBasicParsing "$B/api/business/stock?format=csv" -Headers @{ Authorization = "Bearer $T" }).StatusCode; B = '' } 200
Done
