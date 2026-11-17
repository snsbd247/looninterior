# Loon Interior: ওয়েবসাইট + অ্যাডমিন প্যানেল

> 📋 প্রজেক্টের প্ল্যান ও অগ্রগতি: [plan.md](plan.md) · 🏗️ আর্কিটেকচার ও UI: [UI.md](UI.md) · 📏 কাজের নিয়ম: [CLAUDE.md](CLAUDE.md)
>
> সব মডিউল ঠিক আছে কিনা এক কমান্ডে যাচাই: `powershell -ExecutionPolicy Bypass -File scripts\smoke-test.ps1`

ইন্টেরিয়র ডিজাইন কোম্পানির সম্পূর্ণ ডাইনামিক ওয়েবসাইট। সাইটের সব লেখা, ছবি, প্রজেক্ট, সার্ভিস, লোগো, কালার আর SEO অ্যাডমিন প্যানেল থেকে বদলানো যায়, কোড ধরতে হয় না।

| অংশ | প্রযুক্তি |
|---|---|
| Backend (API) | Laravel 12 + Sanctum (Bearer টোকেন) |
| Frontend | React 18 + Vite 5 + React Router 6 + axios |
| Database | MySQL / MariaDB |
| হোস্টিং | Shared cPanel এর জন্য বানানো (লোকালে XAMPP) |

```
labib/
├── backend/    Laravel API, React এর build ফাইলও এখান থেকে সার্ভ হয় (public/build)
├── frontend/   React সোর্স কোড: পাবলিক সাইট + /admin প্যানেল
└── README.md
```

---

## সূচিপত্র

