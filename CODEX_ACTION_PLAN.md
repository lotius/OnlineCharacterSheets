# Codex Build Action Plan: Real-Time Dungeon Crawl Character & Stream Overlay Platform

## 0. Purpose

This document is the implementation blueprint for a new Laravel application that provides a real-time, web-based character sheet and game-room system for HeroQuest-style dungeon crawl / tabletop RPG games, while remaining generic enough to support other game systems later.

The initial visual presentation should strongly support a HeroQuest-inspired theme, but the underlying data model, permissions, realtime events, UI components, and overlay system must remain game-system agnostic.

This project is intended to be built as a **self-contained Laravel application** with no required Redis, Firebase, Pusher, Ably, or other external realtime/state services.

The application will later integrate with **HQForge** through a stable internal/public API and shared realtime events, so APIs and domain boundaries must be designed cleanly from the start.

---

# 1. Core Product Goals

The application must ultimately support all of the following:

1. Authenticated users.
2. A DM/game-master user who can create a game room/session.
3. Authenticated players who can join a game room through an invite or join request.
4. The DM can approve/reject join requests where applicable.
5. The DM can assign characters to players.
6. Players can interact only with characters they are permitted to control.
7. The DM can interact with all characters in the room.
8. Optional anonymous/public read-only viewing of a room.
9. Individual character-sheet URLs suitable for mobile/phone use.
10. Live updates across all connected clients.
11. A complete audit trail of meaningful game-state mutations.
12. DM-accessible undo operations.
13. Current-turn/current-character tracking and highlighting.
14. Generic stats, equipment slots, inventory, spells, skills, notes, etc.
15. Generic game-system definitions rather than hard-coded HeroQuest columns.
16. Item icons sourced from a locally stored/curated subset of game-icons.net SVGs.
17. HeroQuest-inspired visual theming.
18. A configurable stream overlay designer.
19. Token-protected browser-source overlay URLs for OBS/Twitch/stream software.
20. Realtime overlay updates.
21. Reusable overlay templates and data bindings.
22. Reconnect/resync behavior for phones, sleeping browsers, OBS, and unstable networks.
23. Snapshot/campaign/session persistence.
24. An API clean enough for later HQForge integration.
25. A single-server deployment path with Laravel + database + Reverb, without Redis.

---

# 2. Recommended Technical Stack

Use this stack unless there is a compelling implementation reason to deviate:

- **Laravel**: latest stable version compatible with the selected PHP version.
- **PHP**: latest stable production-supported release compatible with Laravel.
- **Vue 3**.
- **Inertia.js**.
- **Vite**.
- **Laravel Reverb** for WebSockets.
- **Laravel Echo** in the browser.
- **MySQL or PostgreSQL**. Prefer PostgreSQL if the new project has no existing database requirement; MySQL is also acceptable.
- **Database-backed sessions**.
- **Database-backed queues**.
- **Database-backed cache** where useful.
- **No Redis dependency** in the initial architecture.
- **Tailwind CSS** or equivalent utility styling if already included in the selected Laravel starter stack.
- **Laravel Sanctum** for future API consumers such as HQForge.
- **Laravel Policies/Gates** for authorization.
- **Pest or PHPUnit** for automated tests. Prefer Pest if the starter project supports it.
- **SortableJS / Vue Draggable** or a similarly lightweight drag/drop library for overlay layout editing only if needed.
- Use browser-native CSS transforms and pointer events where practical.

Do not introduce:
- microservices,
- Kubernetes,
- Firebase,
- Supabase as the main backend,
- Pusher,
- Ably,
- Redis as a hard dependency,
- separate Node.js backend services,
- direct database access from HQForge.

The target architecture is a **modular monolith**.

---

# 3. High-Level Architecture

The application contains a shared game-state core and several presentation surfaces.

```text
                         ┌──────────────────────┐
                         │      GAME CORE       │
                         │                      │
                         │ Sessions             │
                         │ Characters           │
                         │ Stats                │
                         │ Equipment            │
                         │ Inventory            │
                         │ Spells / Skills      │
                         │ Turns                │
                         │ Events / Undo        │
                         │ Snapshots            │
                         └──────────┬───────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌────────────────┐         ┌──────────────────┐        ┌──────────────────┐
│ Room Interface │         │ Personal Sheet   │        │ Public Viewer    │
│ Authenticated  │         │ Authenticated    │        │ Optional anon    │
│ DM + Players   │         │ Single character │        │ Read-only        │
└────────────────┘         └──────────────────┘        └──────────────────┘
        │                           │                           │
        └───────────────────────────┼───────────────────────────┘
                                    │
                                    ▼
                             Laravel Reverb
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │  Stream Overlay UI   │
                         │  Token-protected     │
                         │  Read-only renderer  │
                         └──────────────────────┘
                                    │
                                    ▼
                              Future HQForge
```

The server/database is always authoritative.

Realtime transport is a notification/synchronization mechanism, not the primary state store.

---

# 4. Critical Architectural Rules

Codex must follow these rules throughout implementation.

## 4.1 Server-authoritative state

Never mutate important game state exclusively in browser memory.

Mutation flow:

```text
Vue action
    ↓
Laravel request/controller/action
    ↓
authorization
    ↓
validation
    ↓
database transaction
    ↓
game event/audit record
    ↓
revision increment
    ↓
broadcast via Reverb
    ↓
other clients update
```

## 4.2 WebSockets are not the source of truth

Clients may optimistically update UI if desired, but authoritative data must come from the server.

After reconnect, revision mismatch, or any uncertain synchronization state, refetch game state over HTTP.

## 4.3 Generic domain, themed presentation

Do not create game-specific database columns such as:

```text
body_points
mind_points
helmet
shield
weapon
```

Instead use generic definitions and values:

- stat_definitions
- character_stats
- slot_definitions
- character_items
- tray_definitions
- etc.

HeroQuest is a configured game system/theme, not the database schema.

## 4.4 Player and character are separate concepts

A user account is not a character.

A character may:
- be assigned to a player,
- become unassigned,
- move to a different player,
- be controlled temporarily by the DM,
- persist across campaign sessions.

## 4.5 Audit trail is append-only

Undo does not delete historical records.

Undo creates a compensating event.

## 4.6 Overlay URLs are not normal login sessions

Overlay editors require normal DM authentication.

OBS/browser-source render URLs use strong random access tokens and are read-only.

## 4.7 Public viewers must receive filtered state

Do not expose DM-only/private information merely because the public route is read-only.

Use role-specific API resources/serializers.

---

# 5. Suggested Application Module Boundaries

Organize code by domain rather than putting all logic into controllers.

Recommended conceptual modules:

```text
app/
  Domain/
    GameSystems/
    Sessions/
    Characters/
    Inventory/
    Abilities/
    Turns/
    GameEvents/
    Snapshots/
    Overlays/
    Assets/
  Actions/
  Events/
  Http/
    Controllers/
    Requests/
    Resources/
  Policies/
  Services/
```

Exact folder naming may be adjusted to normal Laravel conventions, but preserve clear boundaries.

Avoid “fat controllers.”

Prefer:
- Form Requests for validation.
- Policies for authorization.
- Action/service classes for mutations.
- API Resources for output shaping.
- Events for realtime broadcasting.

---

# 6. Build Order Overview

Implement in the following order.

