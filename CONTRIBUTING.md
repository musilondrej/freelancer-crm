# Contributing

Thanks for contributing to Freelancer CRM. The project is intended to stay small, practical, and maintainable, so the main expectation is focused changes with clear behavior and solid test coverage.

## Before You Start

- Search existing issues and pull requests before starting work.
- Open an issue or discussion first for larger features, schema changes, or UX direction changes.
- Do not use public issues for security reports. Follow [SECURITY.md](SECURITY.md).

## Local Setup

1. Fork the repository.
2. Clone your fork.
3. Install the project using the setup steps from [README.md](README.md).
4. Create a branch from `main`.

Example branch names:

- `feat/project-margin-widget`
- `fix/worklog-rate-resolution`
- `docs/readme-open-source-pass`

## Expected Workflow

1. Confirm the change is scoped and justified.
2. Implement the smallest reasonable solution.
3. Add or update tests.
4. Run formatting, static analysis, and the affected tests.
5. Open a pull request with a clear description and rationale.

## Development Rules

### Runtime

- Run project commands through `vendor/bin/sail`.
- Use Laravel conventions and existing repo structure.
- Avoid introducing new dependencies without prior discussion.

### Architecture

- Prefer Eloquent models and relationships over raw queries.
- Use `Model::query()` patterns instead of the `DB::` facade unless there is a clear reason.
- Keep multi-tenant ownership rules intact. Domain models should continue to respect the owner scoping approach used across the app.
- Follow existing Filament resource organization: `Pages`, `Schemas`, and `Tables`.

### Code Style

- Use explicit parameter and return types.
- Use descriptive names over abbreviations.
- Prefer PHPDoc where extra context is needed.
- Keep comments rare and useful.
- Match existing naming and folder conventions in neighboring files.

### Testing

- Pest is the test framework.
- Every functional change should be covered by a new or updated test.
- Prefer focused test runs while working, then run the relevant broader checks before opening a PR.

## Verification Commands

Run the minimum necessary commands locally before submitting:

```bash
# targeted or full test run
vendor/bin/sail artisan test --compact

# full lint pipeline
vendor/bin/sail composer run lint

# type coverage gate
vendor/bin/sail composer run test:type-coverage
```

If you changed PHP files, also format them:

```bash
vendor/bin/sail bin pint --dirty --format agent
```

## Pull Request Guidelines

- Keep each pull request focused on one fix, feature, or documentation change.
- Explain the problem being solved, not just the code you changed.
- Include screenshots or recordings for UI changes when useful.
- Mention any schema, seed, or migration impact explicitly.
- Link the related issue when one exists.

## Good First Contributions

These are usually the easiest ways to contribute productively:

- documentation improvements,
- focused bug fixes with regression tests,
- UX polish in existing Filament resources,
- performance or query improvements with measurable impact.

## Bug Reports

Please include:

- a short summary,
- exact steps to reproduce,
- expected result,
- actual result,
- screenshots or logs if relevant,
- environment details such as PHP version, OS, and whether Sail is being used.

## Feature Requests

Feature requests are most useful when they explain:

- the workflow problem,
- why existing behavior is insufficient,
- the expected outcome,
- whether the change is narrow or broad in scope.

## Communication Standards

By participating in this project, you agree to follow [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## License

By contributing, you agree that your contributions will be licensed under the MIT License used by this repository.

## Questions

If a change is non-trivial and you are unsure about direction, open a discussion or issue before writing a large patch. That reduces rework and keeps the project coherent.
