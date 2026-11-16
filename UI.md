# Loon Interior: আর্কিটেকচার

> **শেষ আপডেট:** ১৬ নভেম্বর ২০২৬ (Business ধাপ ১–৭ সম্পূর্ণ)
> **প্ল্যান:** [plan.md](plan.md) | **ইনস্টল/ডেপ্লয়:** [README.md](README.md)
>
> **চিহ্ন:** ✅ = তৈরি হয়ে গেছে, 🔜 = Business মডিউলে (পর্যায় D) তৈরি হবে।

---

## ১. সিস্টেমের সার্বিক চিত্র

```
                    ব্রাউজার (ভিজিটর / স্টাফ)
                              │
                              ▼
┌──────────────────────────── Apache (cPanel / XAMPP) ────────────────────────────┐
│  backend/public/                                                                │
│   ├── build/         React এর বিল্ড (JS/CSS)  ← সরাসরি স্ট্যাটিক ফাইল হিসেবে সার্ভ   │
│   ├── uploads/       আপলোড করা ছবি            ← স্ট্যাটিক (এখানে PHP চলে না)      │
│   ├── brand/         লোগো SVG                                                    │
│   └── index.php      বাকি সব URL → Laravel                                       │
└──────────────────────────────────┬──────────────────────────────────────────────┘
                                   ▼
┌───────────────────────────── Laravel 12 ────────────────────────────────────────┐
│  routes/web.php   /sitemap.xml, /robots.txt, /install/{key}                     │
│                   বাকি সব URL → React index.html (+ সেই পেজের SEO meta)          │
│  routes/api.php   /api/*        পাবলিক API (সাইটের ডেটা, ফর্ম)                    │
│                   /api/admin/*  অ্যাডমিন API (Sanctum Bearer টোকেন)              │
└──────────────────────────────────┬──────────────────────────────────────────────┘
                                   ▼
                     MySQL / MariaDB  +  storage/app/cvs (গোপন ফাইল)
                                   │
                     SMTP (নোটিফিকেশন / রিসেট ইমেইল)
```

**মূল নীতি:**
- **এক জায়গায় পুরো সাইট:** একটাই Laravel অ্যাপ API আর React সাইট দুটোই সার্ভ করে, তাই cPanel এ একটা ফোল্ডারই পুরো সাইট।
- **React SPA:** ব্রাউজারে একবার লোড হয়, তারপর সব ডেটা JSON API থেকে আসে।
- **SEO:** সার্চ ইঞ্জিন আর সোশ্যাল মিডিয়ার জন্য SEO meta সার্ভার থেকে HTML এ বসানো হয় (§৪.৬)।
- **Shared hosting:** SSH, queue worker বা Node সার্ভার ছাড়াই চলে।

---

## ২. টেক স্ট্যাক

| স্তর | প্রযুক্তি | নোট |
|---|---|---|
| Backend | PHP 8.2+, Laravel 12 | API, auth, ইমেইল, SEO |
| Auth | Laravel Sanctum 4 | Bearer টোকেন, ব্রাউজারের `localStorage` এ রাখা |
| Database | MySQL 5.7+ / MariaDB | `utf8mb4`, string index 191 |
| Frontend | React 18, React Router 6 | SPA, পাবলিক সাইট আর অ্যাডমিন একই অ্যাপে |
| Build | Vite 5 | আউটপুট সরাসরি `backend/public/build` এ |
| HTTP | axios | `api` (পাবলিক) আর `adminApi` (টোকেনসহ) |
| UI | নিজস্ব CSS (CSS ভেরিয়েবল), react-icons (Feather), react-quill | কোনো UI framework নেই, তাই বান্ডেল ছোট |
| PDF ✅ | mPDF 8 + Hind Siliguri (`resources/fonts`, SIL OFL) | বাংলা যুক্তাক্ষর (OTL) যাচাই করা |
| লোকাল | XAMPP 8.2 (Apache + MariaDB), Windows সার্ভিস | `localhost/loon` |

---

## ৩. ফোল্ডার কাঠামো

