# Freelancer CRM

Freelancer CRM is a self-hosted Laravel and Filament application for freelancers and owner-operated service businesses who want one admin panel for leads, customers, projects, work logs, recurring services, and operational finance visibility.

The project is intentionally focused on single-owner workflows. It already covers the core CRM and delivery loop well, but it is still an MVP and should be evaluated as such.

## Why It Exists

Most CRMs are too broad for freelancer operations, while many time trackers and invoicing tools only solve one slice of the workflow. This project combines the parts that commonly end up scattered across notes, spreadsheets, timers, and ad hoc dashboards:

- pipeline and lead tracking,
- client and project management,
- work logging and billable tracking,
- recurring services,
- operational reporting for a single owner.

## What Is Implemented Today

### Sales and CRM

- Customers, client contacts, notes, tags, and related projects.
- Leads with pipeline stages, statuses, estimated value, and lead sources.
- Filament resources for customers, contacts, leads, lead sources, notes, and tags.

### Delivery and Time Tracking

- Projects with customizable status workflows and pricing models.
- Reusable activity catalog for work types.
- Work logs for hourly and one-time work.
- Billable, non-billable, invoiced, and done-state tracking.
- Topbar quick-action time tracker for creating manual entries or starting a running timer.
- Backlog items with a direct "convert to worklog" action.

### Billing and Finance

- Hourly rate fallback chain verified in tests: `worklog -> project -> customer -> user default`.
- Currency fallback chain verified in tests for projects and recurring services.
- Built-in FX configuration for CZK, EUR, and USD display.
- Recurring services and recurring service types for retainer-like work.
- Dashboard widgets for KPIs, revenue trend, work hours, unbilled done work, upcoming revenue, and overdue activities.

### Personalization and Setup

- User-configurable status dictionaries for projects and work logs.
- Reorderable setup resources for statuses, tags, activities, lead sources, and recurring service types.
- Safety checks before deleting statuses that are still in use.
- User settings for locale, timezone, week start, date format, time format, and rounding preferences.
- Multi-tenant data ownership enforced per authenticated user.

## Included Admin Resources

The admin panel currently exposes these resource areas:

- Work Log: projects, worklogs, backlog items
- Sales: leads
- CRM: customers, client contacts, recurring services, notes
- Setup: activities, tags, lead sources, recurring service types, project statuses, worklog statuses, user settings

The dashboard is customizable per user and stores the selected metric cards in user data.

## Current Scope and Limitations

The project already handles CRM, delivery tracking, and recurring revenue context, but some areas are intentionally not implemented yet:

- no invoice model, invoice PDF generation, or accounting export pipeline,
- no public REST or API surface,
- no role and permission system beyond owner-scoped data isolation,
- no background notification workflow for recurring reminders,
- test coverage exists for core domain logic and basic panel access, but not full UI behavior.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Filament 5
- Livewire 4
- Tailwind CSS 4
- Pest 4
- PHPStan, Rector, Pint
- Laravel Sail for local development

## Project Status

The project is usable today and tagged with an initial MVP release: `v0.1.0`.

Current state:

- domain model and main Filament resources are in place,
- starter seed data is included,
- domain tests exist for financial resolution, cadence logic, backlog conversion, and user defaults,
- the project is still early and should be treated as a strong self-hosted base rather than a finished product.

## Quick Start

### Prerequisites

- Docker Desktop or a compatible Docker engine
- Composer
- Node.js is not required on the host when using Sail, but it can help for local tooling

### Installation

```bash
composer install
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate --no-interaction
vendor/bin/sail artisan migrate:fresh --seed --no-interaction
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

The application root redirects to `/admin`.

Starter seeded account:

- email: `test@example.com`
- password: `password`

The default seeder also creates demo lead sources, customers, contacts, projects, activities, recurring services, tags, notes, and leads.

### Daily Development

```bash
vendor/bin/sail up -d
vendor/bin/sail npm run dev
```

Optional all-in-one development command:

```bash
vendor/bin/sail composer run dev
```

### Stopping The Environment

```bash
vendor/bin/sail stop
```

## Quality Checks

Run all commands through Sail.

```bash
# run tests
vendor/bin/sail artisan test --compact

# run static analysis only
vendor/bin/sail composer run lint:static

# run full lint pipeline
vendor/bin/sail composer run lint

# format modified PHP files
vendor/bin/sail bin pint --dirty --format agent

# enforce type coverage gate
vendor/bin/sail composer run test:type-coverage
```

Current automated tests cover domain behaviors such as backlog conversion, financial fallback resolution, recurring cadence handling, user provisioning defaults, rounding preferences, currency conversion, and basic admin panel access.

## Architecture Notes

- The app is built around customers, projects, worklogs, leads, recurring services, backlog items, notes, tags, and user settings.
- Most domain models are scoped to the authenticated owner via the `EnforcesOwner` trait.
- Filament resources follow a split structure with `Pages`, `Schemas`, and `Tables` subdirectories.
- Setup resources are grouped under a dedicated Configuration cluster inside the Setup navigation group.
- Laravel Sail is the expected local runtime.
- The root route redirects to the Filament admin panel.

## Localization and Preferences

- Interface locale support is currently limited to English and Czech.
- User preferences store locale, timezone, week start, date format, time format, and time-rounding behavior.

## Who This Is For

This repository is a good fit if you are:

- a freelancer who wants a self-hosted CRM tailored to actual billable work,
- an owner who wants client, delivery, and billing context in one place,
- a Laravel developer looking for a Filament-based CRM foundation to extend.

It is likely a poor fit if you need:

- enterprise CRM workflows,
- a multi-company SaaS with advanced team permissions,
- invoice generation and accounting exports out of the box,
- a polished public API surface today.

## Contributing

Contributions are welcome. Start with [CONTRIBUTING.md](CONTRIBUTING.md).

Before opening a pull request:

- discuss large changes first,
- keep changes focused,
- add or update tests,
- run the required quality checks locally.

## Community Standards

- Code of conduct: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- Security policy: [SECURITY.md](SECURITY.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- License: [LICENSE](LICENSE)

## Potential Roadmap

These are likely next-step areas, not promises or committed milestones:

- invoice-oriented workflows built on top of existing invoiced flags and references,
- broader reporting across customers, projects, and recurring revenue,
- stronger UI and resource-level automated test coverage,
- import and export flows for spreadsheet-based migration,
- public-facing docs polish, screenshots, and demo evaluation material.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
