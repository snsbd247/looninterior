. "$PSScriptRoot\th.ps1"
# Website cost calculator: options API, server-side estimate, lead + PDF, spam guard, admin options.
"== options"
$r = Http GET '/api/calculator'
Check 'GET /api/calculator' $r 200
$o = $r.J.options
Assert "4 groups with options" ($o.type.Count -ge 1 -and $o.style.Count -ge 1 -and $o.quality.Count -ge 1 -and $o.addon.Count -ge 1)
$type = $o.type | Select-Object -First 1; $style = $o.style | Select-Object -First 1; $qual = $o.quality | Select-Object -Last 1; $add = $o.addon | Select-Object -First 1
$token = (Http GET '/api/site').J.form_token
Start-Sleep 3   # the form token must be at least 3 seconds old (SpamGuard)

"== quote (server recomputes)"
$body = @{ name = 'T- Calc Visitor'; phone = '01700000555'; email = 'calc@example.com'; location = 'Gulshan'; type_id = $type.id; style_id = $style.id; quality_id = $qual.id; area = 1500; addon_ids = @($add.id); form_token = $token; website = ''; estimate = 1 }
$r = Http POST '/api/calculator/quote' $body
Check 'quote' $r 201
$e = $r.J.estimate
$expRate = [math]::Round([decimal]$type.value * [decimal]$style.value * [decimal]$qual.value, 2)
$expEst = $expRate * 1500 + [decimal]$add.value
Assert "rate $expRate = $($e.rate)" ([decimal]$e.rate -eq $expRate)
Assert "estimate $expEst = $($e.estimate) (browser value ignored)" ([decimal]$e.estimate -eq $expEst)
Assert "range low < estimate < high ($($e.low) / $($e.high))" ([decimal]$e.low -lt $expEst -and [decimal]$e.high -gt $expEst)
Assert "timeline days ($($e.days))" ($e.days -gt 0 -and $e.days % 5 -eq 0)
Assert 'breakdown sums to ~100%' ([math]::Abs((($e.breakdown | Measure-Object percent -Sum).Sum) - 100) -lt 0.5)
$pdfUrl = $r.J.pdf_url
$pdf = Invoke-WebRequest -UseBasicParsing $pdfUrl
Assert 'signed estimate PDF' ($pdf.Headers['Content-Type'] -eq 'application/pdf')
Check 'PDF without signature rejected' (Http GET ($pdfUrl -replace '\?.*$', '' -replace [regex]::Escape($B), '')) 403

"== CRM lead"
$lead = ((Http GET '/api/business/leads?q=T-%20Calc' $null $T).J.data | Select-Object -First 1)
Assert 'lead created' ($null -ne $lead)
$ld = (Http GET "/api/business/leads/$($lead.id)" $null $T).J.data
Assert "lead expected value = estimate ($($ld.expected_value))" ([decimal]$ld.expected_value -eq $expEst)
Assert 'lead note has the selections' ((@($ld.activities | Where-Object { $_.note -like '*Cost calculator*' }).Count) -ge 1)
$inq = ((Http GET '/api/admin/inquiries?q=T-%20Calc' $null $T).J.data | Select-Object -First 1)
Assert "inquiry type calculator ($($inq.type))" ($inq.type -eq 'calculator')

"== validation & spam"
function With($h, $patch) { $c = @{} + $h; foreach ($k in $patch.Keys) { $c[$k] = $patch[$k] }; $c }
Check 'wrong option group rejected' (Http POST '/api/calculator/quote' (With $body @{ type_id = $style.id })) 422
Check 'area too small rejected' (Http POST '/api/calculator/quote' (With $body @{ area = 5 })) 422
Check 'phone required' (Http POST '/api/calculator/quote' (With $body @{ phone = '' })) 422
$before = @((Http GET '/api/admin/inquiries?q=T-%20Bot' $null $T).J.data).Count
$r = Http POST '/api/calculator/quote' (With $body @{ name = 'T- Bot'; website = 'http://spam' })
Assert 'honeypot: looks OK to the bot' ($r.S -eq 201)
Assert 'honeypot: no inquiry saved' (@((Http GET '/api/admin/inquiries?q=T-%20Bot' $null $T).J.data).Count -eq $before)

"== room by room"
$rooms = @($o.room)
Assert "room options listed ($($rooms.Count))" ($rooms.Count -ge 3)
$r1 = $rooms[0]; $r2 = $rooms[1]
$rb = With $body @{ name = 'T- Calc Rooms'; phone = '01700000556'; mode = 'rooms'; rooms = @(@{ id = $r1.id; qty = 1 }, @{ id = $r2.id; qty = 2 }, @{ id = $rooms[2].id; qty = 0 }); addon_ids = @() }
$r = Http POST '/api/calculator/quote' $rb
Check 'room quote' $r 201
$f = [decimal]$style.value * [decimal]$qual.value
$u1 = [math]::Round([decimal]$r1.value * $f, 2); $u2 = [math]::Round([decimal]$r2.value * $f, 2)
$exp = $u1 + 2 * $u2
Assert "room estimate $exp = $($r.J.estimate.estimate)" ([decimal]$r.J.estimate.estimate -eq $exp)
Assert "2 room lines (zero-count room ignored)" (@($r.J.estimate.rooms).Count -eq 2 -and $r.J.estimate.mode -eq 'rooms')
Assert 'room PDF' ((Invoke-WebRequest -UseBasicParsing $r.J.pdf_url).Headers['Content-Type'] -eq 'application/pdf')
Check 'no rooms rejected' (Http POST '/api/calculator/quote' (With $rb @{ rooms = @(@{ id = $r1.id; qty = 0 }) })) 422
Check 'too many of a room rejected' (Http POST '/api/calculator/quote' (With $rb @{ rooms = @(@{ id = $r1.id; qty = 50 }) })) 422
Check 'non-room id rejected' (Http POST '/api/calculator/quote' (With $rb @{ rooms = @(@{ id = $type.id; qty = 1 }) })) 422

"== admin options"
$n = Http POST '/api/admin/calculator_options' @{ group = 'addon'; name = 'T- Test Extra'; value = 999; is_active = $true; sort_order = 99 } $T
Check 'admin adds option' $n 201
Assert 'new option is public' (@((Http GET '/api/calculator').J.options.addon | Where-Object name -eq 'T- Test Extra').Count -eq 1)
Check 'bad group rejected' (Http POST '/api/admin/calculator_options' @{ group = 'x'; name = 'x'; value = 1 } $T) 422
Check 'admin deletes option' (Http DELETE "/api/admin/calculator_options/$($n.J.data.id)" $null $T) 200
$sal = RoleUser 'support'
Check 'support cannot edit options' (Http POST '/api/admin/calculator_options' @{ group = 'addon'; name = 'x'; value = 1 } $sal) 403
Done

