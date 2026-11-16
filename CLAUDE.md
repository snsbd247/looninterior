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

- Wrap every posting in `DB::transaction`. A half-saved invoice, payment or stock move must be impossible.
- Never hard-delete financial or stock documents. Cancel them, or post a reversing entry.
- Totals are always computed on the server. Never trust totals sent by the browser.
- Branch isolation is enforced on the server (the `BranchContext` middleware plus the model scope), not only in the UI.
- Every business API route needs a `perm:` middleware. Hiding a menu item is not access control.
- Document numbers come from `DocumentNumber::next()` with a row lock. They must never be generated in PHP memory or JS.

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
