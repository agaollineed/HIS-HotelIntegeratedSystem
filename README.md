<!--
  README.md for nyamanapp/
  Sections marked VERIFY are assumptions — check them against the repo before committing.
-->

<div align="center">

<img src="https://capsule-render.vercel.app/api?type=rect&color=0:0d0716,50:1a0f2e,100:0d0716&height=170&section=header&text=NyamanApp&fontSize=54&fontColor=ffffff&fontAlignY=42&desc=Kelola%20Penginapan%20dalam%20Satu%20Aplikasi&descAlignY=66&descSize=16" width="100%" alt="NyamanApp" />

**Hotel management system for daily operations — stock, room occupancy, and reporting in one place.**

<p>
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="laravel" />
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="php" />
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="mysql" />
  <img src="https://img.shields.io/badge/Blade-No%20Build%20Step-7C3AED?style=for-the-badge&logo=laravel&logoColor=white" alt="blade" />
  <img src="https://img.shields.io/badge/cPanel-Deployed-FF6C2C?style=for-the-badge&logo=cpanel&logoColor=white" alt="cpanel" />
</p>

<p>
  <img src="https://img.shields.io/badge/status-in%20production-7C3AED?style=flat-square&labelColor=0d0716" alt="status" />
  <img src="https://img.shields.io/badge/roles-admin%20%C2%B7%20staff-A78BFA?style=flat-square&labelColor=0d0716" alt="roles" />
  <img src="https://img.shields.io/badge/region-Magetan%2C%20ID-A78BFA?style=flat-square&labelColor=0d0716" alt="region" />
</p>

</div>

---

## 📖 Overview

NyamanApp is a Laravel-based operational system for a hotel in Magetan, East Java. It replaces the paper-and-spreadsheet workflow that front-desk staff used for bookings, stock, and attendance with a single web application that admins can monitor in real time.

The application runs on two roles with deliberately different surfaces:

- **Staff** — record what happens during the shift: bookings, stock movement, attendance.
- **Admin** — monitor, approve, and pull reports across all of it.

Everything is written server-side with Blade. There is no JavaScript build step, which keeps deployment on shared cPanel hosting to a single upload.

<div align="center">
  <img src="./docs/screenshot-landing.png" alt="NyamanApp landing" width="100%" />
</div>

---

## ✨ Features

### Booking & Rooms
- Room availability calendar with a shared `HandlesRoomCalendar` trait, so admin and staff views never drift apart
- Bookings grouped into a *pesanan*, with down payment and discount applied per booking group rather than per room row
- Discount nominal is recalculated server-side at save time — browser-sent values are ignored to block tampering
- Availability is re-checked at **approval** time, not only at submission, so two pending requests cannot double-book a room

### Attendance (Absensi)
- Shift scheduling (*jadwal*) linked to attendance records
- Selfie-based check-in, stored on a private disk and served only through an authenticated controller

### Stock & Reporting
- Daily stock and occupancy recording by staff
- Admin-side daily reports consolidating stock, occupancy, and bookings <!-- VERIFY -->

### Security
- All role checks route through methods on the `User` model — a single source of truth instead of scattered checks across views, controllers, and routes
- Uploaded documents (ID cards, attendance selfies, payment proofs) never sit in `public/`. They are stored on the `local` disk and streamed with `Cache-Control: private, no-store`

---

## 🧰 Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.3 |
| Views | Blade (server-rendered, no Node.js / Vite) |
| Database | MySQL 8 in production, SQLite locally |
| Storage | Laravel filesystem, private `local` disk for sensitive uploads |
| Hosting | Shared cPanel hosting (Rumahweb) |
| Dev environment | macOS, VS Code, `php artisan serve` |

---

## 🚀 Getting Started

### Requirements

- PHP 8.3 or higher
- Composer 2
- MySQL 8 (or SQLite for local development)

> There is no `npm install` step. Assets are plain CSS and Blade, so skip any Node tooling entirely.

### Installation

```bash
# 1. Clone and enter the app directory
git clone https://github.com/agaollineed/HIS-HotelIntegeratedSystem-.git
cd HIS-HotelIntegeratedSystem-/nyamanapp

# 2. Install PHP dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Configure the database in .env, then migrate
php artisan migrate

# 5. Run
php artisan serve
```

Open `http://localhost:8000` and pick a role from the landing page.

---