```
labib/
├── README.md, plan.md, UI.md, .gitignore
├── backend/                          Laravel
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Api/                  PublicController, FormController
│   │   │   ├── Admin/                Auth, Dashboard, Resource (জেনেরিক CRUD), Setting
│   │   │   └── Business/             ✅ UserController, RoleController, BusinessSettingController,
│   │   │                             ActivityLogController
│   │   │                             ✅ Lookup, Lead, Customer, Quotation, WorkProject, Invoice, Payment,
│   │   │                             Account, Transaction, Report
│   │   │                             Account, Transaction, Store, Item, Purchase, Stock, Ticket, Report …
│   │   ├── Models/                   ওয়েবসাইটের মডেল (BaseModel → active()/ordered() + LogsActivity)
│   │   │   ├── Business/             ✅ Branch, Role, TaxRate, ActivityLog, Customer, Lead, LeadActivity,
│   │   │   │                         Quotation(+Item), WorkProject, ProjectMilestone/Task/File, PaymentSchedule,
│   │   │   │                         Invoice(+Item), Payment, CreditNote, Account, TransactionCategory, Transaction
│   │   │   └── Concerns/             ✅ LogsActivity, BelongsToBranch (trait)
│   │   ├── Services/                 ✅ DocumentNumber, Pdf, CompanyProfile, AmountInWords, Money,
│   │   │                             LeadIntake, DocumentTotals (সার্ভারে মোট হিসাব), Documents ($doc বানানো + ইমেইল),
│   │   │                             Ledger (post/transfer/cancel/cancelFor), Billing (receive/cancelPayment/creditNote/cancelInvoice)
│   │   ├── Console/Commands/         ✅ BusinessReminders (business:reminders, প্রতিদিন ০৯:০০, routes/console.php)
│   │   │                             Stock (receive/out/in/reverse/unreceive, row lock), Purchasing (purchase, entry, supplier pay)
│   │   ├── Support/                  ResourceRegistry, Uploader, SpamGuard, Notifier, Seo,
│   │   │                             ✅ BranchContext, ActivityLogger, BusinessFoundation
│   │   ├── Http/Middleware/          ✅ EnsureUserIsActive (active), RequirePermission (perm),
│   │   │                             ResolveBranch (branch)
│   │   └── Providers/                AppServiceProvider (rate limiters)
│   ├── config/                       site.php, logging.php (mail চ্যানেল), ✅ permissions.php
│   ├── lang/en|bn/doc.php            ✅ PDF লেবেল (ইংরেজি/বাংলা)
│   ├── database/migrations, seeders/
│   ├── resources/fonts/              ✅ HindSiliguri-*.ttf + OFL.txt
│   ├── resources/views/emails/       ইমেইল টেমপ্লেট (Blade)
│   │   └── pdf/document.blade.php    ✅ সব ডকুমেন্টের এক টেমপ্লেট (en, bn); emails/document.blade.php (PDF সংযুক্ত মেইল)
│   ├── routes/                       web.php, api.php, console.php
│   ├── storage/app/cvs/              CV (পাবলিক না)
│   └── public/                       index.php, .htaccess, build/, uploads/, brand/
└── frontend/                         React
    ├── vite.config.js                base path, build আউটপুট, dev proxy
    ├── .env.local                    VITE_BASE_PATH=/loon (শুধু লোকাল, গিটে যায় না)
    └── src/
        ├── main.jsx, App.jsx         Router (basename = BASE_PATH)
        ├── lib/                      api.js, useFetch.js, icons.jsx
        ├── context/SiteContext.jsx   /api/site এর ডেটা, ব্র্যান্ড কালার, useTitle
        ├── components/               Header, Footer, HeroSlider, Cards, QuoteForm …
        ├── pages/                    ১৫টা পাবলিক পেজ
        ├── styles/site.css           পাবলিক সাইটের ডিজাইন
        ├── admin/                    অ্যাডমিন (lazy chunk)
        │   ├── AdminApp.jsx          লেআউট, সাইডবার, রাউট, টোস্ট
        │   ├── resources.js          জেনেরিক CRUD এর UI কনফিগ
        │   ├── settingsSchema.js     Settings এর ট্যাব আর ফিল্ড
        │   ├── Fields.jsx            ফর্মের ইনপুট কম্পোনেন্ট
        │   ├── pages/                Login, Forgot/ResetPassword, Dashboard,
        │   │                         ResourceList/Form, Settings, Profile
        │   └── admin.css
        └── business/                 Business মডিউল (আলাদা lazy chunk, /admin/biz/*)
            ├── BusinessRoutes.jsx    ✅ রাউট + প্রতিটা পেজে পারমিশন গার্ড
            ├── business.css          ✅ b-* ক্লাস (admin.css এর টোকেন ব্যবহার করে)
            ├── lib.js                ✅ money() (BD কমা), label(), dateTime(), useList(), useLookup(), phoneHref(), waHref()
            ├── components/           ✅ Pagination, ui.jsx (Status, Money, Modal, Field, BranchPicker, InfoGrid, Stat, Empty),
            │                         LineItems (+TotalsBox, calcTotals), CustomerSelect, DocActions (PDF/ইমেইল/WhatsApp)
            └── pages/                ✅ users/ (Users, UserForm, Roles, RoleForm), crm/ (Leads+Board, LeadForm,
                                      LeadDetail, FollowUps, Customers, CustomerForm, CustomerDetail),
                                      quotations/ (Quotations, QuotationForm, QuotationDetail+ItemsTable+TotalsView),
                                      projects/ (Projects+Progress, ProjectForm, ProjectDetail: tabs overview/work/files/billing/costs + publish),
                                      billing/ (Invoices, InvoiceForm, InvoiceDetail+Receive/Credit/Cancel modals, Payments, Receivables),
                                      accounts/ (Accounts, AccountLedger, Transactions+EntryModal+TransferModal, Reports+Bars),
                                      settings/BusinessSettings, activity/ActivityLog
                                      inventory/ (Stock, ItemLedger, StockEntries+Form, Stores), purchases/ (PurchaseOrders, Purchases, Suppliers+PayModal),
                                      tickets/ (TicketList, TicketForm, TicketDetail)
                                      🔜 dashboard/
```

---

## ৪. Backend আর্কিটেকচার

### ৪.১ রাউটিং ✅

| URL | কাজ |
|---|---|
| `/api/site`, `/api/home`, `/api/{services,projects,posts,careers,pages,faqs,testimonials}` | পাবলিক ডেটা |
| `POST /api/inquiries`, `POST /api/careers/{slug}/apply` | ফর্ম (`throttle:forms`, SpamGuard) |
| `POST /api/admin/{login,forgot-password,reset-password}` | টোকেন ছাড়া (আলাদা rate limiter) |
| `/api/admin/{me,profile,logout}` | `auth:sanctum` + `active` (সব স্টাফ) |
| `/api/admin/{dashboard,settings,…}` | + `perm:website.manage` |
| `/api/admin/{resource}[/{id}]` | জেনেরিক CRUD, পারমিশন রেজিস্ট্রির `permission` কী থেকে (ডিফল্ট `website.manage`)। এর মধ্যে `branches` আর `tax_rates`ও আছে |
| `/api/business/*` ✅ | `auth:sanctum` + `active` + `branch` + `perm:*` |
| ↳ ধাপ ১ | `permissions`, `roles[/{id}]`, `users[/{id}]`, `settings`, `pdf/sample?lang=`, `activity-logs` |
| ↳ lookup | `lookup/staff`, `lookup/tax-rates`, `lookup/branches` (যেকোনো স্টাফ) |
| ↳ ধাপ ৪ প্রজেক্ট | `projects[?all=1]`, `projects/{id}` (+finance, invoices, expenses), `…/milestones`, `…/tasks[/{t}]`, `…/files[/{f}]`, `…/schedule`, `…/publish` (projects.view/.manage; publish এ website.manage ও লাগবে) |
| ↳ ধাপ ৪ বিলিং | `invoices[?status=unpaid|overdue|…]`, `invoices/prefill?schedule_id|quotation_id|project_id`, `invoices/{id}`, `…/issue`, `…/receive`, `…/credit-note`, `…/cancel`, `…/pdf`, `…/email`, `payments[/{id}]`, `payments/{id}/cancel|pdf|email`, `receivables[?format=csv]` (billing.view/.manage) |
| ↳ ধাপ ৫ অ্যাকাউন্টস | `accounts[/{id}?from&to]` (লেজার), `transactions`, `transactions/transfer`, `transactions/{id}/cancel|attachment`, `reports/{cashbook,summary,profit-loss,projects,branches}` (সব রিপোর্টে `?format=csv`), `lookup/accounts`, `lookup/categories` (accounts.view/.manage) |
| ↳ ধাপ ৬ ইনভেন্টরি | `stock[?store_id&low&format=csv]`, `stock/items/{id}`, `stock-entries[/{id}]`, `stock-entries/{id}/cancel`, `stores[/{id}]`, `purchase-orders[/{id}]`, `…/status|pdf|email`, `purchases[/{id}]`, `purchases/{id}/cancel`, `suppliers[/{id}]`, `suppliers/{id}/pay`, `supplier-payments/{id}/cancel`, `lookup/stores|items` (inventory.view/.manage, suppliers.pay); Items/Categories/Suppliers তৈরি-এডিট জেনেরিক CRUD দিয়ে |
| ↳ ধাপ ৭ টিকেটিং | `tickets[?status=active|…&priority&assigned_to=me]`, `tickets/report`, `tickets/{id}`, `POST tickets`, `tickets/{id}` (assign/status/visit/priority/project), `tickets/{id}/comments` (ছবিসহ) (tickets.view/.manage); পাবলিক: `POST /api/service-requests` (throttle:forms + SpamGuard) → `TicketIntake` |
| ↳ ধাপ ৩ কোটেশন | `quotations`, `quotations/{id}`, `…/pdf?lang=&download=`, `…/status`, `…/revise`, `…/email` (quotations.view / .manage); পাবলিক: `GET /api/public/quotations/{id}/pdf?lang=&expires=&signature=` (signed) |
| ↳ ধাপ ২ CRM | `leads[?view=board]`, `leads/{id}`, `leads/{id}/stage`, `leads/{id}/activities`, `leads/{id}/convert`, `lead-activities/{id}/toggle`, `follow-ups`, `customers[/{id}]` (crm.view / crm.manage) |
| `/sitemap.xml`, `/robots.txt` | SEO |
| `/install/{key}` | একবার চালানোর ইনস্টলার (`INSTALL_KEY` খালি থাকলে বন্ধ) |
| বাকি সব | React index.html + SEO meta |

