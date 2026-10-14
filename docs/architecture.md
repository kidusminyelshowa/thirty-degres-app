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
| Email           | Resend through Laravel's `Mail` facade, sent from thirtydegres.xyz         | 3,000 free emails a month; swapping provider is a config change, not a code change                                          |
| Fonts           | Self-hosted Instrument Sans and Noto Sans Ethiopic                         | No third-party CDN; Ethiopic files load only when a page shows Ge'ez text                                                   |

## Hosting

- **Pilot:** Laravel Cloud, Frankfurt region, with a monthly spending cap. Suitable for clients who accept data stored in Europe.
- **In-country:** for banks, telcos and government covered by Proclamation 1321/2024, the same app runs on a server in Addis Ababa (e.g. Wingu Africa or Raxio) with Postgres and an S3-compatible store. Nothing in the code may depend on Laravel Cloud.
- What the data laws require, what we do during the pilot, and the per-region plan are in [data-residency.md](data-residency.md).

## Tenant isolation

Every brand workspace's data is separated by Laravel policies and query scopes in the app, and by Postgres row-level security underneath. Roles, partners, share links and the three locks are designed in [roles-and-permissions.md](roles-and-permissions.md) (THI-11). Tests must prove one workspace cannot read another's assets.

## Asset library (THI-36)

- Files belong to a brand and start as **drafts**. A Brand manager who did not upload a file approves it; only approved files reach Viewers. Approved files are archived rather than deleted.
- The original is stored as uploaded at `workspaces/{workspace}/assets/{asset}/original.{ext}` on the default disk (`FILESYSTEM_DISK`), never under the uploader's file name. Downloads stream it back as an attachment.
- Pages only show **previews**: a WebP at most 800 px on its longest side, made with PHP's GD when a JPEG, PNG, WebP or GIF is uploaded. SVG logos are their own preview, served with a sandbox policy so scripts inside them cannot run. Fonts and documents show an icon.
- Previews are made during the upload request, so they work without a queue worker. Move this to a queued job once a worker runs in every environment.
- Tags are shared across a workspace's brands; search matches file names and tags.

## Not decided yet

- Video previews, and previews of PDFs and design files (they show an icon for now)
- Billing through Chapa (paid plans come after the free pilot)