1. Project bootstrap and coding standards.
2. Authentication.
3. Database foundation and game-system definition model.
4. Game sessions and membership.
5. Character model and generic stats.
6. Character assignment and permissions.
7. Equipment/inventory/item definitions.
8. Trays, spells, skills, notes.
9. Room UI.
10. Personal mobile character-sheet view.
11. Turn tracking.
12. Event log, revisioning, and undo.
13. Reverb realtime synchronization.
14. Reconnect/resync behavior.
15. Public spectator view.
16. Assets and game-icons integration.
17. Theme architecture and HeroQuest-style theme.
18. Campaign/session snapshots.
19. Overlay data model.
20. Overlay renderer.
21. Overlay token access.
22. Overlay designer.
23. Overlay bindings and dynamic/repeater elements.
24. Overlay conditionals and animations.
25. Overlay layout revisions/templates.
26. API normalization for HQForge.
27. Testing, security hardening, deployment.
28. Documentation and seed/demo content.

Do not begin the overlay designer before game-state models, permissions, events, and realtime synchronization are stable.

---

# 7. Phase 1 — Project Bootstrap

## 7.1 Install/configure

Set up:

- Laravel.
- Vue 3.
- Inertia.
- Vite.
- Laravel Reverb.
- Laravel Echo.
- Laravel Sanctum.
- Database queue driver.
- Database session driver.
- Database cache driver if needed.
- Pest/PHPUnit.
- ESLint/Prettier if desired.
- Laravel Pint.

## 7.2 Environment variables

Create/document at minimum:

```env
APP_NAME=
APP_URL=

DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=
REVERB_SCHEME=
```

Do not require Redis-related environment variables.

## 7.3 Queue behavior

Use database queues for normal async jobs.

For highly interactive state changes where broadcasting must be immediate, consider `ShouldBroadcastNow`.

Make this decision event-by-event rather than adding Redis just for realtime.

## 7.4 Basic test harness

Before building domain logic, create:
- database refresh test setup,
- authenticated request helpers,
- basic WebSocket/broadcast event tests where practical,
- factories for User.

### Acceptance criteria

- Application boots locally.
- User can register/login/logout.
- Queue worker can process database jobs.
- Reverb starts independently.
- Echo can connect to Reverb.
- Test suite runs successfully.

---

# 8. Phase 2 — Authentication and User Model

Implement standard Laravel authentication.

Required capabilities:

- register,
- login,
- logout,
- password reset if starter supports it,
- email verification optional for MVP but recommended,
- account profile.

User accounts are required for:
- DMs,
- players.

Anonymous access is only permitted for explicitly public/spectator functionality and overlay-token endpoints.

Do not create global roles such as “DM” on the User model.

A user is a DM or player **within a game session**.

---

# 9. Phase 3 — Game-System Definitions

Create a generic game-definition layer.

## 9.1 `game_systems`

Suggested fields:

```text
id
slug
name
description nullable
default_theme nullable
is_active boolean
created_at
updated_at
```

Examples:
- heroquest
- generic-dungeon-crawl

## 9.2 `game_system_versions`

Suggested fields:

```text
id
game_system_id
version
name nullable
configuration json nullable
is_current boolean
created_at
updated_at
```

Purpose:
- game definitions can evolve,
- sessions can remain tied to a specific version,
- later rules changes do not silently alter old campaigns.

## 9.3 `stat_definitions`

Suggested fields:

```text
id
game_system_version_id
key
name
icon nullable
description nullable
data_type
default_value nullable
default_max_value nullable
minimum_value nullable
maximum_value nullable
display_order
is_public boolean
configuration json nullable
```

Examples for HeroQuest:
- body
- mind

Do not hard-code these in the characters table.

## 9.4 `slot_definitions`

Suggested fields:

```text
id
game_system_version_id
key
name
icon nullable
capacity integer default 1
display_order
configuration json nullable
```

Examples:
- head
- body
- left_hand
- right_hand

Allow future rule metadata such as:
- two-handed requirements,
- slot groups,
- mutually exclusive slots.

Do not require rule enforcement for MVP unless explicitly configured.

## 9.5 `tray_definitions`

Suggested fields:

```text
id
game_system_version_id
key
name
icon nullable
display_order
is_collapsible boolean
default_open boolean
configuration json nullable
```

Examples:
- equipment
- inventory
- spells
- skills
- artifacts
- notes

## 9.6 `character_class_definitions`

Suggested fields:

```text
id
game_system_version_id
key
name
description nullable
portrait_asset_id nullable
configuration json nullable
display_order
```

Examples:
- barbarian
- dwarf
- elf
- wizard

Class definition configuration can describe:
- default stats,
- visible trays,
- starting equipment,
- optional restrictions.

### Acceptance criteria

- Admin/dev seeder can create one HeroQuest game-system version.
- Definitions can be loaded without game-specific PHP constants.
- Character generation can be driven from definitions.

---

# 10. Phase 4 — Game Sessions / Rooms

Internally call these `GameSession` records even if UI uses “Room.”

## 10.1 `game_sessions`

Suggested fields:

```text
id
game_system_version_id
owner_user_id
name
slug nullable
join_code
status
visibility
current_revision bigint default 0
current_character_id nullable
started_at nullable
ended_at nullable
settings json nullable
created_at
updated_at
```

Suggested `status`:
- lobby
- active
- paused
- ended

Suggested `visibility`:
- private
- invite_only
- public_view

Generate short human-readable join codes.

## 10.2 `game_session_members`

Suggested fields:

```text
id
game_session_id
user_id
role
status
joined_at nullable
approved_at nullable
created_at
updated_at
```

Suggested role enum/value:
- dm
- co_dm
- player
- observer

Suggested membership status:
- requested
- invited
- active
- rejected
- removed

The owner is normally DM, but model membership explicitly.

## 10.3 Invite/join flow

Support:
- DM invites via room code/link.
- Player requests to join when policy requires.
- DM approves/rejects.
- DM removes player.
- Player voluntarily leaves where valid.

Do not expose sequential game IDs as the only invite mechanism.

### Acceptance criteria

- Authenticated DM creates room.
- Authenticated player joins or requests access.
- DM can approve/reject.
- Unauthorized users cannot mutate room state.
- Room membership appears live later after realtime phase.

---

# 11. Phase 5 — Characters and Generic Stats

## 11.1 `characters`

Suggested fields:

```text
id
game_system_version_id
character_class_definition_id nullable
owner_user_id nullable
name
class_name_override nullable
portrait_asset_id nullable
notes nullable
configuration json nullable
created_at
updated_at
```

A character may exist outside a particular active room so it can persist across campaigns/sessions.

## 11.2 `character_stats`

Suggested fields:

```text
id
character_id
stat_definition_id
current_value
maximum_value nullable
configuration json nullable
created_at
updated_at
```

For numeric stats, values may be decimal/integer depending on the generic model. If supported values are only numeric initially, document that.

## 11.3 `session_characters`

Suggested fields:

```text
id
game_session_id
character_id
controlling_user_id nullable
position integer nullable
turn_order integer nullable
is_active boolean
settings json nullable
created_at
updated_at
```

This is where room-specific character assignment lives.

The same persistent character may appear in later sessions.

## 11.4 Character creation

Support:
- create from class definition,
- optional custom name,
- default stat values from class/game-system config,
- default trays,
- default equipment later.

### Acceptance criteria

- DM can add/create character.
- Character stats are generic.
- DM can assign/unassign character to player.
- Player can modify only assigned character according to policy.
- DM can modify all room characters.

---

# 12. Phase 6 — Authorization Policies

Create Laravel Policies for at minimum:

- GameSessionPolicy
- GameSessionMemberPolicy
- CharacterPolicy
- CharacterStatPolicy
- CharacterItemPolicy
- TurnPolicy
- GameEventPolicy
- OverlayPolicy
- AssetPolicy

Rules:

