# Thirty Degres

A brand operating system for Ethiopian and East African companies: brand assets, guidelines, templates and voice in one place, built for Amharic, Afan Oroma, Swahili and English and for low-bandwidth connections.

Built with Laravel 13, React 19 and Postgres. See [docs/architecture.md](docs/architecture.md) for the stack and hosting plan, and [CLAUDE.md](CLAUDE.md) for development rules.

## Run it locally

You need PHP 8.3, Composer, Node 22 and Postgres 16.

```bash
createdb thirty_degres && createdb thirty_degres_test
composer setup
composer dev
```

Then open http://localhost:8000.

## Checks

```bash
composer ci:check
```

Runs the same lint, type checks, static analysis and tests as CI.

Based on the [Laravel React starter kit](https://github.com/laravel/react-starter-kit) (MIT).
