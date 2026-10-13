# Architecture

Decided 13 October 2026 (Linear THI-12). This note records what the app is built on and why, so later changes don't drift from it.

## Shape

One Laravel application serves the React screens through Inertia, so there is no separate API to secure. Alongside it run a Postgres database and a queue worker for slow jobs such as generating image previews.

| Part            | Choice                                                                     | Why                                                                                                                         |
| --------------- | -------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Server          | Laravel 13, PHP 8.3                                                        | Mirrors Frontify's PHP-server pattern; login, queues, storage and permission policies are built in; large local hiring pool |
| Screens         | React 19, TypeScript, Inertia, Tailwind, shadcn/ui                         | Modern UI with typed code that catches AI mistakes early                                                                    |
| Login           | Fortify: email verification, two-factor, passkeys                          | Enterprise clients expect strong sign-in from day one                                                                       |
| Database        | Postgres                                                                   | Row-level security for tenant isolation; runs the same in Laravel Cloud and in an Ethiopian data centre                     |
| Files           | Laravel `Storage` on an S3-compatible bucket (Cloudflare R2 for the pilot) | Free egress keeps download costs low; any S3-compatible store can replace it                                                |
| Background jobs | Laravel queues                                                             | Preview renditions and zipped brand kits without slowing pages                                                              |
| Fonts           | Self-hosted Instrument Sans and Noto Sans Ethiopic                         | No third-party CDN; Ethiopic files load only when a page shows Ge'ez text                                                   |

## Hosting

- **Pilot:** Laravel Cloud, Frankfurt region, with a monthly spending cap. Suitable for clients who accept data stored in Europe.
- **In-country:** for banks, telcos and government covered by Proclamation 1321/2024, the same app runs on a server in Addis Ababa (e.g. Wingu Africa or Raxio) with Postgres and an S3-compatible store. Nothing in the code may depend on Laravel Cloud.

## Tenant isolation

Every brand workspace's data is separated twice: Laravel policies and query scopes in the app, and Postgres row-level security underneath (to be designed in THI-11). Tests must prove one workspace cannot read another's assets.

## Not decided yet

- Workspace, brand and asset data model (THI-11, THI-13)
- Image and video rendition pipeline details
- Billing through Chapa (paid plans come after the free pilot)