### ৪.২ জেনেরিক CRUD (ওয়েবসাইট কনটেন্ট) ✅

```
backend/app/Support/ResourceRegistry.php   ← প্রতিটা resource এর মডেল, ফিল্ড, ছবি, boolean,
                                              ভ্যালিডেশন, সার্চ, ফিল্টার, সর্টিং
        │
Admin/ResourceController  index / store / show / update / destroy
        │                 (ছবি আপলোড, গ্যালারি, private file সব নিজে সামলায়)
        ▼
frontend/src/admin/resources.js            ← টেবিলের কলাম, ফর্মের ফিল্ড, আইকন, গ্রুপ
        │
ResourceList.jsx / ResourceForm.jsx        ← একই দুইটা পেজ সব resource চালায়
```

নতুন সেকশন যোগ করতে মাইগ্রেশন আর মডেল বানিয়ে দুই রেজিস্ট্রিতে একটা করে এন্ট্রি দিলেই হয়।

**Business এ কোথায় চলবে না:** টাকা বা স্টক জড়িত মডিউল জেনেরিক CRUD দিয়ে হবে না, এগুলোর আলাদা কন্ট্রোলার আর সার্ভিস লাগবে (§৪.৮)। তবে সাধারণ তালিকা জেনেরিক CRUD এর মতো সহজ রাখা হবে, যেমন খরচের খাত, একক, মালামালের ক্যাটাগরি।

### ৪.৩ অথেন্টিকেশন ✅

- **লগইন:** লগইন করলে Sanctum personal access token দেওয়া হয়। React সেটা `localStorage.admin_token` এ রাখে আর প্রতিটা রিকোয়েস্টে `Authorization: Bearer` হেডারে পাঠায়।
- **401:** সার্ভার 401 দিলে axios interceptor ইউজারকে লগইন পেজে পাঠিয়ে দেয়।
- **পাসওয়ার্ড রিসেট:**
  - `password_reset_tokens` টেবিলে টোকেনের hash রাখা হয়, ৬০ মিনিট মেয়াদ।
  - লিংক তৈরি হয় `APP_URL` থেকে, ব্রাউজারের Host হেডার থেকে না।
  - রিসেটের পর ইউজারের সব টোকেন মুছে যায়।
- **টার্মিনাল:** `php artisan admin:password {email} {password?}`।

### ৪.৪ নিরাপত্তা ✅

| বিষয় | ব্যবস্থা |
|---|---|
| Rate limit | `forms` 10/মিনিট, `login` 10/মিনিট, `password-reset` প্রতি IP ও প্রতি ইমেইলে 3/10 মিনিট, `test-mail` 5/মিনিট। প্রতিটার কাউন্টার আলাদা |
| স্প্যাম | `SpamGuard`: honeypot ফিল্ড `website`, আর `/api/site` থেকে দেওয়া এনক্রিপ্ট করা টাইমস্ট্যাম্প `form_token` (কমপক্ষে ৩ সেকেন্ড, সর্বোচ্চ ১২ ঘণ্টা পুরনো) |
| আপলোড | MIME আর আকার যাচাই। `uploads/` এ PHP চালানো বন্ধ |
| CV | `storage/app/cvs` এ থাকে, শুধু লগইন করা অ্যাডমিন API দিয়ে ডাউনলোড করতে পারে |
| গোপন সেটিং | `Setting::PRIVATE_KEYS` (যেমন `notify_email`) পাবলিক `/api/site` এ যায় না |
| এরর | `APP_DEBUG=false`, এরর ট্রেস ব্রাউজারে দেখায় না |
| পারমিশন ✅ | প্রতিটা API রুটে `perm:` middleware (`perm:a\|b` মানে যেকোনো একটা থাকলেই চলবে)। জেনেরিক CRUD এ রেজিস্ট্রির `permission` কী। শুধু মেনু লুকানো যথেষ্ট না |
| শাখা ✅ | `ResolveBranch` middleware `X-Branch-Id` হেডার যাচাই করে: অনুমোদিত শাখা না হলে 403। "all" শুধু `branches.all` পারমিশনে। `BelongsToBranch` trait সার্ভারেই ফিল্টার করে |
| নিষ্ক্রিয় ইউজার ✅ | `active` middleware: পুরনো টোকেন থাকলেও 401 দেয় আর টোকেন মুছে দেয়। লগইনও বন্ধ |
| সেটিংস আলাদা ✅ | `biz_*` কী শুধু Business Settings API দিয়ে লেখা আর পড়া যায়। ওয়েবসাইট সেটিংস আর পাবলিক API তে আসে না |

### ৪.৫ ইমেইল ✅

- **`Notifier`:** নতুন কোট বা কন্টাক্ট রিকোয়েস্টে `emails.inquiry`, আর জব আবেদনে `emails.application` (CV সংযুক্ত)।
- **প্রাপক:** Settings → `notify_email`, খালি থাকলে কন্টাক্ট ইমেইল।
- **ইমেইল ব্যর্থ হলে:** `Log::error` এ লেখা হয়, ফর্ম জমা আটকায় না।
- **লোকালে:** `MAIL_MAILER=log` + `MAIL_LOG_CHANNEL=mail`, ইমেইল লেখা হয় `storage/logs/mail.log` এ।
- **টেমপ্লেট:** `resources/views/emails/` (layout, inquiry, application, reset-password, test)। ইনলাইন স্টাইল, যাতে Gmail/Outlook এ ঠিক দেখায়।

### ৪.৬ সার্ভার-সাইড SEO ✅

