. "$PSScriptRoot\th.ps1"
"== dashboard blocks per role"
$d = (Http GET '/api/business/dashboard' $null $T).J
Assert 'admin sees every block' ($d.crm -and $d.quotations -and $d.billing -and $d.accounts -and $d.projects -and $d.inventory -and $d.tickets)
Assert 'billing has 6-month trend' (@($d.billing.trend).Count -eq 6)
Assert 'no branch table in single-branch view' (-not $d.branches)
$all = (Http GET '/api/business/dashboard' $null $T 'all').J
Assert "central view has branch table ($(@($all.branches).Count) rows)" (@($all.branches).Count -ge 1 -and $all.central)
$users = @{}
foreach ($r in 'accounts', 'store', 'sales', 'support') { $users[$r] = RoleUser $r }
$acc = (Http GET '/api/business/dashboard' $null $users.accounts).J
Assert 'accounts: billing + accounts, no inventory manage block hidden? (has inventory view)' ($acc.billing -and $acc.accounts -and $acc.inventory -and -not $acc.tickets)
$sto = (Http GET '/api/business/dashboard' $null $users.store).J
Assert 'store: inventory + projects only' ($sto.inventory -and $sto.projects -and -not $sto.billing -and -not $sto.crm -and -not $sto.accounts)
$sal = (Http GET '/api/business/dashboard' $null $users.sales).J
Assert 'sales: crm + quotations + billing view, no accounts' ($sal.crm -and $sal.quotations -and $sal.billing -and -not $sal.accounts -and -not $sal.inventory)
$sup = (Http GET '/api/business/dashboard' $null $users.support).J
Assert 'support: tickets + crm + projects, no money' ($sup.tickets -and $sup.crm -and -not $sup.billing -and -not $sup.accounts)
Check 'HO user cannot ask central view' (Http GET '/api/business/dashboard' $null $users.sales 'all') 403
Done
