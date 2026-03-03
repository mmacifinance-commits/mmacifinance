# Physical Architecture

> Three-tier deployment architecture showing the Presentation, Application, and Data tiers.

```mermaid
flowchart TB

    %% ── Tier 1: Presentation ──────────────────────────────────────
    subgraph T1 ["Tier 1 — Presentation Layer (Client)"]
        direction LR
        BR1["💻 Desktop Browser\n(Chrome / Firefox / Edge)"]
        BR2["📱 Mobile Browser\n(Responsive / Tailwind CSS)"]
    end

    %% ── Tier 2: Application ───────────────────────────────────────
    subgraph T2 ["Tier 2 — Application Layer (Web Server)"]
        direction TB

        subgraph WEB ["Web Server"]
            APACHE["Apache 2.4\n(Laragon)\nPort 80 / 443"]
        end

        subgraph APP ["Laravel Application (PHP 8.2)"]
            ROUTER["Laravel Router\n(routes/web.php)"]
            MIDDLEWARE["Middleware Stack\n• HandleInertiaRequests\n• CheckRole (RBAC)\n• auth"]
            CONTROLLERS["Controllers\n• DashboardController\n• AnnualBudgetController\n• ExpenseController\n• DisbursementController\n• Auth: Login + TwoFactor"]
            MODELS["Eloquent Models\n• User  • AnnualBudget\n• BudgetItem  • BudgetCategory\n• BudgetParticular  • Department\n• Expense  • Disbursement"]
            INERTIA["Inertia.js Bridge\n(Server-Side Adapter)"]
        end

        subgraph FRONTEND ["Frontend Assets (compiled by Vite)"]
            VITE_ASSETS["Vue 3 SPA Bundles\n• Pages/**/*.vue\n• Layouts/AppLayout.vue\n• Components/Modal.vue\n(Tailwind CSS)"]
        end

        subgraph WORKER ["Background Process"]
            QUEUE["Queue Worker\nphp artisan queue:listen\n(OTP mail dispatch)"]
        end
    end

    %% ── Tier 3: Data ──────────────────────────────────────────────
    subgraph T3 ["Tier 3 — Data Layer"]
        direction LR

        subgraph DB ["Primary Database"]
            MYSQL["MySQL 8.0 / SQLite\nDomain Tables:\n• users  • departments\n• budget_categories\n• budget_particulars\n• annual_budgets\n• budget_items\n• expenses  • disbursements"]
        end

        subgraph DBSYS ["Laravel System Tables"]
            SYS["• sessions\n• cache / cache_locks\n• jobs / failed_jobs"]
        end
    end

    %% ── External ──────────────────────────────────────────────────
    subgraph EXT ["External Services"]
        SMTP["📧 SMTP Mail Server\n(OTP Email Delivery)\ne.g. Gmail / Mailgun /\nInternal Relay"]
    end

    %% ── Flow ──────────────────────────────────────────────────────
    BR1 & BR2 <-->|"HTTP/HTTPS Request\n(Inertia XHR / Full Page)"| APACHE
    APACHE --> ROUTER
    ROUTER --> MIDDLEWARE
    MIDDLEWARE --> CONTROLLERS
    CONTROLLERS <--> MODELS
    CONTROLLERS --> INERTIA
    INERTIA -->|"HTML + JSON props"| APACHE
    APACHE -->|"Static JS/CSS bundles"| VITE_ASSETS
    VITE_ASSETS -->|"Vue hydrates in browser"| BR1 & BR2

    MODELS <-->|"Eloquent ORM\nPDO Queries"| MYSQL
    MODELS <-->|"Session / Cache / Jobs"| SYS

    CONTROLLERS -->|"dispatch mail job"| QUEUE
    QUEUE -->|"SMTP"| SMTP
    SMTP -->|"OTP email"| BR1 & BR2
```

## Component Responsibilities

| Component | Technology | Responsibility |
|---|---|---|
| Browser Client | Any modern browser | Renders Vue 3 SPA pages; sends Inertia XHR requests on navigation |
| Apache Web Server | Apache 2.4 (Laragon) | Accepts HTTP/S connections; routes all requests to Laravel's `public/index.php` |
| Laravel Router | Laravel 12 | Maps URLs to controller methods; applies middleware groups |
| Middleware Stack | Laravel + Custom | `HandleInertiaRequests` shares global props (auth, permissions, flash); `CheckRole` enforces RBAC |
| Controllers | PHP 8.2 | Fetch data via Eloquent, run validation, return `Inertia::render()` responses |
| Eloquent Models | Laravel ORM | Define schema relationships, casts, and helper methods |
| Inertia.js Bridge | inertiajs/inertia-laravel | Converts controller responses to JSON props for the Vue frontend on XHR; returns full HTML on first load |
| Vue 3 Pages | @inertiajs/vue3 + Vite | SPA pages compiled by Vite; `AppLayout.vue` wraps all authenticated pages |
| Queue Worker | Laravel Queue | Processes mail jobs from the `jobs` table; sends OTP emails via SMTP asynchronously |
| Database | MySQL 8.0 / SQLite | Persists all application data; also stores sessions, cache, and queue jobs |
| SMTP Mail Server | Configurable | Delivers OTP emails; configured in `.env` via `MAIL_*` variables |

## Data Flow Summary

```
Browser  ──POST /login──►  Laravel  ──validate──►  DB (users)
                                    ──generate OTP──►  DB (users.otp_code)
                                    ──dispatch job──►  Queue Worker  ──►  SMTP  ──►  User Email
Browser  ──POST /2fa/verify──►  Laravel  ──check OTP──►  DB
                                         ──Auth::login()──►  Session (DB)
Browser  ──GET /annual-budgets──►  Inertia  ──props──►  Vue Page renders
```
