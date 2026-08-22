<?php

namespace App\Services\Cms;

use Illuminate\Support\Collection;

final readonly class ConflictReport
{
    /**
     * @param  Collection<int, BlockDiff>  $entries
     * @param  Collection<int, FieldDiff>  $fieldEntries
     */
    public function __construct(
        public Collection $entries,
        public Collection $fieldEntries,
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts()->isNotEmpty() || $this->fieldConflicts()->isNotEmpty();
    }

    /** @return Collection<int, BlockDiff> */
    public function conflicts(): Collection
    {
        return $this->entries->filter(fn (BlockDiff $d) => $d->isConflict())->values();
    }

    /** @return Collection<int, BlockDiff> */
    public function autoMerges(): Collection
    {
        return $this->entries->filter(fn (BlockDiff $d) => ! $d->isConflict() && ! $d->isNoop())->values();
    }

    /** @return Collection<int, FieldDiff> */
    public function fieldConflicts(): Collection
    {
        return $this->fieldEntries->filter(fn (FieldDiff $d) => $d->isConflict())->values();
    }

    /** @return Collection<int, FieldDiff> */
    public function fieldChanges(): Collection
    {
        return $this->fieldEntries->filter(fn (FieldDiff $d) => ! $d->isConflict() && ! $d->isNoop())->values();
    }
}
