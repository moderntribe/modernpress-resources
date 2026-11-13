# SSH Release Deploy and Rollback

Manual, GitHub-Actions-driven deploy and rollback for SSH-only hosts (for example Nexcess), using a
Capistrano-style release-directory model. Everything runs and is logged in GitHub Actions. There are **no server-side
git hooks**.

Originally built in [DEVOPS-172](https://moderntribe.atlassian.net/browse/DEVOPS-172) and in use on
`tribe-website-2026`.

| File | Copy to | Purpose |
| --- | --- | --- |
| [`ssh-release-deploy.yml`](./ssh-release-deploy.yml) | `.github/workflows/` | Builds on the runner, deploys a new release, runs checks, flips `current` |
| [`ssh-release-rollback.yml`](./ssh-release-rollback.yml) | `.github/workflows/` | Repoints `current` at a previous release |
| [`deploy-exclude.txt`](./deploy-exclude.txt) | `.deploy/` | rsync exclude list: what never lands in a release |
| [`local-config-deployssh.php`](./local-config-deployssh.php) | `.deploy/config/` | Template for the server-only `shared/local-config.php` (WordPress/Moose projects) |

The mechanics live in the shared action
[`moderntribe/actions/deploy/ssh-deploy`](https://github.com/moderntribe/actions/tree/main/deploy/ssh-deploy). These
workflows pin it to `v1.4.0`. Bump the tag deliberately; the rollback script is shipped by the action, so see
[Rollback](#rollback).

## How it works

Each deploy builds on the runner, rsyncs the build into a fresh `releases/<timestamp>.tmp/` on the server, symlinks the
persistent `shared/` items into it, runs the [release check](#release-check), renames it to `releases/<timestamp>/`, then
**atomically flips** `current` to the new release and prunes old releases (never the live one). The webroot points at
`current`.

A build only gets its final name after it succeeds, so a failed or interrupted deploy never leaves something that looks
like a release. It leaves a `*.tmp` folder, which rollback ignores and the next deploy removes. Each release holds a
`REVISION` file with the commit it was built from.

```
<deploy-path>/
├── current -> releases/2026-07-14_16-22-01/   # webroot points HERE
├── releases/
│   ├── 2026-07-14_16-22-01/                    # this deploy
│   └── 2026-07-11_09-15-33/                    # previous = rollback target
├── shared/                                      # persistent, never overwritten
│   ├── uploads/            -> wp-content/uploads in each release
│   └── local-config.php    -> local-config.php  in each release
├── finalize-release.sh                          # shipped by the deploy
└── rollback.sh                                  # shipped by the deploy
```

**Rollback target = release retention.** The newest `keep-releases` releases are kept (5 in the workflow; the action
defaults to 2), so there is no separate backup step. Unchanged files are hardlinked between releases, which keeps the
extra disk use small. A release holds code only: the database and `shared/` (uploads, config) are not in it, so a
rollback does **not** undo a database change.

## Prerequisites

- A host reachable over SSH with `rsync`, `bash` and (for the sample checks) `wp` available to the SSH user.
- A GitHub Environment per target (`Production`, `Staging`) containing the secrets and variables below.
- You can rename the workflow files. Keep both `concurrency.group` values identical so deploys and rollbacks to the
  same environment cannot overlap.
- The sample build uses the Moose `composer-install` composite action
  ([`.github/actions/composer-install`](https://github.com/moderntribe/moose/tree/main/.github/actions)), 1Password for
  the composer `auth.json`, and `.nvmrc`/`npm run dist` for assets. Swap these out for your project's toolchain.

## Setting up a new project

### 1. Copy the files

```bash
mkdir -p .deploy/config
cp <moose-resources>/workflows/ssh-release/ssh-release-deploy.yml   .github/workflows/
cp <moose-resources>/workflows/ssh-release/ssh-release-rollback.yml .github/workflows/
cp <moose-resources>/workflows/ssh-release/deploy-exclude.txt       .deploy/
cp <moose-resources>/workflows/ssh-release/local-config-deployssh.php .deploy/config/
```

The `.deploy/` paths are referenced by `exclude-file` and `shared-seeds` in the deploy workflow. If you put them
elsewhere, update both (the paths are prefixed with `site/` because the deploy job works from the unpacked artifact).

### 2. Adapt the build

In `ssh-release-deploy.yml`, edit the `build` job. Everything that must ship in the release has to exist in the
workspace when the `Package build` step runs. Look for the `PROJECT:` comments. The usual changes:

- Node version, build commands, or remove the Node steps if there is no asset build.
- Composer install (keep it on the runner so `vendor/` ships in the release).
- Remove the **Redis object cache drop-in** step if the project does not use Redis.
- Anything that must not be shipped (secrets, `node_modules`, `.git`) is excluded in the `tar` command and in
  `deploy-exclude.txt`.

The `build` job never references the SSH secrets. That is deliberate: composer and npm run third-party install scripts,
and they must not run next to the SSH key. Keep any new build step in this job, not in `deploy`.

### 3. Adapt the deploy step

Still in `ssh-release-deploy.yml`, review the `ssh-deploy` inputs:

| Input | What to set |
| --- | --- |
| `shared-items` | `src:dest` pairs. `src` is under `<deploy-path>/shared`, `dest` is relative to the release root. Each `dest` becomes a symlink to the persistent `src`. List every directory or file that must survive between releases (uploads, server-only config) |
| `shared-seeds` | `dest:src` pairs. Copies a committed template into `shared/` on the **first deploy only**; never overwrites an existing file |
| `exclude-file` | rsync exclude list. See `deploy-exclude.txt` |
| `keep-releases` | How many releases to retain, and therefore how far back you can roll back |
| `verify-command` | Runs on the server, inside the new release, **before** it goes live. A non-zero exit aborts the deploy and leaves `current` untouched |
| `post-deploy-command` | Runs on the server in `current` **after** the flip, for example a cache flush. A failure only warns |
| `health-check-url` | Public URL requested from the runner after the release is live. Must answer 2xx within `health-check-timeout` seconds (default 60) |
| `health-check-on-failure` | `fail` leaves the new release live; `rollback` points `current` back first. The sample uses `rollback` on Production and `fail` on Staging |

Full input reference: the
[action's `action.yml`](https://github.com/moderntribe/actions/blob/main/deploy/ssh-deploy/action.yml).

### 4. Create the GitHub Environments

Create one Environment per target (**Settings → Environments**), named exactly as the workflow's `options`
(`Production`, `Staging`). Add more by extending the `options` list in **both** workflows.

Set the values **on the environment**, not as repository secrets. An environment without its own value falls back to the
repository one and would deploy to the wrong server.

Restrict who can deploy: on the `Production` environment add **required reviewers** and limit **deployment branches**
(for example to `main` and release branches). Without that, anyone with write access can deploy any branch to
production, because `workflow_dispatch` runs the workflow from the branch you pick.

#### Secrets

| Secret | Purpose |
| --- | --- |
| `SSH_HOST` | Server hostname or IP |
| `SSH_USER` | SSH user |
| `SSH_KEY` | Passphrase-less private key for that user |
| `SSH_PORT` | SSH port (optional; defaults to 22) |
| `DEPLOY_PATH` | Absolute path that holds `current/`, `releases/` and `shared/` |
| `OP_SERVICE_ACCOUNT_TOKEN`, `OP_VAULT`, `OP_ITEM` | 1Password auth for composer (sample build only) |
| `SLACK_WEBHOOK` | Slack incoming webhook for deploy and rollback messages (usually a repository secret) |

#### Variables

| Variable | Purpose |
| --- | --- |
| `SSH_KNOWN_HOSTS` | Pinned host key of the server (a public key, so a variable, not a secret). **Required for rollback**; recommended for deploy |
| `HEALTH_CHECK_URL` | URL requested after the release is live. Optional; no check when empty |
| `SITE_URL` | Site URL shown in the Slack message. Optional; defaults to the run URL |

#### Pinning the host key

Without a pinned key the deploy trusts whatever answers on each run, so a spoofed server would be accepted.

1. Get the key: `ssh-keyscan -t ed25519 <host>` (add `-p <port>` for a port other than 22; the line then starts with
   `[host]:port`). Use the one line that starts with the host, not the `#` comment.
2. Check it is genuine: compare `ssh-keyscan -t ed25519 <host> | ssh-keygen -lf -` with `ssh-keygen -F <host> -l` on a
   machine that already trusts the server, or ask the host for the fingerprint. The `SHA256:` values must match.
3. Save the line as the `SSH_KNOWN_HOSTS` variable on the environment.

### 5. One-time server setup (per environment)

The deploy creates the base `releases/` and `shared/` directories itself. Do the rest once, before the first deploy:

1. **Authorize the CI key.** Add the public half of `SSH_KEY` to the SSH user's `authorized_keys`. Ensure that user can
   create directories and symlinks under `<deploy-path>`.
2. **Point the webroot/docroot at `<deploy-path>/current`.**
3. **Migrate existing uploads into `shared/`** (existing sites only):
   ```bash
   mkdir -p <deploy-path>/shared
   mv <live-site>/wp-content/uploads <deploy-path>/shared/uploads
   ```
4. **Fill in the server-only config.** `shared/local-config.php` is seeded on the first deploy from
   `.deploy/config/local-config-deployssh.php` and never overwritten after that. Edit the server copy with the DB
   credentials and salts. To skip the seed, place your own `shared/local-config.php` before the first deploy. A copy
   created from an older template must be updated by hand.
5. **Set up the database.** On a new site it is empty, and the release check fails with no output (`wp core
   is-installed` exits 1 when WordPress is not installed). The deploy does not install or import anything. After the
   first deploy stops at the check, run one of these inside the kept `releases/<timestamp>.tmp` folder (its config is
   already linked), then re-run the deploy:
   ```bash
   wp db import <dump>.sql && wp search-replace '<old-url>' '<new-url>' --all-tables
   wp core install --url=<url> --title=<title> --admin_user=<user> --admin_email=<email>
   ```
   Use the import for an existing site and `core install` for a blank one.
6. **`wp-config.php` ships with each release.** Do not symlink it from `shared/`; PHP resolves symlinks in `__DIR__`, so
   it would stop finding its sibling files. It should hold no secrets and load `local-config.php` instead (as Moose
   does via `wp-config-environment.php`).
7. **Redis (optional).** The template ships with Redis disabled. To enable it, set the `WP_REDIS_*` values in
   `shared/local-config.php` (a prefix unique to the site, plus `WP_REDIS_SELECTIVE_FLUSH` if the server is shared)
   and set `WP_REDIS_DISABLED` to `false`.

### 6. Run it

- Deploy: **Actions → "SSH Release Deploy" → Run workflow**, choose the environment.
- Start with `Staging`. Confirm the release check, health check and the site before trying `Production`.
- Try a rollback on Staging once, so you know it works before you need it.

Workflows with `workflow_dispatch` only appear in the Actions tab once the file exists on the repository's **default
branch**.

## Release check

`verify-command` runs on the server inside the new release, once the shared links are in place and before the release
goes live. The sample runs `wp core is-installed`, which loads WordPress with the real config, database and plugins, so
a release that fatals on load (a PHP version mismatch, missing core, a broken config) is stopped before it goes live.

If it fails, the deploy stops before `current` flips and the live release is untouched. The release is kept as
`releases/<timestamp>.tmp` so you can `cd` into it and run the command by hand; the next deploy removes it. The check
uses the server's command-line `php` and `wp`, which can differ from the web PHP, so check both when the hosting PHP
version changes.

## Health check

After the flip and the `post-deploy-command`, the runner requests `HEALTH_CHECK_URL` and retries every 5 seconds for up
to 60 seconds. It passes only on a 2xx response from a request that finished cleanly. If it never passes, the deploy
fails and the Slack message reports it. With `health-check-on-failure: rollback` (Production in the sample) the deploy
first points `current` back at the release that was live before it and reruns the post-deploy command; with `fail`
(Staging) the new release stays live so you can look at it. The release before the deploy is never pruned while this is
on.

## Rollback

Run **Actions → "SSH Release Rollback" → Run workflow**.

- Leave *release-id* blank to roll back to the release just before `current`, or enter a specific id
  (`YYYY-MM-DD_HH-MM-SS`; anything else is rejected).
- The log lists the available releases, marks the live one with `*` and shows the commit each was built from.
- It repoints `current` atomically. There is no rebuild.
- It then flushes the object cache (when Redis is configured), because data cached by the newer release can break the
  older code.

Notes:

- Rollback runs the `rollback.sh` that the **last deploy** shipped to `<deploy-path>`, so changes to that script (for
  example by bumping the action version) take effect after the next deploy.
- It requires `SSH_KNOWN_HOSTS` to be set.
- It restores code only. It does not undo database migrations or changes to `shared/`.

## Caching drop-ins

Every release is a fresh folder, so a drop-in that a plugin writes into `wp-content` (`object-cache.php`,
`advanced-cache.php`) would be gone after the next deploy. Decide per project how to keep it, and carry that choice in
the deploy workflow. For Redis Object Cache, the sample copies the drop-in from the plugin in the **build** job (so it
matches the locked plugin version), does not exclude it in `deploy-exclude.txt`, fails the release check if it is
missing, and flushes only after a guarded `wp cache flush`.

`post-deploy-command` and the rollback flush clear the WordPress object cache only. They do not reset PHP's OPcache or
any page cache.

## Not using WordPress, or not using Redis?

The deploy mechanics are framework-agnostic. Replace:

- the build steps,
- `verify-command` (any command that exits non-zero when the release cannot boot),
- `post-deploy-command` and the **Flush object cache** step in the rollback workflow (or delete them),
- `shared-items` and `shared-seeds`,
- the `.deploy/deploy-exclude.txt` entries.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| Rollback fails on "SSH_KNOWN_HOSTS is not set" | The variable is missing on that environment; see [Pinning the host key](#pinning-the-host-key) |
| Rollback cannot find `rollback.sh` | No deploy has run yet to that `DEPLOY_PATH`; run a deploy first |
| Deploy stops at the release check with no output | Empty database on a new site; see server setup step 5 |
| Site shows a fatal error after a successful deploy | The server's web PHP is older than the version the dependencies were built for; composer's platform check is usually off |
| Deploy goes to the wrong server | A secret is set at repository level and not on the environment |
| "Run workflow" button is missing | The workflow file is not on the default branch yet |
| Two runs queue instead of running together | By design: the shared `concurrency` group serialises deploys and rollbacks per environment |
