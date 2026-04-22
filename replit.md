# MDD Manager App (PHP)

Personnel management system for the Uganda Police Force. Tracks staff across three branches (Kampala HQ, Rwizi, N.Kyoga), records daily attendance, generates reports with CSV/print exports, and keeps a report history.

## Tech Stack
- PHP 8.2 (plain, no framework)
- SQLite (file: `data/mdd.sqlite`, auto-created and seeded on first request)
- Vanilla CSS / HTML (UPF navy-blue theme, gold accent)
- Built-in PHP web server (`php -S 0.0.0.0:5000 -t public public/router.php`)

## Project Structure
```
public/            Web root (entry points)
  index.php          Dashboard
  login.php          Login
  logout.php
  employees.php      Employee CRUD
  daily-status.php   Record daily attendance
  reports.php        Generate / view current report
  export.php         CSV + print/PDF export
  history.php        Saved report history with details
  router.php         Built-in server router
  assets/            CSS, logo
includes/          Shared PHP (config, db, auth, helpers, header, footer)
data/              SQLite database file (gitignored)
attached_assets/   Original assets
```

## Default Accounts
- `admin` / `admin123` — full access to all branches
- `kmgr` / `kmgr123` — Kampala HQ manager
- `rmgr` / `rmgr123` — Rwizi (Mbarara) manager
- `nmgr` / `nmgr123` — N.Kyoga (Lira) manager

## Features
- Role-based access (admin sees all branches; managers limited to their branch)
- Dashboard with totals + per-branch overview cards (auto-refresh every 30s)
- Employees: search, filter, add/edit/delete
- Daily Status: per-employee status (Present, AWOL, On Leave, Sick) with notes, save in bulk
- Reports: per-date attendance report; export CSV; printable PDF view (browser print)
- History: saved reports with branch breakdown detail view

## Running
The workflow `Start application` runs the PHP built-in server on port 5000.
