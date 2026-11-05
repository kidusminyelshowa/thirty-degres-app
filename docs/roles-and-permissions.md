# Roles and permissions

Decided 14 October 2026 (Linear THI-11). This note sets who can see and do what in Thirty Degres, and how we stop one company ever seeing another company's files. Build week 1 (THI-35) builds the first part of it.

## In short

- Each client company gets its own **workspace**. Everything the company owns lives inside it: brands, sub-brands, files, guidelines, share links.
- People are given a role **in the workspace** (Owner, Admin or Member) and a role **on each brand** they work on (Brand manager, Editor or Viewer). Sub-brands inherit from their parent brand.
- People from outside the company (agencies, printers, distributors) join as **partners**: they only see the brands they are invited to, only for a fixed period, and never see the company's settings or member list.
- **Share links** let someone download approved files without an account. They always expire.
- **Segregation of duties**: nobody approves their own work, nobody raises their own access, and money matters stay with the Owner.
- Company data is separated by **three independent locks**, the last one inside the database itself, so a mistake in app code (including code written by AI) cannot show one company's files to another.

## Words we use

| Word              | Meaning                                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------------------- |
| Workspace         | One client company's private space, e.g. "Ethio Telecom". The unit we bill and the unit we isolate.     |
| Brand / sub-brand | A brand the company manages, e.g. "telebirr". A sub-brand sits under a brand, e.g. "telebirr Merchant". |
| Region            | A market label such as Ethiopia, Kenya or Djibouti, set on files and guidelines.                        |
| Member            | A person with an account who belongs to the workspace, either as staff or as a partner.                 |
| Partner           | A member from another organisation (agency, printer, distributor), with limited and time-boxed access.  |
| Share link        | A secret link to a set of approved files, for people without an account.                                |

## Roles

### Workspace roles (one per person per workspace)

| Role   | For                                   | Can                                                                                                                   |
| ------ | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Owner  | The person accountable, often the CMO | Everything an Admin can, plus billing, renaming or deleting the workspace, and making other Owners                    |
| Admin  | Head of brand or marketing operations | Invite and remove people, create brands, give brand roles, see the activity log. Acts as Brand manager on every brand |
| Member | Everyone else                         | Nothing on its own. What they can do comes from their brand roles                                                     |

Why separate Owner and Admin: the person who runs day-to-day brand work should be able to manage the team without also being able to change the plan, the bill or delete the company's whole library. Banks and telcos expect that split.

### Brand roles (per brand, inherited by its sub-brands)

| Role          | Can                                                                                                                        |
| ------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Brand manager | Everything an Editor can, plus approve and publish files and guidelines, archive them, and give people roles on this brand |
| Editor        | Upload files, edit drafts and guidelines, submit them for approval, create share links for approved files                  |
| Viewer        | See and download approved files and guidelines                                                                             |

A role given on a brand also applies to all its sub-brands. A sub-brand can be given a higher role than its parent (an agency can edit "telebirr Merchant" while only viewing "telebirr"), never a lower one, so access is easy to reason about: look at the parent, then add.

Why roles per brand: large groups run several brands with different teams and agencies. A person working on one beer brand should not see the launch plans for a sister brand.

### Regions

Regions are labels on files and guidelines, not a separate permission level. Viewers see all regions of a brand they can view. A brand role can later be narrowed to certain regions (for example a Kenyan distributor sees only Kenya files) by adding an optional region list to the brand role. We design the tables so that is an added column, not a rebuild, and build it when files exist (THI-13) and a pilot client asks for it.

Why not a full region level now: every extra level multiplies the checks and the tests. None of the pilot interviews so far needs region-only staff; a filter on partners covers the likely case.

### Partners

A partner is a member with `kind = partner` instead of `staff`. The difference is enforced in code, not by convention:

- They have workspace role Member, always. They can never be Owner or Admin.
- They can be Editor or Viewer on a brand, never Brand manager.
- Their access must have an end date (default 90 days, renewable by an Admin). After it passes they lose access automatically, without anyone having to remember.
- They see only the brands they are given and only approved files, plus drafts they uploaded themselves.
- They never see the member list, other partners, the activity log or workspace settings.
- Each partner carries the name of their organisation, shown next to their name, so staff always know when they are dealing with an outsider.

Why partners are a kind of member and not a fourth role: an agency designer and an in-house designer do the same work (Editor), but the agency's access must be narrower and must end. Keeping the work role and the "outsider" flag separate means every screen asks one question, not twelve.

### Share links

For printers, journalists or anyone who just needs the logo pack without an account.

