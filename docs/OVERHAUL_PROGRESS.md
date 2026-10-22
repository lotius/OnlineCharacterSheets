# Sheet Overhaul — Progress Log

Working branch: `claude/sheet-overhaul` (branched from `main` @ 16ba52b). All work through the current-turn card change is committed as c0fdbf5 (not pushed, not merged).

If you are resuming in a new session: read this file top to bottom, then `git status` / `git diff --stat main` to see the uncommitted work, then continue at the first unchecked step.

## Goal (agreed 2026-10-21)

Five workstreams from the Claude review:

1. **Instant feel** — delta mutations, optimistic UI, per-character versions, targeted refetch instead of whole-room reload, no write locks on reads.
2. **Item / stat / derived-stat schema** — stat kinds (resource / attribute / currency), item rules (modifiers, allowed/required slots, category, cost, class restrictions, consumable, tags), gold becomes a currency stat, remove `is_active`, HeroQuest starter catalog as importable JSON.
3. **Shared sheet blocks + layout config** — one block library (stat, equipment grid, item list, abilities, currency, notes, portrait), a layout = ordered list of blocks; system default → room override → per-player view prefs; touch-first redesign; DM "play mode".
4. **Overlay** — token-protected transparent OBS page, presets, then a simple drag designer, all using the same blocks.
5. **Update `CODEX_ACTION_PLAN.md`** (+ README) to reflect the new design.

## Baseline (before changes)

- `php artisan test`: 70/70 passing.
- `npm run types:check`: passing. `npm run test:frontend`: passing.
- `npm run check`: formatting issues already present in 5 files (GameHistory.vue, revisionGate.ts, roomSync.ts, Characters/Show.vue, Games/Show.vue).

## Steps

- [x] 1. Instant feel (backend: deltas, character `revision`, JSON mutation responses, no read locks)
- [x] 1b. Instant feel (frontend: character store with optimistic ops + rebase, targeted refetch)
- [x] 2a. Schema migrations (stat kind, gold → currency stat, drop `is_active`, overlays table)
- [x] 2b. Item rules engine (equip auto-slot, two-handed, allowed slots, class restrictions, modifiers, consumable use) — derived/effective stats are computed client-side from item `modifiers` (see 3b)
- [x] 2c. HeroQuest catalog JSON + seeder
- [x] 3a. Single character presenter (replace duplicate serializers)
- [x] 3b. Block components + SheetRenderer + layout resolution (system → room → player) — server side of layouts done (`SheetLayouts`, `PUT /games/{game}/layouts`)
- [x] 3c. Personal sheet + room page redesign (touch, DM play mode, setup drawer, layout editor)
- [x] 4a. Overlay backend (model, tokens, state endpoint, realtime channel)
- [x] 4b. Overlay renderer + presets + designer
- [x] 5. Docs (CODEX_ACTION_PLAN.md, README)
- [x] Final: full test suite, types, lint/format, build

## Log

(newest last)

### 2026-10-21 — Backend complete (steps 1, 2a–c, 3a, 4a)

New/changed backend pieces (all uncommitted on `claude/sheet-overhaul`):

- Migration `2026_10_21_001200_sheet_overhaul.php`: `stat_definitions.kind` (resource|attribute|currency), `characters.revision`, gold column → `gold` currency stat (data migrated), `character_items.is_active` dropped, `stream_overlays` table.
- `App\Services\CharacterPresenter` — the single character JSON shape (replaces the duplicated code in RoomState and the deleted `CharacterSheetState`). Also `system()` context: rules_mode, resolved layouts, slots, stat defs, item categories, catalog (only for viewers who can edit).
- `App\Services\ItemRules` — item `configuration` schema + validation (category, cost, modifiers, allowed/required slots, exclude/only classes, consumable, tags, properties); rules mode tracker|warn|enforce from version configuration.
- `App\Services\SheetLayouts` — block vocabulary (stats, equipment, items, abilities, notes), validation, resolution room → system → generated-from-trays.
- `App\Services\OverlayConfig` — overlay JSON schema/validation (whitelisted element types/props/styles), presets (hero-column, hero-row, current-hero, blank), read-only state.
- `RoomState` rewritten: no `lockForUpdate` on reads; `forCharacter()` serves the phone sheet and targeted refresh.
- `CharacterMutation` bumps `characters.revision`; `RoomRealtimePinged` carries `character_id`/`character_revision` and also broadcasts to `overlays.{channel_key}`.
- Mutations accept deltas (`stats` → `delta|value|max_value`, items → `delta|quantity`); JSON requests get `{character, warnings}` back (`Controller::characterResponse`).
- Equip: `PUT items/{item}/equip` (auto slot, swap-out occupants as separate audited events, two-handed via `required_slots`), `PUT items/{item}/slots/{slot}`, `DELETE items/{item}/equip` (all slots). `POST items/{item}/use` consumes one. Consumables stack on add.
- Portraits: `POST/DELETE /characters/{c}/portrait` (audited `portrait` entity), `/media/{asset}` route wired to the previously unrouted `AssetController@file`.
- Overlays: `POST /games/{g}/overlays`, `GET …/{o}/edit`, `PUT …/{o}`, `POST …/{o}/token` (rotate), `DELETE`; public `GET /overlay/{token}` + `/overlay/{token}/state` (throttled, read-only, token hashed + encrypted at rest).
- Removed: gold endpoint/action/controller, `is_active` endpoint/action.
- HeroQuest data: `database/data/heroquest/items.json`, `spells.json` (catalog-import format; **values from memory — verify against rulebooks**). Seeder adds Attack/Defend/Move (attribute), Gold (currency), Arms slot, version config (rules_mode warn, item_categories, sheet/card layouts).
- Tests: updated old gold/active tests; added `ItemRulesTest`, `StreamOverlayTest`, `SheetLayoutTest`. Status: 82/83 passing — the 1 failure is the missing `Overlays/Show.vue` page (frontend step 4b).

