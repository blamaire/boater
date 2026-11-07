<?php

namespace App\Services\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $meta = null,
        public ?string $snippet = null,
    ) {}
}
