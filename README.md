# Quiz Generator — NEUST Case Study
**ITWS03 / ITWS04 / ITWS05 | AY 2025-2026 S2**

## Tech Stack
- PHP 8.1+ · XAMPP · MySQL · Pusher SDK (WebSockets)

---

## Setup Instructions

### 1. Hosts file configuration
Edit your system `hosts` file and add:
```
127.0.0.1   casestudy
```
- **Windows**: `C:\Windows\System32\drivers\etc\hosts`
- **Mac/Linux**: `/etc/hosts`

### 2. Clone & place project
```bash
git clone <your-repo-url> C:/xampp/htdocs/quiz-generator
```

### 3. Configure environment
```bash
cd C:/xampp/htdocs/quiz-generator
cp .env.example .env
```
Edit `.env` and fill in your Pusher credentials (from [pusher.com](https://pusher.com)) and DB settings.

### 4. Install Pusher PHP SDK
```bash
composer install
```
> If Composer is not installed: https://getcomposer.org

### 5. Import the database
1. Start XAMPP → start **Apache** and **MySQL**
2. Open `http://casestudy/phpmyadmin`
3. Create database `quiz_generator`
4. Import `database.sql`

### 6. Hosts file port config
In `config/database.php` the port defaults to `DB_PORT` in `.env`.
Set `DB_PORT` to any value in **3306–3310** per the case study specs.

### 7. Run the app
Visit: `http://casestudy/quiz-generator/`

---

## Features Checklist

| Requirement | Implementation |
|---|---|
| PHP + XAMPP | ✅ Full-stack PHP |
| Hostname `casestudy` | ✅ `DB_HOST=casestudy` in `.env` |
| Port 3306–3310 | ✅ `DB_PORT` in `.env` |
| WebSockets (Pusher) | ✅ Real-time submission broadcast |
| CRUD | ✅ Full CRUD — see matrix below |
| Admin panel | ✅ User management + audit log viewer |
| Stored Procedures | ✅ `sp_submit_attempt`, `sp_get_quiz_results`, `sp_get_leaderboard` |
| Trigger Functions | ✅ `trg_quiz_after_insert/update/delete`, `trg_attempt_after_update` |
| `.env` file | ✅ All credentials centralized |
| Bcrypt/Argon2 | ✅ `password_hash()` with `PASSWORD_BCRYPT`, cost 12 |
| CSRF Protection | ✅ Anti-forgery tokens on all POST requests |
| SQL Injection | ✅ PDO prepared statements everywhere |
| XSS Prevention | ✅ `htmlspecialchars()` on all output |
| Input Sanitization | ✅ Centralized in `includes/sanitize.php` |
| Public GitHub repo | ⬜ Push your code + `.env.example` (never `.env`) |

---

## Roles
| Role | Permissions |
|---|---|
| `admin` | All teacher permissions + user CRUD + audit log |
| `teacher` | Create/edit/delete/publish quizzes, manage questions, view results |
| `student` | Take published quizzes, view attempt history |

Default admin login (from seed): **admin** / **Admin@1234**

---

## CRUD Matrix

| Entity | Create | Read | Update | Delete |
|--------|--------|------|--------|--------|
| Users | Register, Admin panel | Login, Admin list | Admin panel | Admin panel |
| Quizzes | `quiz-create.php` | Dashboard | `quiz-create.php` | Dashboard → API |
| Questions | `api/question.php` | `quiz-create.php` | `api/question.php` + modal | `api/question.php` |
| Attempts | `quiz-take.php` | `my-attempts.php`, `results.php` | Graded on submit | — |
| Audit log | DB triggers | `admin/audit-log.php` | — | — |

---

## Key URLs

| Page | URL |
|------|-----|
| Dashboard | `/pages/dashboard.php` |
| My Attempts | `/pages/my-attempts.php` |
| Quiz Editor | `/pages/quiz-create.php` |
| Admin — Users | `/pages/admin/users.php` |
| Admin — Audit Log | `/pages/admin/audit-log.php` |

---

## Testing Checklist

1. **Teacher:** Create quiz → add questions → edit question (text/choices) → delete question → delete quiz from dashboard
2. **Teacher:** Cannot change question type after a student has submitted a response to that question
3. **Admin:** Create/edit/delete users; cannot delete self or the last admin
4. **Admin:** Audit log shows quiz and submission events after actions
5. **Student:** `my-attempts.php` lists scores; dashboard retake still works
6. **Security:** CSRF rejected on API calls without token; output escaped with `e()`

---

## Security Notes
- `.env` is in `.gitignore` — **never commit it**
- `.env.example` shows key names only (no values)
- Pusher keys are read server-side; the JS client only receives the **App Key** (public) and **cluster** — never the secret
- All database queries use PDO prepared statements
- All user output is escaped with `htmlspecialchars()`
- CSRF tokens validated on every POST endpoint
- Passwords hashed with Bcrypt (cost 12)
- Session cookies: `httponly`, `samesite=Strict`

---

## File Structure
```
quiz-generator/
├── .env.example          ← commit this
├── .env                  ← DO NOT commit
├── composer.json
├── database.sql          ← schema + stored procedures + triggers
├── index.php
├── config/
│   ├── env.php
│   ├── database.php      ← PDO singleton
│   └── pusher.php        ← Pusher server init
├── includes/
│   ├── auth.php          ← Bcrypt login/register/session
│   ├── csrf.php          ← CSRF token generate + verify
│   ├── sanitize.php      ← XSS + input helpers
│   ├── layout.php        ← Shared header/footer/nav
│   ├── nav.php           ← Role-aware navigation
│   └── flash.php         ← Session flash messages
├── api/
│   ├── quiz.php
│   ├── question.php      ← add / update / delete
│   ├── user.php          ← admin user CRUD
│   ├── submit.php        ← grades + broadcasts via Pusher
│   └── logout.php
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── quiz-create.php
│   ├── quiz-take.php
│   ├── my-attempts.php
│   ├── results.php
│   └── admin/
│       ├── users.php
│       ├── user-form.php
│       └── audit-log.php
└── assets/
    ├── css/app.css       ← Shared application styles
    └── js/realtime.js    ← Pusher JS client
```