Next: frontend (1b, 3b, 3c, 4b).

### 2026-10-21 — Frontend, docs and verification complete (steps 1b, 3b, 3c, 4b, 5)

Frontend (all new unless noted):

- `resources/js/sheet/`: `types.ts` (payload types), `api.ts` (JSON requests), `store.ts` (optimistic store, ordered per-character queue, revision-guarded snapshots), `liveSync.ts` + `syncDecision.ts` (targeted refresh vs. full reload), `derive.ts` (effective stats from item modifiers), `prefs.ts` (per-device density/collapsed/hidden), `toasts.ts`.
- `resources/js/components/sheet/`: `SheetRenderer` plus blocks `StatsBlock`/`StatTile`, `EquipmentBlock`, `ItemsBlock`, `AbilitiesBlock`, `NotesBlock`, `BlockFrame`, and `ItemSheet`, `CatalogPicker`, `AmountPad`, `BottomSheet`, `HoldButton`, `ToastHost`.
- `resources/js/components/room/`: `TurnBar` (optimistic), `CharacterCard`, `RoomSetup`, `LayoutEditor`.
- `resources/js/overlay/` + `components/overlay/`: overlay types, hero/visibility resolution, sample party, `OverlayCanvas`, `OverlayElementView`.
- Pages rewritten: `Characters/Show.vue`, `Games/Show.vue` (Play / Setup / History tabs). New: `Overlays/Show.vue` (OBS), `Overlays/Edit.vue` (designer). `Catalog/Index.vue` gained structured item-rule fields, a stat Kind field and spell/skill Group.
- Removed: `lib/roomSync.ts`, `lib/revisionGate.ts` (+ its test), `components/TurnControls.vue`.
- `AppHeader` collapses secondary links into the account menu on phones; `GameHistory` opens expanded.

Backend additions since the previous log entry: the seeder backfills new stats onto existing characters; the migration always marks Gold as a currency; the catalog payload includes `icon_url`; presenter `group` falls back to `category`.

Docs: `CODEX_ACTION_PLAN.md` §0.1 design revision, milestone table, checklist and progress entry; README status, upgrade steps, item-field reference and overlay how-to.

Verification:

- `php artisan test`: 84/84. `pint --test`: pass. `npm run check` (lint + format): pass. `vue-tsc`: pass. `node --test tests/Unit/*.test.ts`: 7/7. `npm run build`: pass.
- Headless Edge smoke test against a SQLite copy (scripts in the session scratchpad, not the repo): 22/22 checks, no console errors. Covered three rapid taps (no lost updates), derived Attack/Defend, equip/use/add from the picker, the gold pad, DM Next turn, Setup/History, designer add/save, catalog edit round-trip, the transparent OBS page, and a spectator with no edit controls. Screenshots were reviewed at phone and desktop widths, and fixes were applied for the header wrapping, emoji arrows, empty-state clutter on cards, and designer ghosting.
- PHPStan still fails on the pre-existing pattern of models without `@property` annotations (as before the overhaul).

Not done / next candidates:

- Nothing is committed yet. Review `git diff main`, then commit on `claude/sheet-overhaul`.
- Verify the HeroQuest catalog values against the rulebooks. More game-icons are needed (only 4 SVGs ship in `public/icons`).
- Overlay: no repeater element, stat-threshold conditions or stored layout revisions. Tokens are viewable by DMs (encrypted at rest) by design.
- Spells and skills are still separate tables (consolidating them into one generic "entries" table is optional future work).
- Theme abstraction (`resources/themes/*`) and an asset manager page have not been started. HQForge API, campaigns and snapshots are deferred.
- Model `@property` annotations to make PHPStan pass.

### 2026-10-21 — Drag-to-reorder turn order on the Play tab

- `resources/js/lib/dragSort.ts`: pointer-based (mouse + touch) live reordering of `[data-sort-id]` items. Auto-scrolls near the viewport edges and re-checks what is under the pointer while scrolling. A swap lock prevents jitter while cards animate. Escape cancels; arrow keys on a handle move one step.
- `CharacterCard` shows a grip handle (DM/co-DM only) and a turn-number badge. `Games/Show.vue` reorders optimistically, saves via `PUT /games/{g}/turn/order`, and rolls back with a toast on error. Cards animate with `TransitionGroup`.
- The up/down controls were removed from Setup → Characters (it now points to the Play tab).
- Verified: 84/84 PHP tests, lint/format/types pass, and the browser smoke test is 25/25 (mouse drag on desktop, touch drag with auto-scroll on phone, server order confirmed).

### 2026-10-21 — Clearer current-turn card

- The current-turn card was drawn with an outside ring (`turn-glow`) that the sticky turn bar covered at the top. It now uses `.turn-card`: an inside gold border, inner glow and warm tint, plus a gold "CURRENT TURN" / "YOUR TURN" banner across the top (`.turn-banner`). The small "TURN" pill was removed. All cards use `border-2`, so nothing shifts when the turn changes.
- Gotcha: the global `app.css` forces `.tracking-widest` text to gold with `!important`, so use `tracking-wider` on gold backgrounds. That override is part of the planned theme-system cleanup.

### 2026-10-21 — Committed

- Commit `c0fdbf5` on `claude/sheet-overhaul` (not pushed, not merged into `main`).
- Agreed next steps: (2) realtime test with Reverb running across two browsers, (5) quick compaction fixes (wrapping item names, hiding empty slots on cards, hiding inapplicable sections, readable labels), then the theme-system visual overhaul.
