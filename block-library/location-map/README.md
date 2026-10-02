# Location Map (Google Maps)

Status: Alpha
Version: 0.1-alpha
Last Updated: 2026-09
Component Created By: Geoff Dusome
Originally Implemented On: [ModernPress PR #366](https://github.com/moderntribe/ModernPress/pull/366)

## What does this component do?

This component adds an interactive Google Maps location map to ModernPress. It ships one block plus everything it needs to work:

- **`tribe/location-map`** — Renders a Google Map with markers, optional marker clustering, an optional sidebar with a location list and/or location cards, and an optional proximity search with Google Places autocomplete.

Supporting pieces included in this directory:

| Piece | Purpose |
| --- | --- |
| `location` post type | Stores locations (title, editor, thumbnail, revisions). |
| `Location_Meta` ACF group | Address, phone, email, hours, latitude/longitude fields. |
| `Location_Geocoder` | Geocodes an address to lat/lng on save, and only re-geocodes when the address hash changes. |
| `Tribe_Settings` page | Settings → Tribe Settings → Maps: API key, Map ID, autocomplete toggle/min chars/debounce. |
| `Google_Maps` | Conditionally prints the Google Maps JS loader (front end only when the block is present). |
| REST endpoints | `tribe/v1/locations` (location payloads) and `tribe/v1/geocode` (server-side geocoding proxy). |
| `Location_Search_Resolver` | Resolves a search term (address, city, state, ZIP) into a lat/lng plus radius. |

**This component is optional.** Install it only on projects that need a location finder / store locator experience.

## Prerequisites

1. **Google Maps API key** — Enable the **Maps JavaScript API**, **Places API**, and **Geocoding API** in Google Cloud Console. Add the key in **Settings → Tribe Settings → Maps**. Nothing renders without it.
2. **Google Maps Map ID** — Required for Advanced Markers. Defaults to `DEMO_MAP_ID`; create a real vector Map ID for production.
3. **ACF Pro** — Already part of ModernPress. Used by `Location_Meta` and the settings page via `extended-acf`.
4. **Node.js** — Required to build block assets (`npm run dist`).

> **Billing warning:** Places autocomplete and geocoding are billed per request. The autocomplete debounce and minimum character settings exist to limit request volume — do not lower them without reason.

---

## ModernPress Implementation

These instructions are written so a developer (or AI agent) can merge the entire feature into a ModernPress (`moose`) project.

### File map

Use this table as the source of truth for where each file in this directory belongs in ModernPress.

| Source (this repo) | Destination (ModernPress) |
| --- | --- |
| `blocks/location-map/*` (except the controller) | `wp-content/themes/core/blocks/tribe/location-map/` |
| `blocks/location-map/Location_Map_Block_Controller.php` | `wp-content/plugins/core/src/Components/Blocks/Location_Map_Block_Controller.php` |
| `plugin/Locations/*` | `wp-content/plugins/core/src/Locations/` |
| `plugin/Locations/Geocoding/*` | `wp-content/plugins/core/src/Locations/Geocoding/` |
| `plugin/Object_Meta/Post_Types/Location_Meta.php` | `wp-content/plugins/core/src/Object_Meta/Post_Types/Location_Meta.php` |
| `plugin/Post_Types/Location/*` | `wp-content/plugins/core/src/Post_Types/Location/` |
| `plugin/Routes/Abstract_Route.php` | `wp-content/plugins/core/src/Routes/Abstract_Route.php` |
| `plugin/Routes/Routes_Subscriber.php` | `wp-content/plugins/core/src/Routes/Routes_Subscriber.php` |
| `plugin/Routes/API/*` | `wp-content/plugins/core/src/Routes/API/` |
| `plugin/Settings/Tribe_Settings.php` | `wp-content/plugins/core/src/Settings/Tribe_Settings.php` |
| `icons/keyboard-return.svg` | `wp-content/themes/core/assets/media/icons/keyboard-return.svg` |
| `icons/map-pin.svg` | `wp-content/themes/core/assets/media/icons/map-pin.svg` |
| `icons/my-location.svg` | `wp-content/themes/core/assets/media/icons/my-location.svg` |
| `Blocks_Definer.snippet.php` | Merge into `wp-content/plugins/core/src/Blocks/Blocks_Definer.php` |
| `Core.snippet.php` | Merge into `wp-content/plugins/core/src/Core.php` |
| `Meta_Definer.snippet.php` | Merge into `wp-content/plugins/core/src/Object_Meta/Meta_Definer.php` |
| `Settings_Definer.snippet.php` | Merge into `wp-content/plugins/core/src/Settings/Settings_Definer.php` |
| `PostSearchField.snippet.js` | Merge into `wp-content/themes/core/assets/js/components/PostSearchField.js` |
| `pcss/icons/_variables.snippet.pcss` | Merge into `wp-content/themes/core/assets/pcss/icons/_variables.pcss` |
| `package.json.snippet.json` | Merge into the root `package.json` → `dependencies` |

### Step-by-step merge

#### 1. Install the npm dependency

From the ModernPress project root:

```bash
npm install @googlemaps/markerclusterer
```

Used by `blocks/location-map/js/google-maps-map.js` for marker clustering.

#### 2. Copy the block

```text
blocks/location-map/  → wp-content/themes/core/blocks/tribe/location-map/
```

Copy the whole directory **except** `Location_Map_Block_Controller.php`, including the `js/` subdirectory.

Then move the controller into the plugin:

```text
blocks/location-map/Location_Map_Block_Controller.php
  → wp-content/plugins/core/src/Components/Blocks/Location_Map_Block_Controller.php
```

#### 3. Copy the plugin PHP

The `plugin/` directory mirrors `wp-content/plugins/core/src/` one-for-one. Copy it in place:

```text
plugin/Locations/                        → wp-content/plugins/core/src/Locations/
plugin/Object_Meta/Post_Types/           → wp-content/plugins/core/src/Object_Meta/Post_Types/
plugin/Post_Types/Location/              → wp-content/plugins/core/src/Post_Types/Location/
plugin/Routes/                           → wp-content/plugins/core/src/Routes/
plugin/Settings/Tribe_Settings.php       → wp-content/plugins/core/src/Settings/Tribe_Settings.php
```

Notes:

- `Routes/Abstract_Route.php` and `Routes/Routes_Subscriber.php` are the base REST plumbing. If the target project already has a `Routes` namespace, **merge** rather than overwrite — add the two endpoint registrations from `Routes_Subscriber.php` into the existing subscriber.
- `Settings/Tribe_Settings.php` is a general-purpose settings page. If the project already has one, merge only the `Maps` tab fields and the `get_google_maps_*` / `has_google_maps_api_key` methods into it.

#### 4. Merge snippet files

| Snippet | Target file | What to merge |
| --- | --- | --- |
| `Blocks_Definer.snippet.php` | `Blocks_Definer.php` | Add `tribe/location-map` to `self::TYPES` |
| `Core.snippet.php` | `Core.php` | Add `Locations_Definer` to `$definers`; add the location, geocode, map, and routes subscribers to `$subscribers` |
| `Meta_Definer.snippet.php` | `Meta_Definer.php` | Add the `Location_Meta` `use` statement and `DI\get()` entry |
| `Settings_Definer.snippet.php` | `Settings_Definer.php` | Add `Tribe_Settings` to `self::PAGES` |
| `PostSearchField.snippet.js` | `PostSearchField.js` | Add the optional `includedTypes` allowlist prop |
| `pcss/icons/_variables.snippet.pcss` | `icons/_variables.pcss` | Add the `keyboard-return`, `map-pin`, and `my-location` icon definitions |
| `package.json.snippet.json` | `package.json` | Add `@googlemaps/markerclusterer` to `dependencies` |

`PostSearchField.js` already exists in ModernPress. The change is additive and backwards compatible — the block passes `includedTypes={ [ 'location' ] }` so the picker only searches locations.

When merging `icons/_variables.pcss`, only add the three new icons. Do not modify existing icons.

#### 5. Copy icons

```text
icons/keyboard-return.svg → wp-content/themes/core/assets/media/icons/keyboard-return.svg
icons/map-pin.svg         → wp-content/themes/core/assets/media/icons/map-pin.svg
icons/my-location.svg     → wp-content/themes/core/assets/media/icons/my-location.svg
```

#### 6. Build assets

```bash
npm run dist
```

#### 7. Configure

1. Visit **Settings → Permalinks** and save once so the `location` post type rewrite rules flush.
2. Visit **Settings → Tribe Settings → Maps** and enter the Google Maps API key and Map ID.
3. Add a few **Locations** with full addresses. Latitude and longitude are geocoded automatically on save.

#### 8. Verify

1. Confirm `GET /wp-json/tribe/v1/locations` returns your locations with `lat`/`lng` values.
2. In the block editor, insert a **Location Map** block. Without an API key it renders a notice linking to the settings page.
3. Set **Location Source** to *All locations*, publish, and confirm markers, clustering, sidebar list, and proximity search work on the front end.

---

## Agent instructions

If you are an AI agent merging this component into ModernPress, follow these rules:

1. Read this entire README before making changes.
2. Use the **File map** table as the authoritative list of source → destination paths.
3. Copy files verbatim where the map shows a direct copy. Do not rename classes or namespaces.
4. For `.snippet.*` files, merge only the described changes into the target file. Never replace a target file with a snippet file — snippets are fragments, not valid standalone source.
5. Move `Location_Map_Block_Controller.php` out of `blocks/location-map/` into `wp-content/plugins/core/src/Components/Blocks/`. Do not leave the controller in the theme block directory.
6. The `plugin/` directory mirrors `wp-content/plugins/core/src/`. Preserve the subdirectory structure exactly.
7. If `wp-content/plugins/core/src/Routes/` or `Settings/Tribe_Settings.php` already exist in the target project, **merge** and report the conflict rather than overwriting.
8. Do **not** commit a Google Maps API key. It is stored as an ACF option, entered per environment.
9. Run `npm install @googlemaps/markerclusterer` and then `npm run dist` from the ModernPress project root after all file operations.
10. Report any merge conflicts (especially in `Core.php`, `icons/_variables.pcss`, or `PostSearchField.js`) rather than silently overwriting unrelated project customizations.

---

## Block usage

1. Add a **Location Map** block to a page or template.
2. Choose a **Location Source**:
   - **Manual** — pick specific locations with the post search field.
   - **All locations** — every published `location` post.
   - **Endpoint** — a custom REST URL returning the same payload shape as `tribe/v1/locations`.
3. Configure the sidebar: show/hide it, choose map position (left or right), and toggle the location list and location cards.
4. Configure search: toggle proximity search and set the default search radius (miles).
5. Configure the map: default center lat/lng, default zoom, fit-bounds behavior, marker clustering, and height (fixed pixel height or viewport-based).

### Endpoint payload shape

The `endpoint` source expects a JSON array of objects matching `tribe/v1/locations`:

```json
[
	{
		"id": 123,
		"title": "Location Name",
		"url": "https://example.com/location/location-name/",
		"lat": 39.10015,
		"lng": -94.58327,
		"address": "123 Main St, Kansas City, MO 64106",
		"phone": "",
		"email": "",
		"hours": "",
		"directionsUrl": "https://www.google.com/maps/dir/?api=1&destination=..."
	}
]
```

`lat` and `lng` are `null` until the address is geocoded. The payload can be adjusted per project with the `tribe_location_map_location_from_post` filter.

### Filters

| Filter | Purpose | Default |
| --- | --- | --- |
| `tribe/google_maps/region` | Region bias for geocoding and Places requests. | `us` |
| `tribe/google_maps/country` | Country restriction for geocoding and Places requests. | `US` |
| `tribe_location_map_location_from_post` | Adjust the normalized location payload used by the block and REST API. | — |

Projects outside the US must set both filters. `Us_States.php` supplies the state select options in `Location_Meta` and should be swapped for a project-appropriate list in non-US builds.

---

## Media

- Jira: [MOOSE-405](https://moderntribe.atlassian.net/browse/MOOSE-405)
- Original PR: [ModernPress PR #366](https://github.com/moderntribe/ModernPress/pull/366)
