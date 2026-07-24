<?php

namespace App\Observers;

use App\Models\Block;
use App\Services\Cms\BlockContentSanitizer;

class BlockObserver
{
    public function __construct(private readonly BlockContentSanitizer $sanitizer) {}

    public function saving(Block $block): void
    {
        $block->content = $this->sanitizer->sanitize($block->type, $block->content);
    }
}