```
GET /projects/modern-apartment
   → web.php $spa → Seo::forPath(path, settings)
        ├── স্ট্যাটিক পেজ (about, services …) → "About Us | Loon Interior"
        ├── ডিটেইল পেজ (services|projects|blog|careers|page)/{slug} → DB থেকে title/desc/image
        ├── admin/* → noindex,nofollow
        └── না পেলে → "Page Not Found", noindex, HTTP 404
   → Seo::tags() → <title>, description, robots, canonical, og:*, twitter:card
   → build/index.html এর <title> এর জায়গায় এই ট্যাগগুলো বসানো হয়
```

React চালু হওয়ার পর `useTitle()` ব্রাউজারের টাইটেল আপডেট করে। sitemap তৈরি হয় `Seo::sitemapUrls()` থেকে।

### ৪.৭ সেটিংস ✅

- **স্টোরেজ:** `settings` টেবিল, key-value। পুরো ওয়েবসাইটের টেক্সট, ছবি, কন্টাক্ট আর SEO এখানে থাকে।
- **কোন ফিল্ড কোন ট্যাবে:** ঠিক হয় `frontend/src/admin/settingsSchema.js` থেকে।
- **নতুন কী:** নতুন কী যোগ করতে backend বদলাতে হয় না, শুধু schema তে ফিল্ড যোগ করলেই চলে।
- **Business সেটিং ✅:**
  - কোম্পানির তথ্য, ব্যাংক, স্বাক্ষর, শর্তাবলী, ফুটার (EN + `_bn`) আর নম্বরের ফরম্যাট একই টেবিলে থাকে, `biz_` prefix সহ।
  - এগুলো `Setting::businessArray()` দিয়ে পড়া হয়। `websiteArray()` আর `publicArray()` এগুলো বাদ দেয়।
  - ট্যাক্স রেট আর নম্বর সিরিজ আলাদা টেবিলে।
  - `CompanyProfile::get($lang)` আগে `biz_*` মান নেয়, না থাকলে ওয়েবসাইটের সেটিং।

### ৪.৮ Business মডিউলের কাঠামো (ভিত্তি ✅, বাকি অংশ 🔜)

```
Request
  → auth:sanctum
  → active                     নিষ্ক্রিয় ইউজার → 401 (টোকেন মুছে যায়)
  → branch (ResolveBranch)     X-Branch-Id হেডার পড়ে, ইউজারের অনুমোদিত শাখার সাথে মেলায়
                               → BranchContext::set(current|null=all, allowed[])
                               "all" শুধু branches.all পারমিশনে। হেডার না থাকলে প্রথম শাখা
  → perm:billing.manage        রোলের পারমিশন চেক (config/permissions.php)
  → Controller (পাতলা)         ভ্যালিডেশন → Service কল → JSON
  → Service (ব্যবসার নিয়ম)      DB::transaction এর ভেতরে:
        DocumentNumber::next('INV', $branch)     ✅ atomic upsert দিয়ে নম্বর (ডুপ্লিকেট/ফাঁক নেই)
        Ledger::post(...)                        ✅ আয়/ব্যয়/ট্রান্সফার এন্ট্রি (Billing::receive এর ভেতরে)
        Stock::receive/out/in(...)               ✅ স্টকের চলাচল + গড় মূল্য (Purchasing এর ভেতরে)
        (LogsActivity trait নিজে থেকে লেখে)       ✅ আগের/পরের মান
  → Model (BelongsToBranch trait ✅: শাখা অনুযায়ী global scope; নতুন রেকর্ডে শাখা বসায়,
           "all" মোডে শাখা না দিলে বা অননুমোদিত শাখা দিলে ভ্যালিডেশন এরর)
```

**রোল ও পারমিশন ✅:**
- **ক্যাটালগ:** `config/permissions.php` এ আছে: `modules` এর ভেতরে `module.action` আর তার লেবেল, এর সাথে ৫টা `default_roles`।
- **রোলে সংরক্ষণ:** `roles.permissions` একটা JSON তালিকা। Admin = `["*"]` (`is_system`, বদলানো যায় না)।
- **নতুন মডিউল:** ক্যাটালগে পারমিশন যোগ করে রুটে `perm:` দিলেই হয়। রোলের চেকবক্সে নিজে থেকে চলে আসে।
- **রোল চেক:** `User::hasPermission()` আর `permissionList()`।
- **ফ্রন্টএন্ডে পাঠানো:** `/api/admin/me` পাঠায় `permissions[]`, `branches[]` আর `can_all_branches`।

**Activity Log ✅:**
- **কোথায় লেখা হয়:** `LogsActivity` trait। এটা BaseModel (সব ওয়েবসাইট কনটেন্ট), User, Role, Branch আর TaxRate এ আছে।
- **কখন লেখা হয়:** শুধু লগইন করা ইউজারের কাজ। ওয়েবসাইটের ভিজিটরের ফর্ম জমা লেখা হয় না।
- **কী বাদ যায়:** password আর remember_token কখনো লেখা হয় না। লম্বা HTML ৩০০ অক্ষরে ছোট করা হয়।
- **শাখা:** লগে শাখা বসে শুধু শাখাভিত্তিক রেকর্ডে (যাতে `branch_id` আছে)। কোম্পানি-ব্যাপী পরিবর্তন সব শাখার লগে দেখায়।
- **হাতে লেখা:** লগইন, পাসওয়ার্ড বদল, ইউজারের শাখা বদল আর সেটিংস সেভ `ActivityLogger::record()` দিয়ে লেখা হয়।

**রুট-বাইন্ডিং নিরাপত্তা ✅:** `bootstrap/app.php` এ middleware priority এমনভাবে সাজানো যে `ResolveBranch` আর `EnsureUserIsActive` চলে authentication এর ঠিক পরে, আর `SubstituteBindings` (route model binding) এর আগে। ফলে `/leads/{lead}` এর মতো URL এ অন্য শাখার রেকর্ড পাওয়া যায় না (404)। টেস্টে এটা ধরা পড়েছিল।

**সময় ✅:** `config/app.php` এ `APP_TIMEZONE=Asia/Dhaka`। DB তে স্থানীয় সময় থাকে, JSON এ যায় UTC (`…Z`), আর ব্রাউজার স্থানীয় সময়ে দেখায়।

**প্রথম চালুতে ডেটা (`BusinessFoundation::ensure()`) ✅:**
- **কাজ:** ৫টা রোল, "Head Office" (HO) শাখা আর VAT 15% তৈরি করে। রোল নেই এমন ইউজারকে Admin বানিয়ে HO তে যুক্ত করে।
- **কোথা থেকে চলে:** একটা migration থেকে (তাই cPanel `/install` এও চলে) আর DatabaseSeeder থেকে।
- **নিরাপদ:** বারবার চালালেও Admin এর বদলানো জিনিস নষ্ট করে না।