- Only Editors and above can create them, and only for approved files.
- Every link expires: 14 days by default, at most 90. There is no "never expires".
- A link can be revoked at once, can have an optional password, and can turn downloads of original files on or off (previews only by default, which also helps slow connections).
- Every open and download is logged with time and rough location.
- We store only a scrambled (hashed) form of the link's secret, so even someone who reads the database cannot rebuild working links.
- A link shows files as they were approved. If a file is archived the link stops serving it.

### Segregation of duties

1. **Nobody approves their own work.** The person who uploaded or last edited a file cannot approve it; another Brand manager, Admin or Owner must. This is also a rule in the database, so no code path can skip it. A workspace with a single person can turn on "allow self-approval" in settings; the activity log marks those approvals.
2. **Nobody raises their own access.** Members cannot change their own role. Admins cannot make Owners or change an Owner. Only an Owner can.
3. **A workspace always has an Owner.** The last Owner cannot leave or be removed until they hand over.
4. **Money and deletion stay with the Owner.** Billing, plan changes and deleting the workspace need an Owner, and deleting needs the Owner's password or passkey again.
5. **Everything sensitive is logged.** Invites, role changes, approvals, share links and downloads by partners go into an activity log that nobody, including Owners, can edit or delete.
6. **Strong sign-in for the powerful.** Owners and Admins must have two-factor sign-in or a passkey turned on.

## What each role can do

✓ = allowed. "Own" = only things they uploaded.

| Action                                   | Owner | Admin | Brand manager | Editor | Viewer | Partner (as Editor / Viewer) |
| ---------------------------------------- | :---: | :---: | :-----------: | :----: | :----: | :--------------------------: |
| See approved files and guidelines        |   ✓   |   ✓   |       ✓       |   ✓    |   ✓    |       ✓ (given brands)       |
| See drafts                               |   ✓   |   ✓   |       ✓       |   ✓    |        |          Own / none          |
| Upload and edit drafts                   |   ✓   |   ✓   |       ✓       |   ✓    |        |           ✓ / none           |
| Approve and publish (not own work)       |   ✓   |   ✓   |       ✓       |        |        |                              |
| Archive files                            |   ✓   |   ✓   |       ✓       |        |        |                              |
| Create share links                       |   ✓   |   ✓   |       ✓       |   ✓    |        |                              |
| Give people roles on a brand             |   ✓   |   ✓   |       ✓       |        |        |                              |
| Invite people to the workspace           |   ✓   |   ✓   |               |        |        |                              |
| Create and rename brands                 |   ✓   |   ✓   |               |        |        |                              |
| See member list and activity log         |   ✓   |   ✓   |               |        |        |                              |
| Billing, delete workspace, manage Owners |   ✓   |       |               |        |        |                              |

Owners and Admins count as Brand manager on every brand. Anyone outside the workspace sees nothing, and gets "not found" rather than "forbidden" so they cannot even learn that a file exists.

## How company data is kept apart

Tenant safety is the biggest risk in this product (CLAUDE.md rule 4). We use three locks. Each one alone would stop most mistakes; together a mistake has to get past all three.

1. **The address.** Every workspace page lives under `/w/{workspace}/…`. A middleware checks the signed-in person is a current member of that workspace (and their partner access has not ended) before anything else runs, and remembers that workspace as "current" for the rest of the request.
2. **The app.** Every model holding company data uses a `BelongsToWorkspace` trait that automatically filters every query to the current workspace and fills in `workspace_id` on new rows. Every action goes through a Laravel policy that applies the role table above.
3. **The database.** Postgres row-level security on every company table. The app tells Postgres which workspace the request is for, and Postgres itself refuses to return or change rows from any other workspace, even if the app code forgot the filter or wrote raw SQL. If the app forgets to say which workspace, Postgres returns nothing at all (it fails closed).

The database lock deliberately covers only "which company", not "which role". Showing a draft to a Viewer inside the same company would be a bug; showing a bank's unreleased campaign to a competitor would end the business. The second is the one we lock in the database. Role rules stay in Laravel policies, where they are easier to read, change and test.

We tried the database lock on Postgres 16 while writing this note: with no workspace set, queries return nothing; set to company A, they return only A's rows; writing a row for company B, pointing A's file at B's brand, or approving your own upload are all refused by the database.

## Data model

Every company table carries `workspace_id`, even when it could be worked out through a parent. That keeps the database rule one simple line per table, which is easy to check automatically.

