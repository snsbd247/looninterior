# Loon Interior: আর্কিটেকচার

> **শেষ আপডেট:** ১৬ নভেম্বর ২০২৬
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
| PDF 🔜 | mPDF | বাংলা যুক্তাক্ষর সমর্থন |
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
│   │   │   └── Business/ 🔜          Branch, User, Lead, Customer, Quotation, WorkProject,
│   │   │                             Invoice, Payment, Account, Transaction, Store, Item,
│   │   │                             Purchase, StockMovement, Ticket, Report …
│   │   ├── Models/                   ওয়েবসাইটের মডেল (BaseModel → active()/ordered())
│   │   │   └── Business/ 🔜          Business এর মডেল
│   │   ├── Services/ 🔜              DocumentNumber, Ledger, Stock, Pdf, AmountInWords
│   │   ├── Support/                  ResourceRegistry, Uploader, SpamGuard, Notifier, Seo
│   │   ├── Http/Middleware/ 🔜       Permission, BranchContext
│   │   └── Providers/                AppServiceProvider (rate limiters)
│   ├── config/                       site.php, logging.php (mail চ্যানেল)
│   ├── database/migrations, seeders/
│   ├── resources/views/emails/       ইমেইল টেমপ্লেট (Blade)
│   │   └── pdf/ 🔜                   কোটেশন/ইনভয়েস/রিসিট/PO (en, bn)
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
        └── business/ 🔜              Business মডিউল (আলাদা lazy chunk)
            ├── components/           DataTable, LineItems, MoneyInput, StatusBadge,
            │                         Kanban, BranchSwitcher, Can, PrintButton …
            ├── hooks/                usePermission, useBranch, useList
            └── pages/                crm/, quotations/, projects/, billing/,
                                      accounts/, inventory/, tickets/, reports/, users/
