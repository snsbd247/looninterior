. "$PSScriptRoot\th.ps1"
# One-click Webmail. Locally there is no cPanel, so a session request must fail cleanly (422), never 500.
"== Webmail: not set up"
Http POST '/api/business/settings' @{ biz_webmail_enabled = '0'; biz_webmail_host = ''; biz_webmail_cpanel_user = ''; biz_webmail_token = ''; biz_webmail_domain = '' } $T | Out-Null
$r = Http GET '/api/business/webmail' $null $T
Check 'admin can open webmail page' $r 200
Assert 'not configured' ($r.J.configured -eq $false)
$r = Http POST '/api/business/webmail/session' $null $T
Check 'session refused when not set up' $r 422
Assert 'clear message' ($r.J.message -like '*not set up*')

"== Settings"
Check 'host with https:// rejected' (Http POST '/api/business/settings' @{ biz_webmail_host = 'https://x.com:2083' } $T) 422
Check 'bad cpanel user rejected' (Http POST '/api/business/settings' @{ biz_webmail_cpanel_user = 'a b' } $T) 422
$r = Http POST '/api/business/settings' @{ biz_webmail_enabled = '1'; biz_webmail_host = '127.0.0.1'; biz_webmail_cpanel_user = 'testuser'; biz_webmail_token = 'T-SECRET-TOKEN'; biz_webmail_domain = 'example.com' } $T
Check 'save webmail settings' $r 200
Assert 'token masked in save response' ($r.J.data.biz_webmail_token -eq '********')
$r = Http GET '/api/business/settings' $null $T
Assert 'token masked in settings' ($r.J.data.biz_webmail_token -eq '********')
Assert 'token never returned' (-not ($r.B -like '*T-SECRET-TOKEN*'))
Check 'saving the mask keeps the token' (Http POST '/api/business/settings' @{ biz_webmail_token = '********' } $T) 200
Assert 'still configured' ((Http GET '/api/business/webmail' $null $T).J.configured -eq $true)
$tok = (& C:\xampp\mysql\bin\mysql.exe -uroot -N -e "SELECT value FROM loon_interior.settings WHERE ``key``='biz_webmail_token'")
Assert 'token kept in database' ($tok -eq 'T-SECRET-TOKEN') $tok