| Table          | Key columns                                                                                                                 |
| -------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `workspaces`   | `id`, `name`, `slug`, `allow_self_approval`                                                                                 |
| `memberships`  | `workspace_id`, `user_id`, `role` (owner, admin, member), `kind` (staff, partner), `partner_organisation`, `access_ends_at` |
| `brands`       | `workspace_id`, `id`, `parent_id` (sub-brand), `name`                                                                       |
| `brand_roles`  | `workspace_id`, `brand_id`, `membership_id`, `role` (manager, editor, viewer); later `regions`                              |
| `invitations`  | `workspace_id`, `email`, workspace role, kind, brand roles, `token_hash`, `expires_at`                                      |
| `share_links`  | `workspace_id`, `brand_id`, `token_hash`, `expires_at`, `revoked_at`, `password_hash`, `allow_originals`, `created_by`      |
| `activity_log` | `workspace_id`, `actor_id`, `action`, `subject`, `details`, `created_at` (insert only)                                      |

Rules that hold for all of them:

- **IDs are UUIDs** (time-ordered v7, Laravel `HasUuids`), so addresses cannot be guessed by counting.
- **Links between tables include the workspace.** A file points at `(workspace_id, brand_id)`, not just `brand_id`, so the database refuses a file in one company pointing at another company's brand.
- **Users are global.** One person can belong to several workspaces (an agency designer working for three clients). The `users` table has no `workspace_id` and no row-level security; it is only ever reached through a membership.
- **Approval is a database rule.** Tables with approvals get a trigger that refuses `approved_by = submitted_by` unless the workspace has `allow_self_approval` on. (A plain `CHECK` constraint does the same job but cannot read the workspace setting.)

## Building it (notes for THI-35 and after)

### Database roles

Row-level security does nothing for a Postgres superuser or a role with `BYPASSRLS`, and by default does nothing for the table owner. So:

- Migrations run as the owner role, as today.
- Every company table gets `ENABLE` and `FORCE ROW LEVEL SECURITY` and one policy: `workspace_id = current_workspace_id()`, for both reading and writing.
- A `NOLOGIN` role `thirty_app` without `BYPASSRLS` gets read and write grants on app tables. On connecting, the app runs `SET ROLE thirty_app`, so every app query runs under the policies. This works on any Postgres, Laravel Cloud or a server in Addis Ababa, with no special hosting features.
- `current_workspace_id()` is a small SQL function reading `current_setting('app.workspace_id', true)`, returning null (no rows) when unset.
- **To check in THI-35:** what attributes Laravel Cloud's database user has (some managed Postgres services give it `BYPASSRLS`). `SET ROLE` handles that case, but a test must prove it on the preview database. `php artisan tenancy:check` does that on any host: it fails if queries can skip row-level security, if the role switch is lost between queries (a transaction pooler), or if a company table is unlocked.

### Setting the current workspace

- The workspace middleware runs `select set_config('app.workspace_id', ?, false)` once per request and clears it when the request ends, so it never leaks into the next request on a reused connection (Laravel Octane or queue workers).
- Queued jobs store the workspace id and set it the same way before running.
- Use a direct (session) database connection, not a transaction-mode pooler; a transaction pooler would drop the setting between queries. If we ever need one, switch to setting it per transaction (`set_config(..., true)`).

### Things that legitimately cross workspaces

Each has one narrow, reviewed doorway, never a general bypass:

- **"Which workspaces am I in?"** A second policy on `memberships` lets a person read their own memberships, using `app.user_id` set by the same middleware.
- **Opening a share link.** No one is signed in and the workspace is not yet known. A `SECURITY DEFINER` database function takes the hashed token and returns only that link's workspace if the link is live; the app then sets that workspace and continues under the normal lock.
- **Accepting an invitation.** Same pattern as share links.
- **Our own support access.** No admin screen in the pilot. If we need to look inside a client's workspace, we do it from the server console with a separate `BYPASSRLS` role, and log it.

### Tests that must exist

These are the automated proof that the locks work. THI-35 adds the first of each, and every later feature that adds a company table adds its own.

1. **Cross-company tests over HTTP.** Two workspaces, A and B. Signed in as an Owner of A, every route that reads, edits or deletes something is called with B's ids, and must answer "not found" and change nothing.
2. **Database lock tests.** With the current workspace set to A, a raw query that skips all app filters (`DB::table(...)`, `withoutGlobalScopes()`) still returns only A's rows, and inserting a row for B fails.
3. **Every company table is locked.** A test lists every table with a `workspace_id` column and fails if any lacks `FORCE ROW LEVEL SECURITY` and a policy. A new table someone forgets to lock turns CI red.
4. **The role table above.** A data-driven test runs each action for each role, including expired partners, and checks allowed or refused against this note.
5. **Segregation rules.** Self-approval refused, self-promotion refused, last Owner cannot leave, Admin cannot touch an Owner.

