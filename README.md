# Online Character Sheets

A Laravel, Vue, and Inertia application for realtime tabletop character sheets and game rooms. The domain is game-system agnostic; the first seeded configuration is HeroQuest-inspired.

## Implemented milestones

- Milestone 1: authentication, generic game definitions, rooms, membership approval, character creation/assignment, policies, and Reverb/Echo connectivity.
- Milestone 2: inventory, equipment slots, spells, skills, notes, configured trays, and a responsive personal character sheet.
- Milestone 3: turn control and order editing, append-only audit history for character and room changes, and DM undo through compensating events.
- Instant-feel sync: taps apply immediately (optimistic, per-character ordered requests sent as deltas), and realtime notices refresh only the character that changed.
- Configurable sheets: stats have kinds (resource / attribute / currency). Item rules (slots, two-handed, modifiers, class restrictions, consumables) drive derived Attack/Defend/Move. Each room can configure its phone-sheet and card layouts from shared blocks.
- Stream overlays: token-protected, transparent OBS browser sources with a visual designer and presets.

The design is summarized in `CODEX_ACTION_PLAN.md` §0.1, and its checklist tracks planned versus completed work. `docs/OVERHAUL_PROGRESS.md` logs the October 2026 overhaul step by step.

## Local setup

Requirements:

- PHP 8.3+ with `pdo_mysql`, `intl`, and standard Laravel extensions
- MariaDB/MySQL
- Composer
- Node.js and npm

Install and initialize:

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Set the MariaDB and Reverb values in `.env` before migrating. Reverb credentials are local application secrets and should not be committed.

## Running locally

Run these in separate terminals:

```powershell
php artisan serve
php artisan reverb:start --debug
php artisan queue:work
npm.cmd run dev
```

Reverb listens on `127.0.0.1:8080` by default. The room and sheet headers show the realtime connection state as a dot (green connected, amber connecting, red offline).

### Fast, multi-device local play (Windows)

Measured on this project, a change on one device appears on the others in about 0.1–0.4 s once these are set:

- **Use an IP address, not `localhost`, for `REVERB_HOST`.** On Windows, sending to `localhost` tries IPv6 first and adds about 220 ms to every change. Phones also cannot reach your PC by `localhost`. Set `REVERB_HOST` to the PC's LAN IP (e.g. `192.168.1.20`), set `APP_URL` to `http://192.168.1.20:8000`, add that IP to `REVERB_ALLOWED_ORIGINS`, then rebuild (`npm run build`) or restart `npm run dev`, because the browser bundle bakes in `VITE_REVERB_HOST`. Start the servers with `php artisan serve --host=0.0.0.0` and `php artisan reverb:start --host=0.0.0.0`.
- **Enable OPcache for the PHP CLI** (`opcache.enable_cli=1` in `php.ini`). `php artisan serve` runs as CLI and otherwise spends about 200–300 ms booting Laravel on every request.
- `php artisan serve` handles one request at a time. For a full table, a real web server (Laravel Herd, or nginx + PHP-FPM) keeps taps snappy under load.

If Reverb is down, changes still save; screens catch up within 15 seconds through the periodic revision check.

## Upgrading an existing database

After pulling the October 2026 overhaul, run both commands:

```powershell
php artisan migrate
php artisan db:seed --class=HeroQuestGameSystemSeeder
```

The migration moves each character's gold into a `Gold` currency stat and removes the old "active item" flag. Re-seeding adds the Attack, Defend and Move stats, the Arms slot, the HeroQuest item and spell catalog, and the default layouts. It also backfills the new stats onto existing characters. Existing weapons are not equipped automatically; tap **Equip** once per hero so Attack reflects the weapon.

## HeroQuest content

The catalog is data in `database/data/heroquest/items.json` and `spells.json`, in the same format as **System catalog → Bulk import**. Add or correct entries there (or in the catalog editor) and re-seed or re-import. Costs, dice and class restrictions were entered from memory, so verify them against your rulebooks. Item fields:

| Field                              | Meaning                                                                                                                                     |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `category`                         | weapon, armor, potion, artifact, tool… (sheet sections can filter by it)                                                                    |
| `cost`                             | gold price (display)                                                                                                                        |
| `modifiers`                        | `[{ "stat": "attack", "op": "set", "value": 3 }]` — `set` = best equipped wins, `add` stacks; `"when": "carried"` applies without equipping |
| `allowed_slots`                    | slots it can go in, preferred first                                                                                                         |
| `required_slots`                   | slots it fills all at once (two-handed)                                                                                                     |
| `exclude_classes` / `only_classes` | class keys; enforced, warned or ignored per the system's `rules_mode`                                                                       |
| `consumable`                       | "Use" removes one; stacks when added                                                                                                        |
| `tags`, `properties`               | display only                                                                                                                                |

## Stream overlays

In a room, open **Setup → Stream overlays**, create one from a preset, and copy its URL into an OBS **Browser Source** (1920×1080). The page is transparent and read-only, updates live, and recovers by itself after disconnects. Treat the URL like a stream key; **New URL** in the designer revokes the old one immediately.

## Realtime access boundaries

- `private-games.{id}` requires an authenticated owner or active room membership.
- `games.public.{id}` receives only filtered, read-only synchronization notices and only when the DM selects `public_view`.
- WebSocket subscriptions never authorize mutations. All changes go through Laravel HTTP actions, validation, and policies.

## Verification

```powershell
composer test
npm.cmd run check
npm.cmd run types:check
npm.cmd run build
```

The test suite uses in-memory SQLite. Enable `pdo_sqlite` and `sqlite3`, or invoke PHPUnit with those extensions for the process.