### DM/co-DM
May:
- edit room settings,
- assign characters,
- modify any room character,
- change turn,
- undo eligible game events,
- manage players,
- manage overlays,
- generate/revoke overlay tokens.

### Player
May:
- view room as permitted,
- modify assigned character,
- view other player information only as allowed by game/session configuration,
- not alter another player's private state.

### Observer
Read-only according to room policy.

### Anonymous spectator
Read-only, only through public resource representation.

Do not duplicate role checks throughout controllers.

---

# 13. Phase 7 — Item Definitions, Inventory, and Equipment

## 13.1 `item_definitions`

Suggested fields:

```text
id
game_system_version_id
key nullable
name
icon nullable
description nullable
category nullable
stackable boolean default false
default_quantity integer default 1
configuration json nullable
created_at
updated_at
```

Examples:
- broadsword
- shield
- helmet
- healing_potion

## 13.2 `character_items`

This should represent owned item instances/stacks.

Suggested fields:

```text
id
character_id
item_definition_id nullable
custom_name nullable
custom_icon nullable
custom_description nullable
quantity integer default 1
configuration json nullable
created_at
updated_at
```

Allow custom player/DM-created items by permitting a null `item_definition_id`.

## 13.3 Equipment relation

Option A:
Create a separate equipment assignment table:

```text
character_item_slots

id
character_item_id
slot_definition_id
slot_position nullable
created_at
updated_at
```

This supports:
- multi-slot items,
- two-handed equipment,
- future slot rules.

Prefer this over putting one `equipped_slot_id` on `character_items`.

## 13.4 Generic rule metadata

Item configuration may later include:
- allowed slots,
- required slot count,
- mutually exclusive item types,
- game-specific bonuses.

Do not overbuild the rules engine in MVP.

Initially allow configurable enforcement mode:
- tracker-only,
- warn,
- enforce.

### Acceptance criteria

- Add item by definition.
- Add custom item.
- Remove item.
- Change quantity.
- Equip/unequip to slot.
- Display icon + text.
- All mutations are authorized and later logged.

---

# 14. Phase 8 — Spells, Skills, Notes, and Trays

Keep these generic but distinct enough to render cleanly.

Possible tables:

```text
spell_definitions
skill_definitions
character_spells
character_skills
```

Suggested definition fields:

```text
id
game_system_version_id
key nullable
name
icon nullable
description nullable
configuration json nullable
```

Character assignment fields can include:

```text
is_used
charges
quantity
notes
configuration json
```

Notes may initially remain directly on the character or use a generic `character_notes` table if multiple note blocks are required.

Each character sheet tray should be driven by `tray_definitions` and class/session configuration.

Required initial trays:
- equipment,
- inventory,
- spells,
- skills,
- notes.

Support open/closed state in browser-local UI preferences unless the DM needs to control it.

---

# 15. Phase 9 — Character Sheet Vue Components

Build reusable components.

Suggested hierarchy:

```text
GameRoom.vue
  TurnTracker.vue
  PlayerList.vue
  CharacterGrid.vue
    CharacterCard.vue
      CharacterHeader.vue
      CharacterPortrait.vue
      CharacterStats.vue
        StatCounter.vue
      EquipmentSlots.vue
        EquipmentSlot.vue
      CharacterTray.vue
        InventoryTray.vue
        SpellTray.vue
        SkillTray.vue
        NotesTray.vue
  GameEventHistory.vue
```

Character cards must support:
- name,
- class,
- controlling player,
- body/mind or generic stats,
- equipment slot icons + labels,
- openable/collapsible trays,
- current-turn highlight,
- read-only mode,
- compact/mobile mode.

Avoid hard-coding the Barbarian, Wizard, etc. in components.

---

# 16. Phase 10 — Personal Character Sheet Route

Provide an authenticated route such as:

```text
/game/{game}/character/{character}
```

or equivalent.

The page:
- uses the same CharacterCard/CharacterSheet components,
- renders the user's assigned character prominently,
- is optimized for phone portrait orientation,
- remains live via Reverb,
- hides DM-only controls,
- supports touch interaction.

If a player has more than one assigned character, allow selecting among them.

---

# 17. Phase 11 — Current Turn / Character Tracking

Store authoritative current character on `game_sessions.current_character_id`.

Use `session_characters.turn_order`.

Required DM controls:
- directly select current character,
- previous,
- next,
- optional reset to first.

Broadcast `TurnChanged`.

All room clients should visually highlight the current character.

Possible later extension:
- round counter,
- initiative groups,
- monster turns.

Do not require those for MVP, but leave room in model/configuration.

---

# 18. Phase 12 — Event Log and Audit Trail

This is mandatory.

## 18.1 `game_events`

Suggested fields:

```text
id
game_session_id
revision bigint
actor_user_id nullable
character_id nullable
event_type
entity_type nullable
entity_id nullable
before_state json nullable
after_state json nullable
metadata json nullable
undoes_event_id nullable
undone_by_event_id nullable
created_at
```

No `updated_at` is strictly necessary for immutable events.

Example event types:

```text
character.created
character.assigned
character.unassigned

stat.changed

item.added
item.removed
item.quantity_changed
item.equipped
item.unequipped

spell.added
spell.removed
spell.used
skill.added
skill.removed

turn.changed

member.joined
member.removed

snapshot.created

event.undone
```

## 18.2 Every meaningful mutation must log

Use a single mutation/action layer so developers cannot easily update state without creating a corresponding game event.

For example:

```text
UpdateCharacterStatAction
EquipCharacterItemAction
ChangeTurnAction
AssignCharacterAction
```

Each action should:
1. authorize,
2. validate,
3. begin database transaction,
4. load current state,
5. make mutation,
6. increment game revision,
7. insert game event,
8. commit,
9. broadcast.

## 18.3 Human-readable history

Game event API resource should produce enough information for a UI list such as:

```text
9:14 PM — Rich — Barbarian lost 1 Body Point
9:13 PM — Sarah — Elf equipped Crossbow
9:12 PM — DM — Wizard received Potion of Healing
```

Avoid storing only the human-readable text; store structured event data and derive labels.

---

# 19. Phase 13 — Revision Numbers

`game_sessions.current_revision` is critical for synchronization.

Each authoritative game mutation increments it exactly once in the same database transaction.

Every game event stores that revision.

Every broadcast payload includes:
- game_session_id,
- revision,
- event_type,
- relevant changed data.

Clients track their latest revision.

Rules:

- If incoming revision == local revision + 1, apply normally.
- If incoming revision <= local revision, ignore duplicate/old event.
- If incoming revision > local revision + 1, client may have missed data; refetch authoritative state.
- On reconnect, compare current state/revision and resync.

This design is required.

---

# 20. Phase 14 — Undo

Only DMs/co-DMs can undo.

Do not delete events or silently restore old rows.

Undo implementation:
1. load target event,
2. verify it is undoable,
3. verify it has not already been undone,
4. verify current state can safely accept the inverse,
5. apply inverse mutation,
6. increment game revision,
7. append a new compensating event,
8. link the undo event to original event,
9. broadcast resulting state change.

Example:

```text
#128 stat.changed: body 8 → 7
#129 event.undone: restores body 7 → 8 and references #128
```

Some events should be marked non-undoable if inverse semantics are unsafe.

Add an `UndoableGameEvent` abstraction or handler registry rather than a giant switch statement if practical.

---

# 21. Phase 15 — Reverb Realtime Synchronization

Use Laravel Reverb.

## 21.1 Channels

Suggested channels:

Authenticated room:
```text
private-game.{gameSessionId}
```

Optional presence channel:
```text
presence-game.{gameSessionId}
```

