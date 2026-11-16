. "$PSScriptRoot\th.ps1"
"== setup"
$lead = (Http POST '/api/business/leads' @{ name = 'T- Quote Lead'; phone = '01788888881'; email = 'quote-customer@example.com' } $T).J.data
$cust = (Http POST "/api/business/leads/$($lead.id)/convert" $null $T).J.data
$tax = ((Http GET '/api/business/lookup/tax-rates' $null $T).J.data | Where-Object is_default)[0]
"customer $($cust.id), tax $($tax.name) $($tax.rate)"

$items = @(
  @{ section = 'Living Room'; description = 'False ceiling'; unit = 'sqft'; qty = 320; rate = 180 },
  @{ section = 'Living Room'; description = 'TV wall'; unit = 'rft'; qty = 12.5; rate = 4500 },
  @{ section = 'Kitchen'; description = 'Cabinet'; unit = 'rft'; qty = 18; rate = 7500 }
)
"== create + server-side totals"
$r = Http POST '/api/business/quotations' @{ customer_id = $cust.id; lead_id = $lead.id; title = 'T- Apartment'; date = (Get-Date -Format 'yyyy-MM-dd'); discount_type = 'percent'; discount_value = 5; tax_rate_id = $tax.id; items = $items; subtotal = 1; total = 1 } $T
Check 'create quotation' $r 201
$Q = $r.J.data
# subtotal = 57600 + 56250 + 135000 = 248850 ; discount 5% = 12442.50 ; tax 15% of 236407.50 = 35461.13 (35461.125 -> round half up) ; total = 271868.63
Assert "subtotal 248850 (got $($Q.subtotal))" ([decimal]$Q.subtotal -eq 248850)
Assert "discount 12442.50 (got $($Q.discount))" ([decimal]$Q.discount -eq 12442.50)
Assert "tax 35461.13 (got $($Q.tax))" ([decimal]$Q.tax -eq 35461.13)
Assert "total 271868.63 (got $($Q.total))" ([decimal]$Q.total -eq 271868.63)
Assert 'browser totals ignored' ([decimal]$Q.total -ne 1)
Assert "number format ($($Q.number))" ($Q.number -match '^HO-QT-\d{4}-\d{4}$')
Check 'discount > 100% rejected' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; discount_type = 'percent'; discount_value = 150; items = $items } $T) 422
Check 'discount > subtotal rejected' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; discount_type = 'amount'; discount_value = 999999; items = $items } $T) 422
Check 'no items rejected' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; items = @() } $T) 422
Check 'valid_until before date rejected' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; valid_until = '2026-11-01'; items = $items } $T) 422

"== edit draft, send, revise"
Check 'edit draft' (Http POST "/api/business/quotations/$($Q.id)" @{ customer_id = $cust.id; date = '2026-11-16'; discount_type = 'amount'; discount_value = 0; items = @($items[0]) } $T) 200
$d = (Http GET "/api/business/quotations/$($Q.id)" $null $T).J
Assert 'edit replaced items' (@($d.data.items).Count -eq 1 -and [decimal]$d.data.total -eq 57600)
Assert 'share links present' ($d.share_url.en -match 'signature=')
Check 'mark sent' (Http POST "/api/business/quotations/$($Q.id)/status" @{ status = 'sent' } $T) 200
$ld = (Http GET "/api/business/leads/$($lead.id)" $null $T).J.data
Assert "lead moved to quotation_sent ($($ld.stage))" ($ld.stage -eq 'quotation_sent')
Check 'sent cannot be edited' (Http POST "/api/business/quotations/$($Q.id)" @{ customer_id = $cust.id; date = '2026-11-16'; items = $items } $T) 422
$r = Http POST "/api/business/quotations/$($Q.id)/revise" $null $T
Check 'revise' $r 201
$R1 = $r.J.data
Assert "revision number ($($R1.number))" ($R1.number -eq "$($Q.number)-R1" -and $R1.status -eq 'draft')
Assert 'old version marked revised' ((Http GET "/api/business/quotations/$($Q.id)" $null $T).J.data.status -eq 'revised')
Assert 'list shows latest only' (@((Http GET "/api/business/quotations?q=$($Q.number)" $null $T).J.data).Count -eq 1)
Check 'cannot revise a draft' (Http POST "/api/business/quotations/$($R1.id)/revise" $null $T) 422
Check 'invalid transition draft->rejected' (Http POST "/api/business/quotations/$($R1.id)/status" @{ status = 'rejected' } $T) 422
Check 'approve revision' (Http POST "/api/business/quotations/$($R1.id)/status" @{ status = 'approved' } $T) 200
$ld = (Http GET "/api/business/leads/$($lead.id)" $null $T).J.data
Assert "lead won on approval ($($ld.stage))" ($ld.stage -eq 'won')

"== documents"
foreach ($l in 'en', 'bn') {
  $p = Invoke-WebRequest -UseBasicParsing "$B/api/business/quotations/$($R1.id)/pdf?lang=$l" -Headers @{ Authorization = "Bearer $T" }
  Assert "pdf $l" ($p.Headers['Content-Type'] -eq 'application/pdf' -and $p.RawContentLength -gt 20000)
}
$share = (Http GET "/api/business/quotations/$($R1.id)" $null $T).J.share_url.bn
$pub = Invoke-WebRequest -UseBasicParsing $share
Assert 'public signed link opens without login' ($pub.Headers['Content-Type'] -eq 'application/pdf')
try { Invoke-WebRequest -UseBasicParsing ($share -replace 'signature=.', 'signature=0') | Out-Null; Assert 'tampered link rejected' $false } catch { Assert 'tampered link rejected' ([int]$_.Exception.Response.StatusCode -eq 403) }
try { Invoke-WebRequest -UseBasicParsing ($share -replace "quotations/$($R1.id)/", "quotations/$($Q.id)/") | Out-Null; Assert 'other id with same signature rejected' $false } catch { Assert 'other id with same signature rejected' ([int]$_.Exception.Response.StatusCode -eq 403) }
Check 'email quotation' (Http POST "/api/business/quotations/$($R1.id)/email" @{ to = 'quote-customer@example.com'; lang = 'bn' } $T) 200
$mail = Get-Content $PSScriptRoot\..\..\backend\storage\logs\mail.log -Raw -ErrorAction SilentlyContinue
Assert 'email has PDF attachment' ($mail -match [regex]::Escape("$($R1.number)") -and $mail -match 'application/pdf')

"== permissions & branch"
$acc = RoleUser 'accounts'; $sto = RoleUser 'store'; $sal = RoleUser 'sales'
Check 'accounts can view' (Http GET "/api/business/quotations/$($R1.id)" $null $acc) 200
Check 'accounts cannot create' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; items = $items } $acc) 403
Check 'store cannot view' (Http GET '/api/business/quotations' $null $sto) 403
Check 'sales can create' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; items = $items } $sal) 201
$tb = (Http POST '/api/admin/branches' @{ name = 'CRM Q Branch'; code = 'CTB' } $T).J.data.id
Check 'customer of other branch rejected' (Http POST '/api/business/quotations' @{ customer_id = $cust.id; date = '2026-11-16'; items = $items } $T $tb) 404
Check 'other branch cannot open quotation' (Http GET "/api/business/quotations/$($R1.id)" $null $T $tb) 404
Done
