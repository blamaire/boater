<?php

namespace App\Services\Cms;

use Illuminate\Support\Collection;

final readonly class ConflictReport
{
    /**
     * @param  Collection<int, BlockDiff>  $entries
     */
    public function __construct(public Collection $entries) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts()->isNotEmpty();
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
}
