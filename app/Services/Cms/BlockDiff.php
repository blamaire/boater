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

    /**
     * Leesbare Nederlandse omschrijving van $type, voor weergave in de
     * diff-viewer en conflictresolver. $mineLabel/$theirsLabel maken de
     * omschrijving van "mine"/"theirs" context-afhankelijk: de diff-viewer
     * vergelijkt twee arbitraire versies naast elkaar (default:
     * "linkerversie"/"rechterversie"), terwijl de conflictresolver een
     * eigen concept tegenover de gepubliceerde versie zet en daar dus
     * "jouw versie"/"de gepubliceerde versie" moet doorgeven.
     */
    public function label(string $mineLabel = 'de linkerversie', string $theirsLabel = 'de rechterversie'): string
    {
        return match ($this->type) {
            'unchanged' => 'Ongewijzigd',
            'added_by_me' => "Toegevoegd in {$mineLabel}",
            'added_by_theirs' => "Toegevoegd in {$theirsLabel}",
            'deleted_by_me' => "Verwijderd in {$mineLabel}",
            'deleted_by_theirs' => "Verwijderd in {$theirsLabel}",
            'deleted_both' => 'Verwijderd in beide versies',
            'edited_by_me' => "Gewijzigd in {$mineLabel}",
            'edited_by_theirs' => "Gewijzigd in {$theirsLabel}",
            'auto_mergeable' => 'Beide gewijzigd, automatisch samengevoegd',
            'conflict_edit_edit' => 'Conflict — beide gewijzigd op hetzelfde veld',
            'conflict_delete_edit' => 'Conflict — verwijderd aan één kant, gewijzigd aan de andere',
            default => $this->type,
        };
    }
}
