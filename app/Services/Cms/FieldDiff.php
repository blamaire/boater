<?php

namespace App\Services\Cms;

use App\Models\PageVersion;

/**
 * Uitkomst van de three-way diff voor één scalair meta-/OG-veld op
 * {@see PageVersion} — het veld-equivalent van {@see BlockDiff}.
 *
 * Types: zelfde betekenis als bij `BlockDiff`, maar dan per veld i.p.v. per
 * blok (geen added/deleted-varianten, een veld bestaat altijd — hooguit `null`).
 */
final readonly class FieldDiff
{
    public function __construct(
        public string $field,
        public string $type,
        public mixed $base,
        public mixed $mine,
        public mixed $theirs,
    ) {}

    public function isConflict(): bool
    {
        return $this->type === 'conflict_edit_edit';
    }

    public function isNoop(): bool
    {
        return $this->type === 'unchanged';
    }

    /**
     * Leesbare Nederlandse omschrijving van $type — zelfde context-afhankelijke
     * opzet als {@see BlockDiff::label()}.
     */
    public function label(string $mineLabel = 'de linkerversie', string $theirsLabel = 'de rechterversie'): string
    {
        return match ($this->type) {
            'unchanged' => 'Ongewijzigd',
            'edited_by_me' => "Gewijzigd in {$mineLabel}",
            'edited_by_theirs' => "Gewijzigd in {$theirsLabel}",
            'auto_mergeable' => 'Beide gewijzigd, automatisch samengevoegd',
            'conflict_edit_edit' => 'Conflict — beide gewijzigd op hetzelfde veld',
            default => $this->type,
        };
    }

    public function fieldLabel(): string
    {
        return match ($this->field) {
            'meta_description' => 'Meta-omschrijving',
            'og_title' => 'OG-titel',
            'og_description' => 'OG-omschrijving',
            'og_image_media_asset_id' => 'OG-afbeelding',
            default => $this->field,
        };
    }
}