Tests and CI must run as the restricted app role, not the `postgres` superuser, or the database lock is silently skipped. THI-35 adds a `thirty_app` role to the CI database and to `.env.example`.

### What THI-35 built

- Tables `workspaces`, `memberships`, `invitations` and `activity_log`, locked by row-level security. Brands, brand roles and share links come with the features that need them.
- The migration creates the `thirty_app` role (name set by `DB_APP_ROLE`, default `thirty_app`). The app switches to it the moment it connects (`App\Tenancy\DatabaseRole`), and only migrations, `db:wipe` and seeding run as the login user. Tests do the same, so the test suite exercises the real policies.
- `App\Tenancy\Tenancy` holds the current workspace and sets `app.workspace_id` and `app.user_id`; it clears them after every request.
- Accepting an invitation uses a third setting, `app.invitation_token_hash`, instead of a `SECURITY DEFINER` function: Postgres shows the one invitation whose hashed secret the request carries, and that workspace's name. It works the same for a non-superuser owner.
- Not yet enforced: two-factor or passkey for Owners and Admins (rule 6). It would block the first sign-up on a preview until two-factor is set up, so it ships with the security settings work.

### What THI-36 built

- Tables `brands`, `brand_roles`, `assets`, `tags` and `asset_tag`, each locked by row-level security, with every link between them including `workspace_id`.
- Brand roles (Brand manager, Editor, Viewer) on brands and one level of sub-brands. `App\Assets\BrandAccess` works out a person's role on each brand; Owners and Admins are Brand manager everywhere, and partners are capped at Editor.
- The role table above for files, in `AssetPolicy`, with the same rules for lists in `App\Assets\VisibleAssets`.
- Two database rules: a trigger refuses `approved_by = uploaded_by` unless the workspace has `allow_self_approval` on (an Owner can turn it on from the workspace home), and another refuses a partner as Brand manager.
- Not yet: the region list on brand roles, and "last edited" counting as authorship for approval (files cannot be replaced yet, only renamed and tagged).

### What THI-37 built

- Table `guideline_pages`, locked by row-level security and linked to its brand through `(workspace_id, brand_id)`. Each page keeps the working copy Editors change and the copy last published, which is all Viewers see.
- Every text on a page is stored per language code. Each brand chooses its languages and their order (`brands.guideline_languages`, default Amharic then English) from `config/languages.php`: Amharic, English, Afaan Oromoo, Tigrinya, Somali, Swahili, Arabic (right to left), French and Afar so far. Only Brand managers who are staff change them.
- The role table above for guidelines, in `GuidelinePagePolicy`: Viewers see published pages; Editors write and save drafts (partner Editors only pages they started); Brand managers publish and delete; staff Editors and Brand managers reorder pages.
- A database trigger refuses `published_by = edited_by` unless the workspace has `allow_self_approval` on, the same rule as for files. Publishing also waits until every file on the page is approved, since Viewers could not see a draft file.
- A page can only use files from its brand or the parent brand that the editor can see, and only font files as typefaces. Readers get only the files they may see.

### What THI-38 built

- Partners are invited straight onto one brand: the invitation carries `brand_id` and `brand_role` (Editor or Viewer only, also a database check), and accepting it creates the brand role. A partner sees that brand and its sub-brands and nothing else.
- Tables `share_links` and `share_link_assets`, locked by row-level security. A link shares either a brand's published guidelines (every published page, and only the approved files those pages show) or a kit of chosen approved files. Opening one uses a fourth setting, `app.share_token_hash`: Postgres shows the one link whose hashed secret the request carries, then the request continues inside that link's workspace with no membership, so every policy says no and the share pages read only what the link allows (`App\Sharing`).
- Links expire after 14 days by default and at most 90 (also a database check), can have a password, serve previews only unless originals are allowed (brand typefaces are original files, so shared guidelines show them only then), and can be turned off by whoever made them or a Brand manager. Archiving a file or taking a page down stops every link serving it.
- Opens (once per visit) and downloads are logged in the activity log and counted on the brand's **Share** page. Rough location is not recorded yet: it needs an IP-to-country lookup, which would be a new service.

## Defaults picked here, easy to change

These are product choices more than technical ones. Each is one setting.

- Partner access lasts 90 days by default before an Admin must renew it.
- Share links last 14 days by default, 90 at most, and serve previews only unless "allow originals" is ticked.
- Owners and Admins must use two-factor sign-in or a passkey.
- Partners from the same agency cannot see each other in the workspace.
- A one-person workspace can allow self-approval; everyone else needs a second person.
