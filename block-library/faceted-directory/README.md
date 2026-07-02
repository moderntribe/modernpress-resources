# Faceted Directory (FacetWP)

Status: Alpha
Version: 0.1-alpha
Last Updated: 2026-06
Component Created By: Geoff Dusome
Originally Implemented On: [ModernPress PR #327](https://github.com/moderntribe/ModernPress/pull/327)

## What does this component do?

This component adds a faceted directory experience to ModernPress using the [FacetWP](https://facetwp.com/) plugin. It includes three blocks:

- **`tribe/facetwp-archive`** — Parent block that composes the filter bar and results grid. Supports top or sidebar filter bar layouts.
- **`tribe/facetwp-filter-bar`** — Renders selected FacetWP facets (search, filters, reset, etc.). Only available as an inner block of the archive block.
- **`tribe/facetwp-grid`** — Renders a `WP_Query` loop integrated with FacetWP. Only available as an inner block of the archive block.

The component also ships a `FacetWP` integration class that registers custom facets (pagination, search, reset), improves accessibility, and customizes FacetWP markup.

**This component is optional.** Not every ModernPress project needs a faceted directory. Install it only on projects that use FacetWP.

## Prerequisites

1. **FacetWP plugin** — Must be installed and activated separately. This component does **not** bundle FacetWP. Obtain a license from [facetwp.com](https://facetwp.com/) and install the plugin into `wp-content/plugins/facetwp/`.
2. **FacetWP indexing** — After activation, configure facets in **Settings → FacetWP** and run the indexer.
3. **Node.js** — Required to build block assets (`npm run dist`).

---

## ModernPress Implementation

These instructions are written so a developer (or AI agent) can merge the entire feature into a ModernPress (`moose`) project.

### File map

Use this table as the source of truth for where each file in this directory belongs in ModernPress.

| Source (this repo) | Destination (ModernPress) |
| --- | --- |
| `FacetWP.php` | `wp-content/plugins/core/src/Integrations/FacetWP.php` |
| `Integrations_Subscriber.snippet.php` | Merge into `wp-content/plugins/core/src/Integrations/Integrations_Subscriber.php` |
| `Abstract_Block_Controller.snippet.php` | Merge into `wp-content/plugins/core/src/Components/Abstracts/Abstract_Block_Controller.php` |
| `Blocks_Definer.snippet.php` | Merge into `wp-content/plugins/core/src/Blocks/Blocks_Definer.php` |
| `theme.json.snippet.json` | Merge into `wp-content/themes/core/theme.json` → `settings.animationExcludes` |
| `blocks/facetwp-archive/*` (except controller) | `wp-content/themes/core/blocks/tribe/facetwp-archive/` |
| `blocks/facetwp-archive/FacetWP_Archive_Controller.php` | `wp-content/plugins/core/src/Components/Blocks/FacetWP_Archive_Controller.php` |
| `blocks/facetwp-filter-bar/*` (except controller and `components/`) | `wp-content/themes/core/blocks/tribe/facetwp-filter-bar/` |
| `blocks/facetwp-filter-bar/components/*` | `wp-content/themes/core/components/filter-bar/` |
| `blocks/facetwp-filter-bar/FacetWP_Filter_Bar_Controller.php` | `wp-content/plugins/core/src/Components/Blocks/FacetWP_Filter_Bar_Controller.php` |
| `blocks/facetwp-grid/*` (except controller) | `wp-content/themes/core/blocks/tribe/facetwp-grid/` |
| `blocks/facetwp-grid/FacetWP_Grid_Controller.php` | `wp-content/plugins/core/src/Components/Blocks/FacetWP_Grid_Controller.php` |
| `pcss/integrations/_mixins.pcss` | `wp-content/themes/core/assets/pcss/integrations/_mixins.pcss` |
| `pcss/integrations/_variables.pcss` | `wp-content/themes/core/assets/pcss/integrations/_variables.pcss` |
| `pcss/integrations/facetwp.pcss` | `wp-content/themes/core/assets/pcss/integrations/facetwp.pcss` |
| `pcss/_variables-import.snippet.pcss` | Merge into `wp-content/themes/core/assets/pcss/_variables.pcss` |
| `pcss/theme-import.snippet.pcss` | Merge into `wp-content/themes/core/assets/pcss/theme.pcss` |
| `pcss/icons/_variables.snippet.pcss` | Merge into `wp-content/themes/core/assets/pcss/icons/_variables.pcss` |
| `icons/filter.svg` | `wp-content/themes/core/assets/media/icons/filter.svg` |
| `icons/select-chevron.svg` | `wp-content/themes/core/assets/media/icons/select-chevron.svg` |

### Step-by-step merge

#### 1. Install FacetWP

Install and activate the FacetWP plugin. This component cannot function without it.

#### 2. Copy integration PHP

```text
FacetWP.php
  → wp-content/plugins/core/src/Integrations/FacetWP.php
```

#### 3. Copy block controllers

Move each controller out of its block directory and into the plugin Components folder:

```text
blocks/facetwp-archive/FacetWP_Archive_Controller.php
  → wp-content/plugins/core/src/Components/Blocks/FacetWP_Archive_Controller.php

blocks/facetwp-filter-bar/FacetWP_Filter_Bar_Controller.php
  → wp-content/plugins/core/src/Components/Blocks/FacetWP_Filter_Bar_Controller.php

blocks/facetwp-grid/FacetWP_Grid_Controller.php
  → wp-content/plugins/core/src/Components/Blocks/FacetWP_Grid_Controller.php
```

#### 4. Copy block source files

Copy each block directory **without** the controller PHP files or filter bar template parts:

```text
blocks/facetwp-archive/   → wp-content/themes/core/blocks/tribe/facetwp-archive/
blocks/facetwp-filter-bar/ → wp-content/themes/core/blocks/tribe/facetwp-filter-bar/
blocks/facetwp-grid/      → wp-content/themes/core/blocks/tribe/facetwp-grid/
```

Copy the filter bar template parts into the theme components directory:

```text
blocks/facetwp-filter-bar/components/
  → wp-content/themes/core/components/filter-bar/
```

These template parts are loaded via `get_template_part()` from `render.php`:

| Template part | Purpose |
| --- | --- |
| `components/filter-bar/top.php` | Top filter bar layout |
| `components/filter-bar/sidebar.php` | Sidebar filter bar flyout layout |
| `components/filter-bar/facets.php` | Shared facet markup used by both layouts |

#### 5. Merge snippet files

| Snippet | Target file | What to merge |
| --- | --- | --- |
| `Integrations_Subscriber.snippet.php` | `Integrations_Subscriber.php` | Add all `add_filter` / `add_action` hooks inside `register()` |
| `Abstract_Block_Controller.snippet.php` | `Abstract_Block_Controller.php` | Add `$context` property and constructor assignment |
| `Blocks_Definer.snippet.php` | `Blocks_Definer.php` | Add three block names to `self::TYPES` |
| `theme.json.snippet.json` | `theme.json` | Add three block names to `settings.animationExcludes` |
| `pcss/_variables-import.snippet.pcss` | `_variables.pcss` | Add integrations variables import (skip if already present) |
| `pcss/theme-import.snippet.pcss` | `theme.pcss` | Add `facetwp.pcss` import (skip if already present) |
| `pcss/icons/_variables.snippet.pcss` | `icons/_variables.pcss` | Add `filter` and `select-chevron` icon definitions |

When merging `theme.json`, add the entries from `theme.json.snippet.json` to the existing `settings.animationExcludes` array. Do not replace the file.

When merging `icons/_variables.pcss`, only add the `filter` and `select-chevron` entries. Do not modify existing icons.

#### 6. Copy integration styles

```text
pcss/integrations/_mixins.pcss    → wp-content/themes/core/assets/pcss/integrations/_mixins.pcss
pcss/integrations/_variables.pcss → wp-content/themes/core/assets/pcss/integrations/_variables.pcss
pcss/integrations/facetwp.pcss    → wp-content/themes/core/assets/pcss/integrations/facetwp.pcss
```

If `integrations/_mixins.pcss` or `integrations/_variables.pcss` already exist in the project, merge the FacetWP-specific rules into those files rather than overwriting unrelated integration styles.

#### 7. Copy icons

```text
icons/filter.svg         → wp-content/themes/core/assets/media/icons/filter.svg
icons/select-chevron.svg → wp-content/themes/core/assets/media/icons/select-chevron.svg
```

#### 8. Build assets

From the ModernPress project root:

```bash
npm run dist
```

#### 9. Verify

1. Confirm FacetWP is active and indexed.
2. In the block editor, insert a **FacetWP Archive** block.
3. Configure facets in the filter bar block settings.
4. Publish and confirm filtering, search, reset, and pagination work on the front end.

---

## Agent instructions

If you are an AI agent merging this component into ModernPress, follow these rules:

1. Read this entire README before making changes.
2. Use the **File map** table as the authoritative list of source → destination paths.
3. Copy files verbatim where the map shows a direct copy. Do not rename classes or namespaces.
4. For `.snippet` files, merge only the described changes into the target file. Do not replace entire target files unless the project does not already contain conflicting integration code.
5. Move controller PHP files out of `blocks/*/` into `wp-content/plugins/core/src/Components/Blocks/`. Do not leave controllers in the theme block directories.
6. Copy `blocks/facetwp-filter-bar/components/` to `wp-content/themes/core/components/filter-bar/`. Do not leave these template parts in the block directory after merge.
7. Do **not** copy the FacetWP plugin itself. It must be installed separately by the project team.
8. Do **not** modify `wp-content/plugins/core/src/Blocks/Block_Base.php` or `Blocks_Subscriber.php` unless the target project explicitly requires asset-loading changes outside the scope of this component.
9. After all file operations, run `npm run dist` from the ModernPress project root.
10. Report any merge conflicts (especially in `integrations/_variables.pcss`, `Integrations_Subscriber.php`, or `theme.json`) rather than silently overwriting unrelated project customizations.

---

## Block usage

1. Add a **FacetWP Archive** block to a page or template.
2. Choose **Filter Bar Position** (top or sidebar) in the block settings.
3. Select the **FacetWP Filter Bar** inner block and add facets from the block settings panel. Facets can be reordered via drag-and-drop and given custom display labels.
4. Configure the **FacetWP Grid** inner block: choose post type, posts per page (up to 99), and whether to show pagination.
5. Build a card layout inside the grid using a **Query Loop** or pattern that works with the FacetWP template class (`.facetwp-template`).

### Custom facets registered by this component

The integration registers these facets automatically via the `facetwp_facets` filter:

| Facet | Type | Purpose |
| --- | --- | --- |
| `pagination` | pager (numbers) | Front-end pagination |
| `search` | search | Keyword search |
| `reset_filters` | reset | Clear all filters button |

Project-specific taxonomy and meta facets must still be created in the FacetWP admin.

---

## Media

- Demo Video: https://www.loom.com/share/50be905daf4642d5a02640d6e1e43ace
- Jira: [MOOSE-350](https://moderntribe.atlassian.net/browse/MOOSE-350)
