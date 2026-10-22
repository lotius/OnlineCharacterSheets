// Shapes produced by App\Services\CharacterPresenter. Keep in sync with that class.

export type StatKind = 'resource' | 'attribute' | 'currency';

export type Stat = {
    id: number;
    key: string;
    name: string;
    kind: StatKind;
    icon: string | null;
    value: number;
    max_value: number | null;
    minimum: number | null;
    maximum: number | null;
};

export type Modifier = {
    stat: string;
    op: 'add' | 'set';
    value: number;
    when?: 'equipped' | 'carried' | null;
};

export type ItemRules = {
    category: string | null;
    cost: number | null;
    modifiers: Modifier[];
    allowed_slots: string[];
    required_slots: string[];
    exclude_classes?: string[];
    only_classes?: string[];
    consumable: boolean;
    tags: string[];
    properties: Record<string, string>;
};

export type SlotRef = { id: number; key: string; name: string };

export type Item = ItemRules & {
    id: number;
    definition_id: number | null;
    key: string | null;
    name: string;
    description: string | null;
    icon: string | null;
    quantity: number;
    slots: SlotRef[];
    restriction?: string | null;
};

export type Ability = {
    id: number;
    definition_id: number | null;
    key: string | null;
    name: string;
    description: string | null;
    icon: string | null;
    group: string | null;
    is_used: boolean;
    charges: number | null;
};

export type Character = {
    id: number;
    revision: number;
    name: string;
    class_key: string | null;
    class_name: string | null;
    portrait: string | null;
    can_update: boolean;
    stats: Stat[];
    items: Item[];
    spells: Ability[];
    skills: Ability[];
    note: { content: string | null; is_private: boolean } | null;
    // Room context (absent on mutation responses; the store keeps the previous values).
    session_character_id?: number;
    assigned_user_id?: number | null;
    assigned_user_name?: string | null;
};

export type BlockType = 'stats' | 'equipment' | 'items' | 'abilities' | 'notes';

export type Block = {
    id: string;
    type: BlockType;
    title: string | null;
    collapsed: boolean;
    keys?: string[];
    category?: string | null;
    exclude_categories?: string[];
    kind?: 'spells' | 'skills';
};

export type Layouts = { sheet: Block[]; card: Block[] };

export type CatalogItem = ItemRules & {
    id: number;
    key: string;
    name: string;
    icon: string | null;
    description: string | null;
};

export type CatalogAbility = {
    definition_id: number;
    key: string;
    name: string;
    description: string | null;
    icon: string | null;
    group: string | null;
};

export type SystemContext = {
    rules_mode: 'tracker' | 'warn' | 'enforce';
    layouts: Layouts;
    slots: Array<SlotRef & { icon: string | null; capacity: number }>;
    stats: Array<{ key: string; name: string; kind: StatKind }>;
    item_categories: Array<{ key: string; name: string }>;
    catalog: {
        items: CatalogItem[];
        spells: CatalogAbility[];
        skills: CatalogAbility[];
    } | null;
};

export type SheetMode = 'sheet' | 'card' | 'overlay';
