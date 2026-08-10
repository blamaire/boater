<?php

namespace App\Services\Cms;

use App\Models\PageVersion;
use Illuminate\Support\Collection;

/**
 * Three-way diff voor de scalaire meta-/OG-velden van een pagina-versie —
 * het veld-equivalent van {@see ConflictDetector}, dat op blok-niveau werkt.
 */
class MetaFieldConflictDetector
{
    /**
     * @var list<string>
     */
    private const FIELDS = [
        'meta_description',
        'og_title',
        'og_description',
        'og_image_media_asset_id',
    ];

    /**
     * @return Collection<int, FieldDiff>
     */
    public function detect(PageVersion $mine, PageVersion $theirs, ?PageVersion $base): Collection
    {
        return collect(self::FIELDS)
            ->map(fn (string $field) => $this->classify($field, $mine, $theirs, $base))
            ->values();
    }

    private function classify(string $field, PageVersion $mine, PageVersion $theirs, ?PageVersion $base): FieldDiff
    {
        $mineValue = $mine->getAttribute($field);
        $theirsValue = $theirs->getAttribute($field);

        if ($base === null) {
            return $mineValue === $theirsValue
                ? new FieldDiff($field, 'unchanged', null, $mineValue, $theirsValue)
                : new FieldDiff($field, 'conflict_edit_edit', null, $mineValue, $theirsValue);
        }

        $baseValue = $base->getAttribute($field);
        $mineChanged = $mineValue !== $baseValue;
        $theirsChanged = $theirsValue !== $baseValue;

        if (! $mineChanged && ! $theirsChanged) {
            return new FieldDiff($field, 'unchanged', $baseValue, $mineValue, $theirsValue);
        }

        if ($mineChanged && ! $theirsChanged) {
            return new FieldDiff($field, 'edited_by_me', $baseValue, $mineValue, $theirsValue);
        }

        if (! $mineChanged) {
            return new FieldDiff($field, 'edited_by_theirs', $baseValue, $mineValue, $theirsValue);
        }

        $type = $mineValue === $theirsValue ? 'auto_mergeable' : 'conflict_edit_edit';

        return new FieldDiff($field, $type, $baseValue, $mineValue, $theirsValue);
    }
}
