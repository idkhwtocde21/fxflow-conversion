# Currency Conversion Platform — System Documentation

## 1. Project Overview

A full-featured currency conversion web platform where users can:

- Convert between currencies using near-real-time exchange rates
- Create an account and view their conversion history
- Save favorite currency pairs
- Set rate alerts and get notified when a target rate is hit
- Be managed by admins through a dedicated dashboard

**Tech Stack**
| Layer | Technology |
|---|---|
| Backend | Laravel 11 (REST API) |
| Frontend | React 18 (Vite) |
| Styling | Tailwind CSS |
| Auth | Laravel Sanctum (SPA token-based auth) |
| Database | MySQL 8 (or PostgreSQL) |
| Queue/Jobs | Laravel Queue (database or Redis driver) |
| Scheduler | Laravel Task Scheduling (cron) |
| Notifications | Laravel Notifications (Mail channel, optional broadcast) |
| Exchange Rate Source | Frankfurter API (https://www.frankfurter.app) — free, no API key required |

---

## 2. Architecture

```
┌─────────────────────┐        REST/JSON (Sanctum SPA auth)      ┌──────────────────────┐
│   React Frontend     │ ───────────────────────────────────────▶ │   Laravel API Backend │
│  (Vite + Tailwind)   │ ◀─────────────────────────────────────── │   (routes/api.php)    │
└─────────────────────┘                                           └──────────┬───────────┘
                                                                               │
                                          ┌────────────────────────────────────┼───────────────────────┐
                                          │                                    │                        │
                                   ┌──────▼───────┐                  ┌────────▼────────┐      ┌────────▼────────┐
                                   │   MySQL DB    │                  │  Scheduled Jobs  │      │  Frankfurter API │
                                   │ (users, rates,│                  │ (fetch rates,    │      │  (external rate  │
                                   │ conversions,  │                  │  check alerts)   │      │   source)        │
                                   │ alerts, etc.) │                  └──────────────────┘      └──────────────────┘
                                   └───────────────┘
```

**Key architectural decisions**

- Laravel serves as a pure API (`api.php` routes), no Blade views except maybe a landing/health page.
- React is a separate SPA (built with Vite), served independently or via Laravel's public folder — recommend running them as two separate dev servers locally, and either two deployments or a single combined deployment in production.
- Authentication uses **Laravel Sanctum** for SPA cookie-based auth (secure, avoids storing JWTs in localStorage).
- Exchange rates are **fetched and cached server-side** on a schedule (e.g. every hour) rather than hitting the external API on every user request — this avoids rate limits and keeps conversions fast.
- Rate alerts are evaluated by a **scheduled job** that runs after each rate refresh and dispatches notifications when a user's target is crossed.

---

## 3. Database Schema

### `users`

| Column                  | Type                 | Notes          |
| ----------------------- | -------------------- | -------------- |
| id                      | bigint, PK           |                |
| name                    | string               |                |
| email                   | string, unique       |                |
| password                | string (hashed)      |                |
| role                    | enum('user','admin') | default 'user' |
| email_verified_at       | timestamp, nullable  |                |
| created_at / updated_at | timestamps           |                |

### `currencies`

| Column    | Type              | Notes                                               |
| --------- | ----------------- | --------------------------------------------------- |
| id        | bigint, PK        |                                                     |
| code      | string(3), unique | e.g. USD, EUR, PHP                                  |
| name      | string            | e.g. "US Dollar"                                    |
| symbol    | string, nullable  | e.g. "$"                                            |
| is_active | boolean           | default true — lets admin enable/disable a currency |

### `exchange_rates`

| Column                  | Type            | Notes                              |
| ----------------------- | --------------- | ---------------------------------- |
| id                      | bigint, PK      |                                    |
| base_currency_id        | FK → currencies |                                    |
| target_currency_id      | FK → currencies |                                    |
| rate                    | decimal(20,10)  |                                    |
| fetched_at              | timestamp       | when this rate snapshot was pulled |
| created_at / updated_at | timestamps      |                                    |

> Index on (`base_currency_id`, `target_currency_id`, `fetched_at`) for fast "latest rate" lookups.

### `conversions`

| Column           | Type                 | Notes                                          |
| ---------------- | -------------------- | ---------------------------------------------- |
| id               | bigint, PK           |                                                |
| user_id          | FK → users, nullable | nullable to allow guest conversions if desired |
| from_currency_id | FK → currencies      |                                                |
| to_currency_id   | FK → currencies      |                                                |
| amount           | decimal(20,4)        | input amount                                   |
| converted_amount | decimal(20,4)        | result                                         |
| rate_used        | decimal(20,10)       | rate applied at time of conversion             |
| created_at       | timestamp            |                                                |

### `saved_pairs` (favorites)

| Column           | Type            | Notes |
| ---------------- | --------------- | ----- |
| id               | bigint, PK      |       |
| user_id          | FK → users      |       |
| from_currency_id | FK → currencies |       |
| to_currency_id   | FK → currencies |       |
| created_at       | timestamp       |       |

> Unique constraint on (`user_id`, `from_currency_id`, `to_currency_id`).

### `rate_alerts`

| Column                  | Type                                   | Notes                                     |
| ----------------------- | -------------------------------------- | ----------------------------------------- |
| id                      | bigint, PK                             |                                           |
| user_id                 | FK → users                             |                                           |
| from_currency_id        | FK → currencies                        |                                           |
| to_currency_id          | FK → currencies                        |                                           |
| target_rate             | decimal(20,10)                         |                                           |
| condition               | enum('above','below')                  | trigger when rate goes above/below target |
| status                  | enum('active','triggered','cancelled') | default 'active'                          |
| triggered_at            | timestamp, nullable                    |                                           |
| created_at / updated_at | timestamps                             |                                           |

### `admin_activity_logs` (optional, for admin dashboard audit trail)

| Column     | Type           | Notes                        |
| ---------- | -------------- | ---------------------------- |
| id         | bigint, PK     |                              |
| admin_id   | FK → users     |                              |
| action     | string         | e.g. "disabled currency PHP" |
| meta       | json, nullable |                              |
| created_at | timestamp      |                              |

---

## 4. API Endpoints

All endpoints prefixed with `/api`. Protected routes require Sanctum auth (`auth:sanctum` middleware).

### Auth

| Method | Endpoint           | Description                  | Auth   |
| ------ | ------------------ | ---------------------------- | ------ |
| POST   | `/register`        | Create account               | Public |
| POST   | `/login`           | Login, returns session/token | Public |
| POST   | `/logout`          | Logout                       | Auth   |
| GET    | `/user`            | Get current user             | Auth   |
| POST   | `/forgot-password` | Send reset link              | Public |
| POST   | `/reset-password`  | Reset password               | Public |

### Currencies & Rates

| Method | Endpoint                                  | Description                       | Auth   |
| ------ | ----------------------------------------- | --------------------------------- | ------ |
| GET    | `/currencies`                             | List active currencies            | Public |
| GET    | `/rates?base=USD`                         | Latest rates for a base currency  | Public |
| GET    | `/rates/history?from=USD&to=PHP&range=7d` | Historical rate data (for charts) | Public |

### Conversion

| Method | Endpoint            | Description                                                          | Auth        |
| ------ | ------------------- | -------------------------------------------------------------------- | ----------- |
| POST   | `/convert`          | Convert amount (from, to, amount) → logs to history if authenticated | Public/Auth |
| GET    | `/conversions`      | Paginated conversion history for logged-in user                      | Auth        |
| DELETE | `/conversions/{id}` | Delete a history entry                                               | Auth        |

### Favorites

| Method | Endpoint          | Description      | Auth |
| ------ | ----------------- | ---------------- | ---- |
| GET    | `/favorites`      | List saved pairs | Auth |
| POST   | `/favorites`      | Save a pair      | Auth |
| DELETE | `/favorites/{id}` | Remove a pair    | Auth |

### Rate Alerts

| Method | Endpoint       | Description                                     | Auth |
| ------ | -------------- | ----------------------------------------------- | ---- |
| GET    | `/alerts`      | List user's alerts                              | Auth |
| POST   | `/alerts`      | Create alert (from, to, target_rate, condition) | Auth |
| PUT    | `/alerts/{id}` | Update alert                                    | Auth |
| DELETE | `/alerts/{id}` | Cancel alert                                    | Auth |

### Admin (middleware: `role:admin`)

| Method | Endpoint                 | Description                                               | Auth  |
| ------ | ------------------------ | --------------------------------------------------------- | ----- |
| GET    | `/admin/users`           | List/search users                                         | Admin |
| PUT    | `/admin/users/{id}`      | Update user (role, status)                                | Admin |
| DELETE | `/admin/users/{id}`      | Delete/ban user                                           | Admin |
| GET    | `/admin/currencies`      | List all currencies incl. inactive                        | Admin |
| PUT    | `/admin/currencies/{id}` | Enable/disable currency                                   | Admin |
| GET    | `/admin/stats`           | Dashboard stats (total users, conversions, active alerts) | Admin |
| GET    | `/admin/logs`            | Activity logs                                             | Admin |

---

## 5. Backend Logic Details

### Rate fetching (scheduled job)

- Artisan command: `php artisan rates:fetch`
- Scheduled hourly in `routes/console.php` (Laravel 11) via `Schedule::command('rates:fetch')->hourly();`
- Fetches latest rates from Frankfurter API for all active currency pairs (or fetches base→all in one call, since Frankfurter supports that).
- Stores a new row per pair in `exchange_rates` each run — this naturally builds historical data for charts.
- Wrap the external call in a try/catch; on failure, keep serving the last known rate and log the error (don't break conversions if the API is briefly down).

### Conversion endpoint logic

1. Validate `from`, `to`, `amount`.
2. Look up latest rate for the pair from `exchange_rates` (or compute cross-rate via a common base like USD/EUR if a direct pair isn't cached).
3. Calculate `converted_amount = amount * rate`.
4. If user is authenticated, insert a `conversions` row.
5. Return the result + rate + timestamp of the rate used.

### Alert checking (scheduled job)

- Artisan command: `php artisan alerts:check`
- Scheduled to run right after `rates:fetch` (e.g. `->hourly()->after(...)` or just sequenced a minute later).
- For each active alert, compare current rate against `target_rate` using the `condition` field.
- On match: mark `status = 'triggered'`, set `triggered_at`, and dispatch a `RateAlertTriggered` notification (Mail channel; can add database/broadcast channel later for in-app notifications).

### Admin authorization

- Use a simple `role` column check via a Laravel Policy or middleware (`EnsureUserIsAdmin`).
- Consider `spatie/laravel-permission` package if roles/permissions grow more complex later.

---

## 6. Frontend (React) Structure

```
src/
├── api/
│   ├── axios.js              # axios instance with baseURL + withCredentials for Sanctum
│   ├── auth.js
│   ├── currencies.js
│   ├── conversions.js
│   ├── favorites.js
│   └── alerts.js
├── components/
│   ├── ui/                   # buttons, inputs, modals (Tailwind-based)
│   ├── ConverterWidget.jsx
│   ├── CurrencySelect.jsx
│   ├── RateChart.jsx
│   ├── AlertForm.jsx
│   └── Navbar.jsx
├── pages/
│   ├── Home.jsx               # main converter
│   ├── Login.jsx
│   ├── Register.jsx
│   ├── Dashboard.jsx          # user's history, favorites, alerts
│   ├── History.jsx
│   ├── Favorites.jsx
│   ├── Alerts.jsx
│   └── admin/
│       ├── AdminDashboard.jsx
│       ├── AdminUsers.jsx
│       └── AdminCurrencies.jsx
├── context/
│   └── AuthContext.jsx        # holds current user, login/logout methods
├── hooks/
│   ├── useAuth.js
│   └── useRates.js
├── routes/
│   └── AppRoutes.jsx           # react-router-dom setup, protected routes
├── App.jsx
└── main.jsx
```

**Key frontend notes**

- Use `react-router-dom` for routing; wrap admin routes in a `RequireAdmin` guard and authed routes in a `RequireAuth` guard.
- Use `axios` with `withCredentials: true` and Sanctum's CSRF cookie flow (`/sanctum/csrf-cookie` before login).
- Tailwind for all styling — no separate CSS files beyond `index.css` with Tailwind directives.
- Consider `react-query` (TanStack Query) for data fetching/caching of rates and history — keeps UI in sync without manual state juggling.
- Charting library (e.g. `recharts` or `chart.js`) for the historical rate graph.

---

## 7. Non-Functional Requirements

- **Security**: CSRF protection via Sanctum, hashed passwords, rate-limit auth endpoints (`throttle` middleware), validate all input server-side, sanitize admin actions with logging.
- **Performance**: cache latest rates (Laravel cache, e.g. `Cache::remember`) to avoid hitting DB on every conversion request.
- **Reliability**: rate-fetch job should be idempotent and fail gracefully if the external API is down.
- **Testing**: Feature tests for auth, conversion, alerts (Laravel's built-in PHPUnit/Pest); component tests for React (Vitest + React Testing Library) are a nice-to-have.
- **Environment config**: `.env` should hold `EXCHANGE_RATE_API_URL`, mail credentials, DB credentials, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`.

---

## 8. Suggested Build Order (for vibe-coding in phases)

1. **Scaffold**: Laravel project + Sanctum setup, React (Vite) project + Tailwind config, CORS/Sanctum stateful domains configured so the two can talk locally.
2. **Auth**: register/login/logout end-to-end (API + React forms + AuthContext).
3. **Currencies & rates**: migrations/seeders for `currencies`, the `rates:fetch` command, `/currencies` and `/rates` endpoints, and the basic converter UI.
4. **Conversion + history**: `/convert` endpoint, `conversions` table, history page.
5. **Favorites**: saved pairs CRUD + UI.
6. **Alerts**: `rate_alerts` table, `alerts:check` command, notification email, alerts UI.
7. **Admin dashboard**: user management, currency management, stats, activity logs.
8. **Polish**: loading states, error handling, empty states, responsive design, rate charts.

---

## 9. Open Assumptions (adjust as needed)

- Using Frankfurter (free, no key) as the rate source — swap to Open Exchange Rates or Fixer later if more currencies/precision are needed.
- MySQL as the database; PostgreSQL works identically with Laravel if preferred.
- Notifications limited to email for now; in-app/browser push can be added later via broadcasting (Pusher/Reverb).
- Guest users can convert but not save history, favorites, or alerts — those require login.
