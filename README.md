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
| CRUD | ✅ Quizzes, Questions, Attempts, Responses |
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
| `admin` | All teacher permissions + admin panel |
| `teacher` | Create/edit/delete/publish quizzes, view results |
| `student` | Take published quizzes, view own scores |

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
│   └── sanitize.php      ← XSS + input helpers
├── api/
│   ├── quiz.php
│   ├── question.php
│   ├── submit.php        ← grades + broadcasts via Pusher
│   └── logout.php
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── quiz-create.php
│   ├── quiz-take.php
│   └── results.php
└── assets/
    ├── css/style.css
    └── js/realtime.js    ← Pusher JS client
```
