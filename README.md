# Online Character Sheets

A Laravel, Vue, and Inertia application for realtime tabletop character sheets and game rooms. The domain is game-system agnostic; the first seeded configuration is HeroQuest-inspired.

## Implemented milestones

- Milestone 1: authentication, generic game definitions, rooms, membership approval, character creation/assignment, policies, and Reverb/Echo connectivity.
- Milestone 2: inventory, equipment slots, spells, skills, notes, configured trays, and a responsive personal character sheet.
- Milestone 3 (in progress): DM/co-DM turn selection, previous/next/reset, current-turn indicators, and a DM-only turn history with compensating undo. Turn changes commit their revision and event together, then notify clients through the existing room channel. Apply migrations before opening a room.

Turn traversal uses configured `session_characters.turn_order`, then session-character ID for ties and unconfigured entries. Removing the current character clears the turn. Undo is limited to the latest unchanged turn event; it cannot restore a removed character. Inventory, stats, and other existing mutations still need migration to the audit pipeline, and turn-order editing remains planned.

Detailed planned-versus-completed status is maintained in `CODEX_ACTION_PLAN.md`.

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

Reverb listens on `127.0.0.1:8080` by default. The room screen shows its connection state and includes a test-ping control.

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
