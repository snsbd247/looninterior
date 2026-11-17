# Loon Interior: Working Rules

Laravel 12 API + React 18 (Vite) + MySQL interior-design website with an admin panel, growing into a
multi-branch business system (CRM, quotations, projects, billing, accounts, inventory, ticketing).
Must keep running on shared cPanel hosting (no SSH, no queue worker, no Node server).

**The owner writes in Bangla. Reply in Bangla.**

## Source of truth: read before and after every task

| File | What it holds |
|---|---|
| [plan.md](plan.md) | Roadmap, approved decisions, roles/permissions matrix, step checklists, progress |
| [UI.md](UI.md) | Architecture: folders, request flow, data model, design tokens, UI patterns |
| [README.md](README.md) | Install, local setup, cPanel deploy, API summary |

## Workflow for every task (mandatory)

1. **Before starting**
   - Read the relevant parts of `plan.md` and `UI.md`.
   - Make sure the task fits the approved plan and decisions. If it conflicts, ask the owner first.
   - Run the smoke test and confirm everything passes before touching code:
     `powershell -ExecutionPolicy Bypass -File scripts\smoke-test.ps1`
2. **While working**
   - Follow the existing patterns in `UI.md`. Do not invent a parallel structure.
   - Do not change the contract of existing APIs, tables or components. Add, don't rewrite:
     - new columns must be nullable or have defaults;
     - new routes go under new prefixes;
     - never rename or drop existing tables or columns.
   - Business tables must not clash with website tables (`customers`, not `clients`; `work_projects`, not `projects`).
3. **After finishing**
   - Run `npm run build`, then the smoke test again. Every check must pass.
   - Add smoke-test checks for the new endpoints (section "Business modules" in `scripts/smoke-test.ps1`).
   - Check mobile width 390px for new screens (`C:\Users\Administrator\Downloads\loon-tools\shot.js` / `admin.js`).
   - Remove any test data you created.
   - Update `plan.md` (tick the checklist, update the progress table) and `UI.md` (new tables, routes, components).
     Update `README.md` if setup or deploy changed.
   - Commit with a clear message. Push to GitHub only when the owner has approved.

## Non-negotiable rules for money and stock

- Delete (phase G): use `App\Support\SafeDelete` (deletes only when no foreign key points to the record, otherwise 422
  with the reason) and the `DeleteButton` component. Generic CRUD resources opt in with `safe_delete`. Watch cascade
  pivots that would lock people out (e.g. `branch_user`): pass them in `$block`.
- Wrap every posting in `DB::transaction`. A half-saved invoice, payment or stock move must be impossible.
- Never hard-delete financial or stock documents. Cancel them, or post a reversing entry.
- Totals are always computed on the server. Never trust totals sent by the browser.
- Branch isolation is enforced on the server (the `BranchContext` middleware plus the model scope), not only in the UI.
- Every business API route needs a `perm:` middleware. Hiding a menu item is not access control.
- Document numbers come from `DocumentNumber::next()`, called inside the document's transaction. It does an atomic upsert and was tested with 6 parallel processes. They must never be generated in PHP memory or JS.
- New business tables with a `branch_id` use the `BelongsToBranch` trait. New models use `LogsActivity`.
- New permissions go in `config/permissions.php`. Every new route needs `perm:`.

## UI rules (owner decisions, 17 Nov 2026)

- All text on the website and the admin panel is **English**. Bangla appears only inside business document PDFs ("PDF (Bangla)" option, `lang/bn/doc.php`).
- The admin sidebar is an **accordion submenu** (`SideMenu` in `AdminApp.jsx`). New sections go into an existing group (`GROUPS` / `EXTRA_LINKS`), or a new group with an icon in `GROUP_ICONS`.

## Client portal and token rules

- Two token owners share Sanctum: staff `User` and portal `Customer`. Staff routes must keep the `active`
  middleware (it rejects non-`User` tokens). Portal routes live under `/api/portal` with `auth:sanctum` + `portal`.
- Portal queries always filter by the signed-in `customer_id` and never show drafts, cancelled documents or
  internal ticket notes (only `kind = client` or `is_public = true` comments).
- `ActivityLogger` records staff only. Never write a customer id into a `user_id` column.

## SMS and email

- Send SMS only through `App\Services\Sms` (`Sms::event()` for automatic messages: it waits for the DB commit
  and never throws). With no gateway URL it writes to `storage/logs/sms.log`.
- Gateway secrets live in `biz_sms_*` settings, which only `settings.business` can read. Never return them elsewhere.
- Providers: `greenweb` (token only, `Sms::sendMany` sends 100 numbers per request) or `custom` (URL template).
  Greenweb's format was not verified against their docs (login-only): confirm with a real token before relying on it.
- Marketing campaigns (`MarketingController`) send in browser-driven batches (no queue on cPanel). Always skip
  `marketing_opt_out` people and keep the signed unsubscribe link in campaign e-mails.

## Webmail

- One-click cPanel Webmail (`App\Services\Webmail`, `perm:webmail.use`). The cPanel API token (`biz_webmail_token`) is a
  full-access secret: the settings API returns it masked (`********`), and a masked value sent back keeps the stored token.
  Never return it, log it or put it in the frontend.
- Mailboxes are created only from the Users form (`create_mailbox`, re-checked on the server, created before the
  user is saved) and only on the configured domain. Deactivating a user suspends mailbox login. Never delete a mailbox
  from the app.

## Tests

- API tests per module are in `scripts/tests/` (`th.ps1` helpers). Add a test file for each new module and list it in
  `run-all.ps1`. Login is throttled (10/min), so wait about 60 s between test runs.
- Reset local test data with `scripts/tests/dev-reset-business-data.sql`. Never run it on a live server.

## Commands (local Windows / XAMPP)

```powershell
# PHP / artisan
C:\xampp\php\php.exe f:\Lovable\labib\backend\artisan migrate
C:\xampp\php\php.exe C:\xampp\php\composer.phar <cmd>     # run inside backend\

# Frontend build (output goes to backend\public\build)
cd f:\Lovable\labib\frontend; $env:Path = "C:\Program Files\nodejs;$env:Path"; npm run build

# Git
$env:Path = "C:\Program Files\Git\cmd;$env:Path"

# Regression check
powershell -ExecutionPolicy Bypass -File scripts\smoke-test.ps1
```

- **Local site:** http://localhost/loon (Apache alias in `C:\xampp\apache\conf\extra\loon.conf`).
- **Database:** `loon_interior` (root, no password).
- **E-mail:** `MAIL_MAILER=log` writes e-mails to `backend\storage\logs\mail.log`.
- **Secrets:** `backend\.env` and `frontend\.env.local` are git-ignored. The repo is **public**, so never commit credentials.