"== Mailbox and access"
$stamp = Get-Random
$rw = (Http POST '/api/business/roles' @{ name = "T- Webmail $stamp"; permissions = @('webmail.use') } $T).J.data
$rn = (Http POST '/api/business/roles' @{ name = "T- NoWebmail $stamp"; permissions = @('crm.view') } $T).J.data
$u = Http POST '/api/business/users' @{ name = 'Test Webmail'; email = 'test-webmail@example.com'; role_id = $rw.id; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T
Check 'create staff user' $u 201
Check 'bad mailbox rejected' (Http POST "/api/business/users/$($u.J.data.id)" @{ name = 'Test Webmail'; email = 'test-webmail@example.com'; mailbox = 'not-an-email'; role_id = $rw.id; branch_ids = @(1) } $T) 422
$W = Login 'test-webmail@example.com' 'TestPass#123'
$r = Http GET '/api/business/webmail' $null $W
Check 'staff with webmail.use' $r 200
Assert 'login e-mail on the domain is the mailbox' ($r.J.mailbox -eq 'test-webmail@example.com') $r.B
$r = Http POST '/api/business/webmail/session' $null $W
Check 'unreachable cPanel -> 422' $r 422
Assert 'reports cPanel problem' ($r.J.message -like '*cPanel*') $r.J.message
Check 'set own mailbox' (Http POST "/api/business/users/$($u.J.data.id)" @{ name = 'Test Webmail'; email = 'test-webmail@example.com'; mailbox = 'Sales@Other.example'; role_id = $rw.id; branch_ids = @(1) } $T) 200
Assert 'mailbox field wins, lower-cased' ((Http GET '/api/business/webmail' $null $W).J.mailbox -eq 'sales@other.example')
Http POST '/api/business/users' @{ name = 'Test NoWebmail'; email = 'test-nowebmail@example.com'; role_id = $rn.id; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T | Out-Null
$N = Login 'test-nowebmail@example.com' 'TestPass#123'
Check 'no permission -> 403' (Http GET '/api/business/webmail' $null $N) 403
Check 'no permission session -> 403' (Http POST '/api/business/webmail/session' $null $N) 403
Check 'cannot read the token' (Http GET '/api/business/settings' $null $N) 403

"== Mailbox check / create from Users (no cPanel locally)"
Check 'quota setting saved' (Http POST '/api/business/settings' @{ biz_webmail_quota = '1024' } $T) 200
Check 'bad quota rejected' (Http POST '/api/business/settings' @{ biz_webmail_quota = '-5' } $T) 422
$r = Http GET '/api/business/webmail/check?email=someone@gmail.com' $null $T
Check 'check other domain' $r 200
Assert 'other domain status' ($r.J.status -eq 'other_domain') $r.B
$r = Http GET '/api/business/webmail/check?email=new.staff@example.com' $null $T
Assert 'cPanel unreachable -> error status' ($r.J.status -eq 'error') $r.B
Check 'check needs users.manage' (Http GET '/api/business/webmail/check?email=a@example.com' $null $W) 403
$r = Http POST '/api/business/users' @{ name = 'Test Mailbox'; email = 'test-mailbox@example.com'; create_mailbox = $true; role_id = $rw.id; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T
Check 'create mailbox fails -> user not saved (422)' $r 422
Assert 'reason on email field' ($r.J.errors.email -ne $null) $r.B
Assert 'user really not saved' (-not ((Http GET '/api/business/users?search=test-mailbox' $null $T).B -like '*test-mailbox@example.com*'))
Check 'create mailbox for other domain -> 422' (Http POST '/api/business/users' @{ name = 'Test Mailbox'; email = 'test-mailbox@example.org'; create_mailbox = $true; role_id = $rw.id; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T) 422
Check 'short mailbox password -> 422' (Http POST '/api/business/users' @{ name = 'Test Mailbox'; email = 'test-mailbox@example.com'; create_mailbox = $true; mailbox_password = 'abc'; role_id = $rw.id; branch_ids = @(1); password = 'TestPass#123'; password_confirmation = 'TestPass#123' } $T) 422
$r = Http POST "/api/business/users/$($u.J.data.id)" @{ name = 'Test Webmail'; email = 'test-webmail@example.com'; mailbox = 'sales@other.example'; role_id = $rw.id; branch_ids = @(1); is_active = $false } $T
Check 'deactivate user with off-domain mailbox still works' $r 200

"== Cleanup"
Http POST '/api/business/settings' @{ biz_webmail_enabled = '0'; biz_webmail_host = ''; biz_webmail_cpanel_user = ''; biz_webmail_token = ''; biz_webmail_domain = '' } $T | Out-Null
& C:\xampp\mysql\bin\mysql.exe -uroot loon_interior -e "DELETE t FROM personal_access_tokens t JOIN users u ON u.id=t.tokenable_id WHERE u.email IN ('test-webmail@example.com','test-nowebmail@example.com'); DELETE bu FROM branch_user bu JOIN users u ON u.id=bu.user_id WHERE u.email IN ('test-webmail@example.com','test-nowebmail@example.com'); DELETE FROM users WHERE email IN ('test-webmail@example.com','test-nowebmail@example.com','test-mailbox@example.com'); DELETE FROM roles WHERE name LIKE 'T- %Webmail%'; DELETE FROM settings WHERE ``key`` LIKE 'biz_webmail_%'; DELETE FROM activity_logs WHERE description LIKE '%Webmail%' OR description LIKE '%biz_webmail%';"
Done