Public spectator:
```text
public-game.{publicTokenOrSlug}
```

Overlay:
```text
private-overlay.{overlayTokenOrOverlayId}
```

Exact channel type may vary based on Reverb authorization capability, but keep spectator/overlay data isolated from DM/player private channels.

## 21.2 Broadcast events

Suggested event classes:

```text
GameRevisionAdvanced
CharacterStatChanged
CharacterItemChanged
CharacterAssignmentChanged
CharacterAbilityChanged
TurnChanged
MemberChanged
GameEventUndone
```

It is acceptable to consolidate into a generic `GameStateChanged` event if payloads remain typed/clear, but do not send full unrestricted room state to every client.

## 21.3 No Redis

Configure Reverb for a single application server.

No horizontal scaling is required initially.

If scale eventually requires multiple Reverb instances, Redis can be introduced later as an optional infrastructure evolution.

---

# 22. Phase 16 — Reconnect and Resynchronization

Phones and OBS browser sources will sleep/reconnect.

Build explicit resync behavior.

Required flow:

```text
WebSocket disconnect
    ↓
browser reconnects
    ↓
request current game/overlay state via HTTP
    ↓
compare revision
    ↓
replace local state if needed
    ↓
resume realtime updates
```

Do not depend on replaying every missed WebSocket event.

Add:
- visible connection state indicator for DM/player UI,
- silent automatic recovery for overlay renderer,
- backoff/reconnect handling using Echo/Reverb defaults plus app-level state refresh.

---

# 23. Phase 17 — Public Spectator View

Game session visibility determines whether anonymous viewing is allowed.

Suggested route:

```text
/game/{publicSlug}/watch
```

Create separate API Resources:

```text
DmGameStateResource
PlayerGameStateResource
PublicGameStateResource
OverlayGameStateResource
```

Do not reuse the same serializer blindly.

Public state should expose only explicitly allowed information.

Future hidden information could include:
- DM notes,
- unrevealed items,
- secret spells,
- monster details,
- hidden quest state.

Even if those fields do not exist yet, build the separation now.

---

# 24. Phase 18 — Assets and Icon Management

Create an `assets` system.

## 24.1 `assets`

Suggested fields:

```text
id
owner_user_id nullable
game_system_version_id nullable
type
name
storage_disk
storage_path
mime_type
width nullable
height nullable
metadata json nullable
created_at
updated_at
```

Asset types may include:
- portrait,
- icon,
- overlay_image,
- frame,
- background,
- logo,
- theme_asset.

Use Laravel storage APIs.

Do not store binary image data in the SQL database.

## 24.2 game-icons.net

Use a local curated SVG library.

Store an icon key such as:

```text
broadsword
shield
helmet
potion-ball
```

Resolve to local files, e.g.:

```text
/resources/icons/game-icons/broadsword.svg
```

or a compiled public asset equivalent.

Do not hotlink game-icons.net for runtime rendering.

Create an icon catalog/index that can be searched by name.

Later, item creation UI should include:
- icon search,
- icon preview,
- category,
- item name.

Review and comply with game-icons.net licensing/attribution requirements before production release.

---

# 25. Phase 19 — Theme Architecture

Functionality must be independent from visual theme.

Suggested organization:

```text
resources/
  themes/
    default/
    heroquest/
```

Theme may control:
- CSS variables,
- fonts,
- borders,
- panel backgrounds,
- parchment textures,
- stone/metal/ornamental frames,
- typography,
- icon frames,
- buttons,
- tray appearance,
- stat treatments,
- portrait frames.

Vue components should expose semantic classes and variables.

Example conceptual variables:

```css
--sheet-bg
--panel-bg
--panel-border
--heading-font
--body-font
--stat-body-color
--stat-mind-color
--active-character-glow
```

Do not embed HeroQuest artwork directly into business logic.

The user will later provide HeroQuest rulebooks and quest books. Once supplied, use them as visual reference material to refine:
- border language,
- parchment treatment,
- color palette,
- typography hierarchy,
- spacing,
- panel motifs,
- decorative flourishes.

Keep actual reusable design assets legally appropriate and do not assume scanned copyrighted book art can simply be republished.

---

# 26. Phase 20 — Campaigns and Snapshots

The system should support characters persisting between game sessions.

Recommended optional `campaigns` model:

```text
campaigns
id
game_system_version_id
owner_user_id
name
description nullable
created_at
updated_at
```

Then:
```text
game_sessions.campaign_id nullable
```

## 26.1 Snapshots

Create:

```text
game_snapshots

id
game_session_id
campaign_id nullable
revision
name nullable
state json
created_by_user_id
created_at
```

Snapshot use cases:
- save game,
- end-of-session save,
- restore/reference previous state,
- campaign resume.

Do not use snapshots as the normal undo mechanism.

Snapshots and event log serve different purposes.

---

# 27. Phase 21 — Overlay Domain Model

The overlay system is a first-class module.

## 27.1 `stream_overlays`

Suggested fields:

```text
id
owner_user_id
game_system_version_id nullable
name
canvas_width
canvas_height
background_mode
configuration json
is_template boolean
created_at
updated_at
```

Recommended initial canvas presets:
- 1920x1080
- 2560x1440
- 3840x2160
- custom

Default background:
- transparent.

## 27.2 Overlay/session assignment

An overlay layout should be reusable.

Create either:
- `game_session_overlays`,
or fields/config that assign an overlay template to a game session.

Recommended relation table:

```text
game_session_overlays

id
game_session_id
stream_overlay_id
name nullable
binding_configuration json nullable
is_active boolean
created_at
updated_at
```

This allows one template to be used across multiple sessions.

---

# 28. Phase 22 — Overlay Access Tokens

Create a separate token model, for example:

```text
stream_overlay_tokens

id
game_session_overlay_id
token_hash
label nullable
last_used_at nullable
revoked_at nullable
created_at
```

Requirements:

- Generate cryptographically secure random token.
- Store only a hash if practical.
- Display raw token once/copy action in DM UI.
- Token is treated like a stream key.
- DM can regenerate/revoke.
- Old token stops working immediately after revocation.
- Overlay renderer is read-only.
- Rate-limit token endpoint appropriately.
- Never permit mutation through token-authenticated routes.

Suggested render route:

```text
/overlay/{token}
```

The route renders only:
- transparent page,
- overlay renderer,
- no nav,
- no controls,
- no normal app chrome.

This URL is intended for OBS Browser Source.

---

# 29. Phase 23 — Overlay Renderer

Build the renderer before the visual designer.

The renderer consumes a JSON layout configuration and game-state binding data.

## 29.1 Layout JSON

Example:

```json
{
  "version": 1,
  "background": "transparent",
  "elements": [
    {
      "id": "portrait-hero-1",
      "type": "character-portrait",
      "x": 20,
      "y": 100,
      "width": 72,
      "height": 72,
      "binding": {
        "type": "hero-slot",
        "slot": 1,
        "property": "portrait"
      },
      "style": {}
    },
    {
      "id": "body-hero-1",
      "type": "stat-value",
      "x": 100,
      "y": 105,
      "width": 60,
      "height": 70,
      "binding": {
        "type": "hero-slot",
        "slot": 1,
        "stat": "body",
        "property": "current"
      },
      "style": {
        "fontSize": 64,
        "fontWeight": 700
      }
    }
  ]
}
```

Version the JSON schema from day one.

## 29.2 Renderer requirements

- absolute-positioned elements,
- transparent background,
- canvas scaling,
- text,
- images,
- character portraits,
- stat values,
- item counts,
- current-turn marker,
- generic image,
- generic text,
- simple shapes/containers,
- groups/repeaters later.

