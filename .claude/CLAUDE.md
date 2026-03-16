# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A personal CRM for freelancers/agencies built with Laravel 12, Filament v5, and Livewire v4. Tracks clients, projects, time/worklogs, leads, recurring services, and financials across multiple currencies. All data is multi-tenant, scoped to the authenticated user via the `EnforcesOwner` trait.

## Commands

All commands run through Laravel Sail (`vendor/bin/sail`).

```bash
vendor/bin/sail up -d                                     # Start containers
vendor/bin/sail composer run dev                          # Server + queue + logs + vite
vendor/bin/sail artisan test --compact                    # Run all tests
vendor/bin/sail artisan test --compact --filter=testName  # Run specific test
vendor/bin/sail composer run lint                         # Rector + Pint + PHPStan
vendor/bin/sail composer run lint:static                  # PHPStan level 5 only
vendor/bin/sail bin pint --dirty --format agent           # Format modified PHP files
vendor/bin/sail composer run test:type-coverage           # Pest type coverage (min 100%)
```

## Architecture

### Domain Model

- **Customer** → has many **Projects** → has many **Worklogs** (DB table: `project_activities`)
- **Customer** → has many **ClientContacts**, **RecurringServices**
- **Lead** → has **LeadSource**, tracks pipeline stages and estimated value
- **Activity** = category/type applied to worklogs (has default billable flag and hourly rate)
- **BacklogItem** → convertible to Worklog via `convertToWorklog()`
- **Note** and **Tag** are polymorphic (morphable to multiple entities)
- **UserSetting** stores JSON preferences (time tracking rounding, UI locale/timezone/formats)

### Multi-Tenancy

`EnforcesOwner` trait (`app/Models/Concerns/`) adds a global scope filtering by `owner_id` and auto-sets it on create/update. Used on all domain models.

### Financial Resolution (Cascading)

Rates and currency cascade: **Worklog → Project → Customer → User defaults**
- `Project::effectiveHourlyRate()`, `Project::effectiveCurrency()`
- `Worklog::effectiveUnitRate()` — depends on type (Hourly vs OneTime)
- FX rates and symbols in `config/crm.php`

### Custom Status Workflows

Projects and Worklogs have per-user customizable statuses (`ProjectStatusOption`, `ProjectActivityStatusOption`). These are cached, enforce single-default rules, and cascade code renames to related records.

### Filament Resource Structure

Each resource in `app/Filament/Resources/{Entity}/` has subdirectories:
- `Pages/` — List, Create, Edit, View
- `Schemas/` — Form schema classes
- `Tables/` — Table configuration classes

Panel configured in `AdminPanelProvider` with 4 nav groups: Work Log, Sales, CRM, Setup.

### Localization

Two locales: `en`, `cs`. Translation files in `lang/`. User preferences applied per-request via `ApplyUserInterfacePreferences` middleware.

### Enums

All enums (`app/Enums/`) implement Filament's `HasColor`, `HasIcon`, `HasLabel`. Keys are TitleCase.
