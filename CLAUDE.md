# Thirty Degres app

A brand operating system for Ethiopian and East African companies: one place for a brand's assets, guidelines, templates and voice. The founder is not an engineer; Claude writes the code and the founder reviews preview links. Product context and decisions live in Linear (team `THI`).

## Stack

- Laravel 13 (PHP 8.3) with Fortify for login, two-factor and passkeys
- React 19 + TypeScript through Inertia 3; Tailwind 4 and shadcn/ui components in `resources/js/components/ui`
- Wayfinder generates typed route helpers into `resources/js/actions` and `resources/js/routes` (gitignored; regenerate with `php artisan wayfinder:generate --with-form`)
- Postgres everywhere: local, CI and production. Never add SQLite-only or MySQL-only SQL
- Hosting target: Laravel Cloud (Frankfurt) for the pilot; must stay portable to a self-hosted server in Addis Ababa. See `docs/architecture.md`

## Commands

- `composer setup` installs everything, copies `.env`, migrates and builds
- `composer dev` runs the app, queue worker, logs and Vite together
- `composer ci:check` is what CI runs: frontend lint/format, `tsc`, Pint, PHPStan (level 7) and the test suite. Run it before every push
- `composer lint` and `npm run check:fix` auto-fix formatting

Local Postgres defaults are in `.env.example` (`thirty_degres`, and `thirty_degres_test` for tests).

## Rules for every change

1. Work from a Linear issue. Name the branch after it and link it in the PR.
2. Every behaviour change ships with a feature test in `tests/Feature`.
3. Keep PHPStan at level 7 with no baseline and `tsc` clean. Do not add `@phpstan-ignore`, `any` or `@ts-ignore` to get green.
4. Tenant safety is the top risk. Any query on tenant data must be scoped to the current workspace, authorised with a Laravel policy, and covered by a test that tries to read another workspace's data. Postgres row-level security will be added as a second lock (THI-11).
5. Keep it portable: files go through Laravel's `Storage` facade (S3-compatible disk in production), never a vendor SDK directly; no Laravel Cloud-only APIs in app code.
6. Low bandwidth is the default: serve resized previews, not originals; lazy-load images; no new third-party CDNs (fonts are self-hosted via `@fontsource`).
7. All user-facing text goes through translation helpers so Amharic can be added. Test layouts with Ge'ez text, which runs taller than Latin. Use the agreed Amharic words in `docs/design-system.md`.
8. Don't commit secrets. Configuration goes in `.env` and `.env.example`.