Renderer must not contain game logic.

It resolves bindings against a prepared overlay state payload.

---

# 30. Phase 24 — Overlay Binding Model

Do not bind only to raw database IDs.

Support reusable symbolic bindings.

Initial binding concepts:

### Specific character
```text
character:{id}
```

### Hero slot
```text
hero-slot:1
hero-slot:2
...
```

### Current character
```text
current-character
```

### Class-based
```text
class:barbarian
```

Potential later:
- player slot,
- party aggregate,
- current player.

For reusable layouts, hero-slot bindings are preferred.

A session then maps:

```text
Hero Slot 1 → Barbarian
Hero Slot 2 → Dwarf
Hero Slot 3 → Knight
Hero Slot 4 → Wizard
Hero Slot 5 → Elf
```

Store this mapping in `game_session_overlays.binding_configuration` or a normalized child table.

---

# 31. Phase 25 — Overlay Element Types

Implement in stages.

## MVP element types

General:
- text,
- image,
- rectangle/container.

Character:
- portrait,
- name,
- class,
- player name.

Stats:
- current value,
- maximum value,
- formatted current/max,
- stat bar.

Inventory:
- item icon,
- item quantity/count,
- equipped item name/icon.

Game:
- current turn indicator,
- room/session name.

## Later element types

- repeater/group,
- conditional container,
- progress ring,
- custom HTML-like styled block only if safely constrained,
- timer,
- round counter,
- quest title.

Do not permit arbitrary JavaScript in layouts.

Do not permit arbitrary unsanitized HTML.

---

# 32. Phase 26 — Overlay Designer

Build a DM-only visual editor.

Suggested route:

```text
/game/{game}/overlays/{overlay}/edit
```

## 32.1 UI layout

Recommended structure:

```text
┌──────────────────────────────────────────────────────────┐
│ Toolbar                                                  │
├──────────────┬────────────────────────────┬───────────────┤
│ Element      │                            │ Properties    │
│ Palette      │        Canvas              │ Inspector     │
│              │                            │               │
│ Text         │                            │ X/Y           │
│ Image        │                            │ W/H           │
│ Portrait     │                            │ Binding       │
│ Stat         │                            │ Font          │
│ Item Count   │                            │ Visibility    │
│ Turn Marker  │                            │ Animation     │
└──────────────┴────────────────────────────┴───────────────┘
```

## 32.2 Required editing operations

- add element,
- select element,
- drag,
- resize,
- duplicate,
- delete,
- bring forward,
- send backward,
- align,
- snap optional,
- keyboard delete,
- keyboard arrow nudge,
- undo/redo editor changes locally,
- save,
- duplicate layout,
- preview.

## 32.3 Canvas scaling

Display the 1920x1080 logical canvas scaled down to available browser size.

Store logical coordinates in the configured canvas coordinate system, not screen pixels after scaling.

---

# 33. Phase 27 — Overlay Preview Data

Designer must support:

```text
Use Live Game Data
Use Sample Data
```

Sample mode should permit values such as:
- character name,
- portrait,
- body points,
- mind points,
- healing potion count,
- current-turn state.

This allows layout creation without altering a real game.

Provide a standard seeded HeroQuest-like preview party.

---

# 34. Phase 28 — Repeater / Character Group Elements

Implement after basic designer is stable.

A repeater allows designing one row/card and repeating it for active characters.

Example:

```text
Repeat over: Active Heroes
Direction: Vertical
Spacing: 10px
Maximum: 6
```

Child layout might contain:
- portrait,
- body points,
- healing potions,
- active-turn frame.

Support:
- vertical,
- horizontal,
- fixed maximum,
- hide unused slots or show placeholders.

This is useful for variable player counts.

---

# 35. Phase 29 — Empty Hero Slot / Placeholder Logic

Support a HeroQuestFans-style “Hero Spot Open” presentation.

For a hero-slot binding:
- if slot contains character, show character display,
- if empty, optionally show placeholder element/group.

This should be implemented generically through visibility conditions rather than a hard-coded HeroQuest feature.

Example condition:

```text
show when hero-slot.1.isOccupied == false
```

---

# 36. Phase 30 — Conditional Visibility

Add safe declarative conditions.

Examples:

```text
Body > 0
Body <= 2
Healing Potion Count > 0
Current Character == this Character
Hero Slot Occupied
Hero Slot Empty
```

Do not allow arbitrary JavaScript evaluation.

Use a constrained condition schema such as:

```json
{
  "left": {
    "binding": "hero-slot:1.stat:body.current"
  },
  "operator": "<=",
  "right": 2
}
```

Supported operators initially:
- ==
- !=
- >
- >=
- <
- <=
- truthy
- falsy

---

# 37. Phase 31 — Overlay Animations

Because the overlay is rendered in a browser source, add optional CSS-driven update animations.

Possible animation presets:
- none,
- pulse,
- flash,
- scale,
- fade,
- shake,
- slide.

Allow configuration:
- on increase,
- on decrease,
- on any change,
- on becoming current turn.

Store only safe named presets and duration values.

Do not allow arbitrary injected CSS/JS initially.

---

# 38. Phase 32 — Overlay Live Updates

Overlay renderer initial load:

```text
GET /api/overlay/{token}/state
```

Response should include:
- overlay definition,
- session binding map,
- filtered game state,
- current revision.

Then connect to overlay-specific Reverb channel.

When state changes:
- update relevant bindings immediately,
- run configured animations,
- if revision gap occurs, refetch full overlay state.

OBS/browser source should recover automatically after temporary disconnect.

---

# 39. Phase 33 — Overlay Layout Revisions

Overlay editing should have its own revision/history mechanism separate from gameplay events.

Suggested table:

```text
stream_overlay_revisions

id
stream_overlay_id
revision
configuration json
created_by_user_id
created_at
```

Create revisions:
- on explicit Save,
- optionally on significant checkpoint/autosave.

Required UI actions later:
- restore previous version,
- duplicate layout,
- save as template.

Do not create a database revision for every pixel movement while dragging.

---

# 40. Phase 34 — Overlay Templates

Allow layouts to be marked reusable templates.

Examples:
- HeroQuest 4-Hero Vertical
- HeroQuest 5-Hero Vertical
- Generic 4-Character Horizontal
- Minimal Stat Bar

Template contains:
- layout,
- binding placeholders,
- styles,
- conditionals.

Session assignment provides:
- actual character slot mappings,
- session-specific images if needed.

---

# 41. Phase 35 — Sample HeroQuestFans Stream Layout

Use the uploaded sample screenshot as a functional reference, not as a source asset.

The screenshot demonstrates a useful target layout containing vertically stacked hero rows:
- circular character portrait,
- large yellow current body-point number,
- potion icon/count in green,
- placeholder graphic for an unused/open hero slot.

The overlay system must be capable of reproducing this *kind* of layout using generic elements.

Required capabilities to prove:
1. Five vertical hero positions.
2. Occupied slots show portrait.
3. Empty slots show “open hero spot” placeholder.
4. Body point value bound to each hero.
5. Healing potion count bound to each hero.
6. Optional item icon next to quantity.
7. Current turn can optionally add a frame/glow/marker.
8. All values update live.
9. Overlay background remains transparent.

Do not hard-code the screenshot-specific exact coordinates into core code.

Create it as a seed/example template after the designer is ready.

---

# 42. Phase 36 — Public / Player / DM / Overlay Resources

Create explicit state representations.

## `DmGameStateResource`
May include:
- all characters,
- all stats,
- all inventory,
- assignments,
- private/DM metadata,
- history,
- permissions.