**টাকা আর স্টকের নিয়ম:**
- **অঙ্কের ধরন:** সব টাকার অঙ্ক `decimal(14,2)`। হিসাব সবসময় সার্ভারে হয়, ব্রাউজারের পাঠানো মোট কখনো বিশ্বাস করা হয় না।
- **মোছা:** টাকা আর স্টকের ডকুমেন্ট মোছা যায় না। স্ট্যাটাস `cancelled` করা হয়, সাথে উল্টো এন্ট্রি।
- **ব্যালেন্স:** অ্যাকাউন্ট ব্যালেন্স হিসাব হয় `transactions` এর যোগফল থেকে, আর স্টক হিসাব হয় `stock_movements` এর যোগফল থেকে। `*_balances` টেবিল শুধু দ্রুত দেখানোর জন্য ক্যাশ, প্রয়োজনে আবার হিসাব করে নেওয়া যায়।
- **নির্ধারিত কাজ:** রিমাইন্ডারের মতো কাজ চলে Laravel Scheduler দিয়ে, cPanel Cron থেকে: `* * * * * php artisan schedule:run`।

---

## ৫. ডাটা মডেল

### ৫.১ ওয়েবসাইট (বিদ্যমান) ✅

| টেবিল | মূল ফিল্ড |
|---|---|
| `users` | name, email, password (hashed) |
| `personal_access_tokens`, `password_reset_tokens` | Sanctum, রিসেট |
| `settings` | key, value |
| `sliders`, `stats`, `features`, `process_steps`, `faqs` | হোমপেজের সেকশন (sort_order, is_active) |
| `services` | title, slug, icon, short_description, description, image, is_featured |
| `project_categories` → `projects` → `project_images` | পোর্টফোলিও আর গ্যালারি |
| `testimonials`, `clients` (লোগো), `team_members` | |
| `posts`, `pages` | ব্লগ, ডাইনামিক পেজ |
| `careers` → `job_applications` | জব পোস্ট, আবেদন (cv_path, status, admin_note) |
| `inquiries` | কোট/কন্টাক্ট রিকোয়েস্ট (type, status, admin_note, ip) |

### ৫.২ Business (✅ তৈরি / 🔜 পরিকল্পিত)

> **নামের সংঘাত এড়াতে:**
> - ওয়েবসাইটের `clients` (লোগো) আর `projects` (পোর্টফোলিও) টেবিল আগের মতোই থাকবে।
> - Business এর ক্লায়েন্টের টেবিল হবে **`customers`**, আর চলমান কাজের টেবিল হবে **`work_projects`**।
>
> নিচে "শাখাভিত্তিক" লেখা প্রতিটা টেবিলে `branch_id` থাকবে।

| গ্রুপ | টেবিল | মূল ফিল্ড / সম্পর্ক |
|---|---|---|
| **ভিত্তি ✅** | `branches` | name, code (unique, বড় হাতের অক্ষরে; যেমন HO, DHK), phone, email, address, is_active। মোছা যায় না |
| | `roles` | name, slug, **permissions (JSON তালিকা)**, is_system। আলাদা permissions টেবিল নেই, ক্যাটালগ থাকে `config/permissions.php` এ |
| | `users` (+ `role_id`, `phone`, `is_active`, `last_login_at`), `branch_user` | ইউজার ↔ একাধিক শাখা। ইউজার মোছা যায় না |
| | `activity_logs` | user_id, branch_id, action (created/updated/deleted/login/password_changed), subject_type, subject_id, description, changes (JSON before/after), ip, created_at |
| | `tax_rates` | name, rate (decimal 6,3), is_default (একটাই), is_active। মোছা যায় না |
| | `document_sequences` | branch_id, type (QT/INV/RCV/PO/GRN/TKT), year, last_number, unique(branch, type, year) |
| **CRM ✅** | `leads` (শাখাভিত্তিক) | name, phone, email, address, source, stage, service, property_type, area_size, budget, expected_value, requirements, assigned_to, inquiry_id, customer_id, next_follow_up_at (সবচেয়ে কাছের খোলা ফলো-আপ, ক্যাশ), lost_reason, won_at, created_by |
| | `lead_activities` | lead_id, user_id, type (call/meeting/visit/note/follow_up/stage), note, follow_up_at, done_at |
| | `customers` (শাখাভিত্তিক) | name, phone, email, address, company, notes, created_by (লিড → customer_id দিয়ে যুক্ত) |
| **কোটেশন ✅** | `quotations` (শাখাভিত্তিক) | number (unique), base_number, revision, parent_id, customer_id, lead_id, title, status (draft/sent/approved/rejected/revised/cancelled), date, valid_until, discount_type (amount/percent), discount_value, subtotal, discount, tax_rate_id, tax_name, tax_rate (snapshot), tax, total, terms, notes, created_by, sent_at, approved_at |
| | `quotation_items` | section (ঘরের নাম, আলাদা টেবিল নেই), description, unit, qty (12,3), rate, amount, sort_order |
| **প্রজেক্ট ✅** | `work_projects` (শাখাভিত্তিক) | code, customer_id, quotation_id, title, site_address, contract_value, start_date, end_date, handover_date, warranty_until, manager_id, status (planning/running/on_hold/completed/cancelled), progress (milestone গড়), description, portfolio_project_id (ওয়েবসাইটের projects), created_by |
| | `project_milestones`, `project_tasks`, `project_files`, `payment_schedules` | milestone (name, progress, due_date, sort); task (title, notes, milestone_id, assigned_to, due_date, status todo/doing/done); file (kind image→public uploads / doc→storage/app/project-files, path, original_name, size); schedule (label, percent, amount, due_date, invoice_id) |
| **বিলিং ✅** | `invoices` (শাখাভিত্তিক) | number, customer_id, work_project_id, quotation_id, title, date, due_date, status (draft/issued/partial/paid/cancelled), discount_type/value, subtotal, discount, tax_rate_id, tax_name, tax_rate, tax, total, **paid, credited** (due = total − paid − credited, হিসাব করা মান), terms, notes, issued_at, cancelled_at, cancel_reason |
| | `invoice_items` | কোটেশনের আইটেমের মতো একই কলাম |
| | `payments` (শাখাভিত্তিক) | number (RCV), invoice_id, customer_id, account_id, method, amount, reference, date, notes, status (received/cancelled), cancel_reason |
| | `credit_notes` (শাখাভিত্তিক) | number (CN), invoice_id, amount, reason, date |
| **অ্যাকাউন্টস ✅** | `accounts` (শাখাভিত্তিক) | name, type (cash/bank/mobile), bank_name, account_number, opening_balance, is_active। ব্যালেন্স = opening + Σ(income+transfer_in) − Σ(expense+transfer_out), শুধু active এন্ট্রি |
| | `transaction_categories` | type, name, slug (system: customer_payment, supplier_payment, other_income, other_expense), is_system, is_active। জেনেরিক CRUD এ সম্পাদনা করা যায় |
| | `transactions` (শাখাভিত্তিক) | date, type (income/expense/transfer_in/transfer_out), account_id, category_id, amount (সবসময় ধনাত্মক), work_project_id, party_* (morph), source_* (morph: Payment/SupplierPayment…), description, reference, attachment (storage/app/receipts), transfer_group (uuid), status (active/cancelled), cancel_reason, created_by |
| **ইনভেন্টরি ✅** | `stores` (শাখাভিত্তিক) | name, address, is_active। প্রতি শাখায় "Main Store" নিজে থেকে |
| | `item_categories`, `items` (কোম্পানি-ব্যাপী, জেনেরিক CRUD) | item: sku (unique), name, item_category_id, unit, min_stock, avg_cost (decimal 14,4, weighted average), is_active। আলাদা units টেবিল নেই, একক ফ্রি টেক্সট |
| | `suppliers` (কোম্পানি-ব্যাপী, জেনেরিক CRUD) | name, phone, email, address, opening_due, notes। দেনা = opening_due + Σ purchases.total (received) − Σ supplier_payments (paid), বর্তমান শাখা-স্কোপে |
| | `purchase_orders` → `purchase_order_items` (শাখাভিত্তিক) | number (PO), supplier_id, store_id, date, expected_date, status (draft/sent/partial/received/cancelled), total; item: item_id, qty, rate, amount, received_qty |
| | `purchases` → `purchase_items` (শাখাভিত্তিক) | number (GRN/DP), type (grn/direct), supplier_id (null = নগদ বাজার), purchase_order_id, store_id বা work_project_id, supplier_ref, date, total, paid, status (received/cancelled) |
| | `stock_entries` → `stock_entry_items` (শাখাভিত্তিক) | number (ISS/RTN/TRF/ADJ), type (issue/return/transfer/adjust), store_id, to_store_id, work_project_id, date, notes, status; item: item_id, qty, unit_cost, amount |
| | `stock_movements` (শাখাভিত্তিক, লেজার) | store_id, item_id, type (in/issue/return/transfer_in/transfer_out/adjust/reverse), qty (+/−), unit_cost, value (±), work_project_id, source (morph: Purchase/StockEntry), date। কখনো বদলানো বা মোছা হয় না |
| | `stock_balances` | store_id + item_id (unique), qty। ক্যাশ, প্রতিটা movement এর সাথে একই transaction এ row lock দিয়ে আপডেট হয় |
| | `supplier_payments` (শাখাভিত্তিক) | number (SP), supplier_id, purchase_id, account_id, method, amount, reference, date, status (paid/cancelled) → Ledger এ "supplier_payment" খরচ (প্রজেক্ট ছাড়া) |
| **টিকেটিং ✅** | `tickets` (শাখাভিত্তিক) | number (TKT), customer_id (null হতে পারে), work_project_id, contact_name/phone/email, address, subject, description, priority (low/medium/high/urgent), status (open/in_progress/resolved/closed), source (phone/website/email/walk_in/visit), assigned_to, visit_at, warranty_until (প্রজেক্ট থেকে snapshot; `in_warranty` হিসাব করা মান), resolved_at, closed_at, created_by |
| | `ticket_comments` | ticket_id, user_id (null = সিস্টেম/ওয়েবসাইট), body, attachment (public uploads/tickets ছবি), kind (comment/status/system) |

