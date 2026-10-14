# How we build

Decided 14 October 2026 (Linear THI-10). Thirty Degres is built by Claude, with Kidus deciding what gets built and approving every change before it goes live. This note is the loop every feature follows, so any Claude session (or a future engineer) works the same way.

## The loop

| Step                | Who                       | What happens                                                                                                                                                                                                                                                   |
| ------------------- | ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Spec             | Kidus, with Claude's help | A Linear issue using the feature template below: the problem, who it's for, what "done" looks like.                                                                                                                                                            |
| 2. Build            | Claude                    | A branch named after the issue (e.g. `thi-35-workspaces`), code plus feature tests, following `CLAUDE.md`.                                                                                                                                                     |
| 3. Self-check       | Claude                    | `composer ci:check` passes locally, the screens are tried with Amharic text, and a security review of the diff is run before opening the PR.                                                                                                                   |
| 4. Pull request     | Claude                    | Uses the PR template: the Linear issue, "Before / After" in plain words, and the checklist.                                                                                                                                                                    |
| 5. Automatic checks | GitHub                    | CI runs formatting, type checks, static analysis, the full test suite on Postgres, and a scan of dependencies for known vulnerabilities. A red check means Claude fixes it before review; a branch rule on `main` (see below) stops red PRs from being merged. |
| 6. Preview          | Laravel Cloud             | A private copy of the app with its own empty database is deployed for the PR, and its link is posted on the PR.                                                                                                                                                |
| 7. Review           | Kidus                     | Opens the preview and walks the acceptance criteria. Comments on the PR or in the project thread if anything is off; Claude fixes and the preview updates.                                                                                                     |
| 8. Merge            | Kidus                     | Clicks Merge. Production deploys automatically and the preview is deleted.                                                                                                                                                                                     |
| 9. Close            | Claude                    | Marks the Linear issue Done with the PR linked.                                                                                                                                                                                                                |

## Protecting `main`

In GitHub, **Settings > Rules > Rulesets > New branch ruleset** for `main`: require a pull request and require the `ci` check to pass. Then nothing reaches production without passing CI, even by accident.

## What Claude never does without Kidus saying so

- Merge a pull request, or change production settings and environment variables.
- Run anything that deletes or rewrites real client data (destructive migrations, bulk deletes).
- Add a paid service, or a new third-party service that receives client data.
- Weaken a check to get green: skipping tests, lowering PHPStan, adding `@ts-ignore`.

## If something goes wrong in production

1. In Laravel Cloud, open the production environment's **Deployments** and redeploy the last good one. That restores the old code in about a minute.
2. Claude then opens a PR that reverts or fixes the change, and it goes through the loop above like any other.
3. Database changes are written so the previous release still runs on the new schema (add columns first, remove them in a later release), which keeps step 1 safe.

## Feature issue template

Kidus adds this once in Linear under **Settings > Teams > Thirty Degres > Templates > New template**, named "Feature". Every feature issue starts from it.

```markdown
## Problem

Who has this problem, and what do they do today?

## What we're building

One or two sentences in plain words.

## Acceptance criteria

- [ ] A person with role ___ can ___
- [ ] A person without access cannot ___
- [ ] Works with Amharic text
- [ ] Works on a slow connection (previews, not originals)

## Out of scope

What this deliberately doesn't do yet.

## Design

Links to sketches or screenshots, if any.
```

Acceptance criteria are what Kidus checks on the preview in step 7, and Claude turns each one into at least one feature test.
