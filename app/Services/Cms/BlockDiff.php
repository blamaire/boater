<?php

namespace App\Services\Cms;

use App\Models\Block;

/**
 * Uitkomst van de three-way diff per (origin_block_id) blokinstantie.
 *
 * Types:
 *  - unchanged: alle drie zijn identiek → geen actie
 *  - added_by_me / added_by_theirs: bestaat alleen in mijn/hun versie → automerge (respectievelijk overnemen)
 *  - deleted_by_me / deleted_by_theirs / deleted_both: verwijderd zonder edit-conflict → automerge
 *  - edited_by_me / edited_by_theirs: gewijzigd aan één kant, andere kant ongewijzigd → automerge
 *  - auto_mergeable: beide gewijzigd, maar op verschillende keys → automerge (per key overnemen)
 *  - conflict_edit_edit: beide gewijzigd op dezelfde keys, verschillend → handmatige resolutie nodig
 *  - conflict_delete_edit: één kant verwijderd, andere kant gewijzigd → handmatige resolutie nodig
 */
final readonly class BlockDiff
{
    /**
     * @param  list<string>  $conflictingKeys
     */
    public function __construct(
        public int $originBlockId,
        public string $type,
        public ?Block $base,
        public ?Block $mine,
        public ?Block $theirs,
        public array $conflictingKeys,
    ) {}

    public function isConflict(): bool
    {
        return in_array($this->type, ['conflict_edit_edit', 'conflict_delete_edit'], true);
    }

    public function isNoop(): bool
    {
        return in_array($this->type, ['unchanged', 'deleted_both'], true);
    }
}