**মূল সম্পর্ক:**

```
inquiries ─→ leads ─→ customers ─┬→ quotations ─→ work_projects ─┬→ invoices ─→ payments ─→ transactions
                                 │                               ├→ stock_movements (issue/direct purchase)
                                 │                               ├→ transactions (খরচ)
                                 └──────────────────────────────→└→ tickets
suppliers ─→ purchase_orders ─→ purchases ─┬→ stock_movements
                                           └→ transactions (সাপ্লায়ার পেমেন্ট)
```

---

## ৬. Frontend আর্কিটেকচার

### ৬.১ রাউটিং ✅

```
<BrowserRouter basename={BASE_PATH}>          BASE_PATH = "" (ডোমেইন) বা "/loon"
  /admin/*  → <AdminApp/>  (lazy chunk)
                ├── login, forgot-password, reset-password   (টোকেন ছাড়া)
                └── * → RequireAuth → <Layout/>
                       ├── /admin                       Dashboard
                       ├── /admin/r/:resource[/new|/:id] জেনেরিক CRUD
                       ├── /admin/settings (website.manage), /profile
                       └── /admin/biz/* ✅              <BusinessRoutes/> (আলাদা lazy chunk)
                              ├── users, users/new, users/:id        (users.manage)
                              ├── roles, roles/new, roles/:id        (roles.manage)
                              ├── settings                           (settings.business)
                              └── activity                           (activity.view)
                       (Branches আর Tax Rates চলে জেনেরিক /admin/r/branches আর /admin/r/tax_rates দিয়ে)
  /*        → <PublicSite/>  → SiteProvider → Header + Routes + Footer + FloatingContact
```

### ৬.২ ডেটা আনা ✅

- **`lib/api.js`:**
  - `api` আর `adminApi` axios instance।
  - `sitePath()` সব URL এ base path যোগ করে, `img()` ছবির পাথ বা পূর্ণ URL ঠিক করে।
  - `errorMessage()` আর `fieldErrors()` Laravel এর 422 এরর পড়ে।
- **`useFetch(url)`:** পাবলিক পেজের `{ data, loading, error }`।
- **`SiteContext`:**
  - `/api/site` একবারই লোড হয়: settings, মেনুর services/categories, footer pages, `form_token`।
  - ব্র্যান্ড কালার (`--primary`) আর favicon সেট করে।
- **Business ✅:**
  - **API instance:** `businessApi` (`/api/business`), আর `adminApi`। দুটোই টোকেন আর `X-Branch-Id` হেডার পাঠায়।
  - **শাখা মনে রাখা:** বর্তমান শাখা `localStorage.admin_branch` এ থাকে। `/me` লোড হলে মিলিয়ে দেখা হয়: অনুমোদিত না হলে প্রথম শাখা নেওয়া হয়।
  - **শাখা বদলালে:** `.a-content` এর `key={branch}` বদলায়, ফলে পেজের ডেটা নতুন শাখার জন্য আবার লোড হয়।
  - **পারমিশন চেক:** `useAdmin().can('perm')` (`'a|b'` = যেকোনো একটা), `<Can perm>`, `<NoAccess/>`।
  - **কোথায় চেক হয়:** সাইডবার, ResourceList/Form আর Business রাউট সব জায়গায় পারমিশন চেক হয়। জেনেরিক রিসোর্সের পারমিশন আসে `resourcePerm(cfg)` থেকে।
  - **Dashboard:** `website.manage` না থাকলে স্বাগত কার্ড দেখায়। রোলভিত্তিক Dashboard আসবে ধাপ ৮ এ।
  - ✅ `useList(endpoint, filters)`: পেজিনেশন, সার্চ আর ফিল্টারসহ তালিকা। `useLookup(name)` dropdown এর জন্য, মেমরিতে cache রাখে। `downloadCsv()` আর `openPdf()` লগইন টোকেনসহ ফাইল খোলে।