## `PlayerGameStateResource`
Include only player-visible state.

## `PublicGameStateResource`
Anonymous-safe, read-only subset.

## `OverlayGameStateResource`
Only fields needed by overlay bindings.

This prevents accidental data leakage.

---

# 43. Phase 37 — API Design for Future HQForge Integration

Build APIs cleanly enough that the existing Vue interface could theoretically consume them independently.

Suggested API routes:

```text
GET    /api/games/{game}
GET    /api/games/{game}/state
GET    /api/games/{game}/characters
GET    /api/characters/{character}

PATCH  /api/characters/{character}/stats/{stat}

POST   /api/characters/{character}/items
PATCH  /api/characters/{character}/items/{item}
DELETE /api/characters/{character}/items/{item}

POST   /api/characters/{character}/items/{item}/equip
POST   /api/characters/{character}/items/{item}/unequip

POST   /api/games/{game}/turn/next
POST   /api/games/{game}/turn/previous
POST   /api/games/{game}/turn/{character}

POST   /api/games/{game}/events/{event}/undo

GET    /api/games/{game}/events

GET    /api/overlay/{token}/state
```

Use route model binding carefully.

For HQForge:
- use Sanctum,
- scope tokens/permissions,
- never allow direct database access,
- expose stable JSON resources,
- document event payloads.

---

# 44. Phase 38 — Realtime Event Contract for HQForge

Define broadcast payloads deliberately.

Suggested common envelope:

```json
{
  "event": "stat.changed",
  "gameSessionId": 123,
  "revision": 1455,
  "occurredAt": "2026-08-30T21:00:00-05:00",
  "actor": {
    "id": 7,
    "name": "Rich"
  },
  "entity": {
    "type": "character",
    "id": 42
  },
  "data": {}
}
```

Examples:

### stat.changed

```json
{
  "data": {
    "characterId": 42,
    "statKey": "body",
    "previous": 6,
    "current": 5,
    "maximum": 8
  }
}
```

### turn.changed

```json
{
  "data": {
    "previousCharacterId": 42,
    "currentCharacterId": 51
  }
}
```

Keep payload naming consistent.

Version the API/event contract when necessary.

---

# 45. Phase 39 — Database Transactions and Concurrency

Game mutations must use transactions.

For high-contention counters such as stats and revision numbers:
- use row locking or atomic updates as appropriate,
- ensure two simultaneous clients cannot produce duplicate revisions,
- ensure body points are not lost through race conditions.

Recommended mutation pattern:

1. transaction begin,
2. lock game session row,
3. load/lock target state if necessary,
4. compute mutation,
5. increment revision,
6. save state,
7. insert game event,
8. commit,
9. broadcast after commit.

Prefer Laravel's after-commit event behavior where appropriate.

---

# 46. Phase 40 — Validation

Create Form Request classes for mutations.

Examples:
- CreateGameSessionRequest
- JoinGameSessionRequest
- AssignCharacterRequest
- UpdateCharacterStatRequest
- AddCharacterItemRequest
- EquipCharacterItemRequest
- UpdateTurnRequest
- UndoGameEventRequest
- SaveOverlayRequest
- CreateOverlayTokenRequest

Validate:
- membership,
- ownership,
- definition belongs to same game-system version,
- valid stat ranges,
- valid slot ids,
- valid quantities,
- safe JSON configuration,
- canvas coordinate bounds where practical.

Authorization belongs in policies, not only validation rules.

---

# 47. Phase 41 — Security Requirements

Implement from the beginning.

## Authentication/authorization
- CSRF protection on normal app routes.
- Sanctum/token auth for external API where appropriate.
- Policy enforcement on every mutation.
- Overlay token routes read-only.
- Reverb private channel authorization.

## Overlay token
- strong random token,
- revoke/regenerate,
- rate-limit,
- do not log raw token,
- do not display raw token in application logs/errors,
- consider token hash at rest.

## Input safety
- sanitize/escape custom text,
- no arbitrary JavaScript in overlay definitions,
- no arbitrary HTML without sanitization,
- no arbitrary CSS injection initially,
- validate uploaded MIME types.

## Public data
- use dedicated filtered resources,
- do not expose user email addresses,
- do not expose DM-only notes.

---

# 48. Phase 42 — Responsive UX

Character UI must work on:
- desktop,
- tablet,
- phone.

Desktop:
- grid of character cards,
- player/member list,
- turn controls,
- event history.

Phone personal-sheet route:
- single full-width character,
- touch-sized controls,
- collapsible trays,
- sticky stat/header area optional.

Overlay editor:
- desktop-first.
- Do not optimize full designer for phones initially.

OBS renderer:
- fixed logical canvas,
- browser-source compatible.

---

# 49. Phase 43 — HeroQuest-Inspired UI Targets

When reference rulebooks/quest books become available, refine the theme.

Desired feel:
- parchment/card stock,
- ornate fantasy framing,
- bold character headings,
- distinct body/mind stat treatment,
- polished portrait frames,
- tactile card sections,
- expandable trays that still feel visually thematic.

Do not sacrifice usability:
- readable typography,
- sufficient contrast,
- responsive controls,
- clear focus states,
- keyboard navigation where feasible.

Use decorative visual elements in CSS/assets, not in domain logic.

---

# 50. Phase 44 — Character Sheet Interaction Details

Required interactions:

### Stats
- tap/click +/- buttons,
- direct numeric entry optional for DM,
- enforce configured min/max,
- live broadcast.

### Equipment
- add item,
- assign to slot,
- move between slots,
- remove/unequip,
- display icon and text.

### Inventory
- quantity adjustment,
- remove,
- custom item creation.

### Spells/skills
- add/remove,
- optional used/exhausted state,
- optional charge counters.

### Trays
- expand/collapse,
- maintain user's local open/closed preference.

### Current turn
- current sheet visibly highlighted.
- personal sheet may show “Your Turn” indicator.

---

# 51. Phase 45 — Room Controls

DM room toolbar should eventually include:

- Start/Pause/End Session
- Invite / Join Code
- Player Requests
- Assign Characters
- Previous Turn
- Next Turn
- Direct Character Selection
- Event History
- Undo
- Public View toggle
- Overlay Manager
- Snapshot / Save Game
- Room Settings

Players see only appropriate controls.

---

# 52. Phase 46 — Game Event History UI

Provide:
- chronological list,
- actor,
- character,
- event description,
- timestamp,
- undo button for eligible event,
- visual mark if event was undone.

Later filters:
- character,
- event type,
- actor.

Do not let clients fabricate history descriptions as the only record. Use structured server events.

---

# 53. Phase 47 — Tests

Automated test coverage is mandatory for domain rules.

## Authentication
- unauthenticated users blocked from private routes.

## Sessions
- DM creates room.
- player requests join.
- DM approves.
- unauthorized member blocked.

## Character permissions
- player can mutate assigned character.
- player cannot mutate another character.
- DM can mutate all.

## Stats
- changes save.
- event created.
- revision increments.
- broadcast event dispatched.

## Inventory
- add/remove/equip/quantity.
- policy enforcement.
- event and revision.

## Turns
- next/previous/direct selection.
- current character updates.
- broadcast.

## Undo
- valid event reverses.
- second undo blocked.
- unauthorized player blocked.
- new event appended.
- revision increments.

## Public view
- hidden fields absent.

## Overlay
- token grants read-only render access.
- revoked token fails.
- overlay state filtered.
- mutation routes unavailable to token.
- revision mismatch resync path.

## Concurrency
At least one test or service-level test should validate revision increment safety.

---

# 54. Phase 48 — Seeders / Demo Data

