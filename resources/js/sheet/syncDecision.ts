export type SyncNotice = {
    revision: number;
    character_id: number | null;
    character_revision: number | null;
};

export type SyncDecision =
    | 'ignore'
    | 'advance'
    | 'refresh-character'
    | 'reload';

/**
 * What to do with a realtime notice given the last applied room revision and, when the notice names a
 * character we already show, that character's local revision.
 *
 * - old/duplicate notice → ignore
 * - next revision for a known character we already have (our own mutation response arrived first) → advance
 * - next revision for a known character → refresh only that character
 * - anything else (gap, room-level change, unknown character) → reload the page props
 */
export function decide(
    notice: SyncNotice,
    applied: number,
    knownCharacterRevision: number | null,
): SyncDecision {
    if (!Number.isSafeInteger(notice.revision) || notice.revision <= applied)
        return 'ignore';
    if (
        notice.revision !== applied + 1 ||
        notice.character_id === null ||
        knownCharacterRevision === null
    )
        return 'reload';
    return (notice.character_revision ?? Infinity) > knownCharacterRevision
        ? 'refresh-character'
        : 'advance';
}