### ৬.৩ ডিজাইন সিস্টেম: পাবলিক সাইট ✅ (`styles/site.css`)

| টোকেন | মান | ব্যবহার |
|---|---|---|
| `--primary` | `#b08d57` (গোল্ড, Settings থেকে বদলানো যায়) | বাটন, অ্যাকসেন্ট, লিংক |
| `--primary-dark` | primary এর 80% + কালো | হোভার |
| `--dark` / `--dark-2` | `#1b1a18` / `#262421` | ফুটার, ডার্ক সেকশন |
| `--heading` / `--text` / `--muted` | `#1b1a18` / `#5b5852` / `#8a867f` | লেখা |
| `--cream` | `#f7f3ec` | হালকা ব্যাকগ্রাউন্ডের সেকশন |
| `--border` | `#e7e1d6` | বর্ডার |
| `--radius` | `6px` | কার্ড, ইনপুট |
| `--font-head` | Playfair Display → Hind Siliguri → serif | শিরোনাম |
| `--font-body` | Jost → Hind Siliguri → sans-serif | বডি টেক্সট (বাংলা এলে Hind Siliguri) |

**বাটন:**
- **বেস `.btn`:** উচ্চতা 48px, uppercase, `.06em` letter-spacing, 1.5px বর্ডার।
- **হোভার:** বাটন 2px উপরে ওঠে আর আইকন 4px সামনে সরে। `:focus-visible` এ outline দেখায়।
- **ভ্যারিয়েন্ট:** `btn-primary`, `btn-dark`, `btn-outline`, `btn-outline-light`।
- **সাইজ:** `btn-sm`, `btn-lg` (56px), `btn-block`।

**ব্রেকপয়েন্ট:**

| প্রস্থ | পরিবর্তন |
|---|---|
| ≤ 1100px | গ্রিডের কলাম কমে |
| ≤ 960px | হ্যামবার্গার মেনু (ডান দিক থেকে খোলে, backdrop সহ), নেভিগেশন উল্লম্ব |
| ≤ 640px | মোবাইল: 16px gutter, সেকশন 56px, হিরো 82svh (লেখা নিচে আর gradient), স্ট্যাট 2 কলাম, ফিল্টার আনুভূমিক স্ক্রল, CTA আর জব বাটন পূর্ণ প্রস্থ, ফুটার 2 কলাম, ভাসমান বাটন 48px |

**মূল কম্পোনেন্ট:**
- **Layout:** `Header` (topbar + sticky nav + dropdown), `PageHeader` (ভেতরের পেজের ব্যানার + breadcrumb), `Footer`, `FloatingContact` (WhatsApp/Call/Messenger)।
- **Home:** `HeroSlider`, `SectionHead`, `Sections` (হোমপেজের ব্লক)।
- **কার্ড আর কনটেন্ট:** `Cards` (Service/Project/Post/Team/Job), `Rich` (নিরাপদ HTML), `Img` (lazy + fallback)।
- **ফর্ম:** `QuoteForm` (+ `Honeypot`, `hasFieldErrors`)।

### ৬.৪ ডিজাইন সিস্টেম: অ্যাডমিন ✅ (`admin/admin.css`)

| টোকেন | মান |
|---|---|
| `--a-bg` / `--a-panel` | `#f4f5f7` / `#fff` |
| `--a-border` | `#e5e7eb` |
| `--a-text` / `--a-head` / `--a-muted` | `#374151` / `#111827` / `#6b7280` |
| `--a-side` / `--a-side-text` | `#16181d` / `#a1a5ad` (ডার্ক সাইডবার) |
| `--a-accent` | `var(--primary)` (ব্র্যান্ড গোল্ড) |

**লেআউট:**

```
┌──────────┬───────────────────────────────────────────────┐
│ সাইডবার   │ টপবার:  [☰] [শাখা ▾]     View Site · নাম · Logout │
│ (ডার্ক)    ├───────────────────────────────────────────────┤
│ Dashboard│ .a-page-head   শিরোনাম + প্রধান অ্যাকশন বাটন     │
│ Website  │ .a-panel       ফিল্টার / টেবিল / ফর্ম            │
│  …       │   .a-table-wrap  (মোবাইলে আনুভূমিক স্ক্রল)        │
│ CRM…      │   .a-form-grid   (half/full ফিল্ড)               │
│  CRM     │ .a-form-actions                               │
│  …       │                                   টোস্ট (নিচে) │
│ System   │                                               │
└──────────┴───────────────────────────────────────────────┘
```

**মূল ক্লাস:**
- **লেআউট:** `.a-panel`, `.a-panel-head`, `.a-page-head`, `.a-tabs`, `.a-cards`/`.a-card` (Dashboard এর সংখ্যা), `.a-grid-2`।
- **টেবিল আর ফর্ম:** `.a-table-wrap`, `.a-field`, `.a-help`।
- **বাটন আর ব্যাজ:** `.a-btn` (`-primary`, `-light`, `-block`), `.a-badge`।
- **বার্তা:** `.a-alert` (`-error`, `-success`), `.toast`।

**রেসপন্সিভ:**
- **≤ 1000px:** `.a-grid-2` এক কলাম।
- **≤ 860px:**
  - সাইডবার লুকানো থাকে, হ্যামবার্গারে খোলে, backdrop সহ।
  - কার্ড 2 কলাম, টেবিলের প্যাডিং কম।

### ৬.৫ Business UI প্যাটার্ন

