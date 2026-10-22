import type { Block, Character, SystemContext } from '@/sheet/types';

// Mirrors App\Services\OverlayConfig (schema version 1).

export type ElementType =
    | 'text'
    | 'name'
    | 'class'
    | 'player'
    | 'portrait'
    | 'stat'
    | 'item-count'
    | 'equipped'
    | 'turn-marker'
    | 'box'
    | 'image'
    | 'hero-card';

export type ShowWhen =
    | 'always'
    | 'occupied'
    | 'empty'
    | 'current'
    | 'not-current';
export type Animation = 'none' | 'pulse' | 'flash' | 'bump' | 'shake';

export type ElementStyle = {
    fontSize?: number;
    fontWeight?: number;
    fontFamily?: 'sans' | 'serif' | 'display' | 'mono';
    color?: string;
    background?: string;
    borderColor?: string;
    borderWidth?: number;
    radius?: number;
    padding?: number;
    opacity?: number;
    align?: 'left' | 'center' | 'right';
    shadow?: boolean;
    glow?: string;
};

export type OverlayElement = {
    id: string;
    type: ElementType;
    x: number;
    y: number;
    w: number;
    h: number;
    z: number;
    hero: number | 'current' | null;
    show: ShowWhen;
    animate: Animation;
    props: {
        text?: string;
        stat?: string;
        display?: 'value' | 'value-max' | 'bar' | 'pips' | 'icon-value';
        item?: string;
        category?: string;
        src?: string;
        label?: string;
        blocks?: Block[];
    };
    style: ElementStyle;
};

export type OverlayConfig = {
    version: 1;
    width: number;
    height: number;
    hero_slots: Array<number | null> | null;
    elements: OverlayElement[];
};

export type OverlayState = {
    overlay: {
        id: number;
        name: string;
        configuration: OverlayConfig;
        channel: string;
    };
    game: {
        id: number;
        name: string;
        current_character_id: number | null;
        current_revision: number;
    };
    characters: Character[];
    system: Pick<
        SystemContext,
        'slots' | 'stats' | 'item_categories' | 'layouts'
    >;
};