```

---

## ৪. Backend আর্কিটেকচার

### ৪.১ রাউটিং ✅

| URL | কাজ |
|---|---|
| `/api/site`, `/api/home`, `/api/{services,projects,posts,careers,pages,faqs,testimonials}` | পাবলিক ডেটা |
| `POST /api/inquiries`, `POST /api/careers/{slug}/apply` | ফর্ম (`throttle:forms`, SpamGuard) |
| `POST /api/admin/{login,forgot-password,reset-password}` | টোকেন ছাড়া (আলাদা rate limiter) |
| `/api/admin/*` | `auth:sanctum` |
| `/api/admin/{resource}[/{id}]` | জেনেরিক CRUD |
| `/api/business/*` 🔜 | `auth:sanctum` + `branch` + `perm:*` |
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
| পারমিশন 🔜 | প্রতিটা Business API রুটে `perm:` middleware। শুধু মেনু লুকানো যথেষ্ট না |
| শাখা 🔜 | সার্ভারেই শাখা ফিল্টার হয় (§৫.২)। ক্লায়েন্টের পাঠানো শাখা আইডি বিশ্বাস করা হয় না, ইউজারের অনুমোদিত শাখার সাথে মিলিয়ে দেখা হয় |

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
- **Business সেটিং 🔜:** কোম্পানির তথ্য, ব্যাংক, স্বাক্ষর, শর্তাবলী EN/BN একই টেবিলে থাকবে, তবে `biz_` prefix সহ আর private হিসেবে। ট্যাক্স রেট আর নম্বর সিরিজ থাকবে আলাদা টেবিলে।

### ৪.৮ Business মডিউলের কাঠামো 🔜

```
Request
  → auth:sanctum
  → BranchContext middleware   X-Branch-Id হেডার পড়ে, ইউজারের অনুমোদিত শাখার সাথে মেলায়।
                               "all" শুধু Admin এর জন্য।
  → perm:invoices.create       রোলের পারমিশন চেক
  → Controller (পাতলা)         ভ্যালিডেশন → Service কল → JSON
  → Service (ব্যবসার নিয়ম)      DB::transaction এর ভেতরে:
        DocumentNumber::next('INV', $branch)     row lock দিয়ে নম্বর
        Ledger::post(...)                        আয়/ব্যয়/ট্রান্সফার এন্ট্রি
        Stock::move(...)                         স্টকের চলাচল + গড় মূল্য
        ActivityLog::record(...)                 আগের/পরের মান
  → Model (BelongsToBranch trait: শাখা অনুযায়ী global scope)
```

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

### ৫.২ Business (পরিকল্পিত) 🔜

> **নামের সংঘাত এড়াতে:**
> - ওয়েবসাইটের `clients` (লোগো) আর `projects` (পোর্টফোলিও) টেবিল আগের মতোই থাকবে।
> - Business এর ক্লায়েন্টের টেবিল হবে **`customers`**, আর চলমান কাজের টেবিল হবে **`work_projects`**।
>
> নিচে "শাখাভিত্তিক" লেখা প্রতিটা টেবিলে `branch_id` থাকবে।

| গ্রুপ | টেবিল | মূল ফিল্ড / সম্পর্ক |
|---|---|---|
| **ভিত্তি** | `branches` | name, code (DHK), address, phone, is_active |
| | `roles`, `permissions`, `permission_role` | রোল (Admin, Accounts, Store, Sales & Marketing, Support), `module.action` পারমিশন |
| | `users` (+ role_id, is_active, phone), `branch_user` | ইউজার ↔ একাধিক শাখা |
| | `activity_logs` | user, branch, subject (polymorphic), action, before/after (JSON), ip |
| | `tax_rates` | name, rate %, is_default |
| | `document_sequences` | branch, type (QT/INV/RCV/PO/GRN/TKT), year, last_number |
| **CRM** | `leads` (শাখাভিত্তিক) | name, phone, email, source, stage, assigned_to, inquiry_id, customer_id, expected_value |
| | `lead_activities` | lead, type (call/meeting/visit/note), note, follow_up_at, done_at |
| | `customers` (শাখাভিত্তিক) | name, phone, email, address, lead_id |
| **কোটেশন** | `quotations` (শাখাভিত্তিক) | number, customer, revision, parent_id, status, subtotal, discount, tax_rate, tax, total, valid_until, terms_en/bn |
| | `quotation_sections` → `quotation_items` | ঘর → আইটেম (description, unit, qty, rate, amount) |
| **প্রজেক্ট** | `work_projects` (শাখাভিত্তিক) | code, customer, quotation, title, site_address, contract_value, start/end, manager_id, status, progress, portfolio_project_id |
| | `project_milestones`, `project_tasks`, `project_files` | ধাপ, টাস্ক (assignee, due, status), ছবি/ডকুমেন্ট |
| **বিলিং** | `invoices` (শাখাভিত্তিক) | number, customer, work_project, quotation, status (draft/issued/partial/paid/cancelled), subtotal, discount, tax, total, paid, due_date |
| | `invoice_items`, `payment_schedules` | আইটেম, কিস্তি (%/টাকা, due_date) |
| | `payments` (শাখাভিত্তিক) | number (RCV), invoice, customer, account, method, amount, reference, date, status |
| | `credit_notes` | invoice, amount, reason |
| **অ্যাকাউন্টস** | `accounts` (শাখাভিত্তিক) | name, type (cash/bank/mobile), number, opening_balance |
| | `transaction_categories` | type (income/expense), name |
| | `transactions` (শাখাভিত্তিক) | date, type (income/expense/transfer_in/transfer_out), account, category, amount, work_project, party (polymorphic: customer/supplier), source (polymorphic: payment/purchase …), attachment, transfer_group |
| **ইনভেন্টরি** | `stores` (শাখাভিত্তিক) | name, address |
| | `item_categories`, `units`, `items` | মালামাল (sku, unit, min_stock, avg_cost) |
| | `suppliers` | name, phone, address, opening_due |
| | `purchase_orders` → `purchase_order_items` | PO (supplier, store, status) |
| | `purchases` (GRN/সরাসরি) → `purchase_items` | type (grn/direct), store বা work_project, supplier, total, paid |
| | `stock_movements` | item, store, qty (+/−), unit_cost, type (in/issue/return/transfer/adjust), work_project, source (polymorphic), date |
| | `stock_balances` | item, store, qty, value (ক্যাশ) |
| **টিকেটিং** | `tickets` (শাখাভিত্তিক) | number, customer, work_project, subject, priority, status, assigned_to, visit_at, warranty_until, resolved_at |
| | `ticket_comments` | ticket, user/customer, body, attachments |

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
                       ├── /admin/settings, /profile
                       └── /admin/biz/* 🔜              <BusinessApp/> (আলাদা lazy chunk)
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
- **Business 🔜:**
  - `useList(endpoint, filters)`: পেজিনেশন, সার্চ আর ফিল্টারসহ তালিকা।
  - `BranchContext`: বর্তমান শাখা `localStorage` এ মনে রাখে, `adminApi` প্রতিটা রিকোয়েস্টে `X-Branch-Id` হেডার পাঠায়।
  - `usePermission()` আর `<Can perm="invoices.create">`: ইউজারের পারমিশন অনুযায়ী মেনু, বাটন আর রাউট দেখানো বা লুকানো।

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
│ সাইডবার   │ টপবার:  [☰] [শাখা ▾]🔜   View Site · নাম · Logout │
│ (ডার্ক)    ├───────────────────────────────────────────────┤
│ Dashboard│ .a-page-head   শিরোনাম + প্রধান অ্যাকশন বাটন     │
│ Website  │ .a-panel       ফিল্টার / টেবিল / ফর্ম            │
│  …       │   .a-table-wrap  (মোবাইলে আনুভূমিক স্ক্রল)        │
│ Business🔜│   .a-form-grid   (half/full ফিল্ড)               │
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

### ৬.৫ Business UI প্যাটার্ন 🔜

| প্যাটার্ন | কোথায় | আচরণ |
|---|---|---|
| **BranchSwitcher** | টপবার | Admin সব শাখা আর "সব শাখা (সেন্ট্রাল)" দেখে। অন্যরা শুধু নিজের শাখা। শাখা বদলালে পেজের ডেটা নতুন করে লোড হয় |
| **DataTable** | সব তালিকা | সার্চ, ফিল্টার (তারিখ, স্ট্যাটাস, শাখা), পেজিনেশন, কলাম সর্ট, Excel এক্সপোর্ট। মোবাইলে কার্ড ভিউ |
| **StatusBadge** | সব জায়গায় | স্ট্যাটাস অনুযায়ী রং, যেমন Draft ধূসর, Sent নীল, Approved/Paid সবুজ, Partial হলুদ, Overdue/Cancelled লাল |
| **Kanban** | CRM পাইপলাইন | ধাপের কলাম। কার্ড টেনে ধাপ বদলানো যায়। মোবাইলে কলামগুলো আনুভূমিক স্ক্রল |
| **LineItemsEditor** | কোটেশন, ইনভয়েস, PO | সেকশন/ঘর → আইটেমের সারি (বিবরণ, একক, পরিমাণ, দর, মোট)। সারি যোগ/মুছা/সাজানো। নিচে subtotal → discount → tax → total (ব্রাউজারে শুধু দেখানোর জন্য হিসাব, আসল হিসাব সার্ভারে) |
| **MoneyInput / Money** | টাকার ফিল্ড | `৳ 1,20,000.00` বাংলাদেশি কমা-ফরম্যাট, ডান দিকে সাজানো |
| **DocumentView** | কোটেশন/ইনভয়েস/রিসিট/PO | ডকুমেন্টের প্রিভিউ, পাশে অ্যাকশন: Edit, Send, **Print/PDF (EN \| বাংলা)**, WhatsApp, Cancel |
| **Timeline** | লিড, টিকেট, প্রজেক্ট | যোগাযোগের ইতিহাস আর কমেন্ট, তারিখ অনুযায়ী সাজানো |
| **ConfirmDialog** | বাতিল, পেমেন্ট | টাকা জড়িত কাজে নিশ্চিত করতে বলে, বাতিলের কারণ লিখতে হয় |
| **Can** | বাটন, মেনু, রাউট | পারমিশন না থাকলে জিনিসটাই দেখায় না |
| **রোলভিত্তিক Dashboard** | `/admin` | রোল অনুযায়ী আলাদা কার্ড আর তালিকা (plan.md ধাপ ৮) |

### ৬.৬ প্রিন্ট ও PDF 🔜

```
DocumentView → [Print/PDF EN] [Print/PDF বাংলা]
     → GET /api/business/invoices/{id}/pdf?lang=bn
     → Pdf service → resources/views/pdf/invoice.blade.php (lang অনুযায়ী লেবেল)
     → mPDF (Hind Siliguri / Noto Sans Bengali ফন্ট, OTL চালু) → application/pdf
```

- **ভাষা:** লেবেলগুলো `lang/en/doc.php` আর `lang/bn/doc.php` থেকে আসবে। আইটেমের বিবরণ যেমন লেখা হয়েছে তেমনই থাকবে।
- **কথায় অঙ্ক:** `AmountInWords::en()` লাখ/কোটি পদ্ধতিতে, `AmountInWords::bn()` বাংলায়।
- **ইমেইল:** একই PDF ইমেইলের সংযুক্তি হিসেবেও যাবে।

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

**নতুন মডিউল ডেপ্লয় 🔜:**
1. `php artisan migrate --force` চালানো। Terminal না থাকলে `/install/{key}`, এতে migrate হয়, আর ইউজার না থাকলে seed।
2. Business এর রোল, পারমিশন আর "Head Office" শাখা আলাদা seeder চালিয়ে তৈরি হবে। seeder টা idempotent: আগে থেকে থাকা ডেটা নষ্ট না করে, বারবার চালালেও সমস্যা হয় না।
3. cPanel Cron এ একবার `schedule:run` যোগ করা।