| প্যাটার্ন | কোথায় | আচরণ |
|---|---|---|
| **BranchSwitcher** ✅ | টপবার | Admin সব শাখা আর "সব শাখা (সেন্ট্রাল)" দেখে। একটাই শাখা থাকলে শুধু নাম দেখায়। শাখা বদলালে পেজের ডেটা নতুন করে লোড হয়। মোবাইলে টপবারের বাকি জায়গা জুড়ে থাকে |
| **Permission matrix** ✅ | রোল এডিট | মডিউল অনুযায়ী কার্ড। মডিউলের চেকবক্সে সব একসাথে টিক হয়, আংশিক হলে indeterminate দেখায় |
| **EN/BN টুইন ফিল্ড** ✅ | Business Settings | `bn: true` দিলে একই ফিল্ডের "(English)" আর "(বাংলা)" দুই সংস্করণ পাশাপাশি দেখায় |
| **DataTable** | সব তালিকা | সার্চ, ফিল্টার (তারিখ, স্ট্যাটাস, শাখা), পেজিনেশন, কলাম সর্ট, Excel এক্সপোর্ট। মোবাইলে কার্ড ভিউ |
| **Status** ✅ (`ui.jsx`) | সব জায়গায় | স্ট্যাটাস অনুযায়ী রং, যেমন Draft ধূসর, Sent নীল, Approved/Paid সবুজ, Partial হলুদ, Overdue/Cancelled লাল |
| **Kanban** ✅ | CRM পাইপলাইন | ধাপের কলাম (HTML5 drag & drop)। মোবাইলে কলামগুলো আনুভূমিক স্ক্রল আর কার্ডে "move to" dropdown |
| **LineItems** ✅ | কোটেশন, ইনভয়েস, PO | সেকশন/ঘর → আইটেমের সারি (বিবরণ, একক, পরিমাণ, দর, মোট)। সারি যোগ/মুছা/সাজানো। নিচে subtotal → discount → tax → total (ব্রাউজারে শুধু দেখানোর জন্য হিসাব, আসল হিসাব সার্ভারে) |
| **MoneyInput / Money** | টাকার ফিল্ড | `৳ 1,20,000.00` বাংলাদেশি কমা-ফরম্যাট, ডান দিকে সাজানো |
| **DocumentView** ✅ (QuotationDetail + DocActions) | কোটেশন/ইনভয়েস/রিসিট/PO | ডকুমেন্টের প্রিভিউ, পাশে অ্যাকশন: Edit, Send, **Print/PDF (EN \| বাংলা)**, WhatsApp, Cancel |
| **Timeline** ✅ (লিড) | লিড, টিকেট, প্রজেক্ট | যোগাযোগের ইতিহাস আর কমেন্ট, তারিখ অনুযায়ী সাজানো |
| **ConfirmDialog** | বাতিল, পেমেন্ট | টাকা জড়িত কাজে নিশ্চিত করতে বলে, বাতিলের কারণ লিখতে হয় |
| **Can** ✅ | বাটন, মেনু, রাউট | পারমিশন না থাকলে জিনিসটাই দেখায় না |
| **রোলভিত্তিক Dashboard** | `/admin` | রোল অনুযায়ী আলাদা কার্ড আর তালিকা (plan.md ধাপ ৮) |

### ৬.৬ প্রিন্ট ও PDF (ইঞ্জিন ✅)

```
DocumentView → [Print/PDF EN] [Print/PDF বাংলা]
     → GET /api/business/<doc>/{id}/pdf?lang=bn           (ধাপ ১: /api/business/pdf/sample?lang=)
     → Pdf::render('pdf.document', ['company' => CompanyProfile::get($lang), 'doc' => [...]], $lang)
     → mPDF (Hind Siliguri, useOTL) → application/pdf
```

- **টেমপ্লেট:** একটাই জেনেরিক টেমপ্লেট `pdf/document.blade.php` সব ডকুমেন্ট চালায়। এতে থাকে:
  - হেডার (লোগো, কোম্পানি, BIN/TIN), ডকুমেন্টের শিরোনাম, নম্বর আর তারিখ, meta তথ্য।
  - প্রাপক বা পক্ষের ব্লক।
  - সেকশনভিত্তিক আইটেম টেবিল, তারপর উপমোট, ছাড়, ট্যাক্স আর সর্বমোট (paid/due ঐচ্ছিক)।
  - কথায় অঙ্ক, নোট, শর্তাবলী, ব্যাংক তথ্য।
  - স্বাক্ষর আর সিল, ফুটারে পৃষ্ঠা নম্বর, ঐচ্ছিক watermark।
- **নতুন ডকুমেন্ট:** নতুন ডকুমেন্টের জন্য শুধু `$doc` array বানালেই হবে। ফিল্ডের তালিকা টেমপ্লেটের উপরের মন্তব্যে আছে।
- **ভাষা:**
  - লেবেল আসে `lang/en/doc.php` আর `lang/bn/doc.php` থেকে। আইটেমের বিবরণ যেমন লেখা হয়েছে তেমনই থাকে।
  - বাংলায় অঙ্ক বাংলা অঙ্কে (১২৩) দেখাবে কিনা তা ঠিক হয় `biz_bn_digits` সেটিং থেকে।
- **টাকা:** `Money::format()` বাংলাদেশি কমা-ফরম্যাট দেয় (১২,৩৪,৫৬৭.৫০)। কথায় অঙ্কের জন্য `AmountInWords::en()` আর `bn()`, লাখ/কোটি পদ্ধতিতে, পয়সাসহ।
- **ছবি:** লোগো, স্বাক্ষর আর সিল লোকাল ফাইল পাথ হিসেবে দিতে হয় (`Pdf::imagePath`)। বাইরের URL নেওয়া হয় না।
- **অস্থায়ী ফাইল:** mPDF এর temp ফোল্ডার `storage/app/mpdf-tmp`।
- ✅ **ইমেইল:** একই PDF ইমেইলের সংযুক্তি হিসেবে যায় (`Documents::email`, `emails/document.blade.php`)।

---

## ৭. Build ও ডেপ্লয়

```
frontend/  npm run build
   └→ backend/public/build/  (index.html + assets/*.js|css, hash করা নাম)

VITE_BASE_PATH (frontend/.env.local)
   ├── "/loon" → base "/loon/build/", Router basename "/loon"   (লোকাল XAMPP)
   └── খালি   → base "/build/",      basename "/"              (রুট ডোমেইন)
```

| পরিবেশ | ওয়েব সার্ভার কনফিগ | ইমেইল |
|---|---|---|
| লোকাল (XAMPP) | `C:\xampp\apache\conf\extra\loon.conf`: Alias `/loon` → `backend/public`, rewrite আর Authorization পাস করা | `log` → `storage/logs/mail.log` |
| cPanel | Document Root → `public/` (বা public_html পদ্ধতি), `public/.htaccess` | SMTP (cPanel ইমেইল অ্যাকাউন্ট) |

**ডেপ্লয়ের নিয়ম:**
- `.env`, `vendor/`, `node_modules/`, `build/` আর `uploads/` গিটে যায় না।
- ডেপ্লয়ে কী কী লাগে আর ধাপগুলো README তে আছে।

**নতুন মডিউল ডেপ্লয়:**
1. `php artisan migrate --force` চালানো। Terminal না থাকলে `/install/{key}`, এতে migrate হয়, আর ইউজার না থাকলে seed।
2. Business এর রোল, পারমিশন আর "Head Office" শাখা আলাদা seeder চালিয়ে তৈরি হবে। seeder টা idempotent: আগে থেকে থাকা ডেটা নষ্ট না করে, বারবার চালালেও সমস্যা হয় না।
3. cPanel Cron এ একবার যোগ করা: `* * * * * cd /home/USER/loon && php artisan schedule:run >> /dev/null 2>&1`। এতে `business:reminders` প্রতিদিন ০৯:০০ এ চলে।