Create development seeders for:

### Users
- one DM,
- several players.

### HeroQuest-like game system
- Body,
- Mind,
- head/body/left/right hand slots,
- equipment/inventory/spells/skills/notes trays.

### Classes
- Barbarian
- Dwarf
- Elf
- Wizard
- optional Knight or generic extra hero

### Items
- Broadsword
- Shield
- Helmet
- Healing Potion
- a two-handed weapon sample

### Characters
Create sample party with varying body/mind values.

### Overlay template
After overlay module exists, seed a HeroQuestFans-style vertical hero status layout.

Seeders are for development/testing and should not imply bundled copyrighted assets are licensed for redistribution.

---

# 55. Phase 49 — Deployment

Target simple single-server deployment.

Suggested stack:

```text
Ubuntu Linux
Nginx
PHP-FPM
Laravel
MySQL/PostgreSQL
Laravel Reverb
Laravel queue worker
systemd or Supervisor
HTTPS / WSS
```

Processes:
- php-fpm
- queue worker
- reverb server

No Redis required.

Document:
- environment configuration,
- queue worker service,
- Reverb service,
- websocket reverse-proxy configuration,
- HTTPS,
- storage linking,
- migrations,
- deployment order,
- restart sequence.

---

# 56. Phase 50 — Logging and Diagnostics

Add useful application logging around:
- room creation,
- join failures,
- broadcast failures,
- overlay token failures,
- resync events,
- undo failures.

Do not log:
- passwords,
- raw overlay access tokens,
- private API tokens.

Provide DM-visible connection indicator, but do not expose internal server details.

---

# 57. Phase 51 — Performance Guidelines

Initial target scale is modest tabletop sessions.

Do not prematurely optimize.

However:
- eager-load relationships,
- avoid N+1 queries in state resources,
- avoid broadcasting entire room state on every mutation,
- index foreign keys,
- index `game_events(game_session_id, revision)`,
- index membership lookups,
- index join code / public slug,
- index overlay token hash.

Overlay renderer should request a compact filtered state payload.

---

# 58. Phase 52 — Accessibility / Input

Where reasonable:
- keyboard-accessible buttons,
- labels for icon-only controls,
- visible focus states,
- sufficient contrast,
- touch-friendly hit targets,
- do not use color alone for critical state.

HeroQuest theme should remain readable despite decorative styling.

---

# 59. Phase 53 — Suggested Initial Route Map

Web:

```text
/
 /dashboard

/games
/games/create
/games/{game}
/games/{game}/join
/games/{game}/members
/games/{game}/characters
/games/{game}/characters/{character}
/games/{game}/history
/games/{game}/overlays
/games/{game}/overlays/{overlay}/edit

/watch/{publicSlug}

/overlay/{token}
```

API:

```text
/api/games/{game}/state
/api/games/{game}/characters
/api/characters/{character}
/api/characters/{character}/stats/{stat}
/api/characters/{character}/items
/api/characters/{character}/items/{item}
/api/games/{game}/turn/*
/api/games/{game}/events
/api/games/{game}/events/{event}/undo
/api/overlay/{token}/state
```

Naming may change, but keep the separation.

---

# 60. Suggested Initial Model Inventory

Expected major Eloquent models:

```text
User

GameSystem
GameSystemVersion
StatDefinition
SlotDefinition
TrayDefinition
CharacterClassDefinition

GameSession
GameSessionMember

Campaign
GameSnapshot

Character
CharacterStat
SessionCharacter

ItemDefinition
CharacterItem
CharacterItemSlot

SpellDefinition
CharacterSpell
SkillDefinition
CharacterSkill

GameEvent

Asset

StreamOverlay
GameSessionOverlay
StreamOverlayToken
StreamOverlayRevision
```

Some later models may be omitted until needed, but do not collapse fundamentally different responsibilities into one giant JSON blob.

---

# 61. Suggested Mutation Actions / Services

Create explicit application actions such as:

```text
CreateGameSession
RequestGameSessionJoin
ApproveGameSessionMember
RemoveGameSessionMember

CreateCharacter
AssignCharacter
UnassignCharacter

UpdateCharacterStat

AddCharacterItem
UpdateCharacterItemQuantity
RemoveCharacterItem
EquipCharacterItem
UnequipCharacterItem

AddCharacterSpell
UpdateCharacterSpell
RemoveCharacterSpell

AddCharacterSkill
UpdateCharacterSkill
RemoveCharacterSkill

SetCurrentTurn
AdvanceTurn
RewindTurn

UndoGameEvent

CreateGameSnapshot

CreateStreamOverlay
UpdateStreamOverlay
AssignOverlayToGame
RotateOverlayToken
```

Each mutation must funnel through these actions so:
- authorization,
- event creation,
- revisioning,
- transactions,
- broadcasting
stay consistent.

---

# 62. Suggested Realtime Client Store

Use a small centralized Vue state store for active game state.

Pinia may be used if preferred.

Game store should track:
- game state,
- characters,
- members,
- current revision,
- connection state,
- current character,
- permissions.

Realtime handler:
1. receive event,
2. inspect revision,
3. apply or resync,
4. update reactive components.

Do not allow each character card to independently fetch and maintain conflicting copies of authoritative game state.

Overlay renderer may use a smaller dedicated store.

---

# 63. HeroQuest-Specific Configuration, Not Logic

HeroQuest-specific seeded configuration may define:

```text
Stats:
- Body Points
- Mind Points

Slots:
- Head
- Body
- Left Hand
- Right Hand

Trays:
- Equipment
- Inventory
- Spells
- Skills
- Artifacts
- Notes
```

A class definition may indicate:
- Wizard shows Spells,
- Barbarian may hide Spells,
- etc.

All of this should be configuration.

Do not create `if ($character->class === 'Wizard')` scattered through application logic.

---

# 64. Future-Proofing for Rules

Do not build a full rules engine now, but preserve extension points.

Potential future features:
- item stat modifiers,
- class restrictions,
- equipment compatibility,
- two-handed enforcement,
- spell groups,
- charges,
- buffs/debuffs,
- temporary effects,
- turn-limited status,
- monster/NPC sheets,
- party inventory.

Use `configuration` JSON fields sparingly for future metadata while keeping core searchable relationships normalized.

---

# 65. Future HQForge Integration

HQForge should eventually consume this application through:
- authenticated JSON API,
- stable IDs/UUIDs,
- realtime events,
- game/session state endpoints.

Potential integration examples:
- selecting current character highlights corresponding HQForge miniature,
- character body-point changes update HQForge HUD,
- HQForge map actions can trigger character-state mutations through API,
- campaign/session metadata can cross-link.

Do not tightly couple the database schemas of the two applications.

An eventual shared auth strategy may be considered later, but this application should function independently first.

---

# 66. Non-Goals for Initial MVP

Do not delay core functionality to build:

- full combat automation,
- virtual tabletop map rendering,
- dice roller,
- voice/video,
- chat,
- complex rules scripting,
- monster AI,
- marketplace,
- billing,
- multi-node Reverb scaling,
- arbitrary user JavaScript,
- arbitrary CSS scripting,
- WYSIWYG quest editor.

These can be added later.

---

# 67. Recommended Milestone Breakdown

## Milestone 1 — Foundation
Deliver:
- auth,
- game systems,
- sessions,
- membership,
- characters,
- stats,
- policies.

Definition of done:
Two authenticated users can join a room and the DM can assign a character.

## Milestone 2 — Functional Character Sheets
Deliver:
- item definitions,
- inventory,
- equipment slots,
- spells,
- skills,
- trays,
- responsive character card UI.

