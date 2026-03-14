# Freelancer CRM

Filament-based CRM for solo developers and small agencies who need one place for sales, delivery, time tracking, and invoicing visibility.

## Why This Project

Most generic CRMs are heavy for freelancers. This app is focused on day-to-day work:

- track real billable work,
- keep clean client/project context,
- see what is ready to invoice,
- monitor revenue and workload without spreadsheet chaos.

## Core Features

### Sales & CRM

- Customers with contacts, leads, lead sources, tags, and notes.
- Lead pipeline tracking with configurable statuses.
- Resource forms and relationship managers optimized for daily admin use.

### Work Log & Delivery

- Projects with configurable project statuses (managed from Setup).
- Activities catalog (reusable activity types for projects/time entries).
- Project Activities for:
  - hourly work,
  - one-time work,
  - billable/non-billable tracking,
  - invoiced/non-invoiced lifecycle.
- Topbar **Track time** action with modal-based time entry.

### Billing & Finance

- Hourly rate fallback chain: `project -> customer -> user default`.
- Currency fallback chain: `project -> customer -> user default`.
- Multi-currency workflow (CZK / EUR / USD).
- Recurring Services with configurable service types for predictable revenue.
- Financial dashboard widgets:
  - KPI overview (customizable metric cards),
  - revenue trend,
  - work hours timeline,
  - unbilled done work,
  - overdue activities.

### Setup & Configuration

- Setup area for status dictionaries and operational preferences.
- Drag-and-drop ordering for configurable statuses.
- Guardrails for status deletion when records are still assigned.
- Profile preferences page for user-level defaults.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Filament 5
- Livewire 4
- PostgreSQL (recommended)
- Pest, PHPStan, Pint

## Quick Start (Laravel Sail)

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate --no-interaction
vendor/bin/sail artisan migrate:fresh --seed --no-interaction
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

Admin panel URL: `/admin`  
App root `/` redirects to `/admin`.

Starter seeded account:

- email: `test@example.com`
- password: `password`

## Run The Project

### Prerequisites

- Docker Desktop (or compatible Docker engine)
- Composer

### First Run (from clean clone)

```bash
composer install
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate --no-interaction
vendor/bin/sail artisan migrate:fresh --seed --no-interaction
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

### Daily Run

```bash
# start containers
vendor/bin/sail up -d

# run frontend watcher
vendor/bin/sail npm run dev
```

Open the app and go to `/admin`.

### Stop Environment

```bash
vendor/bin/sail stop
```

## Development Commands

```bash
# code style
vendor/bin/sail bin pint --dirty --format agent

# static analysis
vendor/bin/phpstan analyse --no-progress

# tests
vendor/bin/sail artisan test --compact

# type coverage gate (100%)
vendor/bin/sail composer test:type-coverage
```
