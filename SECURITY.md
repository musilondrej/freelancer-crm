# Security Policy

## Supported Versions

Security fixes are provided for the latest published release on the `main` branch and the most recent tagged release when maintainers can reproduce and validate the issue.

At the time of writing, the current public baseline release is:

- `v0.1.0`

Older snapshots, forks, and heavily modified self-hosted deployments may not receive coordinated fixes.

## Reporting A Vulnerability

Please do not report security vulnerabilities in public GitHub issues, discussions, or pull requests.

Instead, report them privately with:

- a clear description of the issue,
- affected versions or commit hashes if known,
- reproduction steps or a proof of concept,
- impact assessment,
- any suggested mitigation if you already have one.

Use GitHub's private vulnerability reporting or security advisory flow when it is available for this repository. If that is not available, contact the repository owner privately through GitHub rather than using a public issue.

## What To Expect

Maintainers will try to:

- acknowledge receipt within a reasonable time,
- reproduce and assess the report,
- determine severity and affected scope,
- prepare a fix or mitigation,
- coordinate disclosure when appropriate.

Response times are best effort. This is an independent open source project, so timelines may vary.

## Disclosure Guidelines

- Give maintainers reasonable time to investigate and ship a fix before public disclosure.
- Avoid publishing exploit details while users remain exposed without a mitigation.
- Coordinated, good-faith disclosure is strongly preferred.

## Scope Notes

Useful reports generally include vulnerabilities involving:

- authentication or authorization bypass,
- multi-tenant data isolation failures,
- sensitive data exposure,
- injection vulnerabilities,
- insecure defaults with real exploitability.

Reports that depend entirely on local misconfiguration, unsupported environments, or non-security best-practice preferences may be closed as out of scope.