Definition of done:
A player can use a phone to manage their assigned character.

## Milestone 3 — Game Control
Deliver:
- turn order,
- current character,
- event log,
- revisioning,
- undo.

Definition of done:
Every meaningful state change is auditable and DM-undoable where applicable.

## Milestone 4 — Realtime
Deliver:
- Reverb,
- Echo,
- private channels,
- live mutations,
- reconnect/resync.

Definition of done:
Two browsers see stat/item/turn changes without refresh.

## Milestone 5 — Viewing
Deliver:
- public spectator state,
- role-filtered resources,
- anonymous watch page.

Definition of done:
DM can permit a read-only public view without exposing private data.

## Milestone 6 — Theme & Assets
Deliver:
- asset manager,
- local SVG icon catalog,
- default theme,
- HeroQuest-inspired theme structure.

Definition of done:
Character sheet can visually present as a themed fantasy/HeroQuest-like interface.

## Milestone 7 — Overlay Renderer
Deliver:
- overlay models,
- token access,
- transparent renderer,
- initial element types,
- live updates.

Definition of done:
OBS can load an overlay URL and live body-point changes appear automatically.

## Milestone 8 — Overlay Designer
Deliver:
- canvas editor,
- drag/resize,
- inspector,
- bindings,
- preview data,
- save/load.

Definition of done:
DM can build a layout without editing code.

## Milestone 9 — Advanced Overlay
Deliver:
- hero-slot mapping,
- repeaters,
- conditional visibility,
- empty slot placeholders,
- animations,
- layout revisions/templates.

Definition of done:
The application can reproduce the functional style of the HeroQuestFans stream status overlay using generic layout elements.

## Milestone 10 — HQForge-Ready API
Deliver:
- normalized API,
- Sanctum token strategy,
- documented realtime event contract,
- integration tests.

Definition of done:
A separate application can read game state and perform authorized mutations without touching the database directly.

---

# 68. Codex Implementation Discipline

While building:

1. Implement one milestone at a time.
2. Run migrations after each schema phase.
3. Create factories and tests alongside models.
4. Do not leave authorization for later.
5. Do not update game state directly from controllers.
6. Use transactions for every audited game mutation.
7. Increment room revision in the same transaction as state change.
8. Broadcast only after successful commit.
9. Keep public/overlay serializers separate.
10. Keep HeroQuest specifics in seed/config/theme layers.
11. Maintain an up-to-date `README.md`.
12. Add an `ARCHITECTURE.md` if implementation details grow beyond the README.
13. Update this action plan or a progress checklist as phases are completed.
14. Prefer simple Laravel-native solutions over extra infrastructure/packages.
15. Add third-party packages only when they materially reduce complexity and are actively maintained.

---

# 69. Suggested Progress Checklist

Codex should maintain this list during implementation.

```text
[ ] Project bootstrap
[ ] Authentication
[ ] Reverb connectivity proof
[ ] Game systems
[ ] Game-system versions
[ ] Stat definitions
[ ] Slot definitions
[ ] Tray definitions
[ ] Character classes
[ ] Game sessions
[ ] Membership/invites/join requests
[ ] Policies
[ ] Characters
[ ] Character stats
[ ] Character assignment
[ ] Item definitions
[ ] Character inventory
[ ] Equipment slots
[ ] Spells
[ ] Skills
[ ] Character sheet components
[ ] Responsive personal sheet
[ ] Turn order
[ ] Current character highlight
[ ] Game event log
[ ] Revision system
[ ] Undo
[ ] Reverb broadcasts
[ ] Vue realtime store
[ ] Reconnect/resync
[ ] Public watch page
[ ] Role-filtered API resources
[ ] Asset library
[ ] Local game-icons catalog
[ ] Theme abstraction
[ ] HeroQuest-style theme
[ ] Campaign model
[ ] Snapshots
[ ] Overlay model
[ ] Overlay/session assignment
[ ] Overlay token generation/revocation
[ ] Overlay state endpoint
[ ] Transparent overlay renderer
[ ] Overlay realtime updates
[ ] Overlay canvas editor
[ ] Element palette
[ ] Property inspector
[ ] Binding system
[ ] Sample/live preview mode
[ ] Hero-slot mappings
[ ] Repeater/group element
[ ] Empty-slot placeholder conditions
[ ] Conditional visibility
[ ] Update animations
[ ] Overlay revisions
[ ] Overlay templates
[ ] HeroQuestFans-style sample overlay
[ ] Sanctum integration API
[ ] API/event documentation
[ ] Security review
[ ] Full test suite
[ ] Deployment documentation
```

---

# 70. Final Product Behavior Reference

A typical game should work like this:

1. DM logs in.
2. DM creates a room.
3. DM selects the HeroQuest game system/version.
4. Players log in and follow invite/join link.
5. DM accepts them if necessary.
6. DM creates or loads characters.
7. DM assigns characters to players.
8. Every player may open the full room or their personal character-sheet URL.
9. Optional spectators open a public read-only room URL.
10. DM starts the game.
11. DM selects the current character or presses Next.
12. Current character is highlighted everywhere.
13. A player lowers Body Points.
14. Laravel authorizes and validates.
15. State is changed in a transaction.
16. Game revision increments.
17. An audit event is created.
18. Reverb broadcasts the change.
19. DM screen, player phones, spectators, and stream overlay update live according to their permissions.
20. If the change was wrong, DM clicks Undo.
21. A compensating event restores the prior value and broadcasts it.
22. If a phone sleeps, it reconnects, checks revision, refetches state if needed, and resumes.
23. At the end of play, the DM may create a snapshot/end the session.
24. Characters remain available for the next campaign session.
25. OBS uses a tokenized transparent overlay URL that requires no interactive login.
26. The DM can build/edit overlay layouts using reusable elements and bindings.
27. A future HQForge client can consume the same API and realtime event model.

---

# 71. First Codex Task Once the Empty Project Is Ready

Start only with **Milestone 1 / Foundation**.

Codex should:

1. Inspect the empty Laravel project and report the exact installed Laravel/PHP/Vue/Inertia versions.
2. Confirm whether authentication scaffolding already exists.
3. Add missing first-party dependencies only as needed.
4. Configure database sessions/queues/cache.
5. Install/configure Reverb and Echo but only prove connectivity; do not build full realtime domain logic yet.
6. Create the foundational migrations/models:
   - GameSystem
   - GameSystemVersion
   - StatDefinition
   - SlotDefinition
   - TrayDefinition
   - CharacterClassDefinition
   - GameSession
   - GameSessionMember
   - Character
   - CharacterStat
   - SessionCharacter
7. Create policies for GameSession and Character.
8. Create development seeders for a minimal HeroQuest-like game-system definition.
9. Implement:
   - room creation,
   - room list,
   - join/request flow,
   - member approval,
   - character creation,
   - generic stat editing,
   - character assignment.
10. Add tests for all permission boundaries.
11. Do not move into inventory, event history, overlays, or advanced theming until Milestone 1 tests pass.

At the end of Milestone 1, Codex should report:
- files created/modified,
- migrations added,
- routes added,
- tests added,
- current known limitations,
- next recommended milestone.

---

# 72. Design Principle to Preserve

The most important design principle for the entire project is:

> Build a generic real-time tabletop character platform whose first configured game system and visual theme happen to be HeroQuest.

If a proposed implementation requires adding a HeroQuest-specific database column or hard-coded class rule, stop and reconsider whether that concept belongs in:
- a game-system definition,
- a theme,
- a binding,
- a configurable rule,
- or a seed.

That principle will keep the application reusable and will make later HQForge integration substantially easier.
