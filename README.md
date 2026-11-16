# Loon Interior: ওয়েবসাইট + অ্যাডমিন প্যানেল

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

### অ্যাডমিন প্যানেল (`/admin`)

**প্রধান অংশ:**
- **Dashboard:** মোট সংখ্যা, নতুন কোট রিকোয়েস্ট, সাম্প্রতিক জব অ্যাপ্লিকেশন।
- **Quote Requests:** স্ট্যাটাস আর নোট, সরাসরি কল / WhatsApp বাটন।
- **Job Applications:** স্ট্যাটাস বদলানো, CV ডাউনলোড।
- **Site Settings:** লোগো, ফেভিকন, ব্র্যান্ড কালার, কন্টাক্ট, সোশ্যাল লিংক, হোমপেজের সব লেখা, About পেজ, SEO।
- **My Profile:** নাম, ইমেইল, পাসওয়ার্ড পরিবর্তন।

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
- এই এক্সটেনশনগুলো চালু রাখুন: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, bcmath, gd, zip`।
- বড় ছবি আপলোডের জন্য `upload_max_filesize = 10M` আর `post_max_size = 64M` দিন।

### ধাপ ৩: ফাইল আপলোড

আগে রুট ডোমেইনের জন্য build করুন ([উপরে দেখুন](#প্রোডাকশন-বিল্ড))। এরপর `backend` ফোল্ডার zip করে **File Manager** দিয়ে আপলোড করে extract করুন। `.env` আর `vendor/` সহ আপলোড করবেন, `node_modules` লাগবে না।

**পদ্ধতি A (Recommended), Document Root বদলানো:**
1. প্রজেক্ট রাখুন `/home/USER/loon` এ।
2. cPanel → **Domains** এ গিয়ে ডোমেইনের Document Root দিন `/home/USER/loon/public`।

**পদ্ধতি B, public_html ব্যবহার করে (Document Root বদলানো না গেলে):**
1. পুরো প্রজেক্ট রাখুন `/home/USER/loon` এ, `public_html` এর **বাইরে**।
2. `loon/public/` এর **ভেতরের সব ফাইল** `public_html/` এ কপি করুন: `index.php`, `.htaccess`, `build/`, `brand/`, `uploads/`, `robots.txt`। Hidden ফাইল দেখতে File Manager → Settings → *Show Hidden Files* চালু করুন।
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
APP_KEY=base64:...........        # লোকালে `php artisan key:generate --show` চালিয়ে কপি করুন
DB_HOST=localhost
DB_DATABASE=cpaneluser_dbname
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=********
ADMIN_EMAIL=admin@looninterior.com
ADMIN_PASSWORD=শক্ত_নতুন_পাসওয়ার্ড
```

### ধাপ ৫: মাইগ্রেশন ও ডেমো ডাটা

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

### ধাপ ৬: পারমিশন
- `storage/` আর `bootstrap/cache/` writable রাখুন (সাধারণত 755)।
- `public/uploads` (বা `public_html/uploads`) writable রাখুন।

কাজ শেষ। `https://yourdomain.com` এ সাইট আর `https://yourdomain.com/admin` এ অ্যাডমিন প্যানেল।

---

## নিরাপত্তা

- **লগইন আর ফর্ম:** প্রতি মিনিটে সর্বোচ্চ ১০ বার চেষ্টা বা জমা দেওয়া যায়।
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
| `POST /api/inquiries` | কোট/কন্টাক্ট ফর্ম |
| `POST /api/careers/{slug}/apply` | জব আবেদন (multipart, `cv`) |

### Admin API (Bearer token)

| Endpoint | কাজ |
|---|---|
| `POST /api/admin/login` | লগইন |
| `POST /api/admin/logout` | লগআউট |
| `GET /api/admin/me` | লগইন করা ইউজারের তথ্য |
| `POST /api/admin/profile` | প্রোফাইল আপডেট |
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

---

## সমস্যা হলে

| সমস্যা | সমাধান |
|---|---|
| সাইট খালি / সাদা পেজ | `VITE_BASE_PATH` ঠিক আছে কিনা দেখুন, তারপর আবার build করুন |
| ডিজাইন বদলেছি কিন্তু দেখাচ্ছে না | `npm run build` চালিয়ে Ctrl+F5 চাপুন |
| অ্যাডমিনে লগইন করলেই আবার লগইন পেজে ফেরত যায় | সার্ভার Authorization হেডার পাস করছে না। `.htaccess` / `loon.conf` চেক করুন |
| ছবি আপলোড হয় না | `uploads` ফোল্ডার writable কিনা আর `upload_max_filesize` কত দেখুন |
| 500 এরর | `backend/storage/logs/laravel.log` দেখুন |
| অন্য ডিভাইস থেকে খোলে না | Firewall রুল চালান ([উপরে দেখুন](#অন্য-ডিভাইস-থেকে-খোলা)) |
| XAMPP চালু হয়নি | `services.msc` এ Apache2.4 আর mysql সার্ভিস Start করুন |
