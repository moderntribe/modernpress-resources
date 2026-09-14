# Algolia Sync

Standalone WordPress plugin that syncs selected post types into shared Algolia indexes already populated by another site.

| | |
| --- | --- |
| Author | Modern Tribe |
| Version | 1.0 |
| Requires | WordPress 6.1+, PHP 8.1+ |
| Access | Editors and Administrators (`edit_pages`) |
| Queueing | None — no Action Scheduler; full sync is pure admin AJAX |

## Architecture

This plugin does **not** use [Action Scheduler](https://wordpress.org/plugins/action-scheduler/) (or any background job library).

| Flow | Mechanism |
| --- | --- |
| Full sync (admin UI) | Recursive `admin-ajax.php` requests driven by `assets/admin-sync.js` |
| Single post publish / update / delete | Synchronous lifecycle hooks |
| Term create / update / delete | Chained WP-Cron single events (`wp_schedule_single_event`) |

Full sync keeps the browser tab open and chains batch AJAX calls (`tribe_algolia_sync_start`, then repeated `tribe_algolia_sync_batch`) until complete. Closing, refreshing, or navigating away interrupts the run — use **Reset status** before retrying.

The plugin can be updated later to use Action Scheduler for background full sync and term reindex batches (run without keeping the admin tab open). The current AJAX design is intentional for a lightweight first version with no extra dependencies.

## Install

1. Copy this folder to `wp-content/plugins/algolia-sync`.
2. Ensure `vendor/` exists. If not: `composer install --no-dev --optimize-autoloader` inside this directory.
3. Activate **Algolia Sync** in wp-admin. No Action Scheduler (or other queue plugin) is required.
4. Open the top-level **Algolia Sync** menu.

## Build release zip (local)

Replicates the GitHub Actions release job without uploading:

```bash
./bin/build-release.sh
```

Requires PHP 8.1+, [Composer](https://getcomposer.org/), and [WP-CLI](https://wp-cli.org/) with the `dist-archive` command (installed automatically if missing).

Output is written to `dist/algolia-sync.{version}.zip`. Override paths with env vars if needed:

```bash
PLUGIN_SLUG=algolia-sync BUILD_FOLDER=.build DIST_FOLDER=dist ./bin/build-release.sh
```

## Configure

1. Enter Algolia **Application ID** and **Admin API key**.
2. Select one or more public **post types**.
3. Add one or more **index base names** (e.g. `general`).
4. Confirm the live preview resolves to `{base}_{environment}` (e.g. `general_local`).
5. Save settings.

## Full sync

Full sync is browser-driven AJAX only (not Action Scheduler, not WP-Cron).

1. Click **Run full sync**.
2. **Stay on this page** until it finishes. Do not navigate away, close the tab, or refresh — leaving interrupts the sync.
3. Watch **Sync status** and **Recent activity** update live.
4. If you left mid-sync, use **Reset status** before starting again.

Batch size: 25 published posts per AJAX request. The admin script recursively posts the next batch until the server reports `done`.

## Automatic sync

- Publish / update a selected post type → upsert to all configured indexes (inline on the request).
- Draft / private / trash / delete → remove from indexes.
- Homepage (`page_on_front`) is excluded.
- Term create / update / delete → WP-Cron single events reindex affected selected posts in batches of 50 (still no Action Scheduler).

## Security notes

- Menu, settings save, AJAX sync, and reset all require `edit_pages`.
- Settings API save capability is explicitly set to `edit_pages` (not `manage_options`).
- AJAX actions verify a nonce on every request.
- Sync hooks are not registered if the Algolia PHP SDK is missing.

## Developer filters

| Filter | Purpose |
| --- | --- |
| `tribe/algolia_sync/post_to_record` | Mutate the final Algolia record |
| `tribe/algolia_sync/taxonomies` | Choose taxonomy slugs collected for a post |
| `tribe/algolia_sync/term_names` | Mutate term names for one taxonomy |
| `tribe/algolia_sync/taxonomy_terms` | Mutate the full taxonomy → names map |
| `tribe/algolia_sync/primary_term` | Mutate primary term name |
| `tribe/algolia_sync/should_index` | Skip indexing a post |
| `tribe/algolia_sync/should_reindex_taxonomy` | Skip term-driven reindex for a taxonomy |

## Record shape (high level)

- Collision-safe `objectID`: `{site_slug}_{post_id}`
- External flags: `is_external_item = true`, `external_site = site name`
- All taxonomies registered to the post type are added by slug
- Optional Rank Math / Yoast primary category when those plugins are already active

## Uninstall

Deleting the plugin removes its options (credentials, settings, status, activity log). Algolia index data is not deleted automatically.