1. [ফিচার](#ফিচার)
2. [কোডের গঠন](#কোডের-গঠন)
3. [লোকাল সেটআপ (এই পিসি, XAMPP)](#লোকাল-সেটআপ-এই-পিসি-xampp)
4. [ডেভেলপমেন্ট মোড](#ডেভেলপমেন্ট-মোড)
5. [প্রোডাকশন বিল্ড](#প্রোডাকশন-বিল্ড)
6. [cPanel এ ডেপ্লয়](#cpanel-shared-hosting-এ-ডেপ্লয়)
7. [নিরাপত্তা](#নিরাপত্তা)
8. [কাস্টমাইজেশন](#কাস্টমাইজেশন)
9. [API সারসংক্ষেপ](#api-সারসংক্ষেপ)
10. [সমস্যা হলে](#সমস্যা-হলে)

---

## ফিচার

### পাবলিক ওয়েবসাইট

**Home পেজের সেকশনগুলো:**
- হিরো স্লাইডার
- About
- Services
- কাউন্টার
- ক্যাটাগরি ফিল্টারসহ প্রজেক্ট
- Why Choose Us
- Work Process
- Testimonials
- Clients (চলমান লোগো)
- FAQ + Quote ফর্ম
- Blog
- CTA

**বাকি পেজগুলো:**

| পেজ | কী আছে |
|---|---|
| About | Mission/Vision, টিম |
| Services, Service Detail | সার্ভিসের তালিকা আর প্রতিটার আলাদা পেজ |
| Projects, Project Detail | ক্যাটাগরি ফিল্টার, পেজিনেশন, গ্যালারি লাইটবক্স, ভিডিও/ভার্চুয়াল ট্যুর |
| Blog, Blog Detail | আর্টিকেল |
| Careers, Career Detail | জব পোস্ট + অনলাইন আবেদন (CV আপলোড) |
| Contact | Google Map সহ যোগাযোগ ফর্ম |
| Get a Quote | কোট রিকোয়েস্ট ফর্ম |
| FAQ | প্রশ্নোত্তর |
| ডাইনামিক পেজ | Privacy, Terms ইত্যাদি (অ্যাডমিন থেকে বানানো যায়) |
| 404 | পেজ না পেলে |

এছাড়া:
- ভাসমান WhatsApp / Call / Messenger বাটন।
- মোবাইল রেসপন্সিভ (390px মোবাইল থেকে বড় ডেস্কটপ পর্যন্ত টেস্ট করা)।
- **ফর্মে স্প্যাম সুরক্ষা:** লুকানো honeypot ফিল্ড আর সময়-স্বাক্ষরিত টোকেন। কোনো API key লাগে না।
- **প্রতিটা পেজের আলাদা SEO:** প্রজেক্ট/সার্ভিস/ব্লগ/জব/পেজের নিজস্ব title, description, OG image আর canonical। ফেসবুক/WhatsApp এ লিংক শেয়ার করলে সঠিক ছবি আর টাইটেল দেখায়। ভুল লিংকে আসল 404 স্ট্যাটাস দেয়।
- **`/sitemap.xml` আর `/robots.txt`:** ডাটাবেজ থেকে নিজে তৈরি হয়। Google Search Console এ sitemap জমা দিন।

### অ্যাডমিন প্যানেল (`/admin`)

**প্রধান অংশ:**
- **Dashboard:** মোট সংখ্যা, নতুন কোট রিকোয়েস্ট, সাম্প্রতিক জব অ্যাপ্লিকেশন।
- **Quote Requests:** স্ট্যাটাস আর নোট, সরাসরি কল / WhatsApp বাটন।
- **Job Applications:** স্ট্যাটাস বদলানো, CV ডাউনলোড।
- **Site Settings:** লোগো, ফেভিকন, ব্র্যান্ড কালার, কন্টাক্ট, সোশ্যাল লিংক, হোমপেজের সব লেখা, About পেজ, SEO।
- **My Profile:** নাম, ইমেইল, পাসওয়ার্ড পরিবর্তন।
- **ইমেইল নোটিফিকেশন:** নতুন কোট/কন্টাক্ট রিকোয়েস্ট এলে আর জব আবেদন এলে (CV সংযুক্ত) ইমেইল যায়। ঠিকানা দেওয়া হয় Settings → Notifications থেকে। সেখানে "Send Test E-mail" বাটনও আছে।
- **Forgot password:** লগইন পেজ থেকে ইমেইলে রিসেট লিংক যায়। লিংক ৬০ মিনিট চলে আর একবারই ব্যবহার করা যায়।

**কনটেন্ট সেকশন:** প্রতিটায় যোগ, এডিট, ডিলিট, সার্চ আর ছবি আপলোড করা যায়।
- Hero Sliders
- Counters
- Why Choose Us
- Work Process
- Services
- Project Categories
- Projects (মাল্টি-ইমেজ গ্যালারি)
- Testimonials
- Clients
- Team
- FAQs
- Blog Posts
- Pages
- Job Posts

**এডিটিং সুবিধা:**
- আইকন পিকার আছে।
- লম্বা লেখার জন্য রিচ টেক্সট এডিটর (Quill) আছে।

### Business প্যানেল: ধাপ ১ (ভিত্তি) ✅

অ্যাডমিনের সাইডবারে **Administration** গ্রুপে আছে। পুরো প্ল্যান [plan.md](plan.md) তে।

- **Users:**
  - স্টাফ যোগ করা যায়, যত খুশি। প্রত্যেককে রোল আর এক বা একাধিক শাখা দেওয়া হয়।
  - ইউজার মোছা যায় না, শুধু "Inactive" করা যায়। Inactive করলে সাথে সাথে লগআউট হয়ে যায়।
- **Roles & Permissions:**
  - শুরুতে ৫টা রোল: Admin, Accounts, Store, Sales & Marketing, Support।
  - চেকবক্সে টিক দিয়ে প্রতিটা রোলের পারমিশন বদলানো যায়। Admin রোল লক করা।
- **Branches:**
  - একাধিক শাখা, প্রতিটার একটা কোড, যেমন DHK। শুরুতে "Head Office" (HO)।
  - উপরের মেনু থেকে শাখা বদলানো যায়। Admin "All branches (central)" বেছে সব শাখা একসাথে দেখতে পারে।
- **Tax Rates:** যেমন VAT 15%। একটা থাকে default।
- **Business Settings:**
  - কোম্পানির নাম আর ঠিকানা (English + বাংলা), BIN/TIN, ব্যাংক তথ্য, শর্তাবলী, স্বাক্ষর আর সিল, ডকুমেন্ট নম্বরের ফরম্যাট।
  - **নমুনা PDF (English / বাংলা)** বাটন দিয়ে দেখে নেওয়া যায় ডকুমেন্টে সব কেমন আসছে।
- **Activity Log:** কে কখন কী তৈরি, বদল বা মুছেছে, আগের আর পরের মানসহ। লগইনও থাকে।
- **রোল অনুযায়ী মেনু:** প্রতিটা স্টাফ শুধু তার রোলের মেনু দেখে। সার্ভারও (API) অনুমতি ছাড়া কিছু করতে দেয় না।

### Business প্যানেল: ধাপ ২ (CRM) ✅

সাইডবারের **CRM** গ্রুপে আছে।

- **Leads:**
  - ওয়েবসাইটের প্রতিটা কোট রিকোয়েস্ট নিজে থেকেই লিড হয়ে যায়। একই ফোন নম্বর হলে আগের লিডের টাইমলাইনে যোগ হয়।
  - হাতেও লিড যোগ করা যায় (ফোন, Facebook, রেফারেন্স…)।
  - তালিকা আর **Pipeline (Kanban)** ভিউ। কার্ড টেনে ধাপ বদলানো যায়।
- **লিডের পেজ:**
  - ধাপের বার থেকে এক ক্লিকে ধাপ বদলানো যায়।
  - Call, Meeting, Site Visit আর Note টাইমলাইনে লেখা যায়, সাথে পরের ফলো-আপের সময়।
  - Call আর WhatsApp বাটন।
- **Follow-ups:** আজকের আর মিস হওয়া ফলো-আপের তালিকা। এক ক্লিকে Done করা যায়।
- **Customers:**
  - লিড Won হলে নিজে থেকে তৈরি হয়।
  - প্রোফাইলে সব লিড দেখা যায়। পরের ধাপে কোটেশন, প্রজেক্ট, ইনভয়েস আর পেমেন্টও এখানে আসবে।

### Business প্যানেল: ধাপ ৩ (কোটেশন / BOQ) ✅

- **Sales & Projects → Quotations:** ঘর/সেকশন অনুযায়ী আইটেম (একক, পরিমাণ, দর), ডিসকাউন্ট (% বা ৳), ট্যাক্স, মেয়াদ আর শর্তাবলী। মোট সার্ভারে হিসাব হয়।
- **স্ট্যাটাস:** Draft → Sent → Approved / Rejected। Sent হলে লিড "Quotation Sent", Approved হলে লিড "Won" হয়ে যায়।
- **Revise:** পাঠানো কোটেশন বদলাতে হলে নতুন সংস্করণ (…-R1) তৈরি হয়। পুরনোটা থেকে যায়।
- **PDF (English / বাংলা)**, ডাউনলোড, **ইমেইল** (PDF সংযুক্ত), আর **WhatsApp** এ ৩০ দিন মেয়াদি PDF লিংক শেয়ার।
### Business প্যানেল: ধাপ ৪ (প্রজেক্ট ও বিলিং) আর ধাপ ৫ (অ্যাকাউন্টস) ✅

- **Projects:**
  - অনুমোদিত কোটেশন থেকে "Create Project" দিলে প্রজেক্ট তৈরি হয়, সাথে ৫টা milestone আর 50/30/20 কিস্তি।
  - ট্যাবগুলো: Overview (লাভ-ক্ষতি), Milestones & Tasks, Files & Photos, Billing (কিস্তি → ইনভয়েস), Costs।
  - কাজ শেষ হলে "Publish to website" দিয়ে ছবিগুলো পোর্টফোলিওতে যায়।
- **Invoices:**
  - Draft → Issue → **Receive Payment** (Cash/Bank/bKash/Nagad/Cheque…, আংশিক পেমেন্ট নেওয়া যায়) → Paid।
  - ক্রেডিট নোট, বাতিল, EN/বাংলা PDF আর ইমেইল।
  - প্রতিটা পেমেন্টের মানি রিসিট।
- **Receivables (Dues):** কার কাছে কত বাকি আর কতদিন ধরে। Excel এ ডাউনলোড করা যায়।
- **Cash & Bank:**
  - প্রতিটা অ্যাকাউন্টের ব্যালেন্স আর লেজার।
  - এক অ্যাকাউন্ট থেকে আরেক অ্যাকাউন্টে ট্রান্সফার, অন্য শাখাতেও।
- **Income & Expenses:**
  - ক্লায়েন্টের পেমেন্ট নিজে থেকেই আয় হিসেবে ওঠে।
  - খরচ খাত অনুযায়ী লেখা যায়, প্রজেক্টের সাথে যুক্ত করা যায়, রসিদের ছবিও দেওয়া যায়।
  - খাতের তালিকা: Income/Expense Heads।
- **Reports:** Cash Book, আয়-ব্যয়, বছরের লাভ-ক্ষতি, প্রজেক্টের লাভ, শাখার তুলনা। Print আর Excel (CSV)।
- **নিয়ম:** টাকার কোনো এন্ট্রি মোছা যায় না, শুধু কারণসহ বাতিল করা যায়। হিসাব সবসময় মিলে থাকে।
- **দৈনিক ইমেইল (সকাল ৯টা):**
  - প্রতিটা স্টাফ পায় তার আজকের ফলো-আপের তালিকা।
  - Admin পায় মেয়াদোত্তীর্ণ ইনভয়েসের তালিকা।
  - এর জন্য cPanel Cron দরকার ([ধাপ ৭ক](#ধাপ-৭ক-cron-job)।)

### Business প্যানেল: ধাপ ৬ (ইনভেন্টরি) ✅

সাইডবারের **Inventory** গ্রুপে আছে।

- **Items / Item Categories / Stores:**
  - মালামালের তালিকা (একক, low-stock সীমা)।
  - প্রতিটা শাখায় এক বা একাধিক স্টোর। নতুন শাখায় "Main Store" নিজে থেকে তৈরি হয়।
- **Purchase Orders:** সাপ্লায়ারকে PO (PDF/ইমেইল)। মাল এলে "Receive Goods" দিয়ে GRN।
- **Purchases (GRN):**
  - স্টোরে মাল ঢোকানো: স্টক আর গড় ক্রয়মূল্য আপডেট হয়।
  - অথবা "Direct to project": মাল সোজা প্রজেক্টের খরচে যায়।
  - নগদে কিনলে "Paid now" দিয়ে সাথে সাথে পেমেন্ট দেওয়া যায়।
- **Issue / Transfer:** প্রজেক্টে মাল ইস্যু, সাইট থেকে ফেরত, স্টোর থেকে স্টোরে ট্রান্সফার, অ্যাডজাস্টমেন্ট। স্টকের বেশি বের করা যায় না।
- **Stock:** কোন স্টোরে কত আছে, গড় মূল্য, মোট মূল্য, কম স্টকের সতর্কতা, প্রতিটা মালামালের ইতিহাস। CSV ডাউনলোড।
- **Suppliers:** কার কাছে কত দেনা, স্টেটমেন্ট, পেমেন্ট (Accounts রোলের `suppliers.pay` পারমিশন লাগে)।
- **প্রজেক্টের খরচ:** ইস্যু করা মাল আর সরাসরি ক্রয় নিজে থেকে প্রজেক্টের "Cost so far" এ যোগ হয়।

### Business প্যানেল: ধাপ ৭ (টিকেটিং) ✅

- **Service → Service Tickets:**
  - কাজ শেষের পর ক্লায়েন্টের অভিযোগ বা মেরামতের টিকেট।
  - টেকনিশিয়ান assign করা, ভিজিটের সময়, অগ্রাধিকার।
  - কমেন্ট আর ছবি দেওয়া যায়। প্রতিটা পরিবর্তন টাইমলাইনে লেখা থাকে।
  - প্রজেক্টের ওয়ারেন্টির মধ্যে আছে কিনা নিজে থেকে দেখায় (প্রজেক্টে "Warranty Until" তারিখ দিন)।
- **ওয়েবসাইটে `/service-request` পেজ (ফুটারে লিংক):**
  - ক্লায়েন্ট নিজে অনুরোধ পাঠাতে পারে। ফোন নম্বর মিলিয়ে তার প্রজেক্ট খুঁজে টিকেট তৈরি হয়।
  - ক্লায়েন্ট ইমেইলে টিকেট নম্বর পায়।

### Business প্যানেল: ধাপ ৮ (Dashboard) ✅

- **Dashboard (`/admin`):** প্রতিটা স্টাফ শুধু তার রোলের অংশ দেখে:
  - **Sales:** লিড আর ফলো-আপ, কোটেশন।
  - **Accounts:** আদায়, বকেয়া, ক্যাশ ও ব্যাংক, ৬ মাসের চার্ট।
  - **Store:** কম স্টক।
  - **Support:** টিকেট।
  - প্রজেক্টের অগ্রগতি আর নিজের টাস্ক।
- **সেন্ট্রাল ভিউ:** Admin উপরের মেনু থেকে "All branches (central)" বাছলে সব শাখার এই মাসের তুলনা দেখে।
- **ট্যাব:** ওয়েবসাইটের পুরনো Dashboard আছে "Website" ট্যাবে।

### পর্যায় F: ক্যালেন্ডার, HR, শ্রমিক, ক্লায়েন্ট পোর্টাল, SMS ✅

- **Calendar (CRM → Calendar):** ফলো-আপ, টাস্ক, মাইলস্টোন, সাইট ভিজিট, ইনভয়েস due আর PO ডেলিভারি এক ক্যালেন্ডারে। মোবাইলে লিস্ট আকারে।
- **HR (সাইডবার → HR):**
  - **Employees:** বেসিক বেতন, ভাতা, যোগদানের তারিখ।
  - **Attendance:** দিনে শুধু অনুপস্থিত/অর্ধদিবস/ছুটি মার্ক করলেই হয়, বাকিরা উপস্থিত। মাসের গ্রিডও আছে।
  - **Leave:** আবেদন আর অনুমোদন; অনুমোদন দিলে হাজিরায় বসে যায়। Unpaid ছুটি বেতন থেকে কাটে।
  - **Salary Sheets:** মাস বেছে "Prepare salary sheet" → বোনাস/অগ্রিম → Finalize → Pay salaries (একটা অ্যাকাউন্ট থেকে, অ্যাকাউন্টসে "Salary" খরচ)। পে-স্লিপ PDF আর CSV।
- **শ্রমিক (Sales & Projects → Labour Work / Contractors):** কতজন × কত দিন × রেট লিখলে প্রজেক্টের খরচে যোগ হয়। ঠিকাদারের বকেয়া, স্টেটমেন্ট আর পেমেন্ট।
- **ক্লায়েন্ট পোর্টাল (`/portal`, ফুটারে "Client Portal"):**
  - স্টাফ কাস্টমারের পেজে "Client portal" → **Enable portal** → পাসওয়ার্ড দিয়ে সেভ। ক্লায়েন্ট ফোন নম্বর বা ইমেইল আর ওই পাসওয়ার্ড দিয়ে লগইন করবে।
  - ক্লায়েন্ট দেখে: প্রজেক্টের অগ্রগতি, স্টেজ, সাইটের ছবি, ইনভয়েস ও মানি রিসিট PDF, সার্ভিস রিকোয়েস্ট (খোলা আর মেসেজ)।
  - টিকেটে স্টাফের কমেন্ট শুধু **Visible to customer** টিক দিলেই ক্লায়েন্ট দেখে।
- **SMS (Business Settings → SMS):** SMS কোম্পানির URL বসান (`{to}` আর `{message}` সহ), চালু করুন, "Send test SMS" দিয়ে যাচাই করুন। পেমেন্ট পেলে আর টিকেট খুললে ক্লায়েন্ট SMS পায়। URL খালি থাকলে SMS শুধু `backend/storage/logs/sms.log` এ লেখা হয় (টেস্ট মোড)।
- **ছোট উন্নতি:** ছবি আপলোডে নিজে থেকে ছোট হয় (১৯২০px); Site Settings → SEO তে Google Analytics (GA4) আর Facebook Pixel ID; Inquiries আর Job Applications এ "Excel (CSV)" বাটন; অ্যাডমিন প্যানেলের নিচে কপিরাইট লাইন।

### পর্যায় G: Delete, Greenweb SMS, মার্কেটিং ক্যাম্পেইন ✅

- **Delete বাটন:** যেখানে Add/Edit আছে প্রায় সবখানে Delete আছে।
  - রেকর্ড কোথাও ব্যবহার না হলে মুছে যায়।
  - ব্যবহার হয়ে থাকলে মুছবে না, কারণ দেখাবে (যেমন "used in 2 invoices… Mark it inactive instead.")।
  - ইনভয়েস, পেমেন্ট, স্টক আর লেজারে আগের মতো **Cancel** থাকে, এগুলো কখনো মোছে না।
- **Greenweb SMS (Business Settings → SMS):** Provider = **Greenweb** বেছে নিয়ে API টোকেন বসান → Save → "Send test SMS" আর "Check balance" দিয়ে যাচাই করুন। পেমেন্ট আর টিকেটের অটো SMS চালু করতে "Automatic SMS" = On করুন।
- **মার্কেটিং (সাইডবার → Marketing → SMS & E-mail Campaigns):**
  - **New campaign:** নাম, SMS না E-mail, মেসেজ (`{name}`, `{company}` বসানো যায়), আর কাকে পাঠাবে (ক্লায়েন্ট বা লিড, স্টেজ, সোর্স, তারিখ)। পাশে সাথে সাথে কতজন আর কত SMS তা দেখায়।
  - **Save draft** → **Send now**। পাঠানোর সময় পেজটা খোলা রাখুন; প্রোগ্রেস বার দেখায়। Pause আর Continue করা যায়, ব্যর্থগুলো **Retry failed** দিয়ে আবার পাঠানো যায়।
  - যারা আর মেসেজ চান না: লিড বা ক্লায়েন্টের Edit পেজে "No marketing SMS / e-mail" টিক দিন। ইমেইলের নিচের Unsubscribe লিংকে ক্লিক করলেও নিজে থেকে বন্ধ হয়ে যায়।
- **ইনভয়েস ও কোটেশন ইমেইল:** ডিটেইল পেজের **Email** বাটনে PDF (English বা Bangla) সংযুক্ত হয়ে যায়।

### খরচের ক্যালকুলেটর (`/cost-calculator`) ✅

- ভিজিটর ধরন, এরিয়া, স্টাইল, কোয়ালিটি আর এক্সট্রা বাছলে সাথে সাথে বাজেটের রেঞ্জ, সময় আর খরচের ভাগ দেখে।
- দুইভাবে হিসাব: **By total area** (প্রতি sqft রেট) অথবা **Room by room** (কোন রুম কয়টা, যেমন ২টা বেডরুম, ১টা কিচেন)। রুমের দাম আর শুরুর সংখ্যা অ্যাডমিন থেকে বদলানো যায় (group = room)।
- নাম আর ফোন দিলে PDF এস্টিমেট পায়। আপনার কাছে **CRM লিড** আসে (সব বাছাই আর আনুমানিক বাজেটসহ), সাথে নোটিফিকেশন ইমেইল।
- **রেট বদলাতে:**
  - অ্যাডমিন → Content → **Cost Calculator** থেকে প্রতিটা ধরনের প্রতি sqft দাম, স্টাইল আর কোয়ালিটির গুণক (1 = সাধারণ দাম, 1.35 = ৩৫% বেশি) আর এক্সট্রার দাম বদলান।
  - Site Settings → **Cost Calculator** থেকে এরিয়ার সীমা, রেঞ্জ %, সময়ের হিসাব আর খরচের ভাগ বদলান।
- এখনকার রেটগুলো **ডেমো**। লাইভ করার আগে নিজের রেট বসান।

### Webmail (অ্যাডমিন থেকে এক ক্লিকে) ✅

প্রত্যেক স্টাফ অ্যাডমিন → **Mail → Webmail** থেকে নিজের কোম্পানির মেইল খোলে। মেইলের পাসওয়ার্ড লাগে না।

একবারের সেটআপ:
1. **cPanel → Email Accounts:** প্রত্যেক স্টাফের মেইলবক্স তৈরি করুন (যেমন `sales@looninterior.com`)।
2. **cPanel → Security → Manage API Tokens → Create:** একটা টোকেন তৈরি করে কপি করুন।
3. **অ্যাডমিন → Business Settings → Webmail:**
   - One-click Webmail = On।
   - E-mail domain আর cPanel server name = `looninterior.com`।
   - cPanel username আর API token বসিয়ে Save করুন। টোকেন পরে `********` দেখাবে।
4. **অ্যাডমিন → Users → Edit:** প্রত্যেকের **Mailbox (Webmail)** ঘরে তাঁর মেইল লিখুন। লগইন ইমেইল কোম্পানির ডোমেইনে হলে ঘরটা খালি রাখা যায়।
5. **Roles:** যাদের দরকার তাদের রোলে **Webmail → use** পারমিশন দিন (Admin-এর আগে থেকেই আছে)।

### SEO আর ট্র্যাকিং ✅

- **Site Settings → SEO:** Meta Title, Meta Description, Social Share Image, **Google Tag Manager ID**, Google Analytics ID, Facebook Pixel ID, Search Console code।
  - GTM ব্যবহার করলে GA4 GTM এর ভেতরে সেট করুন আর এখানে Analytics ID খালি রাখুন (না হলে দুবার গোনা হবে)।
  - GTM এ ইভেন্ট আসে: পেজ বদলালে `page_view`, Quote/Contact ফর্ম জমা দিলে `generate_lead`।
- **প্রতিটা Service, Project, Blog Post আর Page এ "SEO Title / SEO Description"** (ঐচ্ছিক)। খালি থাকলে নিজে থেকে তৈরি হয়।
- **Structured data নিজে থেকে যায়:** হোম আর কন্টাক্টে LocalBusiness (Site Settings এর নাম, ফোন, ইমেইল, ঠিকানা, লোগো, সোশ্যাল লিংক থেকে), সব পেজে Breadcrumb, ব্লগে Article, সার্ভিসে Service। তাই Site Settings এর তথ্য সবসময় সঠিক রাখুন।
- যাচাই: https://search.google.com/test/rich-results এ পেজের লিংক দিন।

### অ্যাডমিন প্যানেলের মেনু আর ভাষা

- **সাইডবার:** submenu স্টাইলে। CRM, Billing, Inventory… তে ক্লিক করলে নিচের মেনু খোলে। যে পেজ খোলা, তার মেনু নিজে থেকে খোলা থাকে।
- **ভাষা:**
  - পুরো ওয়েবসাইট আর অ্যাডমিন প্যানেল **ইংরেজিতে**।
  - বাংলা শুধু ডকুমেন্টের **PDF (Bangla)** অপশনে (কোটেশন, ইনভয়েস, মানি রিসিট, PO)।

---

## কোডের গঠন

### জেনেরিক CRUD

প্রতিটা অ্যাডমিন সেকশনের জন্য আলাদা কন্ট্রোলার নেই।

| ফাইল | কাজ |
|---|---|
| `backend/app/Support/ResourceRegistry.php` | প্রতিটা সেকশনের ফিল্ড, ছবি, ভ্যালিডেশন, সার্চ, সর্টিং |
| `backend/app/Http/Controllers/Admin/ResourceController.php` | একটাই কন্ট্রোলার সব সেকশনের CRUD করে |
| `frontend/src/admin/resources.js` | অ্যাডমিনের ফর্ম আর টেবিল এখান থেকে নিজে তৈরি হয় |

### Backend কন্ট্রোলার

| কন্ট্রোলার | কাজ |
|---|---|
| `Api/PublicController` | ওয়েবসাইটের সব ডেটা |
| `Api/FormController` | কোট আর জব আবেদন ফর্ম (প্রতি মিনিটে সর্বোচ্চ ১০টা) |
| `Admin/AuthController` | লগইন, লগআউট, প্রোফাইল |
| `Admin/DashboardController` | Dashboard আর CV ডাউনলোড |
| `Admin/SettingController` | সাইট সেটিংস |
| `Admin/ResourceController` | জেনেরিক CRUD |

### সাহায্যকারী ক্লাস (`backend/app/Support/`)

| ফাইল | কাজ |
|---|---|
| `SpamGuard.php` | honeypot আর ফর্ম টোকেন যাচাই |
| `Notifier.php` | অ্যাডমিনকে ইমেইল নোটিফিকেশন (ইমেইল ব্যর্থ হলেও ফর্ম জমা হয়, শুধু লগে এরর থাকে) |
| `Seo.php` | পেজভিত্তিক meta ট্যাগ আর sitemap |
| `Uploader.php` | ছবি আপলোড/ডিলিট |

ইমেইলের ডিজাইন: `backend/resources/views/emails/`

### গুরুত্বপূর্ণ ফোল্ডার

| পাথ | কী আছে |
|---|---|
| `backend/database/seeders/DatabaseSeeder.php` | ডেমো কনটেন্ট, সেটিংস, অ্যাডমিন ইউজার |
| `backend/public/brand/` | লোগো (`loon-logo.svg`, `loon-logo-light.svg`, `loon-icon.svg`) |
| `backend/public/uploads/` | অ্যাডমিন থেকে আপলোড করা ছবি |
| `frontend/src/pages/` | পাবলিক সাইটের পেজ |
| `frontend/src/components/` | Header, Footer, কার্ড ইত্যাদি |
| `frontend/src/admin/` | অ্যাডমিন প্যানেল |
| `frontend/src/styles/site.css` | সাইটের ডিজাইন |
| `frontend/src/admin/admin.css` | অ্যাডমিনের ডিজাইন |
| `frontend/src/lib/api.js` | API কল আর base path হেল্পার (`sitePath`, `img`) |

---

## লোকাল সেটআপ (এই পিসি, XAMPP)

সাইটটা এখন এই পিসিতে XAMPP দিয়ে `/loon` সাব-পাথে চলছে।

### লিংক আর লগইন

| বিষয় | মান |
|---|---|
| সাইট | http://localhost/loon, http://192.168.0.171/loon, http://160.25.9.132/loon |
| অ্যাডমিন | http://localhost/loon/admin |
| অ্যাডমিন লগইন | `backend/.env` এর `ADMIN_EMAIL` / `ADMIN_PASSWORD` (এই ফাইল গিটে যায় না) |

### সার্ভার আর কনফিগারেশন

| বিষয় | মান |
|---|---|
| XAMPP | `C:\xampp` (PHP 8.2.12, MariaDB) |
| ডাটাবেজ | `loon_interior` (ইউজার `root`, পাসওয়ার্ড নেই) |
| Apache কনফিগ | `C:\xampp\apache\conf\extra\loon.conf` (`httpd.conf` এর শেষে Include করা) |
| PHP এক্সটেনশন | `php.ini` এ `zip` আর `gd` চালু |
| অটো-স্টার্ট | Apache2.4 আর mysql Windows সার্ভিস (Automatic) |
| Composer | `C:\xampp\php\php.exe C:\xampp\php\composer.phar` |
| Node.js | `C:\Program Files\nodejs` |

`loon.conf` এ কী সেট করা আছে:
- `Alias /loon` সরাসরি `backend/public` ফোল্ডারে যায়।
- `RewriteBase /loon/`।
- Authorization হেডার পাস করা হয়।
- `uploads` ফোল্ডারে PHP চালানো বন্ধ।

### অন্য ডিভাইস থেকে খোলা

**একই নেটওয়ার্কের ডিভাইস:** Administrator PowerShell এ একবার এটা চালান:
```powershell
New-NetFirewallRule -DisplayName "Apache HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow
```

**বাইরের ইন্টারনেট (`160.25.9.132`):** রাউটারে পোর্ট 80 এই পিসির `192.168.0.171` এ ফরওয়ার্ড করতে হবে।

### ডিজাইন বদলানোর পর আবার build

```powershell
cd f:\Lovable\labib\frontend
$env:Path = "C:\Program Files\nodejs;$env:Path"
npm run build
```

Build শেষ হলে ব্রাউজারে Ctrl+F5 চাপুন।

### লোকালে ইমেইল

লোকালে `MAIL_MAILER=log`, তাই ইমেইল আসলে পাঠানো হয় না, `backend/storage/logs/mail.log` ফাইলে লেখা হয়। আসল ইমেইল পাঠাতে `.env` এ SMTP দিন ([ইমেইল সেটআপ](#ধাপ-৫-ইমেইল-smtp) দেখুন)।

### অ্যাডমিন পাসওয়ার্ড টার্মিনাল থেকে বদলানো

ইমেইল সেটআপ না থাকলে বা অ্যাডমিন ইমেইলও ভুলে গেলে:
```powershell
cd f:\Lovable\labib\backend
C:\xampp\php\php.exe artisan admin:password admin@looninterior.com NewPassword123
```
ভুল ইমেইল দিলে কমান্ডটা বিদ্যমান অ্যাডমিন ইমেইলগুলো দেখিয়ে দেয়।

### ডাটাবেজ রিসেট (সব ডেটা মুছে ডেমো কনটেন্ট আবার বসাবে)

```powershell
cd f:\Lovable\labib\backend
C:\xampp\php\php.exe artisan migrate:fresh --seed
```

---

## ডেভেলপমেন্ট মোড

প্রয়োজন: PHP 8.2+, Composer, Node.js 18+, MySQL।

**Backend:**
```bash
cd backend
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
# .env এ DB_DATABASE / DB_USERNAME / DB_PASSWORD দিন
php artisan migrate --seed     # টেবিল + ডেমো কনটেন্ট + অ্যাডমিন ইউজার
php artisan serve              # http://127.0.0.1:8000
```

**Frontend (নতুন টার্মিনালে):**
```bash
cd frontend
npm install
npm run dev                    # http://localhost:5173 (API নিজে থেকেই 8000 এ প্রক্সি হয়)
```

**অ্যাডমিন ইউজার:** তৈরি হয় `.env` এর `ADMIN_EMAIL` / `ADMIN_PASSWORD` থেকে।
- নতুন ইনস্টলে ডিফল্ট হলো `admin@example.com` / `ChangeMe@123`।
- প্রথম লগইনের পরেই My Profile থেকে পাসওয়ার্ড বদলান।

---

## প্রোডাকশন বিল্ড

Build এর আউটপুট সরাসরি `backend/public/build` এ যায়। এরপর `backend` ফোল্ডারটাই পুরো সাইট (API + React)।

### সাব-পাথ নাকি রুট ডোমেইন

| সাইট কোথায় চলবে | কী করতে হবে |
|---|---|
| সাব-পাথ, যেমন `localhost/loon` | `frontend/.env.local` এ `VITE_BASE_PATH=/loon` রাখুন (এখন এটাই সেট করা) |
| রুট ডোমেইন, যেমন `looninterior.com` | `frontend/.env.local` ফাইলটা মুছে দিন, অথবা `VITE_BASE_PATH=` খালি রাখুন |

এরপর build করুন:
```bash
cd frontend
npm run build

cd ../backend
composer install --no-dev --optimize-autoloader
```

> **সতর্কতা:** লোকালের `/loon` build cPanel এর রুট ডোমেইনে আপলোড করলে সাইট খালি দেখাবে। আপলোডের আগে অবশ্যই `.env.local` মুছে আবার build করুন।

---

## cPanel (Shared Hosting) এ ডেপ্লয়

### ধাপ ১: ডাটাবেজ
1. cPanel → **MySQL® Databases** এ গিয়ে নতুন ডাটাবেজ আর ইউজার তৈরি করুন।
2. ইউজারকে **ALL PRIVILEGES** দিন।

### ধাপ ২: PHP ভার্সন
- cPanel → **Select PHP Version / MultiPHP Manager** থেকে **PHP 8.2 বা 8.3** বেছে নিন।
- এই এক্সটেনশনগুলো চালু রাখুন: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, bcmath, gd, zip`। (`gd` আর `mbstring` PDF তৈরির জন্যও দরকার।)
- বড় ছবি আপলোডের জন্য `upload_max_filesize = 10M` আর `post_max_size = 64M` দিন।

### ধাপ ৩: ফাইল আপলোড

আগে রুট ডোমেইনের জন্য build করুন ([উপরে দেখুন](#প্রোডাকশন-বিল্ড))। এরপর `backend` ফোল্ডার zip করে **File Manager** দিয়ে আপলোড করে extract করুন। `.env` আর `vendor/` সহ আপলোড করবেন, `node_modules` লাগবে না।

**পদ্ধতি A (Recommended), Document Root বদলানো:**
1. প্রজেক্ট রাখুন `/home/USER/loon` এ।
2. cPanel → **Domains** এ গিয়ে ডোমেইনের Document Root দিন `/home/USER/loon/public`।

**পদ্ধতি B, public_html ব্যবহার করে (Document Root বদলানো না গেলে):**
1. পুরো প্রজেক্ট রাখুন `/home/USER/loon` এ, `public_html` এর **বাইরে**।
2. `loon/public/` এর **ভেতরের সব ফাইল** `public_html/` এ কপি করুন: `index.php`, `.htaccess`, `build/`, `brand/`, `uploads/`। (`robots.txt` ফাইল রাখবেন না, এটা এখন নিজে তৈরি হয়।) Hidden ফাইল দেখতে File Manager → Settings → *Show Hidden Files* চালু করুন।
3. `public_html/index.php` খুলে এই লাইনটা বদলান:
   ```php
   $basePath = __DIR__.'/../loon';
   ```
   এরপর আপলোড করা ছবি `public_html/uploads` এ সেভ হবে।

> পরে frontend আবার build করলে শুধু `build/` ফোল্ডারটা রিপ্লেস করলেই চলবে।

### ধাপ ৪: `.env`
`loon/.env.example` কপি করে `.env` বানান, তারপর এগুলো ঠিক করুন:
```
APP_NAME="Loon Interior"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Dhaka
APP_KEY=base64:...........        # লোকালে `php artisan key:generate --show` চালিয়ে কপি করুন
DB_HOST=localhost
DB_DATABASE=cpaneluser_dbname
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=********
ADMIN_EMAIL=admin@looninterior.com
ADMIN_PASSWORD=শক্ত_নতুন_পাসওয়ার্ড
```

### ধাপ ৫: ইমেইল (SMTP)

নোটিফিকেশন আর পাসওয়ার্ড রিসেটের জন্য দরকার।
1. cPanel → **Email Accounts** এ একটা অ্যাকাউন্ট বানান, যেমন `noreply@yourdomain.com`।
2. **Connect Devices** থেকে Outgoing Server আর Port দেখে নিন।
3. `.env` এ বসান:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=mail.yourdomain.com
   MAIL_PORT=465
   MAIL_SCHEME=smtps            # পোর্ট 587 হলে এই লাইন মুছে দিন
   MAIL_USERNAME=noreply@yourdomain.com
   MAIL_PASSWORD=ইমেইল_অ্যাকাউন্টের_পাসওয়ার্ড
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="Loon Interior"
   ```
4. Terminal থাকলে `php artisan config:cache` চালান।
5. অ্যাডমিন → Settings → **Notifications** এ যে ঠিকানায় নোটিফিকেশন চান সেটা দিয়ে Save করুন, তারপর **Send Test E-mail** চাপুন।

> Gmail এ ইমেইল Spam এ গেলে cPanel → **Email Deliverability** থেকে SPF আর DKIM ঠিক করুন।

### ধাপ ৬: মাইগ্রেশন ও ডেমো ডাটা

**cPanel এ Terminal থাকলে:**
```bash
cd ~/loon
php artisan migrate --force --seed
php artisan config:cache && php artisan route:cache
```

**Terminal না থাকলে:**
1. `.env` এ একটা গোপন কী দিন, যেমন `INSTALL_KEY=myS3cretKey2026`।
2. ব্রাউজারে একবার যান: `https://yourdomain.com/install/myS3cretKey2026`।
3. কাজ শেষ হলে `.env` থেকে `INSTALL_KEY` এর মান **অবশ্যই মুছে দিন**।

> **আপডেট ডেপ্লয় করলে** (নতুন মডিউল এলে): নতুন ফাইল আপলোড করে আবার `php artisan migrate --force` চালান। Terminal না থাকলে `/install/{key}` লিংকে একবার যান। এতে শুধু নতুন টেবিল যোগ হয়, পুরনো ডেটা থাকে। Business মডিউলের রোল, Head Office শাখা আর VAT নিজে থেকে তৈরি হয়, আর পুরনো অ্যাডমিন Admin রোল পায়।

### ধাপ ৭: পারমিশন
- `storage/` আর `bootstrap/cache/` writable রাখুন (সাধারণত 755)। PDF এর অস্থায়ী ফাইল `storage/app/mpdf-tmp` এ যায়।
- `public/uploads` (বা `public_html/uploads`) writable রাখুন।

কাজ শেষ। `https://yourdomain.com` এ সাইট আর `https://yourdomain.com/admin` এ অ্যাডমিন প্যানেল।

### ধাপ ৭ক: Cron Job

দৈনিক রিমাইন্ডার ইমেইলের জন্য দরকার। cPanel → **Cron Jobs** → "Once Per Minute" বেছে নিয়ে এই কমান্ডটা দিন (`USER` এর জায়গায় আপনার cPanel ইউজারনেম):
```
cd /home/USER/loon && php artisan schedule:run >> /dev/null 2>&1
```
এরপর প্রতিদিন সকাল ৯টায় `business:reminders` নিজে থেকে চলবে। হাতে টেস্ট করতে: `php artisan business:reminders --dry`

**অটো টেস্ট (শুধু লোকালে):** `scripts\tests\` এ প্রতিটা মডিউলের API টেস্ট আছে (যেমন `test-portal.ps1`, `test-hr-labour.ps1`, `test-sms.ps1`, `test-delete.ps1`, `test-marketing.ps1`)। সব একসাথে: `powershell -File scripts\tests\run-all.ps1 -ConfirmWipe`। এটা Business ডেটা মুছে দেয়, তাই localhost ছাড়া চলে না।

### ধাপ ৮: Google এ জমা দেওয়া
- [Google Search Console](https://search.google.com/search-console) এ ডোমেইন যোগ করে `https://yourdomain.com/sitemap.xml` জমা দিন।
- sitemap, canonical আর ইমেইলের লিংক `APP_URL` থেকে তৈরি হয়। তাই `APP_URL` অবশ্যই আসল ডোমেইন (https সহ) হতে হবে।

---

## নিরাপত্তা

- **লগইন আর ফর্ম:** প্রতি মিনিটে সর্বোচ্চ ১০ বার চেষ্টা বা জমা দেওয়া যায়। লগইন আর ফর্মের কাউন্টার আলাদা।
- **স্প্যাম:**
  - honeypot ফিল্ড পূরণ করা বটকে "সফল" দেখানো হয়, কিন্তু কিছু সেভ হয় না।
  - টোকেন ছাড়া, জাল টোকেন দিয়ে বা ৩ সেকেন্ডের কম সময়ে জমা দিলে বাতিল হয়।
- **পাসওয়ার্ড রিসেট:**
  - প্রতি ১০ মিনিটে সর্বোচ্চ ৩টা রিসেট ইমেইল যায়।
  - কোন ইমেইলটা অ্যাডমিনের, উত্তর দেখে তা বোঝা যায় না।
  - রিসেটের পর সব ডিভাইস থেকে লগআউট হয়ে যায়।
  - রিসেট লিংক `APP_URL` থেকে তৈরি হয়, ব্রাউজারের Host হেডার থেকে না।
- **গোপন সেটিং:** নোটিফিকেশন ইমেইল পাবলিক API তে পাঠানো হয় না।
- **অ্যাডমিন API:** Sanctum Bearer টোকেন ছাড়া কাজ করে না। লগআউট করলে টোকেন মুছে যায়।
- **আপলোড:** `uploads` ফোল্ডারে PHP ফাইল চালানো বন্ধ।
- **CV ফাইল:** পাবলিক ফোল্ডারে থাকে না। শুধু লগইন করা অ্যাডমিন ডাউনলোড করতে পারে।
- **`APP_DEBUG=false`:** কোনো এরর হলে কোডের তথ্য ব্রাউজারে দেখায় না।
- **লাইভ করার আগে:**
  - অ্যাডমিন পাসওয়ার্ড বদলান।
  - `INSTALL_KEY` খালি রাখুন।
  - HTTPS (SSL) চালু করুন।

---

## কাস্টমাইজেশন

- **লোগো:**
  - ডেমো লোগো আছে `backend/public/brand/` এ।
  - Settings থেকে নতুন লোগো আপলোড করলে সেটাই দেখাবে।
- **ব্র্যান্ড কালার:** Settings → General → Brand Color থেকে পুরো সাইটের অ্যাকসেন্ট কালার বদলায় (ডিফল্ট গোল্ড `#b08d57`)।
- **ডেমো ছবি:** সিডার Unsplash এর লিংক ব্যবহার করে। অ্যাডমিন থেকে নিজের ছবি দিলে সেগুলো রিপ্লেস হবে।
- **নতুন অ্যাডমিন সেকশন:**
  1. মাইগ্রেশন আর মডেল বানান।
  2. `ResourceRegistry.php` আর `resources.js` এ একটা করে এন্ট্রি দিন।
  3. CRUD পেজ নিজে তৈরি হয়ে যাবে।
- **মেনু:** `frontend/src/components/Header.jsx` আর `Footer.jsx`।
- **আইকন:** তালিকা আছে `frontend/src/lib/icons.jsx` এ।
- **ডিজাইন / রেসপন্সিভ:**
  - পুরো ডিজাইন `site.css` এ।
  - মোবাইলের নিয়ম আছে `@media (max-width: 960px)` আর `@media (max-width: 640px)` ব্লকে।
  - বদলানোর পর আবার build করুন।

---

## API সারসংক্ষেপ

### Public API

| Endpoint | কাজ |
|---|---|
| `GET /api/site` | সেটিংস, মেনুর সার্ভিস/ক্যাটাগরি, ফুটার পেজ |
| `GET /api/home` | হোমপেজের সব সেকশন |
| `GET /api/about` | About পেজের ডেটা |
| `GET /api/services[/{slug}]` | সার্ভিস তালিকা / একটা সার্ভিস |
| `GET /api/projects[?category=]` | প্রজেক্ট তালিকা (ক্যাটাগরি ফিল্টার) |
| `GET /api/projects/{slug}` | একটা প্রজেক্ট |
| `GET /api/posts[/{slug}]` | ব্লগ পোস্ট |
| `GET /api/careers[/{slug}]` | জব পোস্ট |
| `GET /api/pages/{slug}` | ডাইনামিক পেজ |
| `GET /api/faqs` | FAQ |
| `GET /api/testimonials` | Testimonials |
| `POST /api/inquiries` | কোট/কন্টাক্ট ফর্ম (`form_token` + খালি `website` লাগবে) |
| `POST /api/careers/{slug}/apply` | জব আবেদন (multipart, `cv`, `form_token`, `website`) |
| `GET /sitemap.xml`, `GET /robots.txt` | SEO |

`form_token` আসে `GET /api/site` এর রেসপন্স থেকে।

### Admin API (Bearer token)

| Endpoint | কাজ |
|---|---|
| `POST /api/admin/login` | লগইন |
| `POST /api/admin/logout` | লগআউট |
| `GET /api/admin/me` | লগইন করা ইউজারের তথ্য |
| `POST /api/admin/profile` | প্রোফাইল আপডেট |
| `POST /api/admin/forgot-password` | রিসেট লিংক ইমেইল (টোকেন লাগে না) |
| `POST /api/admin/reset-password` | নতুন পাসওয়ার্ড সেট (টোকেন লাগে না) |
| `POST /api/admin/settings/test-mail` | টেস্ট ইমেইল |
| `GET /api/admin/dashboard` | Dashboard |
| `GET/POST /api/admin/settings` | সাইট সেটিংস |
| `GET/POST /api/admin/{resource}` | জেনেরিক CRUD (তালিকা / নতুন যোগ) |
| `GET/POST/DELETE /api/admin/{resource}/{id}` | জেনেরিক CRUD (দেখা / আপডেট / ডিলিট) |
| `GET /api/admin/job_applications/{id}/cv` | CV ডাউনলোড |

**`{resource}` এর মান:**
- কনটেন্ট:
  - `sliders`
  - `services`
  - `project_categories`
  - `projects`
  - `testimonials`
  - `clients`
  - `team_members`
  - `stats`
  - `process_steps`
  - `features`
  - `faqs`
  - `posts`
  - `pages`
  - `careers`
- ফর্ম থেকে আসা ডেটা:
  - `inquiries`
  - `job_applications`
- Business সেটআপ (আলাদা পারমিশন লাগে, মোছা যায় না):
  - `branches` (`branches.manage`)
  - `tax_rates` (`settings.business`)

ওয়েবসাইট কনটেন্টের সব Admin API তে `website.manage` পারমিশন লাগে।

### Business API (Bearer token + পারমিশন)

প্রতিটা রিকোয়েস্টে ঐচ্ছিক হেডার `X-Branch-Id: <id>` বা `X-Branch-Id: all` দেওয়া যায়। না দিলে ইউজারের প্রথম শাখা ধরা হয়।

| Endpoint | পারমিশন | কাজ |
|---|---|---|
| `GET /api/business/permissions` | roles.manage বা users.manage | পারমিশনের ক্যাটালগ |
| `GET/POST /api/business/roles`, `GET/POST/DELETE /api/business/roles/{id}` | roles.manage (তালিকা: users.manage ও) | রোল |
| `GET/POST /api/business/users`, `GET/POST /api/business/users/{id}` | users.manage | স্টাফ ইউজার |
| `GET/POST /api/business/settings` | settings.business | Business Settings |
| `GET /api/business/pdf/sample?lang=en\|bn` | settings.business | নমুনা PDF |
| `GET /api/business/activity-logs` | activity.view | Activity Log (ফিল্টার: action, subject_type, from, to, q) |
| `GET /api/business/lookup/{staff,tax-rates,branches}` | যেকোনো স্টাফ | ফর্মের dropdown |
| `GET /api/business/leads[?view=board]`, `GET leads/{id}`, `GET follow-ups` | crm.view | লিড, পাইপলাইন, ফলো-আপ |
| `POST leads`, `leads/{id}`, `leads/{id}/stage`, `leads/{id}/activities`, `leads/{id}/convert`, `lead-activities/{id}/toggle` | crm.manage | লিড তৈরি/এডিট, ধাপ বদল, টাইমলাইন, ক্লায়েন্ট বানানো |
| `GET/POST /api/business/customers[/{id}]` | crm.view / crm.manage | ক্লায়েন্ট |
| `GET projects[/{id}]`, `POST projects`, `projects/{id}`, `…/milestones`, `…/tasks`, `…/files`, `…/schedule`, `…/publish` | projects.view / projects.manage | প্রজেক্ট |
| `GET invoices[/{id}]`, `invoices/prefill`, `invoices/{id}/pdf`, `payments[/{id}]`, `payments/{id}/pdf`, `receivables` | billing.view | ইনভয়েস, পেমেন্ট, বকেয়া |
| `POST invoices`, `invoices/{id}`, `…/issue`, `…/receive`, `…/credit-note`, `…/cancel`, `…/email`, `payments/{id}/cancel` | billing.manage | ইনভয়েস তৈরি, পেমেন্ট নেওয়া, বাতিল |
| `GET accounts[/{id}]`, `transactions`, `reports/{cashbook,summary,profit-loss,projects,branches}` (`?format=csv`) | accounts.view | অ্যাকাউন্ট, লেজার, রিপোর্ট |
| `POST accounts[/{id}]`, `transactions`, `transactions/transfer`, `transactions/{id}/cancel` | accounts.manage | আয়/ব্যয়, ট্রান্সফার |
| `GET stock`, `stock/items/{id}`, `stock-entries`, `purchase-orders`, `purchases`, `suppliers` | inventory.view | স্টক, ইতিহাস, ক্রয় |
| `POST stores`, `stock-entries`, `purchase-orders`, `purchases`, `…/cancel` | inventory.manage | স্টোর, ইস্যু/ট্রান্সফার, PO, GRN |
| `POST suppliers/{id}/pay`, `supplier-payments/{id}/cancel` | suppliers.pay | সাপ্লায়ার পেমেন্ট |
| `GET tickets`, `tickets/{id}`, `tickets/report` | tickets.view | সার্ভিস টিকেট |
| `POST tickets`, `tickets/{id}`, `tickets/{id}/comments` | tickets.manage | টিকেট খোলা, assign, স্ট্যাটাস, কমেন্ট |
| `POST /api/service-requests` | পাবলিক (form_token) | ওয়েবসাইটের Service Request |
| `GET /api/business/dashboard` | যেকোনো স্টাফ (অংশগুলো পারমিশন অনুযায়ী) | রোলভিত্তিক Dashboard |
| `GET quotations[/{id}]`, `GET quotations/{id}/pdf?lang=en\|bn` | quotations.view | কোটেশন তালিকা, বিস্তারিত, PDF |
| `POST quotations`, `quotations/{id}`, `…/status`, `…/revise`, `…/email` | quotations.manage | তৈরি, এডিট (ড্রাফট), স্ট্যাটাস, রিভিশন, ইমেইল |
| `GET /api/public/quotations/{id}/pdf?…&signature=` | (signed লিংক) | WhatsApp এ শেয়ার করা PDF |
| `GET calendar?from&to&mine` | যেকোনো view পারমিশন | ক্যালেন্ডার |
| `employees`, `attendance`, `leaves`, `payrolls` (+ generate, finalize, pay, unpay, csv, payslip) | hr.view / hr.manage / hr.pay | HR ও বেতন |
| `labour`, `contractors`, `contractors/{id}/pay` | labour.view / labour.manage / labour.pay | শ্রমিক ও ঠিকাদার |
| `POST customers/{id}/portal` | portal.manage | ক্লায়েন্ট পোর্টাল চালু/বন্ধ, পাসওয়ার্ড |
| `POST sms/test`, `GET sms/balance` | settings.business | টেস্ট SMS, Greenweb ব্যালেন্স |
| `DELETE users\|leads\|customers\|quotations\|projects\|accounts\|stores\|purchase-orders\|employees\|leaves\|payrolls\|tickets/{id}` | সংশ্লিষ্ট .manage | Delete (ব্যবহৃত হলে 422 আর কারণ) |
| `GET webmail`, `POST webmail/session` | webmail.use | নিজের মেইলবক্স, এক ক্লিকে cPanel Webmail |
| `campaigns`, `campaigns/{id}` | marketing.view | ক্যাম্পেইন তালিকা ও রিপোর্ট |
| `POST campaigns[/{id}]`, `campaigns/preview`, `…/start`, `…/batch`, `…/retry`, `…/stop`, `DELETE campaigns/{id}` | marketing.send | ক্যাম্পেইন তৈরি ও পাঠানো |
| `GET /api/public/unsubscribe/{lead\|customer}/{id}` | (signed লিংক) | ইমেইল থেকে আনসাবস্ক্রাইব |

### Client Portal API (`/api/portal`, কাস্টমারের টোকেন)

| Endpoint | কাজ |
|---|---|
| `POST login` {login, password} | ফোন/ইমেইল দিয়ে লগইন (10/মিনিট) |
| `GET me`, `POST logout`, `POST password` | প্রোফাইল ও সারাংশ, লগআউট, পাসওয়ার্ড বদল |
| `GET projects[/{id}]` | নিজের প্রজেক্ট, স্টেজ, ছবি |
| `GET invoices`, `invoices/{id}/pdf`, `payments`, `payments/{id}/pdf` | ইনভয়েস আর রিসিট (ড্রাফট/বাতিল দেখায় না) |
| `GET/POST tickets`, `GET tickets/{id}`, `POST tickets/{id}/comments` | সার্ভিস রিকোয়েস্ট |

---

## সমস্যা হলে

| সমস্যা | সমাধান |
|---|---|
| সাইট খালি / সাদা পেজ | `VITE_BASE_PATH` ঠিক আছে কিনা দেখুন, তারপর আবার build করুন |
| ডিজাইন বদলেছি কিন্তু দেখাচ্ছে না | `npm run build` চালিয়ে Ctrl+F5 চাপুন |
| অ্যাডমিনে লগইন করলেই আবার লগইন পেজে ফেরত যায় | সার্ভার Authorization হেডার পাস করছে না। `.htaccess` / `loon.conf` চেক করুন |
| ছবি আপলোড হয় না | `uploads` ফোল্ডার writable কিনা আর `upload_max_filesize` কত দেখুন |
| 500 এরর | `backend/storage/logs/laravel.log` দেখুন |
| নোটিফিকেশন ইমেইল আসে না | Settings → Notifications → Send Test E-mail চাপুন, এরর মেসেজ দেখাবে। `MAIL_MAILER=log` থাকলে ইমেইল শুধু `mail.log` এ লেখা হয় |
| ফর্মে "session has expired" দেখায় | পেজ রিফ্রেশ করে আবার জমা দিন (টোকেন ১২ ঘণ্টা চলে) |
| অ্যাডমিন পাসওয়ার্ড ভুলে গেছি | লগইন পেজে "Forgot password?" চাপুন, অথবা `php artisan admin:password <email>` চালান |
| অন্য ডিভাইস থেকে খোলে না | Firewall রুল চালান ([উপরে দেখুন](#অন্য-ডিভাইস-থেকে-খোলা)) |
| XAMPP চালু হয়নি | `services.msc` এ Apache2.4 আর mysql সার্ভিস Start করুন |
| স্টাফ লগইন করে কোনো মেনু দেখছে না / "No access" দেখায় | Roles & Permissions এ তার রোলে পারমিশন দিন। Users এ তাকে অন্তত একটা সক্রিয় শাখা দিন |
| "Your account is not assigned to any active branch" | Users এ ইউজারকে একটা সক্রিয় শাখা দিন |
| PDF এ লোগো আসছে না | Business Settings → Company → "Logo on documents" এ PNG/JPG লোগো আপলোড করুন |
